<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSchedule;
use App\Models\CheckinDayRecord;
use App\Models\CheckinLog;
use App\Services\CheckinService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CheckinController extends Controller
{
    public function __construct(private readonly CheckinService $checkinService) {}

    /** 打卡（上班/下班），前端传 GPS 坐标 */
    public function checkin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'check_type' => ['required', Rule::in(['in', 'out'])],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $result = $this->checkinService->checkin(
            $request->user(),
            $data['check_type'],
            $data['lat'] ?? null,
            $data['lng'] ?? null,
        );

        return response()->json([
            'message' => $result['message'],
            'result' => $result['log']->result,
            'distance_m' => $result['log']->distance_m,
            'day_record' => $result['day_record'],
        ]);
    }

    /** 我的打卡日历：某月的汇总记录 + 当天排班 */
    public function myRecords(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $user = $request->user();
        $start = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $records = CheckinDayRecord::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($r) => $r->work_date->toDateString());

        $schedules = AttendanceSchedule::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->with(['shift:id,name,start_time,end_time', 'group:id,name'])
            ->get()
            ->keyBy(fn ($s) => $s->work_date->toDateString());

        $logs = CheckinLog::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('checked_at')
            ->get()
            ->groupBy(fn ($l) => $l->work_date->toDateString());

        // 合并出每一天：排班 + 汇总状态 + 流水
        $days = [];
        foreach ($schedules as $date => $schedule) {
            $record = $records->get($date);
            // 过去的排班没有任何打卡记录 → 缺勤
            $status = $record?->status ?? CheckinDayRecord::STATUS_UNKNOWN;
            if ($status === CheckinDayRecord::STATUS_UNKNOWN && Carbon::parse($date)->lt(Carbon::today())) {
                $status = CheckinDayRecord::STATUS_ABSENT;
            }

            $days[] = [
                'work_date' => $date,
                'shift' => $schedule->shift,
                'group' => $schedule->group,
                'status' => $status,
                'check_in_at' => $record?->check_in_at,
                'check_out_at' => $record?->check_out_at,
                'late_minutes' => $record?->late_minutes ?? 0,
                'remark' => $record?->remark,
                'logs' => $logs->get($date, collect())->values(),
            ];
        }

        return response()->json(['month' => $data['month'], 'days' => $days]);
    }

    /** 我的打卡流水 */
    public function myLogs(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $start = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $logs = CheckinLog::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('checked_at')
            ->get();

        return response()->json(['list' => $logs]);
    }
}
