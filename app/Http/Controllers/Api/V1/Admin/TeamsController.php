<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignMemberRequest;
use App\Http\Requests\Admin\StoreTeamRequest;
use App\Http\Requests\Admin\UpdateTeamRequest;
use App\Http\Resources\TeamMemberResource;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamsController extends Controller
{
    public function __construct(protected TeamService $teams)
    {
    }

    /**
     * GET /api/v1/admin/teams
     */
    public function index(): AnonymousResourceCollection
    {
        return TeamResource::collection(
            Team::withCount(['members' => fn ($q) => $q->whereNull('left_at')])
                ->orderBy('id')
                ->get()
        );
    }

    /**
     * POST /api/v1/admin/teams
     */
    public function store(StoreTeamRequest $request): JsonResponse
    {
        $team = $this->teams->create($request->validated());

        return (new TeamResource($team))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/admin/teams/{team}
     */
    public function show(Team $team): JsonResource
    {
        return new TeamResource($team);
    }

    /**
     * PUT /api/v1/admin/teams/{team}
     */
    public function update(UpdateTeamRequest $request, Team $team): JsonResource
    {
        $updated = $this->teams->update($team, $request->validated());

        return new TeamResource($updated);
    }

    /**
     * DELETE /api/v1/admin/teams/{team}
     */
    public function destroy(Team $team): JsonResponse
    {
        $this->teams->delete($team);

        return response()->json(['message' => 'Team deleted.']);
    }

    /**
     * POST /api/v1/admin/teams/{team}/members
     */
    public function assignMember(AssignMemberRequest $request, Team $team): JsonResponse
    {
        $this->teams->assignMember($team, (int) $request->validated('user_id'));

        return response()->json(['message' => 'Member assigned.'], 201);
    }

    /**
     * DELETE /api/v1/admin/teams/{team}/members/{user}
     */
    public function removeMember(Request $request, Team $team, \App\Models\User $user): JsonResponse
    {
        $this->teams->removeMember($team, $user->id);

        return response()->json(['message' => 'Member removed.']);
    }
}
