<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\LoginRequest;
use App\Http\Resources\EmployeeResource;
use App\Services\AuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticationController extends Controller
{
    public function __construct(protected AuthenticationService $auth)
    {
    }

    /**
     * POST /api/v1/employee/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $token = $this->auth->employee(
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
     * GET /api/v1/employee/profile
     */
    public function profile(Request $request): JsonResponse
    {
        return (new EmployeeResource($request->user('sanctum')))->response();
    }

    /**
     * POST /api/v1/employee/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->auth->revoke($request->user('sanctum'));

        return response()->json(['message' => 'Logged out.']);
    }
}
