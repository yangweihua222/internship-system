<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $list = Role::query()
            ->orderBy('id')
            ->get(['id', 'name', 'title']);

        return response()->json(['list' => $list]);
    }
}
