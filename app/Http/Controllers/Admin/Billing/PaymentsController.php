<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentsController extends Controller
{
    public function index(Request $request): View
    {
        $query = Payment::query()->with(['customer', 'invoice', 'order', 'verifiedByAdmin', 'recordedByUser']);

        if ($request->filled('search')) {
            $term = str_replace(['%', '_'], ['\\%', '\\_'], (string) $request->query('search'));
            $query->where(function ($q) use ($term) {
                $q->where('transaction_reference', 'like', '%'.$term.'%')
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$term.'%'))
                    ->orWhereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', '%'.$term.'%'));
            });
        }

        if ($request->filled('method')) {
            $query->where('payment_method', $request->query('method'));
        }

        if ($request->filled('status')) {
            $query->where('payment_status', $request->query('status'));
        }

        if ($request->filled('from')) {
            $query->whereDate('payment_date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('payment_date', '<=', $request->query('to'));
        }

        $payments = $query->latest('payment_date')->latest('id')->paginate(25)->withQueryString();

        return view('admin.billing.payments.index', [
            'payments' => $payments,
            'methods' => [
                'upi' => 'UPI',
                'phonepe' => 'PhonePe',
                'gpay' => 'Google Pay',
                'bank_transfer' => 'Bank Transfer',
                'cash' => 'Cash',
                'card' => 'Card',
                'gateway' => 'Gateway',
                'other' => 'Other',
            ],
            'statuses' => Payment::STATUSES,
            'filters' => $request->only(['search', 'method', 'status', 'from', 'to']),
        ]);
    }
}
