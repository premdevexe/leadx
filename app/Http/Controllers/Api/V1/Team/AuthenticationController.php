<?php

namespace App\Http\Controllers\Api\V1\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\LoginRequest;
use App\Http\Resources\TeamResource;
use App\Services\AuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticationController extends Controller
{
    public function __construct(protected AuthenticationService $auth)
    {
    }

    /**
     * POST /api/v1/team/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $token = $this->auth->team(
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
     * GET /api/v1/team/profile
     */
    public function profile(Request $request): JsonResponse
    {
        return (new TeamResource($request->user('sanctum')))->response();
    }

    /**
     * GET /api/v1/team/members
     */
    public function members(Request $request): JsonResponse
    {
        /** @var \App\Models\Team $team */
        $team = $request->user('sanctum');

        // A team can only see its own active members.
        $members = $team->members()
            ->whereNull('left_at')
            ->with('user')
            ->get();

        return response()->json([
            'data' => \App\Http\Resources\TeamMemberResource::collection($members),
        ]);
    }

    /**
     * POST /api/v1/team/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->auth->revoke($request->user('sanctum'));

        return response()->json(['message' => 'Logged out.']);
    }
}
