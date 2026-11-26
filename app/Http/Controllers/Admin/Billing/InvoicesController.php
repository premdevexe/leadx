<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Services\Billing\InvoicePdfService;
use App\Services\Billing\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvoicesController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected InvoicePdfService $pdfService,
    ) {}

    public function index(Request $request): View
    {
        $this->invoiceService->syncOverdueInvoices();

        $query = Invoice::query()->with(['customer', 'order']);

        if ($request->filled('search')) {
            $term = str_replace(['%', '_'], ['\\%', '\\_'], (string) $request->query('search'));
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', '%'.$term.'%')
                    ->orWhere('customer_name', 'like', '%'.$term.'%')
                    ->orWhere('customer_email', 'like', '%'.$term.'%')
                    ->orWhere('customer_phone', 'like', '%'.$term.'%');
            });
        }

        if ($request->filled('status') && array_key_exists($request->query('status'), Invoice::STATUSES)) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('issue_date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('issue_date', '<=', $request->query('to'));
        }

        $invoices = $query->latest('issue_date')->latest('id')->paginate(20)->withQueryString();

        return view('admin.billing.invoices.index', [
            'invoices' => $invoices,
            'statuses' => Invoice::STATUSES,
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'email', 'phone']),
            'filters' => $request->only(['search', 'status', 'customer_id', 'from', 'to']),
        ]);
    }

    public function create(Request $request): View
    {
        $billing = Setting::billing();
        $selectedCustomerId = $request->integer('customer_id') ?: null;
        $selectedOrderId = $request->integer('order_id') ?: null;
        $selectedOrder = $selectedOrderId ? SalesOrder::with(['product', 'items'])->find($selectedOrderId) : null;

        return view('admin.billing.invoices.form', [
            'invoice' => null,
            'billing' => $billing,
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'email', 'phone']),
            'products' => Product::where('status', 'active')->orderBy('name')->get(),
            'orders' => SalesOrder::latest('id')->limit(100)->get(['id', 'order_number', 'customer_name', 'customer_phone', 'product_name', 'closing_value', 'duration']),
            'selectedCustomerId' => $selectedCustomerId,
            'selectedOrder' => $selectedOrder,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateInvoiceRequest($request);
        $admin = auth()->guard('admin')->user();

        $invoice = $this->invoiceService->createInvoice($validated, $admin);

        return redirect()->route('admin.billing.invoices.show', $invoice)
            ->with('success', 'Invoice '.$invoice->invoice_number.' created successfully.');
    }

    public function show(Invoice $invoice): View
    {
        $this->invoiceService->syncOverdueInvoices();
        $invoice->refresh();
        $invoice->load(['customer', 'order', 'subscription', 'items.product', 'payments.verifiedByAdmin', 'refunds.admin', 'adjustments.createdByAdmin']);

        $auditLogs = AuditLog::query()
            ->where('auditable_type', $invoice->getMorphClass())
            ->where('auditable_id', $invoice->id)
            ->latest('id')
            ->limit(25)
            ->get();

        return view('admin.billing.invoices.show', [
            'invoice' => $invoice,
            'billing' => Setting::billing(),
            'paymentMethods' => $this->paymentMethods(),
            'auditLogs' => $auditLogs,
        ]);
    }

    public function edit(Invoice $invoice): View|RedirectResponse
    {
        if ($invoice->isFinanciallyLocked() || (float) $invoice->amount_paid > 0) {
            return redirect()->route('admin.billing.invoices.show', $invoice)
                ->with('error', 'Finalized or paid invoices cannot be edited directly. Issue a Credit Note or Adjustment instead.');
        }

        $invoice->load('items');

        return view('admin.billing.invoices.form', [
            'invoice' => $invoice,
            'billing' => Setting::billing(),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'email', 'phone']),
            'products' => Product::where('status', 'active')->orderBy('name')->get(),
            'orders' => SalesOrder::latest('id')->limit(100)->get(['id', 'order_number', 'customer_name', 'customer_phone', 'product_name', 'closing_value', 'duration']),
            'selectedCustomerId' => $invoice->customer_id,
            'selectedOrder' => $invoice->order,
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $this->validateInvoiceRequest($request);
        $admin = auth()->guard('admin')->user();

        $this->invoiceService->updateInvoice($invoice, $validated, $admin);

        return redirect()->route('admin.billing.invoices.show', $invoice)
            ->with('success', 'Invoice '.$invoice->invoice_number.' updated.');
    }

    public function send(Invoice $invoice): RedirectResponse
    {
        $this->invoiceService->markAsSent($invoice);

        return back()->with('success', 'Invoice '.$invoice->invoice_number.' marked as Sent.');
    }

    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->invoiceService->cancelInvoice($invoice, $data['reason']);

        return back()->with('success', 'Invoice '.$invoice->invoice_number.' cancelled.');
    }

    public function preview(Request $request, Invoice $invoice): View
    {
        $templateOverride = $request->query('template');

        return view('billing.pdf.invoice', array_merge(
            $this->pdfService->viewData($invoice, is_string($templateOverride) ? $templateOverride : null),
            ['isPreview' => true]
        ));
    }

    public function pdf(Request $request, Invoice $invoice): Response
    {
        $templateOverride = $request->query('template');

        return $this->pdfService->download($invoice, is_string($templateOverride) ? $templateOverride : null);
    }

    public function email(Request $request, Invoice $invoice): RedirectResponse
    {
        $request->validate([
            'recipient_email' => 'nullable|email|max:255',
        ]);

        $sent = $this->pdfService->sendByEmail($invoice, $request->input('recipient_email'));

        if (! $sent) {
            return back()->with('error', 'No valid customer email address found for this invoice.');
        }

        return back()->with('success', 'Invoice PDF sent via email successfully.');
    }

    public function recordPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => ['required', Rule::in(Payment::METHODS)],
            'transaction_reference' => 'nullable|string|max:100',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $admin = auth()->guard('admin')->user();
        $this->invoiceService->recordPayment($invoice, $validated, $admin);

        return back()->with('success', 'Payment recorded and invoice status updated.');
    }

    public function confirmPayment(Payment $payment): RedirectResponse
    {
        $admin = auth()->guard('admin')->user();
        $this->invoiceService->confirmPayment($payment, $admin);

        return back()->with('success', 'Payment confirmed.');
    }

    public function recordRefund(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500',
            'gateway_reference' => 'nullable|string|max:255',
            'payment_id' => 'nullable|integer|exists:payments,id',
        ]);

        $admin = auth()->guard('admin')->user();
        $this->invoiceService->recordRefund($invoice, $validated, $admin);

        return back()->with('success', 'Refund recorded successfully.');
    }

    public function storeAdjustment(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['credit_note', 'debit_note', 'adjustment'])],
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500',
        ]);

        $admin = auth()->guard('admin')->user();
        $this->invoiceService->createAdjustment($invoice, $validated, $admin);

        return back()->with('success', 'Credit Note / Adjustment recorded.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateInvoiceRequest(Request $request): array
    {
        return $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'order_id' => 'nullable|integer|exists:sales_orders,id',
            'customer_address' => 'nullable|string|max:1000',
            'status' => 'nullable|in:draft,sent',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'currency' => 'nullable|string|size:3',
            'template' => ['nullable', Rule::in(array_keys(Invoice::TEMPLATES))],
            'discount_type' => 'nullable|in:fixed,percent',
            'discount_value' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'payment_terms' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'internal_notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.description' => 'required|string|max:255',
            'items.*.duration' => 'nullable|string|max:50',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function paymentMethods(): array
    {
        return [
            'upi' => 'UPI',
            'phonepe' => 'PhonePe',
            'gpay' => 'Google Pay',
            'bank_transfer' => 'Bank Transfer',
            'cash' => 'Cash',
            'card' => 'Card',
            'other' => 'Other',
        ];
    }
}
