<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\PublicOrderShare;
use App\Models\SalesOrder;
use App\Services\PublicOrderShareService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrdersController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\Team $team */
        $team = $request->user();

        $orders = SalesOrder::where('team_id', $team->id)
            ->orderByDesc('id')
            ->paginate(20);

        return view('team.orders.index', [
            'orders' => $orders,
            'team' => $team,
        ]);
    }

    public function show(Request $request, SalesOrder $order): View
    {
        /** @var \App\Models\Team $team */
        $team = $request->user();

        abort_if((int) $order->team_id !== (int) $team->id, 403);

        $order->loadMissing(['product', 'items.product', 'items.subscription', 'subscription', 'subscriptions.orderItem', 'payments', 'assignedEmployee', 'apiCredential']);

        // The order's live public link, if any (exactly one per order).
        $publicShare = PublicOrderShare::where('order_id', $order->id)->firstWhere('revoked_at', null);

        return view('team.orders.show', [
            'order' => $order,
            'team' => $team,
            'publicShare' => $publicShare,
        ]);
    }

    /**
     * POST /team/orders/{order}/public-share - mint (or refresh) the
     * unauthenticated customer-facing link for one of this team's orders.
     * The team token is a broad credential, so the API-key opt-in is not
     * available from the team portal — only the status-only link.
     */
    public function publicOrderShare(Request $request, SalesOrder $order): \Illuminate\Http\RedirectResponse
    {
        /** @var \App\Models\Team $team */
        $team = $request->user();

        abort_if((int) $order->team_id !== (int) $team->id, 403, 'You can only share links for your own team orders.');

        $data = $request->validate([
            'expires_at' => ['nullable', 'date'],
        ]);

        app(PublicOrderShareService::class)->createForOrder(
            $order,
            $team,
            false, // team tokens never fold the customer key into a link
            ! empty($data['expires_at']) ? \Illuminate\Support\Carbon::parse($data['expires_at']) : null,
        );

        return back()->with('status', 'Public link generated for '.$order->order_number.'.');
    }

    /**
     * POST /team/orders/{order}/public-share/revoke
     */
    public function publicOrderShareRevoke(Request $request, SalesOrder $order): \Illuminate\Http\RedirectResponse
    {
        /** @var \App\Models\Team $team */
        $team = $request->user();

        abort_if((int) $order->team_id !== (int) $team->id, 403, 'You can only manage links for your own team orders.');

        $share = PublicOrderShare::where('order_id', $order->id)->firstWhere('revoked_at', null);

        if ($share === null) {
            return back()->with('error', 'This order has no live public link to revoke.');
        }

        $share->revoke($team);

        return back()->with('status', 'Public link for '.$order->order_number.' has been revoked.');
    }
}
