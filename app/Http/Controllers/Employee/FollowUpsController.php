<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\FollowUp\StoreFollowUpRequest;
use App\Http\Requests\FollowUp\UpdateFollowUpRequest;
use App\Models\Customer;
use App\Models\EmployeeTeamAccessGrant;
use App\Models\FollowUp;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use App\Services\FollowUpService;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FollowUpsController extends Controller
{
    protected string $portal = 'employee';

    public function index(Request $request): View|RedirectResponse
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        $viewMode = $request->query('view', 'my_data');
        $hasTeamAccess = $employee->hasActiveTeamAccess();
        $activeGrant = null;

        $query = FollowUp::query()->with(['customer', 'order', 'team', 'assignedEmployee']);

        if ($viewMode === 'team_data') {
            if (! $hasTeamAccess || ! $employee->team_id) {
                return redirect()->route('employee.follow-ups', ['view' => 'my_data'])
                    ->with('warning', 'You do not have an active Team Data access grant from Super Admin.');
            }

            $today = now()->startOfDay()->toDateString();
            $activeGrant = EmployeeTeamAccessGrant::where('user_id', $employee->id)
                ->whereNull('revoked_at')
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->first();

            $orderIds = \App\Models\SalesOrder::where('team_id', $employee->team_id)
                ->whereDate('created_at', '>=', $activeGrant->start_date)
                ->whereDate('created_at', '<=', $activeGrant->end_date)
                ->pluck('id');

            $query->whereIn('order_id', $orderIds);
        } else {
            $viewMode = 'my_data';
            $query->where('assigned_employee_id', $employee->id);
        }

        $filters = $request->validate(['status' => ['nullable', Rule::in(FollowUp::STATUSES)]]);
        $query->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status));
        $followUps = $query->orderByDesc('scheduled_at')->paginate(20)->withQueryString();

        return view('employee.followups.index', [
            'followUps' => $followUps,
            'employee' => $employee,
            'viewMode' => $viewMode,
            'hasTeamAccess' => $hasTeamAccess,
            'activeGrant' => $activeGrant,
            'portal' => $this->portal,
        ]);
    }

    public function create(Request $request): View
    {
        return view('employee.followups.create', [
            'portal' => $this->portal,
            'customers' => $this->customersForActor($request)->orderBy('name')->get(),
            'employees' => $request->user() instanceof Team
                ? User::where('team_id', $request->user()->id)->where('role', 'employee')->where('status', 'active')->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function store(Request $request, FollowUpService $service): RedirectResponse
    {
        $rules = Arr::only((new StoreFollowUpRequest)->rules(), [
            'customer_id', 'title', 'description', 'follow_up_type', 'priority', 'notes', 'sync_to_calendar',
        ]);
        $rules['scheduled_at'] = ['required', 'date'];
        if ($request->user() instanceof Team) {
            $rules['assigned_employee_id'] = ['nullable', 'integer', 'exists:users,id'];
        }
        $data = $request->validate($rules);

        if (! $this->customersForActor($request)->whereKey($data['customer_id'])->exists()) {
            throw ValidationException::withMessages(['customer_id' => 'Select a verified customer associated with your orders.']);
        }

        $followUp = $service->store($request->user(), $data);

        return redirect()->route($this->portal.'.follow-ups.show', $followUp)
            ->with('status', 'Follow-up scheduled.');
    }

    public function show(Request $request, FollowUp $followUp, FollowUpService $service): View
    {
        $service->assertCanView($followUp, $request->user());
        $followUp->load(['customer', 'assignedEmployee', 'order', 'history' => fn ($query) => $query->latest('created_at')->latest('id')]);

        return view('employee.followups.show', [
            'portal' => $this->portal,
            'followUp' => $followUp,
            'canManage' => $request->user() instanceof Team || app(PermissionService::class)->hasPermission($request->user(), 'follow_ups.edit'),
        ]);
    }

    public function complete(Request $request, FollowUp $followUp, FollowUpService $service): RedirectResponse
    {
        $data = $request->validate(Arr::only((new UpdateFollowUpRequest)->rules(), ['outcome', 'notes']));
        $service->complete($followUp, $request->user(), $data['outcome'] ?? null, $data['notes'] ?? null);

        return redirect()->route($this->portal.'.follow-ups.show', $followUp)->with('status', 'Follow-up completed.');
    }

    public function reschedule(Request $request, FollowUp $followUp, FollowUpService $service): RedirectResponse
    {
        $rules = Arr::only((new UpdateFollowUpRequest)->rules(), ['next_follow_up_at', 'notes']);
        $rules['scheduled_at'] = ['required', 'date'];
        $data = $request->validate($rules);
        $service->reschedule($followUp, $request->user(), $data['scheduled_at'], $data['next_follow_up_at'] ?? null, $data['notes'] ?? null);

        return redirect()->route($this->portal.'.follow-ups.show', $followUp)->with('status', 'Follow-up rescheduled.');
    }

    public function cancel(Request $request, FollowUp $followUp, FollowUpService $service): RedirectResponse
    {
        $data = $request->validate(Arr::only((new UpdateFollowUpRequest)->rules(), ['notes']));
        $service->cancel($followUp, $request->user(), $data['notes'] ?? null);

        return redirect()->route($this->portal.'.follow-ups.show', $followUp)->with('status', 'Follow-up cancelled. Its history has been retained.');
    }

    protected function customersForActor(Request $request): Builder
    {
        $actor = $request->user();
        $query = Customer::query()->verifiedPurchases();
        if ($actor instanceof Team) {
            return $query->where('team_id', $actor->id);
        }

        // Match the verified-customer picker to the employee's own orders.
        $orders = SalesOrder::where('assigned_employee_id', $actor->id);

        return $query->where('team_id', $actor->team_id)->where(function (Builder $customers) use ($orders, $actor) {
            $customers->whereIn('phone', (clone $orders)->whereNotNull('customer_phone')->select('customer_phone'))
                ->orWhereIn('email', (clone $orders)->whereNotNull('customer_email')->select('customer_email'))
                ->orWhereHas('subscriptions.order', fn (Builder $order) => $order->where('assigned_employee_id', $actor->id))
                ->orWhereHas('payments.order', fn (Builder $order) => $order->where('assigned_employee_id', $actor->id));
        });
    }
}
