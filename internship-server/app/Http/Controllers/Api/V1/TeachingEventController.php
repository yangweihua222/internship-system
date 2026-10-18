<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TeachingEvent;
use App\Models\TeachingEventRecord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeachingEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TeachingEvent::query()
            ->with(['department:id,name', 'creator:id,name'])
            ->withCount('records')
            ->orderByDesc('start_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $events = $query->get();

        // 附上当前用户的签到状态
        $userId = $request->user()->id;
        $myRecords = TeachingEventRecord::query()
            ->whereIn('teaching_event_id', $events->pluck('id'))
            ->where('user_id', $userId)
            ->get()
            ->keyBy('teaching_event_id');

        $events->each(function ($event) use ($myRecords) {
            $event->my_record = $myRecords->get($event->id);
        });

        return response()->json(['list' => $events]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'content' => ['nullable', 'string'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'location' => ['nullable', 'string', 'max:200'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $event = TeachingEvent::create(array_merge($data, [
            'created_by' => $request->user()->id,
            'status' => 'published',
        ]));

        return response()->json(['id' => $event->id], 201);
    }

    public function updateStatus(Request $request, TeachingEvent $event): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['published', 'finished', 'canceled'])],
        ]);
        $event->update(['status' => $data['status']]);

        return response()->json(['message' => 'ok']);
    }

    /** 签到 / 签退 */
    public function sign(Request $request, TeachingEvent $event): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['in', 'out'])],
        ]);

        if ($event->status !== 'published') {
            abort(422, '活动已结束或取消');
        }

        $record = TeachingEventRecord::firstOrCreate(
            ['teaching_event_id' => $event->id, 'user_id' => $request->user()->id],
        );

        if ($data['action'] === 'in') {
            if ($record->sign_in_at) {
                abort(422, '已签到，请勿重复操作');
            }
            $record->sign_in_at = Carbon::now();
        } else {
            if (! $record->sign_in_at) {
                abort(422, '请先签到');
            }
            if ($record->sign_out_at) {
                abort(422, '已签退，请勿重复操作');
            }
            $record->sign_out_at = Carbon::now();
        }
        $record->save();

        return response()->json(['message' => 'ok']);
    }

    /** 活动的签到记录（老师/管理员） */
    public function records(TeachingEvent $event): JsonResponse
    {
        $records = $event->records()->with('user:id,name,username')->orderBy('user_id')->get();

        return response()->json(['list' => $records]);
    }

    /** 给学生打活动分 */
    public function scoreRecord(Request $request, TeachingEventRecord $record): JsonResponse
    {
        $data = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:100'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $record->update($data);

        return response()->json(['message' => 'ok']);
    }
}
