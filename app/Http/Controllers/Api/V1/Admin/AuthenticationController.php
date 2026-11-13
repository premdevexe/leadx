<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Http\Resources\AdminResource;
use App\Services\AuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticationController extends Controller
{
    public function __construct(protected AuthenticationService $auth)
    {
    }

    /**
     * POST /api/v1/admin/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $token = $this->auth->admin(
            $request->validated('login'),
            $request->validated('password'),
            $request->ip()
        );

        return response()->json([
            'token_type' => 'Bearer',
            'token' => $token->plainTextToken,
        ]);
    }

    /**
     * GET /api/v1/admin/profile
     */
    public function profile(Request $request): JsonResponse
    {
        return (new AdminResource($request->user('sanctum')))->response();
    }

    /**
     * POST /api/v1/admin/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->auth->revoke($request->user('sanctum'));

        return response()->json(['message' => 'Logged out.']);
    }
}
