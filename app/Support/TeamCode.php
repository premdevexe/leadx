<?php

namespace App\Support;

/**
 * Canonical definition of a valid Team Code.
 *
 * A Team Code is uppercase letters and numbers only:
 *   - a plain alpha code of 2-6 letters (e.g. CLA, GEM, GPT), or
 *   - a TEAM-prefixed code with up to 4 digits (e.g. TEAM01).
 *
 * Kept in one place so FormRequests, the service layer and tests all share
 * the exact same rule instead of each hard-coding a copy of the regex.
 */
final class TeamCode
{
    /**
     * The single source of truth for the Team Code format.
     *
     * @var string
     */
    public const PATTERN = '/^(?:[A-Z]{2,6}|TEAM[0-9]{1,4})$/';

    public static function isValid(string $code): bool
    {
        return preg_match(self::PATTERN, $code) === 1;
    }

    /**
     * Human-readable rule for validation error messages.
     */
    public static function formatHint(): string
    {
        return '2-6 uppercase letters (e.g. GEM), or TEAM followed by 1-4 digits (e.g. TEAM01)';
    }
}
