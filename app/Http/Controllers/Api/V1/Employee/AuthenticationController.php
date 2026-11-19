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
     * GET /api/v1/employee/me
     *
     * The caller's identity plus the flags a client needs to decide what to
     * show — team, active team-data grant, and the permission set the web
     * panel gates its menu on. (Profile details are also at GET
     * /employee/profile, which returns the same identity document.)
     */
    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user('sanctum');
        $employee->loadMissing(['team', 'preset']);

        return response()->json([
            'data' => new EmployeeResource($employee),
            'has_team_data_access' => $employee->hasActiveTeamAccess() || ! empty($employee->team_id),
            'permissions' => [
                'orders.view' => $employee->hasPermission('orders.view'),
                'orders.create' => $employee->hasPermission('orders.create'),
                'orders.complete' => $employee->hasPermission('orders.complete'),
                'customers.view' => $employee->hasPermission('customers.view'),
                'payments.view' => $employee->hasPermission('payments.view'),
                'subscriptions.view' => $employee->hasPermission('subscriptions.view'),
                'follow_ups.view' => $employee->hasPermission('follow_ups.view'),
                'follow_ups.create' => $employee->hasPermission('follow_ups.create'),
                'follow_ups.edit' => $employee->hasPermission('follow_ups.edit'),
            ],
        ]);
    }

    /**
     * GET /api/v1/employee/profile
     */
    public function profile(Request $request): JsonResponse
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user('sanctum');
        $employee->loadMissing(['team', 'preset']);

        return (new EmployeeResource($employee))->response();
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
