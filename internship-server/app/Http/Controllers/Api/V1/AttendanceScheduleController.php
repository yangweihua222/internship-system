<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSchedule;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceScheduleController extends Controller
{
    /** 按月查看排班（可按考勤组/学生过滤） */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'group_id' => ['nullable', 'integer', 'exists:attendance_groups,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $start = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $query = AttendanceSchedule::query()
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->with(['user:id,name,username', 'shift:id,name,start_time,end_time', 'group:id,name'])
            ->orderBy('work_date');

        if (! empty($data['group_id'])) {
            $query->where('group_id', $data['group_id']);
        }
        if (! empty($data['user_id'])) {
            $query->where('user_id', $data['user_id']);
        }

        return response()->json(['list' => $query->get()]);
    }

    /** 批量排班：一组人 × 日期范围 × 班次 × 考勤组（同日期已存在的跳过） */
    public function bulkStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'group_id' => ['required', 'integer', 'exists:attendance_groups,id'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
        ]);

        $weekdays = $data['weekdays'] ?? null;
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($data, $weekdays, &$created, &$skipped) {
            $period = CarbonPeriod::create($data['start_date'], $data['end_date']);

            foreach ($data['user_ids'] as $userId) {
                foreach ($period as $day) {
                    if ($weekdays !== null && ! in_array($day->isoWeekday(), $weekdays)) {
                        continue;
                    }
                    $exists = AttendanceSchedule::query()
                        ->where('user_id', $userId)
                        ->where('work_date', $day->toDateString())
                        ->exists();
                    if ($exists) {
                        $skipped++;

                        continue;
                    }
                    AttendanceSchedule::create([
                        'user_id' => $userId,
                        'work_date' => $day->toDateString(),
                        'shift_id' => $data['shift_id'],
                        'group_id' => $data['group_id'],
                    ]);
                    $created++;
                }
            }
        });

        return response()->json(['message' => 'ok', 'created' => $created, 'skipped' => $skipped], 201);
    }

    public function destroy(AttendanceSchedule $schedule): JsonResponse
    {
        $schedule->delete();

        return response()->json(['message' => 'ok']);
    }
}
