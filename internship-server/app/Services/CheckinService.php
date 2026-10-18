<?php

namespace App\Services;

use App\Models\AttendanceSchedule;
use App\Models\CheckinDayRecord;
use App\Models\CheckinLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CheckinService
{
    /**
     * 打卡：写流水 + 更新当日汇总。
     *
     * @return array{log: CheckinLog, day_record: CheckinDayRecord, message: string}
     */
    public function checkin(User $user, string $checkType, ?float $lat, ?float $lng, ?Carbon $at = null): array
    {
        $at = $at ?? Carbon::now();
        $workDate = $at->toDateString();

        $schedule = AttendanceSchedule::query()
            ->where('user_id', $user->id)
            ->where('work_date', $workDate)
            ->with(['shift', 'group'])
            ->first();

        if (! $schedule) {
            abort(422, '今天没有你的排班，无需打卡（如需补卡请走 OA 申请）');
        }

        // 围栏校验：考勤组配置了围栏且用户传了坐标时计算距离
        $distanceM = null;
        $result = 'normal';
        $group = $schedule->group;
        if ($group->hasFence() && $lat !== null && $lng !== null) {
            $distanceM = (int) round($this->haversineMeters((float) $lat, (float) $lng, (float) $group->lat, (float) $group->lng));
            if ($distanceM > $group->radius_m) {
                $result = 'outside';
            }
        }

        return DB::transaction(function () use ($user, $schedule, $checkType, $at, $lat, $lng, $distanceM, $result, $workDate) {
            $log = CheckinLog::create([
                'user_id' => $user->id,
                'work_date' => $workDate,
                'check_type' => $checkType,
                'checked_at' => $at,
                'lat' => $lat,
                'lng' => $lng,
                'distance_m' => $distanceM,
                'result' => $result,
                'source' => 'self',
            ]);

            $record = CheckinDayRecord::firstOrNew([
                'user_id' => $user->id,
                'work_date' => $workDate,
            ]);

            $shift = $schedule->shift;

            if ($checkType === 'in') {
                // 重复上班打卡以第一次为准
                if ($record->check_in_at === null) {
                    $record->check_in_at = $at;
                    $shiftStart = Carbon::parse($workDate.' '.$shift->start_time);
                    $lateMinutes = $at->gt($shiftStart) ? $shiftStart->diffInMinutes($at) : 0;
                    $record->late_minutes = (int) $lateMinutes;
                    $record->status = $lateMinutes > $shift->late_threshold_minutes
                        ? CheckinDayRecord::STATUS_LATE
                        : CheckinDayRecord::STATUS_NORMAL;
                }
            } else {
                $record->check_out_at = $at;
                $shiftEnd = Carbon::parse($workDate.' '.$shift->end_time);
                // 下班时间早于上班时间视为跨夜班
                if ($shiftEnd->lt(Carbon::parse($workDate.' '.$shift->start_time))) {
                    $shiftEnd->addDay();
                }
                if ($record->status !== CheckinDayRecord::STATUS_LEAVE) {
                    if ($at->lt($shiftEnd)) {
                        $record->status = CheckinDayRecord::STATUS_EARLY;
                    } elseif ($record->status === CheckinDayRecord::STATUS_UNKNOWN) {
                        $record->status = CheckinDayRecord::STATUS_NORMAL;
                    }
                }
            }

            $record->save();

            $message = $result === 'outside'
                ? "打卡成功，但你在围栏外（距离 {$distanceM} 米，超出 {$schedule->group->radius_m} 米范围）"
                : '打卡成功';

            return ['log' => $log, 'day_record' => $record, 'message' => $message];
        });
    }

    /** Haversine 公式，返回两点球面距离（米） */
    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earthRadius * asin(min(1, sqrt($a)));
    }
}
