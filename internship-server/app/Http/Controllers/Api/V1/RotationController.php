<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RotationDetail;
use App\Models\RotationPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RotationController extends Controller
{
    public function plans(Request $request): JsonResponse
    {
        $query = RotationPlan::query()
            ->withCount(['details', 'students'])
            ->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(['list' => $query->get()]);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'remark' => ['nullable', 'string'],
        ]);

        $plan = RotationPlan::create($data);

        return response()->json(['id' => $plan->id], 201);
    }

    public function updatePlan(Request $request, RotationPlan $plan): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'remark' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([RotationPlan::STATUS_DRAFT, RotationPlan::STATUS_ACTIVE, RotationPlan::STATUS_FINISHED])],
        ]);

        $plan->update($data);

        return response()->json(['message' => 'ok']);
    }

    public function destroyPlan(RotationPlan $plan): JsonResponse
    {
        if ($plan->status === RotationPlan::STATUS_ACTIVE) {
            return response()->json(['message' => '启用中的轮转不能直接删除，请先标记为已完成'], 422);
        }

        $plan->delete(); // 明细、成员级联删除

        return response()->json(['message' => 'ok']);
    }

    /** 轮转的成员学生 */
    public function members(RotationPlan $plan): JsonResponse
    {
        $students = $plan->students()->select(['users.id', 'users.name', 'users.username'])->get();

        return response()->json(['list' => $students]);
    }

    /** 把学生加入轮转 */
    public function addMembers(Request $request, RotationPlan $plan): JsonResponse
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $added = 0;
        foreach ($data['student_ids'] as $id) {
            $user = User::find($id);
            if (! $user || $user->type !== 'student') {
                return response()->json(['message' => "用户 ID {$id} 不是学生"], 422);
            }
            if (! $plan->students()->where('users.id', $id)->exists()) {
                $plan->students()->attach($id);
                $added++;
            }
        }

        return response()->json(['message' => 'ok', 'added' => $added]);
    }

    /** 把学生移出轮转（连带删除其分段安排） */
    public function removeMember(RotationPlan $plan, User $student): JsonResponse
    {
        RotationDetail::query()
            ->where('rotation_plan_id', $plan->id)
            ->where('student_id', $student->id)
            ->delete();

        $plan->students()->detach($student->id);

        return response()->json(['message' => 'ok']);
    }

    /** 轮转下的分段明细 */
    public function details(RotationPlan $plan): JsonResponse
    {
        $details = RotationDetail::query()
            ->where('rotation_plan_id', $plan->id)
            ->with(['student:id,name,username', 'department:id,name'])
            ->orderBy('student_id')
            ->orderBy('start_date')
            ->get();

        return response()->json(['list' => $details]);
    }

    /** 批量分配分段：学生 + 科室 + 起止日期（须在轮转周期内、不重叠） */
    public function assign(Request $request, RotationPlan $plan): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.student_id' => ['required', 'integer', 'exists:users,id'],
            'items.*.department_id' => ['required', 'integer', 'exists:departments,id'],
            'items.*.start_date' => ['required', 'date'],
            'items.*.end_date' => ['required', 'date', 'after_or_equal:items.*.start_date'],
        ]);

        foreach ($data['items'] as $item) {
            $student = User::findOrFail($item['student_id']);

            // 必须是本轮转的成员
            if (! $plan->students()->where('users.id', $student->id)->exists()) {
                return response()->json(['message' => "学生「{$student->name}」不在本轮转中，请先添加"], 422);
            }

            // 分段必须在轮转总周期内
            if ($plan->start_date && $plan->end_date) {
                if ($item['start_date'] < $plan->start_date->toDateString() || $item['end_date'] > $plan->end_date->toDateString()) {
                    return response()->json([
                        'message' => "学生「{$student->name}」的分段 {$item['start_date']}~{$item['end_date']} 超出轮转周期（{$plan->start_date->toDateString()}~{$plan->end_date->toDateString()}）",
                    ], 422);
                }
            }

            // 同一学生在同一轮转内日期不能重叠
            $overlap = RotationDetail::query()
                ->where('rotation_plan_id', $plan->id)
                ->where('student_id', $item['student_id'])
                ->where(function ($q) use ($item) {
                    $q->whereBetween('start_date', [$item['start_date'], $item['end_date']])
                        ->orWhereBetween('end_date', [$item['start_date'], $item['end_date']])
                        ->orWhere(function ($q2) use ($item) {
                            $q2->where('start_date', '<=', $item['start_date'])
                                ->where('end_date', '>=', $item['end_date']);
                        });
                })
                ->exists();

            if ($overlap) {
                return response()->json(['message' => "学生「{$student->name}」在该时间段已有轮转安排"], 422);
            }
        }

        DB::transaction(function () use ($plan, $data) {
            foreach ($data['items'] as $item) {
                RotationDetail::create([
                    'rotation_plan_id' => $plan->id,
                    'student_id' => $item['student_id'],
                    'department_id' => $item['department_id'],
                    'start_date' => $item['start_date'],
                    'end_date' => $item['end_date'],
                    'status' => 'scheduled',
                ]);
            }
        });

        return response()->json(['message' => 'ok']);
    }

    public function destroyDetail(RotationDetail $detail): JsonResponse
    {
        $detail->delete();

        return response()->json(['message' => 'ok']);
    }

    /** 我的轮转（学生）/ 指定学生轮转（管理员/老师可传 student_id） */
    public function calendar(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentId = (int) $request->query('student_id', 0);

        if ($studentId === 0) {
            $studentId = $user->id;
        }

        $details = RotationDetail::query()
            ->where('student_id', $studentId)
            ->with(['department:id,name', 'plan:id,name,status'])
            ->orderBy('start_date')
            ->get()
            ->map(function (RotationDetail $d) {
                $today = Carbon::today();
                $d->is_current = $d->start_date->lte($today) && $d->end_date->gte($today);

                return $d;
            });

        return response()->json(['list' => $details]);
    }

    /** 科室视角：某科室在某日期的在科学生 */
    public function departmentStudents(Request $request): JsonResponse
    {
        $data = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'date' => ['nullable', 'date'],
        ]);

        $date = $data['date'] ?? Carbon::today()->toDateString();

        $details = RotationDetail::query()
            ->where('department_id', $data['department_id'])
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->with(['student:id,name,username', 'plan:id,name'])
            ->orderBy('start_date')
            ->get();

        return response()->json(['date' => $date, 'list' => $details]);
    }
}
