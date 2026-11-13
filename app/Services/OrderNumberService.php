<?php

namespace App\Services;

use App\Models\Team;
use App\Support\TeamCode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Generates immutable, team-scoped order numbers of the form
 *
 *     TEAMCODE-YEAR-SEQUENCE   e.g.  GEM-2026-000001
 *
 * Guarantees:
 *  - Each team keeps its own sequence; a new calendar year starts a fresh
 *    sequence (000001) in the configured business timezone.
 *  - Numbers are never reused, even for deleted orders: the ledger
 *    (order_number_sequences.last_sequence) is a high-water-mark, not a count.
 *  - Immutable: once issued, an order number is never re-issued.
 *  - Changing a team's name or code later does not rewrite history; the code
 *    is snapshotted onto the order at creation time.
 *
 * Concurrency safety: the sequence ledger row is locked with
 * SELECT ... FOR UPDATE inside a DB transaction. If two workers race to
 * create the row for a brand-new (team, year), the unique constraint rejects
 * the loser, which is caught and retried against the now-locked row.
 */
class OrderNumberService
{
    /** Sequence width, zero-padded. */
    private const SEQUENCE_WIDTH = 6;

    /**
     * Maximum times a lost race on the (team, year) insert is retried
     * before giving up.
     */
    private const MAX_RETRIES = 5;

    /**
     * Reserve and return the next order number for a team.
     *
     * This atomically claims the number so a caller can persist it on their
     * record (e.g. inside the same transaction that creates the order).
     */
    public function next(Team $team, ?string $businessTimezone = null): string
    {
        $timezone = $businessTimezone ?: config('leadx.business_timezone', 'UTC');

        // Carbon::setTestNow lets tests freeze "now" to drive the year
        // rollover deterministically; the year is read in the business
        // timezone so a midnight boundary flips the sequence at the right
        // calendar moment.
        $year = (int) Carbon::now($timezone)->format('Y');

        $sequence = $this->nextSequence($team->id, $year);

        return sprintf(
            '%s-%d-%s',
            $team->team_code,
            $year,
            str_pad((string) $sequence, self::SEQUENCE_WIDTH, '0', STR_PAD_LEFT)
        );
    }

    /**
     * Atomically fetch-and-increment the ledger for a team/year.
     *
     * @return int the sequence value just issued
     */
    protected function nextSequence(int $teamId, int $year): int
    {
        $attempt = 0;

        do {
            try {
                return DB::transaction(function () use ($teamId, $year) {
                    $row = DB::table('order_number_sequences')
                        ->where('team_id', $teamId)
                        ->where('year', $year)
                        ->lockForUpdate()
                        ->first(['id', 'last_sequence']);

                    if ($row === null) {
                        // First order for this team/year: claim sequence 1.
                        // A concurrent winner may have inserted the row in the
                        // instant we looked — the unique (team_id, year)
                        // constraint turns that into a retryable exception.
                        DB::table('order_number_sequences')->insert([
                            'team_id' => $teamId,
                            'year' => $year,
                            'last_sequence' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        return 1;
                    }

                    $next = (int) $row->last_sequence + 1;

                    DB::table('order_number_sequences')
                        ->where('id', $row->id)
                        ->update([
                            'last_sequence' => $next,
                            'updated_at' => now(),
                        ]);

                    return $next;
                });
            } catch (UniqueConstraintViolationException) {
                $attempt++;

                if ($attempt >= self::MAX_RETRIES) {
                    throw new \RuntimeException(
                        "Could not reserve order number for team {$teamId} / year {$year} after {$attempt} attempts."
                    );
                }
            }
        } while (true);
    }

    /**
     * Whether a given Team Code string satisfies the required format.
     *
     * Retained here so callers (and tests) have a single entry point that
     * mirrors the validation rule, independent of the FormRequest layer.
     */
    public function teamCodeIsValid(string $code): bool
    {
        return TeamCode::isValid($code);
    }
}
