<?php

namespace App\Services;

use App\Models\AttendanceSchedule;
use App\Models\CheckinDayRecord;
use App\Models\CheckinLog;
use App\Models\DutySchedule;
use App\Models\OaAppendCheckinDetail;
use App\Models\OaLeaveCancelDetail;
use App\Models\OaLeaveDetail;
use App\Models\OaProcess;
use App\Models\OaProcessNode;
use App\Models\OaShiftChangeDetail;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class OaService
{
    /**
     * 发起审批单：创建主单 + 详情 + 审批节点链。
     */
    public function submit(User $applicant, string $type, string $title, array $detailData, array $approverIds): OaProcess
    {
        if (empty($approverIds)) {
            abort(422, '请选择至少一名审批人');
        }

        return DB::transaction(function () use ($applicant, $type, $title, $detailData, $approverIds) {
            $process = OaProcess::create([
                'type' => $type,
                'applicant_id' => $applicant->id,
                'title' => $title,
                'status' => OaProcess::STATUS_PENDING,
                'current_node' => 1,
            ]);

            $detailData['process_id'] = $process->id;
            match ($type) {
                OaProcess::TYPE_LEAVE => OaLeaveDetail::create($detailData),
                OaProcess::TYPE_LEAVE_CANCEL => OaLeaveCancelDetail::create($detailData),
                OaProcess::TYPE_SHIFT_CHANGE => OaShiftChangeDetail::create($detailData),
                OaProcess::TYPE_APPEND_CHECKIN => OaAppendCheckinDetail::create($detailData),
                default => abort(422, '未知的审批类型'),
            };

            foreach (array_values(array_unique($approverIds)) as $i => $approverId) {
                OaProcessNode::create([
                    'process_id' => $process->id,
                    'node_order' => $i + 1,
                    'approver_id' => $approverId,
                    'status' => 'pending',
                ]);
            }

            return $process;
        });
    }

    /** 审批（通过/驳回）当前节点，全部通过时执行业务回写 */
    public function act(OaProcessNode $node, bool $approved, ?string $comment): OaProcess
    {
        $process = $node->process()->lockForUpdate()->first();

        if ($process->status !== OaProcess::STATUS_PENDING) {
            abort(422, '该审批单已结束');
        }
        if ($process->current_node !== $node->node_order) {
            abort(422, '还没到当前节点，不能审批');
        }
        if ($node->status !== 'pending') {
            abort(422, '该节点已审批过');
        }

        return DB::transaction(function () use ($process, $node, $approved, $comment) {
            $node->update([
                'status' => $approved ? 'approved' : 'rejected',
                'comment' => $comment,
                'acted_at' => Carbon::now(),
            ]);

            if (! $approved) {
                $process->update(['status' => OaProcess::STATUS_REJECTED]);

                return $process->fresh();
            }

            $next = $process->nodes()->where('node_order', '>', $node->node_order)->first();
            if ($next) {
                $process->update(['current_node' => $next->node_order]);
            } else {
                $process->update(['status' => OaProcess::STATUS_APPROVED]);
                $this->applySideEffects($process->fresh());
            }

            return $process->fresh();
        });
    }

    /** 审批通过后的业务回写 */
    private function applySideEffects(OaProcess $process): void
    {
        match ($process->type) {
            OaProcess::TYPE_LEAVE => $this->applyLeave($process),
            OaProcess::TYPE_LEAVE_CANCEL => $this->applyLeaveCancel($process),
            OaProcess::TYPE_SHIFT_CHANGE => $this->applyShiftChange($process),
            OaProcess::TYPE_APPEND_CHECKIN => $this->applyAppendCheckin($process),
            default => null,
        };
    }

    /** 请假通过：假期内有排班的日子标记为请假 */
    private function applyLeave(OaProcess $process): void
    {
        $detail = $process->leaveDetail;
        $period = CarbonPeriod::create($detail->start_date->toDateString(), $detail->end_date->toDateString());

        foreach ($period as $day) {
            $hasSchedule = AttendanceSchedule::query()
                ->where('user_id', $process->applicant_id)
                ->where('work_date', $day->toDateString())
                ->exists();
            if (! $hasSchedule) {
                continue;
            }
            CheckinDayRecord::updateOrCreate(
                ['user_id' => $process->applicant_id, 'work_date' => $day->toDateString()],
                ['status' => CheckinDayRecord::STATUS_LEAVE, 'remark' => "请假（{$detail->leave_type}）"],
            );
        }
    }

    /** 销假通过：还原原请假单写入的请假记录 */
    private function applyLeaveCancel(OaProcess $process): void
    {
        $detail = $process->leaveCancelDetail;
        $leave = OaProcess::query()->whereKey($detail->leave_process_id)
            ->where('type', OaProcess::TYPE_LEAVE)
            ->where('status', OaProcess::STATUS_APPROVED)
            ->with('leaveDetail')
            ->first();

        if (! $leave || ! $leave->leaveDetail) {
            return;
        }

        CheckinDayRecord::query()
            ->where('user_id', $process->applicant_id)
            ->where('status', CheckinDayRecord::STATUS_LEAVE)
            ->whereBetween('work_date', [
                $leave->leaveDetail->start_date->toDateString(),
                $leave->leaveDetail->end_date->toDateString(),
            ])
            ->whereNull('check_in_at')
            ->delete();
    }

    /** 换班通过：交换两张值班表的值班人 */
    private function applyShiftChange(OaProcess $process): void
    {
        $detail = $process->shiftChangeDetail()->with(['mySchedule', 'targetSchedule'])->first();
        if (! $detail || ! $detail->mySchedule || ! $detail->targetSchedule) {
            return;
        }

        DB::transaction(function () use ($detail) {
            $mine = $detail->mySchedule;
            $target = $detail->targetSchedule;

            $myUserId = $mine->user_id;
            $mine->update(['user_id' => $target->user_id, 'type' => $target->user->type ?? $target->type]);
            $target->update(['user_id' => $myUserId, 'type' => User::find($myUserId)?->type ?? $mine->type]);
        });
    }

    /** 补卡通过：写打卡流水并更新当日汇总 */
    private function applyAppendCheckin(OaProcess $process): void
    {
        $detail = $process->appendCheckinDetail;
        $checkedAt = Carbon::parse($detail->work_date->toDateString().' '.$detail->check_time);

        CheckinLog::create([
            'user_id' => $process->applicant_id,
            'work_date' => $detail->work_date->toDateString(),
            'check_type' => $detail->check_type,
            'checked_at' => $checkedAt,
            'result' => 'normal',
            'source' => 'oa',
        ]);

        $schedule = AttendanceSchedule::query()
            ->where('user_id', $process->applicant_id)
            ->where('work_date', $detail->work_date->toDateString())
            ->with('shift')
            ->first();

        $record = CheckinDayRecord::firstOrNew([
            'user_id' => $process->applicant_id,
            'work_date' => $detail->work_date->toDateString(),
        ]);

        if ($detail->check_type === 'in' && $record->check_in_at === null) {
            $record->check_in_at = $checkedAt;
            if ($schedule) {
                $shiftStart = Carbon::parse($detail->work_date->toDateString().' '.$schedule->shift->start_time);
                $lateMinutes = $checkedAt->gt($shiftStart) ? (int) $shiftStart->diffInMinutes($checkedAt) : 0;
                $record->late_minutes = $lateMinutes;
                $record->status = $lateMinutes > $schedule->shift->late_threshold_minutes
                    ? CheckinDayRecord::STATUS_LATE
                    : CheckinDayRecord::STATUS_NORMAL;
            } else {
                $record->status = CheckinDayRecord::STATUS_NORMAL;
            }
        } elseif ($detail->check_type === 'out') {
            $record->check_out_at = $checkedAt;
            if ($record->status === CheckinDayRecord::STATUS_UNKNOWN) {
                $record->status = CheckinDayRecord::STATUS_NORMAL;
            }
        }

        $record->save();
    }
}
