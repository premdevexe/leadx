<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\BillingDashboardService;
use App\Services\Billing\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingDashboardController extends Controller
{
    public function __construct(
        protected BillingDashboardService $dashboard,
        protected InvoiceService $invoices,
    ) {}

    public function index(Request $request): View
    {
        $this->invoices->syncOverdueInvoices();

        $preset = $request->query('range', 'month');
        $from = $request->query('from');
        $to = $request->query('to');

        $metrics = $this->dashboard->metrics($preset, $from, $to);

        return view('admin.billing.dashboard', [
            'metrics' => $metrics,
            'preset' => $metrics['range']['preset'],
            'customFrom' => $from,
            'customTo' => $to,
        ]);
    }
}
