<?php

namespace App\Services\Billing;

use App\Models\InvoiceSequence;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    public function nextInvoiceNumber(): string
    {
        $billing = Setting::billing();
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($billing['invoice_prefix'] ?? 'INV')) ?: 'INV');
        $minStart = max(1, (int) ($billing['next_sequence'] ?? 1));

        return $this->nextSequence($prefix, $minStart);
    }

    public function nextAdjustmentNumber(): string
    {
        return $this->nextSequence('CN');
    }

    public function nextExpenseNumber(): string
    {
        return $this->nextSequence('EXP');
    }

    /**
     * Race-safe sequential number generator using atomic insertOrIgnore + row lock.
     */
    protected function nextSequence(string $prefix, int $minStart = 1): string
    {
        $periodKey = now()->format('Ym');

        return DB::transaction(function () use ($prefix, $periodKey, $minStart) {
            $now = now();

            DB::table('invoice_sequences')->insertOrIgnore([
                'prefix' => $prefix,
                'period_key' => $periodKey,
                'last_number' => $minStart - 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $seq = InvoiceSequence::query()
                ->where('prefix', $prefix)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->firstOrFail();

            $next = max($seq->last_number + 1, $minStart);
            $seq->update(['last_number' => $next]);

            return sprintf('%s-%s-%04d', $prefix, $periodKey, $next);
        });
    }
}
