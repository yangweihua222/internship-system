<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceGroupController extends Controller
{
    public function index(): JsonResponse
    {
        $list = AttendanceGroup::query()
            ->with(['department:id,name', 'members:id,name,username'])
            ->orderByDesc('id')
            ->get();

        return response()->json(['list' => $list]);
    }

    public function store(Request $request): JsonResponse
    {
        $group = AttendanceGroup::create($this->validated($request));

        return response()->json(['id' => $group->id], 201);
    }

    public function update(Request $request, AttendanceGroup $group): JsonResponse
    {
        $group->update($this->validated($request));

        return response()->json(['message' => 'ok']);
    }

    public function destroy(AttendanceGroup $group): JsonResponse
    {
        if ($group->schedules()->exists()) {
            return response()->json(['message' => '该考勤组已被排班引用，不能删除'], 422);
        }

        $group->delete();

        return response()->json(['message' => 'ok']);
    }

    /** 整组替换成员 */
    public function assignMembers(Request $request, AttendanceGroup $group): JsonResponse
    {
        $data = $request->validate([
            'user_ids' => ['present', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $group->members()->sync($data['user_ids']);

        return response()->json(['message' => 'ok']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_m' => ['nullable', 'integer', 'min:50', 'max:5000'],
            'remark' => ['nullable', 'string'],
        ]);
    }
}
