<?php

namespace App\Http\Controllers\Team;

use App\Models\FollowUp;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FollowUpsController extends \App\Http\Controllers\Employee\FollowUpsController
{
    protected string $portal = 'team';

    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(FollowUp::STATUSES)]]);
        $followUps = FollowUp::where('team_id', $request->user()->id)
            ->with(['customer', 'assignedEmployee'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('scheduled_at')->paginate(20)->withQueryString();

        return view('team.followups.index', ['followUps' => $followUps, 'portal' => $this->portal]);
    }
}
