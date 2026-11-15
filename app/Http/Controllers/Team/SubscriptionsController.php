<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionsController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\Team $team */
        $team = $request->user();

        $subscriptions = Subscription::where('team_id', $team->id)
            ->with(['order', 'product', 'plan'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('team.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'team' => $team,
        ]);
    }
}
