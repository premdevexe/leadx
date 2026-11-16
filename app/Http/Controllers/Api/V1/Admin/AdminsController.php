<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Admin account management — API mirror of the web /admin/admins panel
 * (create + edit; deletion stays web-only to guarantee at least one
 * reachable super admin).
 */
class AdminsController extends Controller
{
    /**
     * GET /api/v1/admin/admins
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return AdminResource::collection(
            Admin::orderBy('id')->paginate(min(max((int) $request->input('per_page', 20), 1), 100))
        );
    }

    /**
     * POST /api/v1/admin/admins
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:admins,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        $admin = Admin::create($data);

        return (new AdminResource($admin))->response()->setStatusCode(201);
    }

    /**
     * PUT /api/v1/admin/admins/{admin}
     *
     * Password is optional — a blank / omitted password keeps the current
     * one (same rule as the web panel).
     */
    public function update(Request $request, int $admin): JsonResponse
    {
        $model = Admin::findOrFail($admin);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('admins', 'username')->ignore($model->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($model->id)],
            'password' => ['sometimes', 'string', 'confirmed', Password::min(8)],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $model->update($data);

        return (new AdminResource($model->fresh()))->response();
    }
}
