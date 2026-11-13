<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Services\SalesAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function __construct(protected SalesAnalyticsService $analytics)
    {
    }

    /**
     * Show reports and sales analytics with interactive filters.
     */
    public function index(Request $request): View
    {
        $tab = $request->input('tab', 'overview');
        $filters = $request->only([
            'range', 'start_date', 'end_date', 'from_date', 'to_date',
            'product_id', 'employee_id', 'team_id', 'payment_status', 'order_status',
        ]);

        $overview = $this->analytics->overview($filters);
        $revenue = $this->analytics->revenueBreakdown($filters);
        $products = $this->analytics->productReport($filters);
        $employees = $this->analytics->employeeReport($filters);
        $teams = $this->analytics->teamReport($filters);
        $orders = $this->analytics->ordersReport($filters, 15);

        return view('admin.reports.index', [
            'tab' => $tab,
            'filters' => $filters,
            'overview' => $overview,
            'revenue' => $revenue,
            'products' => $products,
            'employees' => $employees,
            'teams' => $teams,
            'orders' => $orders,
            'catalogProducts' => Product::orderBy('name')->get(),
            'allTeams' => Team::orderBy('team_code')->get(),
            'allEmployees' => User::where('role', 'employee')->orderBy('username')->get(),
        ]);
    }
}
