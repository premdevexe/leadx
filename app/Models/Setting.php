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
     * Currencies the admin can pick on the Settings page, code => symbol.
     * The site name and active currency live under the 'site_name' and
     * 'currency' keys.
     *
     * @var array<string, string>
     */
    public const CURRENCIES = [
        'INR' => '₹',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'AED' => 'د.إ',
    ];

    public const KEY_SITE_NAME = 'site_name';
    public const KEY_CURRENCY = 'currency';

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

    /**
     * The site name (default 'LeadX').
     */
    public static function siteName(): string
    {
        $value = static::where('key', self::KEY_SITE_NAME)->value('value');

        return is_string($value) && trim($value) !== '' ? trim($value) : 'LeadX';
    }

    /**
     * Store the site name. An empty value clears the setting so the
     * default ('LeadX') applies again.
     */
    public static function setSiteName(string $name): void
    {
        $name = trim($name);

        static::where('key', self::KEY_SITE_NAME)
            ->first()
            ?->delete();

        if ($name !== '') {
            static::create(['key' => self::KEY_SITE_NAME, 'value' => $name]);
        }
    }

    /**
     * The active currency code (default 'USD').
     */
    public static function currency(): string
    {
        $value = static::where('key', self::KEY_CURRENCY)->value('value');

        return is_string($value) && $value !== '' ? $value : 'USD';
    }

    /**
     * The symbol for the active currency code.
     */
    public static function currencySymbol(): string
    {
        return self::currencySymbolFor(static::currency());
    }

    /**
     * The display symbol for a stored currency code (payment rows carry
     * their own code). Falls back to the code itself when unknown.
     */
    public static function currencySymbolFor(?string $code): string
    {
        $code = $code === null || $code === '' ? 'USD' : $code;

        return self::CURRENCIES[$code] ?? $code;
    }

    /**
     * Persist the active currency code.
     */
    public static function setCurrency(string $code): void
    {
        static::updateOrCreate(['key' => self::KEY_CURRENCY], ['value' => $code]);
    }
}
