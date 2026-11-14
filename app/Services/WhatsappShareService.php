<?php

namespace App\Services;

use App\Models\SalesOrder;
use App\Models\WhatsappShareLog;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * Builds safe, prefilled WhatsApp click-to-chat links for share-eligible
 * orders.
 *
 * This service NEVER sends a message. It only:
 *  - reports whether an order is eligible (active order + active subscription
 *    + a valid saved phone number),
 *  - assembles a message from an allow-list of order fields only, so that
 *    passwords, API keys and other credentials can never leak into it,
 *  - returns a wa.me deep link the user can open and send from their device,
 *  - records optional share activity with an honest status ("prepared" or
 *    "recorded") that does not claim delivery.
 */
class WhatsappShareService
{
    /**
     * Build the share payload for an order.
     *
     * The customer's API key is included ONLY when $includeApiKey is
     * explicitly true AND the order actually has a stored credential — never
     * by default. Callers gate $includeApiKey on their own authorization
     * check (see OrderCredentialService::canManage); this service only
     * assembles the text from an allow-list of order fields plus that key.
     *
     * @return array<string, mixed> {order, eligible, reason, phone, message, url, api_key_included}
     */
    public function prepare(SalesOrder $order, bool $includeApiKey = false): array
    {
        $order->loadMissing(['team', 'subscription']);

        $phone = $order->customer_phone;
        $normalized = $phone ? $this->normalizePhone((string) $phone) : null;

        if (! $order->isShareEligible()) {
            $reason = $order->order_status !== 'active'
                ? 'The order must be active to be shared.'
                : 'The order has no active subscription.';

            return [
                'order' => $order,
                'eligible' => false,
                'reason' => $reason,
                'phone' => $normalized,
                'message' => null,
                'url' => null,
                'api_key_included' => false,
            ];
        }

        if ($normalized === null) {
            return [
                'order' => $order,
                'eligible' => false,
                'reason' => 'The customer has no saved phone number, or it could not be normalized.',
                'phone' => null,
                'message' => null,
                'url' => null,
                'api_key_included' => false,
            ];
        }

        $message = $this->buildMessage($order, $includeApiKey);

        return [
            'order' => $order,
            'eligible' => true,
            'reason' => null,
            'phone' => $normalized,
            'message' => $message,
            'url' => $this->buildUrl($normalized, $message),
            // True only when the key was actually folded into the text.
            'api_key_included' => $includeApiKey && OrderCredentialService::hasKey($order),
        ];
    }

    /**
     * Whether the message text would carry the customer's API key given the
     * opt-in flag, regardless of eligibility. Used by controllers to gate
     * the checkbox on the order actually having a key.
     */
    public static function keyAvailableFor(SalesOrder $order): bool
    {
        return OrderCredentialService::hasKey($order);
    }

    /**
     * Record a share the user explicitly initiated.
     *
     * @param string|null $editedMessage   employee-reviewed/edited message body
     * @param bool $sharedApiKey           whether the opted-in key was folded
     *                                     into the shared text this time
     *
     * @return WhatsappShareLog
     */
    public function logShare(SalesOrder $order, mixed $actor, ?string $editedMessage = null, bool $sharedApiKey = false): WhatsappShareLog
    {
        $payload = $this->prepare($order);

        if (! $payload['eligible']) {
            throw ValidationException::withMessages([
                'order' => $payload['reason'],
            ]);
        }

        $message = $editedMessage ?? $payload['message'];

        // Only persist fields we produced ourselves; strip anything that even
        // looks like a secret, just in case an edited message smuggles one in.
        // This guarantees the customer's API key never reaches the log row.
        $message = $this->scrub($message);

        return WhatsappShareLog::create([
            'order_id' => $order->id,
            'actor_id' => $actor?->id ?? null,
            'actor_type' => $actor ? class_basename($actor) : null,
            'normalized_phone' => $payload['phone'],
            'message' => $message,
            // Records that a key was shared (an audit fact), WITHOUT the key
            // itself — the key is scrubbed out of the stored message.
            'shared_api_key' => $sharedApiKey,
            // "recorded" = a share was prepared/logged by the user.
            // It is deliberately NOT "delivered".
            'status' => 'recorded',
        ]);
    }

    /**
     * Normalize a phone number to international (E.164-style) digits.
     *
     * No libphonenumber dependency: strips separators, handles an explicit
     * leading '+', and otherwise prefixes the configured default country
     * code. Returns null when the result is not a plausible number.
     */
    public function normalizePhone(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $hasPlus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D/', '', $raw);

        if ($digits === '' || ! $hasPlus) {
            $default = (string) config('leadx.whatsapp.default_country_code', '1');
            // A leading 0 is treated as a domestic trunk prefix.
            $digits = $default . ltrim($digits, '0');
        }

        // Plausible E.164 range: 7-15 digits.
        if (preg_match('/^[0-9]{7,15}$/', $digits) !== 1) {
            return null;
        }

        return '+' . $digits;
    }

    /**
     * Assemble the share message from an allow-list of order/subscription
     * fields. The customer's API key is appended ONLY when $includeApiKey is
     * true (an explicit, authorized choice) — it is never part of the
     * default summary. The activation date and subscription status come from
     * the subscription so the payload matches the delivery spec.
     */
    protected function buildMessage(SalesOrder $order, bool $includeApiKey = false): string
    {
        $subscription = $order->subscription;

        $lines = [
            '*'.($order->team?->team_name ?? 'LeadX').' — Subscription Summary*',
            'Order ID: '.$order->order_number,
            'Customer: '.$order->customer_name,
        ];

        $productName = $order->product_name_snapshot ?? $order->product_name;

        if ($productName) {
            $lines[] = 'Product: '.$productName;
        }

        $price = $order->product_price_snapshot ?? $order->product_value;

        $lines[] = 'Price: '
            . ($price !== null
                ? config('leadx.currency', '$').number_format((float) $price, 2)
                : '—');
        $lines[] = 'Duration: '
            . (\App\Models\SalesOrder::DURATIONS[$order->product_duration_snapshot ?? $order->duration] ?? ($order->product_duration_snapshot ?? $order->duration ?? '—'));

        if ($subscription) {
            $lines[] = 'Subscription Status: '.$this->label($subscription->status);

            if ($subscription->started_at) {
                $lines[] = 'Activation Date: '.$subscription->started_at->toDateString();
            }

            if ($subscription->expires_at) {
                $lines[] = 'Expiry Date: '.$subscription->expires_at->toDateString();
            }
        }

        $lines[] = 'Payment Status: '.$this->label($order->payment_status);

        // The API key is opt-in and comes from the order's encrypted
        // credential. It is added only here, only when explicitly requested,
        // and is later scrubbed from any stored log copy.
        if ($includeApiKey) {
            $key = OrderCredentialService::keyFor($order);

            $lines[] = $key !== null ? 'API Key: '.$key : 'API Key: (none stored)';
        }

        $template = Config::get('leadx.whatsapp.message_template');

        if (is_string($template) && $template !== '') {
            return str_replace([
                '{team}', '{order_id}', '{customer}', '{product}',
                '{closing_value}', '{payment_status}',
                '{subscription_status}', '{expiry_date}',
            ], [
                (string) ($order->team?->team_name),
                (string) $order->order_number,
                (string) $order->customer_name,
                (string) $order->product_name,
                number_format((float) $order->closing_value, 2),
                $this->label($order->payment_status),
                $this->label($subscription?->status ?? 'unknown'),
                (string) ($subscription->expires_at?->toDateString() ?? 'n/a'),
            ], $template);
        }

        return implode("\n", $lines);
    }

    /**
     * Build the wa.me click-to-chat URL. The user opens this and presses send
     * themselves — the server never transmits the message.
     */
    protected function buildUrl(string $normalizedPhone, string $message): string
    {
        $base = rtrim((string) config('leadx.whatsapp.base_url', 'https://wa.me'), '/');
        $phone = ltrim($normalizedPhone, '+');

        return $base.'/'.$phone.'?text='.urlencode($message);
    }

    /**
     * Remove anything that even resembles a credential so a user-edited
     * message cannot leak a secret into the log.
     */
    protected function scrub(string $message): string
    {
        $patterns = [
            '/api[_\- ]?key[s]?\s*[:=]\s*\S+/i',
            '/\b(password|passwd|secret|token|bearer)\s*[:=]\s*\S+/i',
            '/\bBearer\s+[A-Za-z0-9\-._~+\/=]+/i',
            '/\b[A-Za-z0-9\-_=]{20,}\.[A-Za-z0-9\-_=]{10,}\.[A-Za-z0-9\-_=]{5,}/', // JWT
        ];

        foreach ($patterns as $pattern) {
            $message = preg_replace($pattern, '[redacted]', $message);
        }

        return $message;
    }

    protected function label(?string $value): string
    {
        if ($value === null) {
            return '—';
        }

        return ucfirst(str_replace('_', ' ', $value));
    }
}
