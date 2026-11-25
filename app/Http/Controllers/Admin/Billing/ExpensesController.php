<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\Billing\BillingAuditService;
use App\Services\Billing\BillingDashboardService;
use App\Services\Billing\InvoiceNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpensesController extends Controller
{
    public function __construct(
        protected InvoiceNumberService $numbers,
        protected BillingAuditService $audit,
        protected BillingDashboardService $dashboard,
    ) {}

    public function index(Request $request): View
    {
        $query = Expense::query()->with('recordedByAdmin');

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }
        if ($request->filled('from')) {
            $query->whereDate('expense_date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('expense_date', '<=', $request->query('to'));
        }

        $expenses = $query->latest('expense_date')->latest('id')->paginate(20)->withQueryString();

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $monthlyRevenue = (float) Payment::query()
            ->where('payment_status', 'paid')
            ->whereBetween('payment_date', [$monthStart, $monthEnd])
            ->sum('amount');

        $monthlyExpenses = (float) Expense::query()
            ->whereBetween('expense_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        $totalExpenses = (float) Expense::query()->sum('amount');
        $totalRevenue = (float) Payment::query()->where('payment_status', 'paid')->sum('amount');

        return view('admin.billing.expenses.index', [
            'expenses' => $expenses,
            'categories' => Expense::CATEGORIES,
            'monthlySeries' => $this->dashboard->monthlyRevenueSeries(6),
            'summary' => [
                'monthly_revenue' => round($monthlyRevenue, 2),
                'monthly_expenses' => round($monthlyExpenses, 2),
                'monthly_profit' => round($monthlyRevenue - $monthlyExpenses, 2),
                'total_revenue' => round($totalRevenue, 2),
                'total_expenses' => round($totalExpenses, 2),
                'net_profit' => round($totalRevenue - $totalExpenses, 2),
            ],
            'filters' => $request->only(['category', 'from', 'to']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => ['required', Rule::in(array_keys(Expense::CATEGORIES))],
            'vendor' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'payment_method' => 'required|string|max:30',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $dir = public_path('uploads/receipts');
            File::ensureDirectoryExists($dir);
            $file = $request->file('receipt');
            $filename = 'receipt_'.now()->format('Ymd_His').'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $file->move($dir, $filename);
            $receiptPath = 'uploads/receipts/'.$filename;
        }

        $admin = auth()->guard('admin')->user();

        $expense = Expense::create([
            'expense_number' => $this->numbers->nextExpenseNumber(),
            'title' => $validated['title'],
            'category' => $validated['category'],
            'vendor' => $validated['vendor'] ?? null,
            'amount' => round((float) $validated['amount'], 2),
            'currency' => Setting::currency(),
            'expense_date' => $validated['expense_date'],
            'payment_method' => $validated['payment_method'],
            'reference' => $validated['reference'] ?? null,
            'receipt_path' => $receiptPath,
            'notes' => $validated['notes'] ?? null,
            'recorded_by_admin_id' => $admin?->id,
        ]);

        $this->audit->log($expense, 'expense.recorded', [], [
            'expense_number' => $expense->expense_number,
            'amount' => $expense->amount,
            'category' => $expense->category,
        ]);

        return back()->with('success', 'Expense '.$expense->expense_number.' recorded.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->audit->log($expense, 'expense.deleted', [
            'expense_number' => $expense->expense_number,
            'amount' => $expense->amount,
        ]);

        if ($expense->receipt_path && file_exists(public_path($expense->receipt_path))) {
            @unlink(public_path($expense->receipt_path));
        }

        $expense->delete();

        return back()->with('success', 'Expense deleted.');
    }
}
