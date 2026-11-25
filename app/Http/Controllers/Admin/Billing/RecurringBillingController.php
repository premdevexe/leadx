<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\Billing\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecurringBillingController extends Controller
{
    public function __construct(protected InvoiceService $invoiceService) {}

    public function index(Request $request): View
    {
        $query = Subscription::query()
            ->with(['customer', 'product', 'order'])
            ->whereIn('status', ['active', 'expired', 'pending']);

        if ($request->filled('cycle')) {
            $query->where('billing_cycle', $request->query('cycle'));
        }

        if ($request->filled('filter')) {
            if ($request->query('filter') === 'expiring') {
                $query->where('status', 'active')
                    ->whereNotNull('expires_at')
                    ->whereBetween('expires_at', [now(), now()->addDays(14)]);
            } elseif ($request->query('filter') === 'due') {
                $query->whereNotNull('renewal_date')
                    ->where('renewal_date', '<=', now()->addDays(7));
            }
        }

        $subscriptions = $query->orderBy('renewal_date')->orderBy('expires_at')->paginate(20)->withQueryString();

        $recurringInvoices = Invoice::query()
            ->whereNotNull('subscription_id')
            ->with(['customer', 'subscription'])
            ->latest('id')
            ->limit(15)
            ->get();

        return view('admin.billing.recurring.index', [
            'subscriptions' => $subscriptions,
            'recurringInvoices' => $recurringInvoices,
            'cycles' => Invoice::BILLING_CYCLES,
            'filters' => $request->only(['cycle', 'filter']),
        ]);
    }

    public function generate(Subscription $subscription): RedirectResponse
    {
        $admin = auth()->guard('admin')->user();
        $invoice = $this->invoiceService->generateRecurringInvoice($subscription, $admin);

        if (! $invoice) {
            return back()->with('warning', 'A renewal invoice already exists for this billing cycle (duplicate prevented).');
        }

        return redirect()->route('admin.billing.invoices.show', $invoice)
            ->with('success', 'Renewal invoice '.$invoice->invoice_number.' generated.');
    }
}
