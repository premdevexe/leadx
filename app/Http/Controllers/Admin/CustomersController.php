<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRM Customers Management (Admin).
 *
 * Displays paying CRM customers who completed verified purchases.
 * Enquiries and unverified purchasers are excluded.
 */
class CustomersController extends Controller
{
    /**
     * Display a listing of verified paying customers.
     */
    public function index(Request $request): View
    {
        $query = Customer::query()
            ->where('status', 'paying')
            ->with(['team']);

        if ($request->filled('search')) {
            $term = str_replace(['%', '_'], ['\\%', '\\_'], (string) $request->query('search'));
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', '%'.$term.'%')
                    ->orWhere('phone', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%');
            });
        }

        if ($request->filled('team_id')) {
            $query->where('team_id', $request->integer('team_id'));
        }

        $customers = $query->orderByDesc('last_purchase_at')->paginate(20)->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'filters' => [
                'search' => $request->input('search'),
                'team_id' => $request->integer('team_id') ?: null,
            ],
            'teams' => \App\Models\Team::orderBy('team_code')->get(['id', 'team_code', 'team_name']),
        ]);
    }

    /**
     * Display customer detail with order, payment, and subscription history.
     */
    public function show(Customer $customer): View
    {
        $customer->loadMissing([
            'team',
            'subscriptions.product',
            'payments.order',
        ]);

        // Resolve all orders linked to this customer via phone or email
        $orders = SalesOrder::query()
            ->where('team_id', $customer->team_id)
            ->where(function ($q) use ($customer) {
                if ($customer->phone) {
                    $q->where('customer_phone', $customer->phone);
                }
                if ($customer->email) {
                    $q->orWhere('customer_email', $customer->email);
                }
            })
            ->with(['subscription', 'payments'])
            ->latest('id')
            ->get();

        return view('admin.customers.show', [
            'customer' => $customer,
            'orders' => $orders,
        ]);
    }
}
