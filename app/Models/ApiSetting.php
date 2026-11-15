<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * Stores third-party and external API integration configurations.
 * Sensitive credentials (keys, secrets, access tokens) are stored
 * encrypted at rest using Laravel's application key.
 */
class ApiSetting extends Model
{
    public const GROUP_PUSHER = 'pusher';
    public const GROUP_GOOGLE = 'google';
    public const GROUP_WHATSAPP = 'whatsapp';
    public const GROUP_GATEWAY = 'payment_gateway';
    public const GROUP_WEBHOOK = 'webhook';

    protected $fillable = [
        'group',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /**
     * Default configurations per group.
     */
    public static function defaults(string $group): array
    {
        return match ($group) {
            self::GROUP_PUSHER => [
                'instance_id' => config('services.pusher.instance_id', ''),
                'server_key' => config('services.pusher.server_key', ''),
                'cluster' => config('services.pusher.options.cluster', 'ap2'),
                'is_enabled' => true,
            ],
            self::GROUP_GOOGLE => [
                'client_id' => config('google.client_id', ''),
                'client_secret' => config('google.client_secret', ''),
                'redirect_uri' => config('google.redirect_uri', ''),
                'is_enabled' => true,
            ],
            self::GROUP_WHATSAPP => [
                'provider' => 'click_to_chat', // click_to_chat or cloud_api
                'base_url' => config('leadx.whatsapp.base_url', 'https://wa.me'),
                'default_country_code' => config('leadx.whatsapp.default_country_code', '91'),
                'message_template' => config('leadx.whatsapp.message_template', 'Hello {customer_name}, your order #{order_number} for {product_name} is confirmed!'),
                'phone_number_id' => '',
                'access_token' => '',
                'is_enabled' => true,
            ],
            self::GROUP_GATEWAY => [
                'driver' => config('leadx.gateway.driver', 'mock'),
                'environment' => 'sandbox', // sandbox or live
                'key_id' => '',
                'secret_key' => '',
                'webhook_secret' => config('leadx.gateway.secret', ''),
                'is_enabled' => true,
            ],
            self::GROUP_WEBHOOK => [
                'endpoint_url' => '',
                'secret_token' => '',
                'events' => ['order.created', 'payment.verified', 'subscription.activated'],
                'is_enabled' => false,
            ],
            default => [],
        };
    }

    /**
     * Retrieve group settings, decrypting any secret fields.
     */
    public static function getGroup(string $group): array
    {
        $record = static::where('group', $group)->first();
        $defaults = static::defaults($group);

        if (! $record || ! is_array($record->payload)) {
            return $defaults;
        }

        $payload = array_merge($defaults, $record->payload);

        // Decrypt secret fields if encrypted
        $secretFields = static::secretFieldsFor($group);
        foreach ($secretFields as $field) {
            if (! empty($payload[$field])) {
                try {
                    $payload[$field] = Crypt::decryptString($payload[$field]);
                } catch (\Throwable) {
                    // Raw string or unencrypted fallback
                }
            }
        }

        return $payload;
    }

    /**
     * Save group settings, encrypting designated secret fields.
     */
    public static function saveGroup(string $group, array $input): void
    {
        $existing = static::where('group', $group)->first();
        $currentPayload = $existing?->payload ?? [];
        $secretFields = static::secretFieldsFor($group);

        $payloadToSave = $input;

        foreach ($secretFields as $field) {
            if (array_key_exists($field, $input)) {
                $newVal = trim((string) $input[$field]);
                if ($newVal === '') {
                    // Keep existing encrypted value if user left field blank
                    $payloadToSave[$field] = $currentPayload[$field] ?? '';
                } else {
                    $payloadToSave[$field] = Crypt::encryptString($newVal);
                }
            }
        }

        static::updateOrCreate(
            ['group' => $group],
            ['payload' => $payloadToSave]
        );

        // Re-bootstrap runtime config immediately
        static::applyGroupToConfig($group, static::getGroup($group));
    }

    /**
     * Secret fields per group that must be encrypted.
     *
     * @return list<string>
     */
    public static function secretFieldsFor(string $group): array
    {
        return match ($group) {
            self::GROUP_PUSHER => ['server_key'],
            self::GROUP_GOOGLE => ['client_secret'],
            self::GROUP_WHATSAPP => ['access_token'],
            self::GROUP_GATEWAY => ['secret_key', 'webhook_secret'],
            self::GROUP_WEBHOOK => ['secret_token'],
            default => [],
        };
    }

    /**
     * Overlays database API configurations into Laravel runtime config.
     */
    public static function bootstrapRuntimeConfig(): void
    {
        try {
            if (! Schema::hasTable('api_settings')) {
                return;
            }

            foreach ([self::GROUP_PUSHER, self::GROUP_GOOGLE, self::GROUP_WHATSAPP, self::GROUP_GATEWAY] as $group) {
                $settings = static::getGroup($group);
                static::applyGroupToConfig($group, $settings);
            }
        } catch (\Throwable) {
            // Silently fall back to default configs during early bootstrap or migrations
        }
    }

    /**
     * Apply resolved group settings to config keys.
     */
    protected static function applyGroupToConfig(string $group, array $settings): void
    {
        if (empty($settings)) {
            return;
        }

        switch ($group) {
            case self::GROUP_PUSHER:
                if (! empty($settings['instance_id'])) {
                    config(['services.pusher.instance_id' => $settings['instance_id']]);
                }
                if (! empty($settings['server_key'])) {
                    config(['services.pusher.server_key' => $settings['server_key']]);
                    config(['services.pusher.secret' => $settings['server_key']]);
                }
                if (! empty($settings['cluster'])) {
                    config(['services.pusher.options.cluster' => $settings['cluster']]);
                }
                break;

            case self::GROUP_GOOGLE:
                if (! empty($settings['client_id'])) {
                    config(['google.client_id' => $settings['client_id']]);
                }
                if (! empty($settings['client_secret'])) {
                    config(['google.client_secret' => $settings['client_secret']]);
                }
                if (! empty($settings['redirect_uri'])) {
                    config(['google.redirect_uri' => $settings['redirect_uri']]);
                }
                break;

            case self::GROUP_WHATSAPP:
                if (! empty($settings['base_url'])) {
                    config(['leadx.whatsapp.base_url' => $settings['base_url']]);
                }
                if (! empty($settings['default_country_code'])) {
                    config(['leadx.whatsapp.default_country_code' => $settings['default_country_code']]);
                }
                if (! empty($settings['message_template'])) {
                    config(['leadx.whatsapp.message_template' => $settings['message_template']]);
                }
                break;

            case self::GROUP_GATEWAY:
                if (! empty($settings['driver'])) {
                    config(['leadx.gateway.driver' => $settings['driver']]);
                }
                if (! empty($settings['webhook_secret'])) {
                    config(['leadx.gateway.secret' => $settings['webhook_secret']]);
                }
                break;
        }
    }
}
