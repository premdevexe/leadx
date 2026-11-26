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
            $request->ip(),
            $request->validated('order_item_id') ? (int) $request->validated('order_item_id') : null
        );

        // API key delivery email: only fires on the explicitly authorized
        // delivery workflow (admin saves the key), and only when the admin
        // ticks "notify the customer" or when the event is enabled and the
        // order carries a customer email. The key value itself is NEVER
        // included in logs — it appears only in the outbound email body.
        if ($result['saved'] && $this->shouldDeliverKeyByEmail($order, $request)) {
            try {
                app(\App\Services\EmailService::class)->dispatch('order.api_key_delivered', [
                    'order_id'       => $order->id,
                    'order_number'   => $order->order_number,
                    'customer_name'  => $order->customer_name,
                    'customer_email' => $order->customer_email,
                    'product_name'   => $order->product_name_snapshot ?? '—',
                    'api_key'        => $request->validated('api_key'),
                    'to_email'       => $order->customer_email,
                ]);
            } catch (\Throwable) {
                // Email failure never blocks the credential save.
            }
        }

        return back()->with($result['saved'] ? 'status' : 'error', $result['message']);
    }

    /**
     * The key email goes out only when the customer has an email address
     * AND the event is enabled in admin settings.
     */
    protected function shouldDeliverKeyByEmail(SalesOrder $order, Request $request): bool
    {
        if (empty($order->customer_email)) {
            return false;
        }

        return \App\Models\EmailNotificationPreference::where('event_key', 'order.api_key_delivered')
            ->where('is_enabled', true)
            ->exists();
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

        // Flash the value so it displays exactly one response after the
        // redirect, then is removed from the session store. A persistent
        // session entry would keep the plaintext key for up to the session
        // lifetime (2h), contradicting the "cleared after Copy" promise.
        session()->flash('order_api_key_reveal', ['order_id' => $order->id, 'key' => $key]);

        return back()->with('status', 'API key revealed for '.$order->order_number.' Use Copy, then it will be cleared.');
    }
}
