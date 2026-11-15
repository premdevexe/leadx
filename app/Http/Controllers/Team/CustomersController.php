<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomersController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\Team $team */
        $team = $request->user();

        $orderCustomerPhones = \DB::table('sales_orders')
            ->where('team_id', $team->id)
            ->distinct()
            ->pluck('customer_phone');

        $customers = Customer::whereIn('phone', $orderCustomerPhones)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('team.customers.index', [
            'customers' => $customers,
            'team' => $team,
        ]);
    }
}
