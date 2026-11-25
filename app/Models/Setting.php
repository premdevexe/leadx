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
        'card' => 'Card',
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
    public const KEY_EMAIL_REMINDERS = 'email_reminders';
    public const KEY_LOGO = 'site_logo';
    public const KEY_LOGO_TRANSPARENT = 'site_logo_transparent';
    public const KEY_FAVICON = 'site_favicon';
    public const KEY_BILLING = 'billing_settings';

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
     * Email expiry-reminder configuration (Section 6).
     *
     * Returns the full settings array with safe defaults so the
     * scheduler works even when nothing has been saved yet:
     *
     * [
     *   'enabled'       => true,
     *   'reminder_days' => [7, 3, 1],
     *   'reminder_time' => '08:00',
     *   'notify_customer' => true,
     *   'notify_team'     => false,
     * ]
     *
     * @return array{enabled: bool, reminder_days: list<int>, reminder_time: string, notify_customer: bool, notify_team: bool}
     */
    public static function emailReminders(): array
    {
        $value = static::where('key', self::KEY_EMAIL_REMINDERS)->value('value');

        $defaults = [
            'enabled'         => true,
            'reminder_days'   => [7, 3, 1],
            'reminder_time'   => config('leadx.notifications.expiry_reminder_time', '08:00'),
            'notify_customer' => true,
            'notify_team'     => false,
        ];

        if (! is_array($value)) {
            return $defaults;
        }

        $days = array_values(array_map('intval', array_filter($value['reminder_days'] ?? [], fn ($d) => $d > 0)));

        return [
            'enabled'         => (bool) ($value['enabled'] ?? $defaults['enabled']),
            'reminder_days'   => $days ?: $defaults['reminder_days'],
            'reminder_time'   => (string) ($value['reminder_time'] ?? $defaults['reminder_time']),
            'notify_customer' => (bool) ($value['notify_customer'] ?? $defaults['notify_customer']),
            'notify_team'     => (bool) ($value['notify_team'] ?? $defaults['notify_team']),
        ];
    }

    /**
     * Persist the email reminder settings.
     *
     * @param  list<int>|null  $days  Reminder windows; null keeps the default.
     */
    public static function setEmailReminders(
        bool $enabled,
        ?array $days,
        string $time,
        bool $notifyCustomer,
        bool $notifyTeam,
    ): void {
        $current = static::emailReminders();

        static::updateOrCreate(
            ['key' => self::KEY_EMAIL_REMINDERS],
            ['value' => [
                'enabled'         => $enabled,
                'reminder_days'   => $days ?: $current['reminder_days'],
                'reminder_time'   => $time,
                'notify_customer' => $notifyCustomer,
                'notify_team'     => $notifyTeam,
            ]]
        );
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

    /**
     * Relative path to the uploaded main logo, or null.
     */
    public static function logoPath(): ?string
    {
        $val = static::where('key', self::KEY_LOGO)->value('value');

        return is_string($val) && trim($val) !== '' ? trim($val) : null;
    }

    /**
     * Full URL to the uploaded main logo, or null if unset/missing.
     */
    public static function logoUrl(): ?string
    {
        $path = static::logoPath();

        return $path && file_exists(public_path($path)) ? asset($path) : null;
    }

    /**
     * Store or clear the main logo path.
     */
    public static function setLogo(?string $path): void
    {
        $path = $path ? trim($path) : '';

        static::where('key', self::KEY_LOGO)->delete();

        if ($path !== '') {
            static::create(['key' => self::KEY_LOGO, 'value' => $path]);
        }
    }

    /**
     * Relative path to the transparent logo, or null.
     */
    public static function logoTransparentPath(): ?string
    {
        $val = static::where('key', self::KEY_LOGO_TRANSPARENT)->value('value');

        return is_string($val) && trim($val) !== '' ? trim($val) : null;
    }

    /**
     * Full URL to the transparent logo, or null if unset/missing.
     */
    public static function logoTransparentUrl(): ?string
    {
        $path = static::logoTransparentPath();

        return $path && file_exists(public_path($path)) ? asset($path) : null;
    }

    /**
     * Store or clear the transparent logo path.
     */
    public static function setLogoTransparent(?string $path): void
    {
        $path = $path ? trim($path) : '';

        static::where('key', self::KEY_LOGO_TRANSPARENT)->delete();

        if ($path !== '') {
            static::create(['key' => self::KEY_LOGO_TRANSPARENT, 'value' => $path]);
        }
    }

    /**
     * Relative path to the uploaded favicon, or null.
     */
    public static function faviconPath(): ?string
    {
        $val = static::where('key', self::KEY_FAVICON)->value('value');

        return is_string($val) && trim($val) !== '' ? trim($val) : null;
    }

    /**
     * Full URL to the uploaded favicon, or null if unset/missing.
     */
    public static function faviconUrl(): ?string
    {
        $path = static::faviconPath();

        if ($path && file_exists(public_path($path))) {
            return asset($path);
        }

        if (file_exists(public_path('favicon.ico')) && filesize(public_path('favicon.ico')) > 0) {
            return asset('favicon.ico');
        }

        return null;
    }

    /**
     * Store or clear the favicon path.
     */
    public static function setFavicon(?string $path): void
    {
        $path = $path ? trim($path) : '';

        static::where('key', self::KEY_FAVICON)->delete();

        if ($path !== '') {
            static::create(['key' => self::KEY_FAVICON, 'value' => $path]);
        }
    }

    /**
     * Enterprise Billing & Invoice configuration with safe defaults.
     *
     * @return array<string, mixed>
     */
    public static function billing(): array
    {
        $stored = static::where('key', self::KEY_BILLING)->value('value');
        $stored = is_array($stored) ? $stored : [];

        return array_merge([
            'company_name' => static::siteName(),
            'company_address' => '',
            'gstin' => '',
            'pan' => '',
            'billing_logo' => null,
            'invoice_prefix' => 'INV',
            'next_sequence' => 1,
            'default_currency' => static::currency(),
            'default_tax_rate' => 0.0,
            'default_tax_label' => 'Tax / GST',
            'default_payment_terms' => 'Due within 14 days of issue',
            'default_due_days' => 14,
            'invoice_footer_text' => 'Thank you for your business. This is a computer-generated invoice.',
            'support_email' => 'billing@leadx.local',
            'support_phone' => '',
            'default_template' => 'classic_navy',
            'pdf_paper_size' => 'a4',
            'pdf_show_gstin' => true,
            'pdf_show_pan' => true,
            'pdf_show_digital_verification' => true,
        ], $stored);
    }

    /**
     * Persist Billing & Invoice configuration.
     *
     * @param  array<string, mixed>  $data
     */
    public static function setBilling(array $data): void
    {
        $merged = array_merge(static::billing(), $data);
        static::updateOrCreate(
            ['key' => self::KEY_BILLING],
            ['value' => $merged]
        );
    }

    /**
     * Full URL or filesystem path for the billing logo (falls back to site logo).
     */
    public static function billingLogoUrl(): ?string
    {
        $billing = static::billing();
        $path = $billing['billing_logo'] ?? null;
        if (is_string($path) && $path !== '' && file_exists(public_path($path))) {
            return asset($path);
        }

        return static::logoUrl() ?? static::logoTransparentUrl();
    }

    public static function billingLogoLocalPath(): ?string
    {
        $billing = static::billing();
        $path = $billing['billing_logo'] ?? null;
        if (is_string($path) && $path !== '' && file_exists(public_path($path))) {
            return public_path($path);
        }

        $siteLogo = static::logoPath() ?? static::logoTransparentPath();
        if ($siteLogo && file_exists(public_path($siteLogo))) {
            return public_path($siteLogo);
        }

        return null;
    }
}
