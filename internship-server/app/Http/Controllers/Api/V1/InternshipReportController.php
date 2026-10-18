<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InternshipReport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InternshipReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = InternshipReport::query()
            ->with(['student:id,name,username', 'department:id,name', 'reviewer:id,name'])
            ->orderByDesc('id');

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

        return response()->json(['list' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $report = InternshipReport::create(array_merge($data, [
            'student_id' => $request->user()->id,
            'department_id' => $request->user()->department_id,
            'status' => $data['status'] ?? 'submitted',
        ]));

        return response()->json(['id' => $report->id], 201);
    }

    public function update(Request $request, InternshipReport $report): JsonResponse
    {
        $this->authorizeEdit($request, $report);

        $data = $request->validate($this->rules());
        $report->update(array_merge($data, ['status' => $data['status'] ?? 'submitted']));

        return response()->json(['message' => 'ok']);
    }

    public function destroy(Request $request, InternshipReport $report): JsonResponse
    {
        $this->authorizeEdit($request, $report);
        $report->delete();

        return response()->json(['message' => 'ok']);
    }

    public function review(Request $request, InternshipReport $report): JsonResponse
    {
        if (! in_array($report->status, ['submitted', 'approved', 'rejected'])) {
            return response()->json(['message' => '该报告尚未提交'], 422);
        }

        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $report->update([
            'status' => $data['action'] === 'approve' ? 'approved' : 'rejected',
            'score' => $data['action'] === 'approve' ? ($data['score'] ?? null) : null,
            'comment' => $data['comment'] ?? null,
            'reviewer_id' => $request->user()->id,
        ]);

        return response()->json(['message' => 'ok']);
    }

    private function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['summary', 'report'])],
            'title' => ['required', 'string', 'max:200'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'content' => ['required', 'string'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'status' => ['nullable', 'in:draft,submitted'],
        ];
    }

    private function authorizeEdit(Request $request, InternshipReport $report): void
    {
        if ($report->student_id !== $request->user()->id) {
            abort(403, '只能操作自己的实习报告');
        }
        if ($report->status === 'approved') {
            abort(422, '已通过的报告不能修改或删除');
        }
    }
}
