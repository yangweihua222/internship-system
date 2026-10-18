<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    public function index(): JsonResponse
    {
        $list = Notice::query()
            ->with('publisher:id,name')
            ->orderByDesc('published_at')
            ->limit(50)
            ->get();

        return response()->json(['list' => $list]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string'],
        ]);

        $notice = Notice::create([
            'title' => $data['title'],
            'content' => $data['content'],
            'publisher_id' => $request->user()->id,
            'published_at' => Carbon::now(),
        ]);

        return response()->json(['id' => $notice->id], 201);
    }

    public function destroy(Request $request, Notice $notice): JsonResponse
    {
        $user = $request->user();
        if ($notice->publisher_id !== $user->id && ! $user->hasRole('admin')) {
            abort(403, '只能删除自己发布的公告');
        }

        $notice->delete();

        return response()->json(['message' => 'ok']);
    }
}
