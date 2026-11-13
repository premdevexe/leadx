<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key/value admin settings. `value` is JSON (list, object or scalar);
 * helpers below keep call sites tidy.
 */
class Setting extends Model
{
    /**
     * All payment methods the admin can toggle on the Settings page.
     * The stored list of enabled ones lives under key 'payment_methods'.
     *
     * @var array<string, string> code => label
     */
    public const PAYMENT_METHODS = [
        'gpay' => 'Google Pay',
        'phonepe' => 'PhonePe',
        'paytm' => 'Paytm',
        'upi' => 'UPI',
        'bank_transfer' => 'Bank Transfer',
        'cash' => 'Cash',
    ];

    public const KEY_PAYMENT_METHODS = 'payment_methods';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * Get a setting as a list; defaults when unset.
     *
     * @return list<string>
     */
    public static function stringList(string $key, array $default): array
    {
        $value = static::where('key', $key)->value('value');

        return is_array($value) ? array_values($value) : $default;
    }

    /**
     * Persist a list of strings under a key.
     *
     * @param list<string> $value
     */
    public static function setStringList(string $key, array $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => array_values(array_filter(array_map('trim', $value)))]
        );
    }

    /**
     * The payment methods currently enabled by the admin (default: all).
     *
     * @return list<string>
     */
    public static function enabledPaymentMethods(): array
    {
        return static::stringList(self::KEY_PAYMENT_METHODS, array_keys(self::PAYMENT_METHODS));
    }

    /**
     * Label for a payment method code, or the raw code when unknown.
     */
    public static function paymentMethodLabel(?string $code): string
    {
        if ($code === null || $code === '') {
            return '—';
        }

        return self::PAYMENT_METHODS[$code] ?? $code;
    }
}
