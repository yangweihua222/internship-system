<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index(): JsonResponse
    {
        $list = Shift::query()->orderBy('id')->get();

        return response()->json(['list' => $list]);
    }

    public function store(Request $request): JsonResponse
    {
        $shift = Shift::create($this->validated($request));

        return response()->json(['id' => $shift->id], 201);
    }

    public function update(Request $request, Shift $shift): JsonResponse
    {
        $shift->update($this->validated($request));

        return response()->json(['message' => 'ok']);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        if ($shift->schedules()->exists()) {
            return response()->json(['message' => '该班次已被排班引用，不能删除'], 422);
        }

        $shift->delete();

        return response()->json(['message' => 'ok']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'late_threshold_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
        ]);
    }
}
