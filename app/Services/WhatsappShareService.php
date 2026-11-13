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
     * @return array<string, mixed> {order, eligible, reason, phone, message, url}
     */
    public function prepare(SalesOrder $order): array
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
            ];
        }

        $message = $this->buildMessage($order);

        return [
            'order' => $order,
            'eligible' => true,
            'reason' => null,
            'phone' => $normalized,
            'message' => $message,
            'url' => $this->buildUrl($normalized, $message),
        ];
    }

    /**
     * Record a share the user explicitly initiated.
     *
     * @param string|null $editedMessage employee-reviewed/edited message body
     *
     * @return WhatsappShareLog
     */
    public function logShare(SalesOrder $order, mixed $actor, ?string $editedMessage = null): WhatsappShareLog
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
        $message = $this->scrub($message);

        return WhatsappShareLog::create([
            'order_id' => $order->id,
            'actor_id' => $actor?->id ?? null,
            'actor_type' => $actor ? class_basename($actor) : null,
            'normalized_phone' => $payload['phone'],
            'message' => $message,
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
     * Assemble the share message from an allow-list of fields only.
     */
    protected function buildMessage(SalesOrder $order): string
    {
        $subscription = $order->subscription;

        $lines = [
            '*'.($order->team?->team_name ?? 'LeadX').' — Subscription Summary*',
            'Order ID: '.$order->order_number,
            'Customer: '.$order->customer_name,
        ];

        if ($order->product_name) {
            $lines[] = 'Product: '.$order->product_name;
        }

        if ($order->subscription_plan) {
            $lines[] = 'Plan: '.$order->subscription_plan;
        }

        $lines[] = 'Closing Value: '
            . ($order->closing_value !== null ? config('leadx.currency', '$') . number_format((float) $order->closing_value, 2) : '—');
        $lines[] = 'Payment Status: '.$this->label($order->payment_status);

        if ($subscription) {
            $lines[] = 'Subscription Status: '.$this->label($subscription->status);

            if ($subscription->expires_at) {
                $lines[] = 'Expiry Date: '.$subscription->expires_at->toDateString();
            }
        }

        $template = Config::get('leadx.whatsapp.message_template');

        if (is_string($template) && $template !== '') {
            return str_replace([
                '{team}', '{order_id}', '{customer}', '{product}',
                '{plan}', '{closing_value}', '{payment_status}',
                '{subscription_status}', '{expiry_date}',
            ], [
                (string) ($order->team?->team_name),
                (string) $order->order_number,
                (string) $order->customer_name,
                (string) $order->product_name,
                (string) $order->subscription_plan,
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
