<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Services\Billing\InvoicePdfService;
use App\Services\Billing\InvoiceService;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected InvoicePdfService $pdfService,
        protected PermissionService $permissions,
    ) {}

    public function index(Request $request): View
    {
        $employee = $request->user();
        $this->invoiceService->syncOverdueInvoices();

        $query = $this->scopedInvoicesQuery($employee)->with(['customer', 'order']);

        if ($request->filled('search')) {
            $term = str_replace(['%', '_'], ['\\%', '\\_'], (string) $request->query('search'));
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', '%'.$term.'%')
                    ->orWhere('customer_name', 'like', '%'.$term.'%');
            });
        }

        if ($request->filled('status') && array_key_exists($request->query('status'), Invoice::STATUSES)) {
            $query->where('status', $request->query('status'));
        }

        $invoices = $query->latest('issue_date')->latest('id')->paginate(15)->withQueryString();

        return view('employee.billing.index', [
            'invoices' => $invoices,
            'statuses' => Invoice::STATUSES,
            'canCreate' => $this->permissions->hasPermission($employee, 'billing.invoices.create'),
        ]);
    }

    public function create(Request $request): View
    {
        $employee = $request->user();
        $customers = Customer::query()
            ->when($employee->team_id, fn ($q) => $q->where('team_id', $employee->team_id))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone']);

        $selectedOrder = null;
        $selectedCustomerId = $request->integer('customer_id') ?: null;
        if ($request->filled('order_id')) {
            $selectedOrder = SalesOrder::with(['product', 'items'])
                ->where('assigned_employee_id', $employee->id)
                ->find($request->integer('order_id'));

            if ($selectedOrder && ! $selectedCustomerId) {
                $selectedCustomerId = Customer::query()
                    ->when($employee->team_id, fn ($q) => $q->where('team_id', $employee->team_id))
                    ->where(function ($q) use ($selectedOrder) {
                        if ($selectedOrder->customer_email) {
                            $q->orWhere('email', $selectedOrder->customer_email);
                        }
                        if ($selectedOrder->customer_phone) {
                            $q->orWhere('phone', $selectedOrder->customer_phone);
                        }
                    })
                    ->value('id');
            }
        }

        return view('employee.billing.create', [
            'customers' => $customers,
            'products' => Product::where('status', 'active')->orderBy('name')->get(),
            'orders' => SalesOrder::where('assigned_employee_id', $employee->id)->with('items')->latest('id')->limit(50)->get(),
            'selectedOrder' => $selectedOrder,
            'selectedCustomerId' => $selectedCustomerId,
            'billing' => Setting::billing(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $request->user();

        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'order_id' => 'nullable|integer|exists:sales_orders,id',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'discount_type' => 'nullable|in:fixed,percent',
            'discount_value' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.description' => 'required|string|max:255',
            'items.*.duration' => 'nullable|string|max:50',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $customer = Customer::findOrFail((int) $validated['customer_id']);
        if ($employee->team_id && $customer->team_id && (int) $customer->team_id !== (int) $employee->team_id) {
            abort(403, 'You can only create invoices for customers in your team.');
        }

        if (! empty($validated['order_id'])) {
            $order = SalesOrder::findOrFail((int) $validated['order_id']);
            $ownsOrder = (int) $order->assigned_employee_id === (int) $employee->id
                || ($employee->team_id && (int) $order->team_id === (int) $employee->team_id && $employee->hasActiveTeamAccess());
            abort_unless($ownsOrder, 403, 'You do not have permission to link this order.');
        }

        $validated['status'] = Invoice::STATUS_SENT;
        $invoice = $this->invoiceService->createInvoice($validated, null, $employee);

        return redirect()->route('employee.billing.show', $invoice)
            ->with('success', 'Invoice '.$invoice->invoice_number.' created.');
    }

    public function show(Request $request, Invoice $invoice): View
    {
        $this->authorizeEmployeeInvoice($request->user(), $invoice);
        $invoice->load(['customer', 'order', 'items.product', 'payments']);

        return view('employee.billing.show', [
            'invoice' => $invoice,
            'canRecordPayment' => $this->permissions->hasPermission($request->user(), 'billing.payments.create'),
        ]);
    }

    public function pdf(Request $request, Invoice $invoice): Response
    {
        $this->authorizeEmployeeInvoice($request->user(), $invoice);

        return $this->pdfService->download($invoice);
    }

    public function recordPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        $employee = $request->user();
        $this->authorizeEmployeeInvoice($employee, $invoice);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => ['required', Rule::in(Payment::METHODS)],
            'transaction_reference' => 'nullable|string|max:100',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        // Employee-recorded payments are recorded as pending until Admin confirms,
        // ensuring an invoice is never marked Paid without authorized confirmation.
        $validated['confirmed'] = false;
        $this->invoiceService->recordPayment($invoice, $validated, null, $employee);

        return back()->with('success', 'Payment submitted for admin confirmation.');
    }

    protected function scopedInvoicesQuery($employee)
    {
        return Invoice::query()->where(function ($q) use ($employee) {
            $q->where('created_by_user_id', $employee->id);
            if ($employee->team_id && $employee->hasActiveTeamAccess()) {
                $q->orWhere('team_id', $employee->team_id);
            }
            $q->orWhereHas('order', fn ($o) => $o->where('assigned_employee_id', $employee->id));
        });
    }

    protected function authorizeEmployeeInvoice($employee, Invoice $invoice): void
    {
        $allowed = $invoice->created_by_user_id === $employee->id
            || ($invoice->order && $invoice->order->assigned_employee_id === $employee->id)
            || ($employee->team_id && $invoice->team_id === $employee->team_id && $employee->hasActiveTeamAccess());

        abort_unless($allowed, 403, 'You do not have permission to view this invoice.');
    }
}
