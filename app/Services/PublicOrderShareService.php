<?php

namespace App\Services;

use App\Models\PublicOrderShare;
use App\Models\SalesOrder;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Lifecycle manager for public, unauthenticated order share links.
 *
 * The link is a bearer token: possession grants read-only access to a small
 * allow-list of order facts plus — when opted in and the order is
 * share-eligible — the customer's own API key. Nothing else is ever exposed,
 * and the token is never written to logs or audit rows (its shape is the only
 * thing that may appear anywhere).
 *
 * One live link per order: re-minting a link for an order retires the
 * previous live one (soft-revoke), so there is a single canonical URL to
 * share at any time.
 */
class PublicOrderShareService
{
    /**
     * Create (or refresh) the public link for an order.
     *
     * @param mixed $actor Admin | User | Team — recorded polymorphically
     * @param bool $includeApiKey fold the customer's delivered key into the page
     * @param Carbon|null $expiresAt optional hard expiry (null = never)
     *
     * Returns the share. A previous live link, if any, is revoked in the
     * same transaction so the customer always has exactly one working URL.
     */
    public function createForOrder(SalesOrder $order, mixed $actor, bool $includeApiKey = false, ?Carbon $expiresAt = null): PublicOrderShare
    {
        $includeApiKey = $includeApiKey && OrderCredentialService::hasKey($order);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($order, $actor, $includeApiKey, $expiresAt) {
            // Retire any existing live link for this order.
            PublicOrderShare::where('order_id', $order->id)
                ->whereNull('revoked_at')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->each(function (PublicOrderShare $old) use ($actor): void {
                    $old->revoke($actor);
                });

            return PublicOrderShare::createForOrder($order, $actor, $includeApiKey, $expiresAt);
        });
    }

    /**
     * Resolve a token to a live share + its order, or null when the token is
     * unknown, revoked, or expired. The caller treats null as 404.
     *
     * @return array{share: PublicOrderShare, order: SalesOrder}|null
     */
    public function resolve(string $token): ?array
    {
        if (! is_string($token) || strlen($token) !== PublicOrderShare::TOKEN_LENGTH) {
            return null;
        }

        try {
            $share = PublicOrderShare::where('token', $token)
                ->with(['order' => fn ($q) => $q->with(['team', 'subscription', 'subscriptions.orderItem', 'items'])])
                ->first();
        } catch (Throwable) {
            return null;
        }

        if ($share === null || ! $share->isLive() || $share->order === null) {
            return null;
        }

        return ['share' => $share, 'order' => $share->order];
    }

    /**
     * The customer API key the page may show, when this share opts in AND
     * the order is share-eligible (active order + active subscription).
     *
     * Both conditions are mandatory: a revoked/expired order must never
     * keep leaking its key even if the link itself is still live, and a
     * share that never opted in never surfaces the key.
     *
     * The plaintext is never logged.
     */
    public function keyForShare(PublicOrderShare $share, SalesOrder $order): ?string
    {
        if (! $share->include_api_key) {
            return null;
        }

        if (! $order->isShareEligible()) {
            return null;
        }

        if (! OrderCredentialService::hasKey($order)) {
            return null;
        }

        return OrderCredentialService::keyFor($order);
    }
}
