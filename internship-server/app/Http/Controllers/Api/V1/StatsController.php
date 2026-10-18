<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSchedule;
use App\Models\CheckinDayRecord;
use App\Models\Evaluation;
use App\Models\MedicalProgress;
use App\Models\OaProcess;
use App\Models\OperationRecord;
use App\Models\PatientCase;
use App\Models\RotationDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    /** 工作台数据看板 */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today()->toDateString();
        $monthStart = Carbon::now()->startOfMonth()->toDateString();

        // 基础计数
        $students = User::where('type', User::TYPE_STUDENT)->count();
        $teachers = User::where('type', User::TYPE_TEACHER)->count();
        $activeRotations = RotationDetail::query()
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->count();
        $pendingOa = OaProcess::where('status', 'pending')->count();
        $pendingTasks = PatientCase::where('status', 'submitted')->count()
            + MedicalProgress::where('status', 'submitted')->count()
            + OperationRecord::where('status', 'submitted')->count();

        // 本月出勤率
        $scheduledDays = AttendanceSchedule::query()
            ->whereBetween('work_date', [$monthStart, $today])
            ->count();
        $attendedDays = CheckinDayRecord::query()
            ->whereBetween('work_date', [$monthStart, $today])
            ->whereIn('status', ['normal', 'late', 'early'])
            ->count();
        $leaveDays = CheckinDayRecord::query()
            ->whereBetween('work_date', [$monthStart, $today])
            ->where('status', 'leave')
            ->count();

        // 近 6 个月考勤趋势
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $mStart = $month->copy()->startOfMonth()->toDateString();
            $mEnd = $month->copy()->endOfMonth()->min(Carbon::today())->toDateString();
            $scheduled = AttendanceSchedule::query()->whereBetween('work_date', [$mStart, $mEnd])->count();
            $attended = CheckinDayRecord::query()
                ->whereBetween('work_date', [$mStart, $mEnd])
                ->whereIn('status', ['normal', 'late', 'early'])
                ->count();
            $trend[] = [
                'month' => $month->format('Y-m'),
                'scheduled' => $scheduled,
                'attended' => $attended,
                'rate' => $scheduled > 0 ? round($attended / $scheduled * 100, 1) : 0,
            ];
        }

        // 评价均分（按被评价人类型）
        $avgByTarget = Evaluation::query()
            ->selectRaw('target_user_id, AVG(total_score) as avg_score, COUNT(*) as cnt')
            ->groupBy('target_user_id')
            ->orderByDesc('avg_score')
            ->limit(10)
            ->with('target:id,name,type')
            ->get()
            ->map(fn ($e) => [
                'target' => $e->target?->name,
                'type' => $e->target?->type,
                'avg_score' => round((float) $e->avg_score, 1),
                'count' => $e->cnt,
            ]);

        return response()->json([
            'summary' => [
                'students' => $students,
                'teachers' => $teachers,
                'active_rotations' => $activeRotations,
                'pending_oa' => $pendingOa,
                'pending_tasks' => $pendingTasks,
                'month_scheduled_days' => $scheduledDays,
                'month_attended_days' => $attendedDays,
                'month_leave_days' => $leaveDays,
                'month_attendance_rate' => $scheduledDays > 0 ? round($attendedDays / $scheduledDays * 100, 1) : 0,
            ],
            'attendance_trend' => $trend,
            'evaluation_top' => $avgByTarget,
        ]);
    }
}
