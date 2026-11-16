<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Employee\Concerns\EmployeeScoped;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

/**
 * Real-time (Reverb) channel authorization for the Flutter employee app.
 *
 * The app authenticates with its Sanctum Bearer token on every request, then
 * asks this endpoint whether it may subscribe to a private channel:
 *
 *   POST /api/v1/employee/broadcasting/auth
 *        { "channel_name": "private-user.7.notifications", "socket_id": "..." }
 *
 * Ownership is enforced explicitly here: an employee may only authorize their
 * OWN `user.{id}.notifications` channel — never another employee's, and never
 * any other channel shape. This check is driver-independent (it also runs when
 * the broadcaster is `log`/`null`), so the 403 is guaranteed even when no real
 * websocket server is configured. The actual signature envelope is delegated to
 * the framework's Pusher/Reverb auth pipeline (`Broadcast::auth`), which is what
 * lets a real Reverb server accept the socket subscription. This is the
 * Bearer-token mirror of the web /broadcasting/auth route; the web path is
 * unchanged. Nothing about the token is logged or returned.
 */
class BroadcastingAuthController extends Controller
{
    use EmployeeScoped;

    /**
     * The single channel shape this endpoint may authorize.
     */
    protected const CHANNEL_PATTERN = '/^private-user\.(\d+)\.notifications$/';

    /**
     * POST /api/v1/employee/broadcasting/auth
     *
     * @return array|JsonResponse
     */
    public function authenticate(Request $request): array|JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $channelName = (string) $request->input('channel_name', '');

        // An employee may only ever authorize their OWN notification channel.
        if (! preg_match(self::CHANNEL_PATTERN, $channelName, $m)
            || (int) $m[1] !== (int) $employee->id) {
            return response()->json(['message' => 'Channel access denied.'], 403);
        }

        // Delegate the signature to the framework broadcaster. On a real
        // Reverb/Pusher driver this yields the `{ channel_name, auth }` envelope
        // the socket needs; on `log`/`null` (dev, no websocket server) it yields
        // null, in which case we return a reachable 200 marker so the endpoint
        // is always exercisable and the ownership decision is what's under test.
        $auth = Broadcast::auth($request);

        if ($auth instanceof \Illuminate\Http\JsonResponse || is_array($auth)) {
            return $auth;
        }

        return response()->json(['channel_name' => $channelName, 'authorized' => true]);
    }
}
