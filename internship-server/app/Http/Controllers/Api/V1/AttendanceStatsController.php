<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSchedule;
use App\Models\CheckinDayRecord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceStatsController extends Controller
{
    /** 月度考勤统计：按人聚合（管理员/老师视角） */
    public function monthly(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'group_id' => ['nullable', 'integer', 'exists:attendance_groups,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $start = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $today = Carbon::today();

        $scheduleQuery = AttendanceSchedule::query()
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->where('work_date', '<=', $today->toDateString());

        if (! empty($data['group_id'])) {
            $scheduleQuery->where('group_id', $data['group_id']);
        }
        if (! empty($data['department_id'])) {
            $scheduleQuery->whereHas('user', fn ($q) => $q->where('department_id', $data['department_id']));
        }

        $schedules = $scheduleQuery->with('user:id,name,username')->get();

        $records = CheckinDayRecord::query()
            ->whereIn('user_id', $schedules->pluck('user_id')->unique())
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('user_id');

        $list = $schedules->groupBy('user_id')->map(function ($userSchedules, $userId) use ($records, $today) {
            $userRecords = $records->get($userId, collect())->keyBy(fn ($r) => $r->work_date->toDateString());
            $user = $userSchedules->first()->user;

            $stat = [
                'user_id' => $userId,
                'username' => $user->username,
                'name' => $user->name,
                'scheduled_days' => 0,
                'normal' => 0,
                'late' => 0,
                'early' => 0,
                'absent' => 0,
                'leave' => 0,
            ];

            foreach ($userSchedules as $schedule) {
                $dateStr = $schedule->work_date->toDateString();
                $stat['scheduled_days']++;
                $record = $userRecords->get($dateStr);
                $status = $record?->status;

                // 已过日期无记录记为缺勤
                if ($status === null && Carbon::parse($dateStr)->lt($today)) {
                    $status = CheckinDayRecord::STATUS_ABSENT;
                }
                if ($status === null || $status === CheckinDayRecord::STATUS_UNKNOWN) {
                    continue; // 今天还没打完，不计
                }
                if (isset($stat[$status])) {
                    $stat[$status]++;
                }
            }

            return $stat;
        })->values();

        return response()->json(['month' => $data['month'], 'list' => $list]);
    }
}
