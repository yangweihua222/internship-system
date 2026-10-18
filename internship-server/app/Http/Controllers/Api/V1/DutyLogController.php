<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DutyLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DutyLogController extends Controller
{
    /** 日志列表：我写的 + 我被授权可看的（管理员可看全部） */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = DutyLog::query()
            ->with(['author:id,name,type', 'viewers:id,name'])
            ->orderByDesc('duty_date')
            ->orderByDesc('id');

        if (! $user->hasAnyRole(['admin', 'teaching-admin'])) {
            $query->where(function ($q) use ($user) {
                $q->where('author_id', $user->id)
                    ->orWhereHas('viewers', fn ($q2) => $q2->where('users.id', $user->id));
            });
        }

        return response()->json(['list' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'duty_schedule_id' => ['nullable', 'integer', 'exists:duty_schedules,id'],
            'duty_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:100'],
            'content' => ['required', 'string'],
            'viewer_ids' => ['nullable', 'array'],
            'viewer_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $log = DutyLog::create([
            'duty_schedule_id' => $data['duty_schedule_id'] ?? null,
            'author_id' => $request->user()->id,
            'duty_date' => $data['duty_date'],
            'title' => $data['title'],
            'content' => $data['content'],
        ]);

        if (! empty($data['viewer_ids'])) {
            $log->viewers()->sync($data['viewer_ids']);
        }

        return response()->json(['id' => $log->id], 201);
    }

    public function update(Request $request, DutyLog $dutyLog): JsonResponse
    {
        if ($dutyLog->author_id !== $request->user()->id) {
            abort(403, '只能修改自己的值班日志');
        }

        $data = $request->validate([
            'duty_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:100'],
            'content' => ['required', 'string'],
            'viewer_ids' => ['nullable', 'array'],
            'viewer_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $dutyLog->update(collect($data)->except('viewer_ids')->all());

        if (array_key_exists('viewer_ids', $data)) {
            $dutyLog->viewers()->sync($data['viewer_ids'] ?? []);
        }

        return response()->json(['message' => 'ok']);
    }

    public function destroy(Request $request, DutyLog $dutyLog): JsonResponse
    {
        $user = $request->user();
        if ($dutyLog->author_id !== $user->id && ! $user->hasAnyRole(['admin', 'teaching-admin'])) {
            abort(403, '只能删除自己的值班日志');
        }

        $dutyLog->delete();

        return response()->json(['message' => 'ok']);
    }
}
