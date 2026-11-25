<?php

namespace App\Services\Billing;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Carbon;

class BillingDashboardService
{
    /**
     * Resolve date range bounds for Today, This Week, This Month, This Year, Custom, or All.
     *
     * @return array{preset: string, from: ?Carbon, to: ?Carbon}
     */
    public function resolveDateRange(?string $preset, ?string $customFrom = null, ?string $customTo = null): array
    {
        $preset = $preset ?: 'month';
        $now = now();

        return match ($preset) {
            'today' => ['preset' => 'today', 'from' => $now->copy()->startOfDay(), 'to' => $now->copy()->endOfDay()],
            'week' => ['preset' => 'week', 'from' => $now->copy()->startOfWeek(), 'to' => $now->copy()->endOfWeek()],
            'month' => ['preset' => 'month', 'from' => $now->copy()->startOfMonth(), 'to' => $now->copy()->endOfMonth()],
            'year' => ['preset' => 'year', 'from' => $now->copy()->startOfYear(), 'to' => $now->copy()->endOfYear()],
            'custom' => [
                'preset' => 'custom',
                'from' => $customFrom ? Carbon::parse($customFrom)->startOfDay() : $now->copy()->startOfMonth(),
                'to' => $customTo ? Carbon::parse($customTo)->endOfDay() : $now->copy()->endOfDay(),
            ],
            default => ['preset' => 'all', 'from' => null, 'to' => null],
        };
    }

    /**
     * Compute enterprise billing KPIs, monthly revenue chart, and recent activity.
     *
     * @return array<string, mixed>
     */
    public function metrics(?string $preset = 'month', ?string $customFrom = null, ?string $customTo = null): array
    {
        $range = $this->resolveDateRange($preset, $customFrom, $customTo);

        $invoiceQuery = Invoice::query()->where('status', '!=', Invoice::STATUS_CANCELLED);
        if ($range['from'] && $range['to']) {
            $invoiceQuery->whereBetween('issue_date', [$range['from']->toDateString(), $range['to']->toDateString()]);
        }

        $paymentQuery = Payment::query();
        if ($range['from'] && $range['to']) {
            $paymentQuery->whereBetween('payment_date', [$range['from'], $range['to']]);
        }

        $expenseQuery = Expense::query();
        if ($range['from'] && $range['to']) {
            $expenseQuery->whereBetween('expense_date', [$range['from']->toDateString(), $range['to']->toDateString()]);
        }

        $totalRevenue = (float) (clone $invoiceQuery)->where('status', '!=', Invoice::STATUS_DRAFT)->sum('grand_total');
        $totalPaymentsReceived = (float) (clone $paymentQuery)->where('payment_status', 'paid')->sum('amount');
        $pendingPaymentsCount = (clone $paymentQuery)->where('payment_status', 'pending')->count();
        $pendingPaymentsAmount = (float) (clone $paymentQuery)->where('payment_status', 'pending')->sum('amount');

        $outstandingAmount = (float) (clone $invoiceQuery)
            ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_OVERDUE])
            ->sum('balance_due');

        $totalInvoices = (clone $invoiceQuery)->count();
        $paidInvoices = (clone $invoiceQuery)->where('status', Invoice::STATUS_PAID)->count();
        $unpaidInvoices = (clone $invoiceQuery)
            ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIALLY_PAID])
            ->count();
        $overdueInvoices = (clone $invoiceQuery)->overdue()->count();

        $totalExpenses = (float) (clone $expenseQuery)->sum('amount');
        $netProfit = round($totalPaymentsReceived - $totalExpenses, 2);

        $monthlyChart = $this->monthlyRevenueSeries();

        $recentTransactions = Payment::query()
            ->with(['customer', 'invoice', 'order'])
            ->latest('payment_date')
            ->latest('id')
            ->limit(8)
            ->get();

        $recentInvoices = Invoice::query()
            ->with(['customer'])
            ->latest('issue_date')
            ->latest('id')
            ->limit(8)
            ->get();

        return [
            'range' => $range,
            'total_revenue' => round($totalRevenue, 2),
            'total_payments_received' => round($totalPaymentsReceived, 2),
            'pending_payments_count' => $pendingPaymentsCount,
            'pending_payments_amount' => round($pendingPaymentsAmount, 2),
            'outstanding_amount' => round($outstandingAmount, 2),
            'total_invoices' => $totalInvoices,
            'paid_invoices' => $paidInvoices,
            'unpaid_invoices' => $unpaidInvoices,
            'overdue_invoices' => $overdueInvoices,
            'total_expenses' => round($totalExpenses, 2),
            'net_profit' => $netProfit,
            'monthly_chart' => $monthlyChart,
            'recent_transactions' => $recentTransactions,
            'recent_invoices' => $recentInvoices,
        ];
    }

    /**
     * Last 6 months Revenue vs Expenses series.
     *
     * @return list<array{label: string, revenue: float, expenses: float}>
     */
    public function monthlyRevenueSeries(int $months = 6): array
    {
        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $rev = (float) Payment::query()
                ->where('payment_status', 'paid')
                ->whereBetween('payment_date', [$start, $end])
                ->sum('amount');

            $exp = (float) Expense::query()
                ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
                ->sum('amount');

            $series[] = [
                'label' => $start->format('M Y'),
                'revenue' => round($rev, 2),
                'expenses' => round($exp, 2),
            ];
        }

        return $series;
    }
}
