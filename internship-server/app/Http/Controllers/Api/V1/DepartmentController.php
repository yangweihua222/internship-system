<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('keyword', ''));

        $query = Department::query()->with('children')->orderBy('sort')->orderBy('id');

        $all = (clone $query)->get();

        if ($keyword !== '') {
            // 命中的节点 + 其所有祖先，保持树形结构
            $hitIds = Department::query()
                ->where('name', 'like', "%{$keyword}%")
                ->pluck('id')
                ->all();
            $keep = $this->withAncestors($hitIds, $all);
            $all = $all->whereIn('id', $keep)->values();
        }

        $tree = $this->buildTree($all);

        return response()->json(['list' => $tree]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        if (! empty($data['parent_id'])) {
            $this->ensureParentExists($data['parent_id']);
        } else {
            $data['parent_id'] = null;
        }

        $department = Department::create($data);

        return response()->json(['id' => $department->id], 201);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $data = $this->validated($request);

        $parentId = $data['parent_id'] ?? null;
        $parentId = $parentId ?: null;

        if ($parentId !== null) {
            $this->ensureParentExists($parentId);
            if ($parentId === $department->id || $this->isDescendant($department, $parentId)) {
                return response()->json(['message' => '上级科室不能选择自己或自己的下级'], 422);
            }
        }

        $department->update([
            'name' => $data['name'],
            'parent_id' => $parentId,
            'sort' => $data['sort'] ?? 0,
        ]);

        return response()->json(['message' => 'ok']);
    }

    public function destroy(Department $department): JsonResponse
    {
        if ($department->children()->exists()) {
            return response()->json(['message' => '该科室下存在子科室，请先删除子科室'], 422);
        }

        if ($department->users()->exists()) {
            return response()->json(['message' => '该科室下仍有用户，请先转移用户'], 422);
        }

        $department->delete();

        return response()->json(['message' => 'ok']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'integer'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function ensureParentExists(int $parentId): void
    {
        if (! Department::whereKey($parentId)->exists()) {
            abort(422, '上级科室不存在');
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Department>  $all
     * @return array<int, int>
     */
    private function withAncestors(array $hitIds, $all): array
    {
        $parentMap = $all->pluck('parent_id', 'id');
        $keep = [];

        foreach ($hitIds as $id) {
            $current = $id;
            while ($current !== null && ! in_array($current, $keep)) {
                $keep[] = $current;
                $current = $parentMap[$current] ?? null;
            }
        }

        return $keep;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Department>  $all
     * @return array<int, array<string, mixed>>
     */
    private function buildTree($all): array
    {
        $nodes = [];
        foreach ($all as $dept) {
            $nodes[$dept->id] = [
                'id' => $dept->id,
                'name' => $dept->name,
                'parent_id' => $dept->parent_id,
                'sort' => $dept->sort,
                'children' => [],
            ];
        }

        $tree = [];
        foreach ($nodes as $id => &$node) {
            $parentId = $node['parent_id'];
            if ($parentId !== null && isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);

        // 空的 children 置为缺失（前端 el-tree 识别空数组也正常，保留数组即可）
        return $tree;
    }

    private function isDescendant(Department $department, int $candidateId): bool
    {
        $ids = Department::query()
            ->where('parent_id', $department->id)
            ->pluck('id')
            ->all();

        $queue = $ids;
        while ($queue) {
            $current = array_shift($queue);
            if ($current === $candidateId) {
                return true;
            }
            $queue = array_merge(
                $queue,
                Department::query()->where('parent_id', $current)->pluck('id')->all(),
            );
        }

        return false;
    }
}
