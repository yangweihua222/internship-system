<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\EvaluationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EvaluationController extends Controller
{
    /** 模板列表（含题目） */
    public function templates(Request $request): JsonResponse
    {
        $query = EvaluationTemplate::query()->with('questions')->orderByDesc('id');

        if ($target = $request->query('target')) {
            $query->where('target', $target);
        }
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json(['list' => $query->get()]);
    }

    /** 新建/更新模板（含整组题目替换） */
    public function storeTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'target' => ['required', Rule::in(['student', 'teacher', 'discharge'])],
            'description' => ['nullable', 'string'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.content' => ['required', 'string', 'max:300'],
            'questions.*.max_score' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $template = DB::transaction(function () use ($data) {
            $template = EvaluationTemplate::create([
                'name' => $data['name'],
                'target' => $data['target'],
                'description' => $data['description'] ?? null,
            ]);
            foreach ($data['questions'] as $i => $q) {
                $template->questions()->create([
                    'content' => $q['content'],
                    'max_score' => $q['max_score'],
                    'sort' => $i + 1,
                ]);
            }

            return $template;
        });

        return response()->json(['id' => $template->id], 201);
    }

    public function toggleTemplate(EvaluationTemplate $template): JsonResponse
    {
        $template->update(['is_active' => ! $template->is_active]);

        return response()->json(['message' => 'ok', 'is_active' => $template->is_active]);
    }

    /** 提交评价：逐题打分 */
    public function submit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer', 'exists:evaluation_templates,id'],
            'target_user_id' => ['required', 'integer', 'exists:users,id'],
            'period' => ['nullable', 'string', 'max:50'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', 'exists:evaluation_questions,id'],
            'answers.*.score' => ['required', 'integer', 'min:0'],
        ]);

        $template = EvaluationTemplate::with('questions')->findOrFail($data['template_id']);

        if (! $template->is_active) {
            abort(422, '该评价模板已停用');
        }

        // 校验：题目都属于该模板、分数不超上限、题目齐全
        $questionMap = $template->questions->keyBy('id');
        $answerMap = collect($data['answers'])->keyBy('question_id');
        foreach ($questionMap as $qid => $question) {
            if (! $answerMap->has($qid)) {
                return response()->json(['message' => "题目「{$question->content}」未评分"], 422);
            }
            if ($answerMap[$qid]['score'] > $question->max_score) {
                return response()->json(['message' => "题目「{$question->content}」分数超出上限 {$question->max_score}"], 422);
            }
        }

        $total = $answerMap->sum('score');

        $evaluation = DB::transaction(function () use ($data, $template, $answerMap, $total, $request) {
            $evaluation = Evaluation::create([
                'template_id' => $template->id,
                'evaluator_id' => $request->user()->id,
                'target_user_id' => $data['target_user_id'],
                'department_id' => $request->user()->department_id,
                'period' => $data['period'] ?? null,
                'total_score' => $total,
                'comment' => $data['comment'] ?? null,
            ]);
            foreach ($answerMap as $qid => $answer) {
                $evaluation->answers()->create([
                    'question_id' => $qid,
                    'score' => $answer['score'],
                ]);
            }

            return $evaluation;
        });

        return response()->json(['id' => $evaluation->id, 'total_score' => $total], 201);
    }

    /** 评价结果：我收到的 / 我评价的 / 管理员看全部 */
    public function results(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Evaluation::query()
            ->with(['template:id,name,target', 'evaluator:id,name', 'target:id,name,type', 'department:id,name'])
            ->orderByDesc('id');

        $scope = $request->query('scope', 'received'); // received=我被评价 given=我评价的 all=全部(管理员)
        if ($scope === 'given') {
            $query->where('evaluator_id', $user->id);
        } elseif ($scope === 'received') {
            $query->where('target_user_id', $user->id);
        } elseif ($scope === 'all' && $user->hasAnyRole(['admin', 'teaching-admin', 'teacher'])) {
            if ($targetId = (int) $request->query('target_user_id', 0)) {
                $query->where('target_user_id', $targetId);
            }
            if ($period = $request->query('period')) {
                $query->where('period', $period);
            }
        } else {
            $query->where('target_user_id', $user->id);
        }

        return response()->json(['list' => $query->get()]);
    }

    /** 某次评价的逐题明细 */
    public function show(Evaluation $evaluation): JsonResponse
    {
        $evaluation->load(['template:id,name,target', 'evaluator:id,name', 'target:id,name', 'answers']);

        return response()->json(['detail' => $evaluation]);
    }
}
