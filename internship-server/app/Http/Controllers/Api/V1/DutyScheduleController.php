<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DutySchedule;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DutyScheduleController extends Controller
{
    /** 按月查看值班表（可过滤老师/学生、科室） */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'type' => ['nullable', 'in:teacher,student'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $start = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $query = DutySchedule::query()
            ->whereBetween('duty_date', [$start->toDateString(), $end->toDateString()])
            ->with(['user:id,name,username,type', 'department:id,name'])
            ->orderBy('duty_date');

        if (! empty($data['type'])) {
            $query->where('type', $data['type']);
        }
        if (! empty($data['department_id'])) {
            $query->where('department_id', $data['department_id']);
        }

        return response()->json(['list' => $query->get()]);
    }

    /** 我的值班（学生/老师查自己） */
    public function mine(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $start = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $list = DutySchedule::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('duty_date', [$start->toDateString(), $end->toDateString()])
            ->with('department:id,name')
            ->orderBy('duty_date')
            ->get();

        return response()->json(['list' => $list]);
    }

    /** 批量排值班：一组人 × 日期范围 × 时段标签 */
    public function bulkStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'shift_label' => ['required', 'string', 'max:30'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
        ]);

        $weekdays = $data['weekdays'] ?? null;
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($data, $weekdays, $request, &$created, &$skipped) {
            $period = CarbonPeriod::create($data['start_date'], $data['end_date']);
            foreach ($data['user_ids'] as $userId) {
                $user = User::find($userId);
                foreach ($period as $day) {
                    if ($weekdays !== null && ! in_array($day->isoWeekday(), $weekdays)) {
                        continue;
                    }
                    $exists = DutySchedule::query()
                        ->where('user_id', $userId)
                        ->where('duty_date', $day->toDateString())
                        ->where('shift_label', $data['shift_label'])
                        ->exists();
                    if ($exists) {
                        $skipped++;

                        continue;
                    }
                    DutySchedule::create([
                        'user_id' => $userId,
                        'department_id' => $data['department_id'] ?? $user->department_id,
                        'duty_date' => $day->toDateString(),
                        'shift_label' => $data['shift_label'],
                        'type' => $user->type === User::TYPE_TEACHER ? 'teacher' : 'student',
                        'created_by' => $request->user()->id,
                    ]);
                    $created++;
                }
            }
        });

        return response()->json(['message' => 'ok', 'created' => $created, 'skipped' => $skipped], 201);
    }

    public function destroy(DutySchedule $dutySchedule): JsonResponse
    {
        $dutySchedule->delete();

        return response()->json(['message' => 'ok']);
    }
}
