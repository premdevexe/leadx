<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\TeamMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TeamMembersController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\Team $team */
        $team = Auth::guard('team')->user();

        $members = $team->members()
            ->with('user')
            ->orderByDesc('id')
            ->paginate(20);

        return view('team.members.index', [
            'members' => $members,
            'team' => $team,
        ]);
    }
}
