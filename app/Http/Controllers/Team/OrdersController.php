<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
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

        $order->loadMissing(['product', 'subscription', 'payments', 'assignedEmployee', 'apiCredential']);

        return view('team.orders.show', [
            'order' => $order,
            'team' => $team,
        ]);
    }
}
