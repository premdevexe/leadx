<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Encrypted-at-rest SMTP configuration.
 *
 * The `value` column stores a JSON string. The `smtp_password` field
 * inside that JSON is individually encrypted using Laravel's encrypt() /
 * decrypt() helpers.
 *
 * The password is NEVER returned through payload() — only the dedicated
 * decryptedPassword() method exposes it, and only to internal email
 * services. All API/response paths receive payload() which strips it.
 */
class EmailSetting extends Model
{
    protected $table = 'email_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            // value is stored as a JSON string; we handle encoding/decoding
            // manually to support per-field encryption of smtp_password.
            'value' => 'string',
        ];
    }

    public const KEY = 'smtp_settings';

    // ── Public API ──────────────────────────────────────────────────

    /**
     * Return the decrypted settings payload with smtp_password stripped.
     * This is the safe-to-use config array for general consumption.
     */
    public static function payload(): array
    {
        $data = static::rawPayload();

        unset($data['smtp_password']);

        return $data;
    }

    /**
     * Persist a validated settings payload. The smtp_password field is
     * individually encrypted before being stored.
     *
     * Blank passwords are treated as "keep existing" and never overwrite
     * a stored password with null.
     */
    public static function savePayload(array $payload): void
    {
        if (array_key_exists('smtp_password', $payload) && trim((string) $payload['smtp_password']) === '') {
            $existing = static::rawPayload();
            if (!empty($existing['smtp_password'])) {
                $payload['smtp_password'] = $existing['smtp_password'];
            } else {
                unset($payload['smtp_password']);
            }
        }

        $toStore = $payload;

        if (isset($toStore['smtp_password']) && $toStore['smtp_password'] !== '') {
            $toStore['smtp_password'] = encrypt((string) $toStore['smtp_password']);
        }

        static::updateOrCreate(
            ['key' => self::KEY],
            ['value' => json_encode($toStore, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
        );
    }

    /**
     * Return the stored SMTP password in plaintext, or null when not set.
     *
     * IMPORTANT: Callers must NEVER log, return, or expose this value.
     * It is used exclusively to build the Swift_Transport for sending.
     */
    public static function decryptedPassword(): ?string
    {
        return static::rawPayload()['smtp_password'] ?? null;
    }

    // ── Internal helpers ────────────────────────────────────────────

    /**
     * Return the raw stored payload array, decrypting smtp_password.
     */
    protected static function rawPayload(): array
    {
        $row = static::where('key', self::KEY)->first();

        if (!$row || empty($row->value)) {
            return [];
        }

        $data = json_decode($row->value, true) ?? [];

        if (isset($data['smtp_password']) && is_string($data['smtp_password'])) {
            try {
                $data['smtp_password'] = decrypt($data['smtp_password']);
            } catch (\Throwable) {
                $data['smtp_password'] = null;
            }
        }

        return $data;
    }
}
