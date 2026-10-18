<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DutySchedule;
use App\Models\OaProcess;
use App\Models\OaProcessNode;
use App\Models\User;
use App\Services\OaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OaController extends Controller
{
    public function __construct(private readonly OaService $oaService) {}

    /** 可选审批人列表：老师 + 管理员（用于发起时选择） */
    public function approvers(): JsonResponse
    {
        $list = User::query()
            ->where('status', true)
            ->whereIn('type', [User::TYPE_ADMIN, User::TYPE_TEACHER])
            ->orderBy('type')
            ->orderBy('id')
            ->get(['id', 'name', 'type', 'department_id']);

        return response()->json(['list' => $list]);
    }

    /** 我的申请 */
    public function myProcesses(Request $request): JsonResponse
    {
        $query = OaProcess::query()
            ->where('applicant_id', $request->user()->id)
            ->with(['nodes.approver:id,name', 'leaveDetail', 'leaveCancelDetail', 'shiftChangeDetail.mySchedule', 'shiftChangeDetail.targetSchedule.user:id,name', 'appendCheckinDetail'])
            ->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(['list' => $query->get()]);
    }

    /** 待我审批 */
    public function pending(Request $request): JsonResponse
    {
        $nodes = OaProcessNode::query()
            ->where('approver_id', $request->user()->id)
            ->where('status', 'pending')
            ->whereHas('process', function ($q) {
                $q->where('status', OaProcess::STATUS_PENDING)
                    ->whereColumn('current_node', 'oa_process_nodes.node_order');
            })
            ->with(['process.applicant:id,name,type', 'process.leaveDetail', 'process.leaveCancelDetail', 'process.shiftChangeDetail.mySchedule', 'process.shiftChangeDetail.targetSchedule.user:id,name', 'process.appendCheckinDetail', 'process.nodes.approver:id,name'])
            ->orderByDesc('id')
            ->get();

        return response()->json(['list' => $nodes]);
    }

    /** 我审批过的历史 */
    public function acted(Request $request): JsonResponse
    {
        $nodes = OaProcessNode::query()
            ->where('approver_id', $request->user()->id)
            ->whereIn('status', ['approved', 'rejected'])
            ->with(['process.applicant:id,name', 'process.leaveDetail', 'process.appendCheckinDetail'])
            ->orderByDesc('acted_at')
            ->limit(50)
            ->get();

        return response()->json(['list' => $nodes]);
    }

    /** 发起请假 */
    public function submitLeave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'leave_type' => ['required', Rule::in(['事假', '病假', '其他'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:500'],
            'approver_ids' => ['required', 'array', 'min:1'],
            'approver_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        $title = "{$user->name} 请假（{$data['leave_type']} {$data['start_date']} 至 {$data['end_date']}）";

        $process = $this->oaService->submit($user, OaProcess::TYPE_LEAVE, $title, [
            'leave_type' => $data['leave_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'],
        ], $data['approver_ids']);

        return response()->json(['id' => $process->id], 201);
    }

    /** 发起销假 */
    public function submitLeaveCancel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'leave_process_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:500'],
            'approver_ids' => ['required', 'array', 'min:1'],
            'approver_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $leave = OaProcess::query()
            ->whereKey($data['leave_process_id'])
            ->where('type', OaProcess::TYPE_LEAVE)
            ->where('status', OaProcess::STATUS_APPROVED)
            ->where('applicant_id', $request->user()->id)
            ->first();

        if (! $leave) {
            abort(422, '原请假单不存在或尚未通过');
        }

        $title = "{$request->user()->name} 销假（原单 #{$leave->id}）";

        $process = $this->oaService->submit($request->user(), OaProcess::TYPE_LEAVE_CANCEL, $title, [
            'leave_process_id' => $leave->id,
            'reason' => $data['reason'],
        ], $data['approver_ids']);

        return response()->json(['id' => $process->id], 201);
    }

    /** 发起换班 */
    public function submitShiftChange(Request $request): JsonResponse
    {
        $data = $request->validate([
            'my_schedule_id' => ['required', 'integer', 'exists:duty_schedules,id'],
            'target_schedule_id' => ['required', 'integer', 'exists:duty_schedules,id', 'different:my_schedule_id'],
            'reason' => ['required', 'string', 'max:500'],
            'approver_ids' => ['required', 'array', 'min:1'],
            'approver_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $mine = DutySchedule::findOrFail($data['my_schedule_id']);
        $target = DutySchedule::findOrFail($data['target_schedule_id']);

        if ($mine->user_id !== $request->user()->id) {
            abort(422, '只能选择自己的值班发起换班');
        }

        $title = "{$request->user()->name} 换班：{$mine->duty_date->toDateString()}({$mine->shift_label}) ⇄ {$target->user->name} {$target->duty_date->toDateString()}({$target->shift_label})";

        $process = $this->oaService->submit($request->user(), OaProcess::TYPE_SHIFT_CHANGE, $title, [
            'my_schedule_id' => $mine->id,
            'target_schedule_id' => $target->id,
            'reason' => $data['reason'],
        ], $data['approver_ids']);

        return response()->json(['id' => $process->id], 201);
    }

    /** 发起补卡 */
    public function submitAppendCheckin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'check_type' => ['required', Rule::in(['in', 'out'])],
            'check_time' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:500'],
            'approver_ids' => ['required', 'array', 'min:1'],
            'approver_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $typeText = $data['check_type'] === 'in' ? '上班' : '下班';
        $title = "{$request->user()->name} 补卡：{$data['work_date']} {$typeText} {$data['check_time']}";

        $process = $this->oaService->submit($request->user(), OaProcess::TYPE_APPEND_CHECKIN, $title, [
            'work_date' => $data['work_date'],
            'check_type' => $data['check_type'],
            'check_time' => $data['check_time'],
            'reason' => $data['reason'],
        ], $data['approver_ids']);

        return response()->json(['id' => $process->id], 201);
    }

    /** 审批通过 */
    public function approve(Request $request, OaProcessNode $node): JsonResponse
    {
        $this->authorizeNode($request, $node);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:500']]);

        $process = $this->oaService->act($node, true, $data['comment'] ?? null);

        return response()->json(['message' => 'ok', 'process_status' => $process->status]);
    }

    /** 审批驳回 */
    public function reject(Request $request, OaProcessNode $node): JsonResponse
    {
        $this->authorizeNode($request, $node);
        $data = $request->validate(['comment' => ['required', 'string', 'max:500']]);

        $process = $this->oaService->act($node, false, $data['comment']);

        return response()->json(['message' => 'ok', 'process_status' => $process->status]);
    }

    /** 撤销自己的待审批单 */
    public function cancel(Request $request, OaProcess $process): JsonResponse
    {
        if ($process->applicant_id !== $request->user()->id) {
            abort(403, '只能撤销自己的申请');
        }
        if ($process->status !== OaProcess::STATUS_PENDING) {
            abort(422, '该申请已结束，不能撤销');
        }

        $process->update(['status' => OaProcess::STATUS_CANCELED]);

        return response()->json(['message' => 'ok']);
    }

    private function authorizeNode(Request $request, OaProcessNode $node): void
    {
        if ($node->approver_id !== $request->user()->id) {
            abort(403, '你不是该节点的审批人');
        }
    }
}
