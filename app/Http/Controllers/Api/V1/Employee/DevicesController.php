<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Employee\Concerns\EmployeeScoped;
use App\Models\EmployeeDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FCM device-token registration for the Flutter employee app.
 *
 * Every route is scoped to the employee owning the token (via
 * `EmployeeScoped::currentEmployee`) — an employee can only ever register /
 * unregister / refresh their own devices, never another employee's.
 */
class DevicesController extends Controller
{
    use EmployeeScoped;

    /**
     * POST /api/v1/employee/devices/register
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'max:255'],
            'platform' => ['sometimes', 'string', 'in:android,ios'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $employee = $this->currentEmployee($request);

        $fcmToken = $validated['fcm_token'];

        // The token belongs to this employee. If it was registered by a
        // different employee (or unclaimed), it cannot be re-assigned — FCM
        // tokens are installation-scoped.
        $foreign = EmployeeDeviceToken::where('fcm_token', $fcmToken)
            ->where('employee_id', '!=', $employee->id)
            ->exists();

        if ($foreign) {
            return response()->json(['message' => 'Device token is already registered to another employee.'], 422);
        }

        $record = EmployeeDeviceToken::firstOrNew(['fcm_token' => $fcmToken]);
        $record->employee_id = $employee->id;
        $record->platform = $validated['platform'] ?? 'android';
        $record->device_name = $validated['device_name'] ?? null;
        $record->is_active = true;
        $record->last_error_at = null;
        $record->last_used_at = now();
        $record->save();

        return response()->json(['message' => 'Device registered.', 'data' => ['device_id' => $record->id]], 201);
    }

    /**
     * POST /api/v1/employee/devices/token-refresh
     *
     * FCM rotates tokens. The client sends the new token plus the old one it
     * originally registered with; we swap the value in place (keeping the row
     * id / employee association) rather than creating a duplicate.
     */
    public function tokenRefresh(Request $request): JsonResponse
    {
        $request->validate([
            'old_token' => ['required', 'string', 'max:255'],
            'new_token' => ['required', 'string', 'max:255'],
        ]);

        $employee = $this->currentEmployee($request);
        $old = $request->input('old_token');
        $new = $request->input('new_token');

        $existing = EmployeeDeviceToken::where('fcm_token', $old)
            ->where('employee_id', $employee->id)
            ->first();

        if (! $existing) {
            return response()->json(['message' => 'No matching device to refresh.'], 404);
        }

        // If this employee already registered the new token (e.g. a duplicate
        // install), drop the stale old row and keep the existing new one.
        $duplicate = EmployeeDeviceToken::where('fcm_token', $new)
            ->where('employee_id', $employee->id)
            ->whereKeyNot($existing->id)
            ->first();

        $existing->delete();

        if ($duplicate) {
            $duplicate->is_active = true;
            $duplicate->last_error_at = null;
            $duplicate->last_used_at = now();
            $duplicate->save();

            return response()->json(['message' => 'Device token refreshed.', 'data' => ['device_id' => $duplicate->id]]);
        }

        $record = EmployeeDeviceToken::create([
            'employee_id' => $employee->id,
            'fcm_token' => $new,
            'platform' => $existing->platform,
            'device_name' => $existing->device_name,
            'is_active' => true,
            'last_used_at' => now(),
        ]);

        return response()->json(['message' => 'Device token refreshed.', 'data' => ['device_id' => $record->id]]);
    }

    /**
     * DELETE /api/v1/employee/devices/unregister
     */
    public function unregister(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => ['required', 'string', 'max:255'],
        ]);

        $employee = $this->currentEmployee($request);

        $deleted = EmployeeDeviceToken::where('fcm_token', $request->input('fcm_token'))
            ->where('employee_id', $employee->id)
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'No matching device to remove.'], 404);
        }

        return response()->json(['message' => 'Device unregistered.']);
    }

    /**
     * GET /api/v1/employee/devices — list this employee's registered devices.
     */
    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $devices = EmployeeDeviceToken::where('employee_id', $employee->id)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'fcm_token', 'platform', 'device_name', 'is_active', 'last_used_at', 'created_at']);

        return response()->json(['data' => $devices, 'meta' => ['total' => $devices->count()]]);
    }
}
