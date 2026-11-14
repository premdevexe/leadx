<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderApiKeyRequest;
use App\Models\SalesOrder;
use App\Services\OrderCredentialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Order-scoped customer API key delivery (server-rendered admin).
 *
 * The admin guard already restricts these routes to Super Admins, who are
 * authorized for every order's credential. The panel:
 *   - shows a masked indicator + who/when (never the plaintext by default);
 *   - stores or replaces the key (encrypted at rest, audited);
 *   - offers an explicit "reveal" that returns the plaintext ONCE, in the
 *     response, so it can be copied — it is not logged.
 *
 * The key is never generated, and no provider/base-url/model fields exist.
 */
class OrderCredentialController extends Controller
{
    public function __construct(protected OrderCredentialService $credentials)
    {
    }

    /**
     * POST /admin/orders/{order}/api-key — store or replace the customer key.
     */
    public function store(StoreOrderApiKeyRequest $request, SalesOrder $order): RedirectResponse
    {
        $actor = $request->user('admin');

        $result = $this->credentials->store(
            $order,
            $request->validated('api_key'),
            $actor,
            $request->ip()
        );

        return back()->with($result['saved'] ? 'status' : 'error', $result['message']);
    }

    /**
     * POST /admin/orders/{order}/api-key/reveal — one-time plaintext read so
     * the admin can copy the customer's key. The value is returned in the
     * session flash and NEVER logged; only Super Admins reach this route.
     */
    public function reveal(Request $request, SalesOrder $order): RedirectResponse
    {
        $actor = $request->user('admin');
        $key = $this->credentials->reveal($order, $actor);

        if ($key === null) {
            return back()->with('error', 'No API key is stored for this order, or it could not be decrypted.');
        }

        // Flash the value so the next response can display it for copying;
        // it is not persisted and not written to any log.
        session(['order_api_key_reveal' => ['order_id' => $order->id, 'key' => $key]]);

        return back()->with('status', 'API key revealed for '.$order->order_number.' Use Copy, then it will be cleared.');
    }
}
