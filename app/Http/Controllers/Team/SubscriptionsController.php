<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubscriptionsController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\Team $team */
        $team = $request->user();

        $filters = $request->validate(['status' => ['nullable', Rule::in(Subscription::STATUSES)]]);
        $subscriptions = Subscription::where('team_id', $team->id)
            ->with(['order', 'product', 'plan', 'customer'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return view('team.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'team' => $team,
        ]);
    }

    public function renew(Request $request, Subscription $subscription, SubscriptionService $service): RedirectResponse
    {
        abort_unless((int) $subscription->team_id === (int) $request->user()->id, 403);
        $service->renew($subscription);
        $this->recordAction($request, $subscription, 'subscription.renewed');

        return redirect()->route('team.subscriptions')->with('status', 'Subscription renewed.');
    }

    public function cancel(Request $request, Subscription $subscription, SubscriptionService $service): RedirectResponse
    {
        abort_unless((int) $subscription->team_id === (int) $request->user()->id, 403);
        $service->cancel($subscription);
        $this->recordAction($request, $subscription, 'subscription.cancelled');

        return redirect()->route('team.subscriptions')->with('status', 'Subscription cancelled.');
    }

    private function recordAction(Request $request, Subscription $subscription, string $event): void
    {
        DB::table('audit_logs')->insert([
            'auditable_type' => $subscription->getMorphClass(),
            'auditable_id' => $subscription->id,
            'event' => $event,
            'new_values' => json_encode(['actor_type' => $request->user()::class, 'actor_id' => $request->user()->id, 'at' => now()->toIso8601String()]),
            'ip' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
