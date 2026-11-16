<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\SalesOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Admin CRM customers — API mirror of the web /admin/customers panel.
 * Read-only: paying customers with optional search/team filters, and a
 * detail view that resolves the customer's order/payment/subscription
 * history by phone/email match (same rule as the web detail page).
 */
class CustomersController extends Controller
{
    /**
     * GET /api/v1/admin/customers
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Customer::query()->where('status', 'paying')->with(['team']);

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

        return CustomerResource::collection(
            $query->orderByDesc('last_purchase_at')
                ->paginate(min(max((int) $request->input('per_page', 20), 1), 100))
        );
    }

    /**
     * GET /api/v1/admin/customers/{id}
     */
    public function show(int $id): JsonResponse
    {
        $customer = Customer::with(['team', 'subscriptions.product', 'payments.order'])->findOrFail($id);

        // Orders linked to the customer via phone or email within the
        // customer's team (identical rule to the web detail page).
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

        return response()->json([
            'data' => new CustomerResource($customer),
            'orders' => $orders,
        ]);
    }
}
