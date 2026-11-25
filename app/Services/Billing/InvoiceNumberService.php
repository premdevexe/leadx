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
        $periodKey = now()->format('Ym');
        $minStart = max(1, (int) ($billing['next_sequence'] ?? 1));

        return DB::transaction(function () use ($prefix, $periodKey, $minStart) {
            $seq = InvoiceSequence::query()
                ->where('prefix', $prefix)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                $seq = InvoiceSequence::create([
                    'prefix' => $prefix,
                    'period_key' => $periodKey,
                    'last_number' => $minStart - 1,
                ]);
            }

            $next = max($seq->last_number + 1, $minStart);
            $seq->update(['last_number' => $next]);

            return sprintf('%s-%s-%04d', $prefix, $periodKey, $next);
        });
    }

    public function nextAdjustmentNumber(): string
    {
        $periodKey = now()->format('Ym');

        return DB::transaction(function () use ($periodKey) {
            $seq = InvoiceSequence::query()
                ->where('prefix', 'CN')
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                $seq = InvoiceSequence::create([
                    'prefix' => 'CN',
                    'period_key' => $periodKey,
                    'last_number' => 0,
                ]);
            }

            $next = $seq->last_number + 1;
            $seq->update(['last_number' => $next]);

            return sprintf('CN-%s-%04d', $periodKey, $next);
        });
    }

    public function nextExpenseNumber(): string
    {
        $periodKey = now()->format('Ym');

        return DB::transaction(function () use ($periodKey) {
            $seq = InvoiceSequence::query()
                ->where('prefix', 'EXP')
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                $seq = InvoiceSequence::create([
                    'prefix' => 'EXP',
                    'period_key' => $periodKey,
                    'last_number' => 0,
                ]);
            }

            $next = $seq->last_number + 1;
            $seq->update(['last_number' => $next]);

            return sprintf('EXP-%s-%04d', $periodKey, $next);
        });
    }
}
