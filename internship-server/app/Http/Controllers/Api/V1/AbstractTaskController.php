<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 实习任务通用控制器：病历 / 病程 / 操作记录共用的「写-提交-批改」流程。
 * 子类只需定义 $modelClass 与 $rules。
 */
abstract class AbstractTaskController extends Controller
{
    /** @var class-string<Model> */
    protected string $modelClass;

    protected string $entityName = '记录';

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = ($this->modelClass)::query()
            ->with(['student:id,name,username', 'department:id,name', 'reviewer:id,name'])
            ->orderByDesc('id');

        // 学生只看自己；老师看本科室的；管理员看全部
        if ($user->hasRole('student')) {
            $query->where('student_id', $user->id);
        } elseif ($user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'teaching-admin'])) {
            if ($user->department_id) {
                $query->where('department_id', $user->department_id);
            }
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($studentId = (int) $request->query('student_id', 0)) {
            $query->where('student_id', $studentId);
        }

        return response()->json(['list' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $item = ($this->modelClass)::create(array_merge($data, [
            'student_id' => $request->user()->id,
            'department_id' => $request->user()->department_id,
            'status' => $data['status'] ?? 'submitted',
        ]));

        return response()->json(['id' => $item->id], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = ($this->modelClass)::findOrFail($id);
        $this->authorizeStudentEdit($request, $item);

        $data = $request->validate($this->rules(isUpdate: true));
        $item->update(array_merge($data, ['status' => $data['status'] ?? 'submitted']));

        return response()->json(['message' => 'ok']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = ($this->modelClass)::findOrFail($id);
        $this->authorizeStudentEdit($request, $item);
        $item->delete();

        return response()->json(['message' => 'ok']);
    }

    /** 老师批改：通过给分 / 驳回重写 */
    public function review(Request $request, int $id): JsonResponse
    {
        $item = ($this->modelClass)::findOrFail($id);

        if (! in_array($item->status, ['submitted', 'approved', 'rejected'])) {
            return response()->json(['message' => "该{$this->entityName}尚未提交"], 422);
        }

        $data = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $item->update([
            'status' => $data['action'] === 'approve' ? 'approved' : 'rejected',
            'score' => $data['action'] === 'approve' ? ($data['score'] ?? null) : null,
            'comment' => $data['comment'] ?? null,
            'reviewer_id' => $request->user()->id,
            'reviewed_at' => Carbon::now(),
        ]);

        return response()->json(['message' => 'ok']);
    }

    private function authorizeStudentEdit(Request $request, Model $item): void
    {
        if ($item->student_id !== $request->user()->id) {
            abort(403, "只能操作自己的{$this->entityName}");
        }
        if (! in_array($item->status, ['draft', 'submitted', 'rejected'])) {
            abort(422, "已通过的{$this->entityName}不能修改或删除");
        }
    }

    /** 子类返回校验规则 */
    abstract protected function rules(bool $isUpdate = false): array;
}
