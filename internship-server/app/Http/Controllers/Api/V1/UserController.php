<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with(['department:id,name', 'roles:id,name,title'])
            ->orderByDesc('id');

        if ($keyword = trim((string) $request->query('keyword', ''))) {
            $query->where(function ($q) use ($keyword) {
                $q->where('username', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%");
            });
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        $pageSize = min((int) $request->query('page_size', 10), 100);
        $paginator = $query->paginate($pageSize);

        return response()->json([
            'list' => collect($paginator->items())->map(fn (User $u) => $this->formatUser($u))->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $user = User::create([
            'username' => $data['username'],
            'name' => $data['name'],
            'password' => $data['password'],
            'type' => $data['type'],
            'phone' => $data['phone'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'status' => true,
        ]);

        if (! empty($data['role_names'])) {
            $user->syncRoles($data['role_names']);
        }

        return response()->json(['id' => $user->id], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $this->validated($request, $user, isUpdate: true);

        $user->fill([
            'name' => $data['name'],
            'type' => $data['type'],
            'phone' => $data['phone'] ?? null,
            'department_id' => $data['department_id'] ?? null,
        ]);

        if (array_key_exists('status', $data)) {
            $user->status = (bool) $data['status'];
        }

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        if (array_key_exists('role_names', $data)) {
            $user->syncRoles($data['role_names'] ?? []);
        }

        return response()->json(['message' => 'ok']);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => '不能删除当前登录账号'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'ok']);
    }

    private function validated(Request $request, ?User $user = null, bool $isUpdate = false): array
    {
        $usernameRule = $isUpdate ? [] : ['required', 'string', 'max:64', Rule::unique('users', 'username')];
        $passwordRule = $isUpdate ? ['nullable', 'string', 'min:6'] : ['required', 'string', 'min:6'];

        $rules = [
            'username' => $usernameRule,
            'name' => ['required', 'string', 'max:50'],
            'password' => $passwordRule,
            'type' => ['required', Rule::in([User::TYPE_ADMIN, User::TYPE_TEACHER, User::TYPE_STUDENT])],
            'phone' => ['nullable', 'string', 'max:20'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'role_names' => ['nullable', 'array'],
            'role_names.*' => ['string', Rule::exists('roles', 'name')],
        ];

        if ($isUpdate) {
            $rules['status'] = ['nullable', 'boolean'];
        }

        return $request->validate($rules);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'type' => $user->type,
            'phone' => $user->phone,
            'status' => $user->status ? 1 : 0,
            'department' => $user->department?->only(['id', 'name']),
            'roles' => $user->roles->map(fn (Role $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'title' => $r->title,
            ])->all(),
        ];
    }
}
