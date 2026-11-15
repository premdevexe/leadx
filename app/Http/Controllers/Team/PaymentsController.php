<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentsController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\Team $team */
        $team = $request->user();

        $orderIds = SalesOrder::where('team_id', $team->id)->pluck('id');

        $payments = Payment::whereIn('order_id', $orderIds)
            ->orderByDesc('id')
            ->paginate(20);

        return view('team.payments.index', [
            'payments' => $payments,
            'team' => $team,
        ]);
    }
}
