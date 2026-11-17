<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\PublicOrderShare;
use App\Models\Setting;
use App\Services\PublicOrderShareService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The unauthenticated, customer-facing order share page.
 *
 * Access is bearer-token: the 40-char URL token is the entire credential.
 * The page shows a small allow-list of order facts (customer, product,
 * money, statuses, dates) and — only when the share opted in AND the order
 * is share-eligible — the customer's own API key. The key is shown masked by
 * default and revealed for a single page view via a POST, never persisted.
 */
class OrdersController
{
    /**
     * GET /public/orders/{token}
     */
    public function show(Request $request, string $token): View
    {
        $result = app(PublicOrderShareService::class)->resolve($token);

        if ($result === null) {
            abort(404, 'This share link is not available.');
        }

        /** @var PublicOrderShare $share */
        $share = $result['share'];
        $order = $result['order'];

        // One-time key reveal, session-scoped like the in-portal reveal.
        $reveal = $request->session()->get('public_order_api_key_reveal');
        $revealedKey = null;
        if (is_array($reveal) && (int) ($reveal['order_id'] ?? 0) === (int) $order->id) {
            $revealedKey = $reveal['key'];
            $request->session()->forget('public_order_api_key_reveal');
        }

        $keyService = app(\App\Services\OrderCredentialService::class);

        return view('public.orders.show', [
            'order' => $order,
            'share' => $share,
            // Status-only facts; the key itself is fetched on demand below.
            'apiCredentialStatus' => $keyService->status($order),
            // Whether the key block is even relevant for this link.
            'keyIncluded' => $share->include_api_key && $order->isShareEligible(),
            'maskedKey' => $keyService->status($order)['masked_key'],
            'revealedKey' => $revealedKey,
            'currency' => Setting::currencySymbol(),
        ]);
    }

    /**
     * POST /public/orders/{token}/api-key/reveal - one-time plaintext read.
     *
     * Gated on the share opting in AND the order remaining share-eligible
     * at reveal time (a cancelled/expired order must not keep serving its
     * key even if the link is still live).
     */
    public function revealApiKey(Request $request, string $token): \Illuminate\Http\RedirectResponse
    {
        $result = app(PublicOrderShareService::class)->resolve($token);

        if ($result === null) {
            abort(404, 'This share link is not available.');
        }

        /** @var PublicOrderShare $share */
        $share = $result['share'];
        $order = $result['order'];

        $key = app(PublicOrderShareService::class)->keyForShare($share, $order);

        if ($key === null) {
            return back()->with('publicOrderKeyError', 'No API key is available for this link.');
        }

        // Flash for exactly one response, mirroring the employee reveal.
        $request->session()->flash('public_order_api_key_reveal', [
            'order_id' => $order->id,
            'key' => $key,
        ]);

        return back()->with('publicOrderKeyStatus', 'API key revealed for this view only.');
    }
}
