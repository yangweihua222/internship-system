<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CheckinDayRecord;
use App\Models\DutySchedule;
use App\Models\Evaluation;
use App\Models\Notice;
use App\Models\OaProcess;
use App\Models\OaProcessNode;
use App\Models\PatientCase;
use App\Models\RotationDetail;
use App\Models\TeachingEvent;
use App\Models\User;
use App\Services\OaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * AI 教学助手工具网关。
 *
 * AI 助手（127.0.0.1:8001）在用户对话中需要查/操作系统数据时，
 * 透传用户的 Sanctum token 调本入口。数据权限 = token 所属用户的权限。
 * 白名单之外的工具一律 404。
 */
class AiToolController extends Controller
{
    public function __construct(private readonly OaService $oaService) {}

    /** 工具白名单：name => [method, 允许的角色类型] */
    private const TOOLS = [
        'get_my_rotation' => ['myRotation', null],
        'get_my_duty' => ['myDuty', null],
        'get_my_attendance' => ['myAttendance', null],
        'get_attendance_stats' => ['attendanceStats', null],
        'get_my_leaves' => ['myLeaves', null],
        'submit_leave' => ['submitLeave', null],
        'list_teaching_events' => ['teachingEvents', null],
        'list_notices' => ['notices', null],
        'get_my_evaluations' => ['myEvaluations', null],
        'get_my_patient_cases' => ['myPatientCases', null],
        'get_my_tasks_summary' => ['tasksSummary', null],
        'get_department_students' => ['departmentStudents', 'staff'],
        'get_pending_approvals' => ['pendingApprovals', 'staff'],
    ];

    /** 统一入口：POST /api/v1/ai/tools/call  {tool, arguments} */
    public function call(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tool' => ['required', 'string'],
            'arguments' => ['nullable', 'array'],
        ]);

        $tool = $data['tool'];
        if (! isset(self::TOOLS[$tool])) {
            return response()->json(['ok' => false, 'error' => "unknown tool: {$tool}"], 404);
        }

        [$method, $roleLimit] = self::TOOLS[$tool];
        $user = $request->user();

        if ($roleLimit === 'staff' && ! $this->isStaff($user)) {
            return response()->json(['ok' => false, 'error' => '该工具仅老师/管理员可用'], 403);
        }

        $args = $data['arguments'] ?? [];

        try {
            $result = $this->$method($user, $args);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['ok' => false, 'error' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage() ?: '请求被拒绝'], $e->getStatusCode());
        }

        return response()->json(['ok' => true, 'data' => $result]);
    }

    /** 工具元数据（给 AI 助手生成 OpenAI tools schema 用，也可给前端展示） */
    public function schema(): JsonResponse
    {
        return response()->json(['tools' => array_keys(self::TOOLS)]);
    }

    private function isStaff(User $user): bool
    {
        return in_array($user->type, [User::TYPE_ADMIN, User::TYPE_TEACHER], true)
            || $user->hasRole(['admin', 'teaching-admin', 'teacher']);
    }

    /** 日期时间统一格式化为本地时间 "Y-m-d H:i"（datetime cast 默认会序列化成 UTC ISO 串） */
    private function fmt($dt): ?string
    {
        if ($dt === null) {
            return null;
        }
        return $dt instanceof \DateTimeInterface ? Carbon::instance($dt)->format('Y-m-d H:i') : (string) $dt;
    }

    // ---------------- 只读工具 ----------------

    /** 我的轮转分段安排 */
    private function myRotation(User $user, array $args): array
    {
        $today = Carbon::today();

        $list = RotationDetail::query()
            ->where('student_id', $user->id)
            ->with(['department:id,name', 'plan:id,name,status'])
            ->orderBy('start_date')
            ->get()
            ->map(fn (RotationDetail $d) => [
                'plan' => $d->plan?->name,
                'department' => $d->department?->name,
                'start_date' => $d->start_date->toDateString(),
                'end_date' => $d->end_date->toDateString(),
                'is_current' => $d->start_date->lte($today) && $d->end_date->gte($today),
                'status' => $d->status,
            ])
            ->values()
            ->all();

        return ['today' => $today->toDateString(), 'list' => $list];
    }

    /** 我的值班表 */
    private function myDuty(User $user, array $args): array
    {
        $month = $args['month'] ?? Carbon::now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $list = DutySchedule::query()
            ->where('user_id', $user->id)
            ->whereBetween('duty_date', [$start->toDateString(), $end->toDateString()])
            ->with('department:id,name')
            ->orderBy('duty_date')
            ->get()
            ->map(fn (DutySchedule $d) => [
                'date' => $d->duty_date->toDateString(),
                'shift' => $d->shift_label,
                'department' => $d->department?->name,
            ])
            ->values()
            ->all();

        return ['month' => $month, 'list' => $list];
    }

    /** 我的打卡记录（按天汇总） */
    private function myAttendance(User $user, array $args): array
    {
        $month = $args['month'] ?? Carbon::now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $list = CheckinDayRecord::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('work_date')
            ->get()
            ->map(fn (CheckinDayRecord $r) => [
                'date' => $r->work_date->toDateString(),
                'status' => $r->status,
                'check_in_at' => $this->fmt($r->check_in_at),
                'check_out_at' => $this->fmt($r->check_out_at),
                'late_minutes' => $r->late_minutes ?? 0,
            ])
            ->values()
            ->all();

        return ['month' => $month, 'list' => $list];
    }

    /** 我的月度考勤统计 */
    private function attendanceStats(User $user, array $args): array
    {
        $month = $args['month'] ?? Carbon::now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $today = Carbon::today();

        $records = CheckinDayRecord::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($r) => $r->work_date->toDateString());

        $scheduledDays = \App\Models\AttendanceSchedule::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->where('work_date', '<=', $today->toDateString())
            ->pluck('work_date');

        $stat = ['normal' => 0, 'late' => 0, 'early' => 0, 'absent' => 0, 'leave' => 0];
        foreach ($scheduledDays as $date) {
            $dateStr = Carbon::parse($date)->toDateString();
            $status = $records->get($dateStr)?->status;
            if ($status === null && Carbon::parse($dateStr)->lt($today)) {
                $status = CheckinDayRecord::STATUS_ABSENT;
            }
            if ($status !== null && isset($stat[$status])) {
                $stat[$status]++;
            }
        }

        return ['month' => $month, 'scheduled_days' => $scheduledDays->count(), 'stat' => $stat];
    }

    /** 我的 OA 申请（请假/换班/补卡等） */
    private function myLeaves(User $user, array $args): array
    {
        $list = OaProcess::query()
            ->where('applicant_id', $user->id)
            ->with(['leaveDetail', 'nodes.approver:id,name'])
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (OaProcess $p) => [
                'id' => $p->id,
                'type' => $p->type,
                'title' => $p->title,
                'status' => $p->status,
                'leave' => $p->leaveDetail ? [
                    'leave_type' => $p->leaveDetail->leave_type,
                    'start_date' => $p->leaveDetail->start_date,
                    'end_date' => $p->leaveDetail->end_date,
                    'reason' => $p->leaveDetail->reason,
                ] : null,
                'created_at' => $p->created_at?->toDateTimeString(),
            ])
            ->values()
            ->all();

        return ['list' => $list];
    }

    /** 教学活动 */
    private function teachingEvents(User $user, array $args): array
    {
        $query = TeachingEvent::query()
            ->with('department:id,name')
            ->orderByDesc('start_at')
            ->limit(20);

        if (! empty($args['status'])) {
            $query->where('status', $args['status']);
        }

        $list = $query->get()->map(fn (TeachingEvent $e) => [
            'id' => $e->id,
            'title' => $e->title,
            'start_at' => $this->fmt($e->start_at),
            'end_at' => $this->fmt($e->end_at),
            'location' => $e->location,
            'department' => $e->department?->name,
            'status' => $e->status,
        ])->values()->all();

        return ['list' => $list];
    }

    /** 通知公告 */
    private function notices(User $user, array $args): array
    {
        $list = Notice::query()
            ->orderByDesc('published_at')
            ->limit(10)
            ->get()
            ->map(fn (Notice $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'content' => $n->content,
                'published_at' => $this->fmt($n->published_at),
            ])
            ->values()
            ->all();

        return ['list' => $list];
    }

    /** 我的考核成绩 */
    private function myEvaluations(User $user, array $args): array
    {
        $list = Evaluation::query()
            ->where('target_user_id', $user->id)
            ->with(['evaluator:id,name', 'department:id,name', 'template:id,name'])
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (Evaluation $e) => [
                'id' => $e->id,
                'template' => $e->template?->name,
                'evaluator' => $e->evaluator?->name,
                'department' => $e->department?->name,
                'period' => $e->period,
                'total_score' => $e->total_score,
                'comment' => $e->comment,
            ])
            ->values()
            ->all();

        return ['list' => $list];
    }

    /** 我的病历记录及评语 */
    private function myPatientCases(User $user, array $args): array
    {
        $list = PatientCase::query()
            ->where('student_id', $user->id)
            ->with(['department:id,name', 'reviewer:id,name'])
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (PatientCase $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'patient_name' => $c->patient_name,
                'department' => $c->department?->name,
                'status' => $c->status,
                'score' => $c->score,
                'comment' => $c->comment,
                'reviewer' => $c->reviewer?->name,
            ])
            ->values()
            ->all();

        return ['list' => $list];
    }

    /** 待办汇总 */
    private function tasksSummary(User $user, array $args): array
    {
        $today = Carbon::today();

        $summary = [
            'unreviewed_patient_cases' => PatientCase::query()
                ->where('student_id', $user->id)->where('status', 'submitted')->count(),
            'my_pending_processes' => OaProcess::query()
                ->where('applicant_id', $user->id)->where('status', OaProcess::STATUS_PENDING)->count(),
            'duty_left_this_month' => DutySchedule::query()
                ->where('user_id', $user->id)
                ->whereBetween('duty_date', [$today->toDateString(), $today->copy()->endOfMonth()->toDateString()])
                ->count(),
        ];

        if ($this->isStaff($user)) {
            $summary['pending_approvals'] = OaProcessNode::query()
                ->where('approver_id', $user->id)
                ->where('status', 'pending')
                ->whereHas('process', function ($q) {
                    $q->where('status', OaProcess::STATUS_PENDING)
                        ->whereColumn('current_node', 'oa_process_nodes.node_order');
                })
                ->count();
        }

        return $summary;
    }

    // ---------------- 写操作 ----------------

    /** 提交请假（审批人自动选教学管理员，仍走原 OA 审批流） */
    private function submitLeave(User $user, array $args): array
    {
        validator($args, [
            'leave_type' => ['required', Rule::in(['事假', '病假', '其他'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:500'],
        ])->validate();

        // 自动选审批人：优先教学管理员，其次管理员
        $approverIds = User::query()
            ->where('status', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['teaching-admin', 'admin']))
            ->orderBy('id')
            ->limit(1)
            ->pluck('id')
            ->all();

        if (empty($approverIds)) {
            abort(422, '系统里还没有教学管理员，无法自动提交请假，请到 OA 页面手动选择审批人');
        }

        $title = "{$user->name} 请假（{$args['leave_type']} {$args['start_date']} 至 {$args['end_date']}）";

        $process = $this->oaService->submit($user, OaProcess::TYPE_LEAVE, $title, [
            'leave_type' => $args['leave_type'],
            'start_date' => $args['start_date'],
            'end_date' => $args['end_date'],
            'reason' => $args['reason'],
        ], $approverIds);

        return ['process_id' => $process->id, 'message' => '请假已提交，等待审批'];
    }

    // ---------------- 老师/管理员专属 ----------------

    /** 某科室今天在科学生 */
    private function departmentStudents(User $user, array $args): array
    {
        validator($args, ['department_id' => ['required', 'integer', 'exists:departments,id']])->validate();

        $date = $args['date'] ?? Carbon::today()->toDateString();

        $list = RotationDetail::query()
            ->where('department_id', $args['department_id'])
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->with(['student:id,name,username', 'plan:id,name'])
            ->orderBy('start_date')
            ->get()
            ->map(fn (RotationDetail $d) => [
                'student' => $d->student?->name,
                'username' => $d->student?->username,
                'plan' => $d->plan?->name,
                'start_date' => $d->start_date->toDateString(),
                'end_date' => $d->end_date->toDateString(),
            ])
            ->values()
            ->all();

        return ['date' => $date, 'list' => $list];
    }

    /** 待我审批的单子 */
    private function pendingApprovals(User $user, array $args): array
    {
        $list = OaProcessNode::query()
            ->where('approver_id', $user->id)
            ->where('status', 'pending')
            ->whereHas('process', function ($q) {
                $q->where('status', OaProcess::STATUS_PENDING)
                    ->whereColumn('current_node', 'oa_process_nodes.node_order');
            })
            ->with(['process.applicant:id,name,type', 'process.leaveDetail'])
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (OaProcessNode $n) => [
                'process_id' => $n->process_id,
                'title' => $n->process?->title,
                'type' => $n->process?->type,
                'applicant' => $n->process?->applicant?->name,
            ])
            ->values()
            ->all();

        return ['list' => $list];
    }
}
