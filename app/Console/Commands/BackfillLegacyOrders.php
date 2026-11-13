<?php

namespace App\Console\Commands;

use App\Models\SalesOrder;
use App\Models\Team;
use App\Services\OrderNumberService;
use Illuminate\Console\Command;

/**
 * Safe legacy-order backfill.
 *
 * sales_orders rows predating the order-number system may be missing a valid
 * team_code_snapshot / order_number. This command:
 *
 *  - assigns order numbers via the team's live sequence ledger ONLY when it
 *    can confidently attribute the order to a team (legacy orders carry a
 *    team_id);
 *  - never touches rows that are already numbered;
 *  - flags rows it cannot safely attribute (unknown/missing team) with a
 *    legacy_flag instead of guessing;
 *  - is idempotent: re-running it only acts on still-unnumbered rows.
 *
 * Run with:  php artisan leadx:backfill-legacy-orders
 */
class BackfillLegacyOrders extends Command
{
    protected $signature = 'leadx:backfill-legacy-orders {--dry-run : Report what would be changed without writing}';

    protected $description = 'Backfill missing order numbers on legacy sales orders and flag the unattributable ones';

    public function handle(OrderNumberService $numbers): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $numbered = 0;
        $flagged = 0;

        $unnumbered = SalesOrder::query()
            ->whereNull('order_number')
            ->orWhere('order_number', '')
            ->orderBy('id')
            ->get();

        foreach ($unnumbered as $order) {
            $team = $order->team_id !== null ? Team::find($order->team_id) : null;

            if ($team === null) {
                if (! $dryRun) {
                    $order->legacy_flag = 'unattributed';
                    $order->saveQuietly();
                }
                $flagged++;
                $this->line("  flagged order #{$order->id} as unattributed (team_id {$order->team_id}).");

                continue;
            }

            if ($team->team_code === null || ! $numbers->teamCodeIsValid($team->team_code)) {
                if (! $dryRun) {
                    $order->legacy_flag = 'team_code_invalid';
                    $order->saveQuietly();
                }
                $flagged++;
                $this->warn("  flagged order #{$order->id}: team {$team->id} code \"{$team->team_code}\" is invalid.");

                continue;
            }

            $orderNumber = $numbers->next($team);

            if (! $dryRun) {
                $order->order_number = $orderNumber;
                $order->team_code_snapshot = $team->team_code;
                $order->legacy_flag = 'backfilled';
                $order->saveQuietly();
            }

            $numbered++;
            $verb = $dryRun ? 'would number' : 'numbered';
            $this->info("  {$verb} order #{$order->id} => {$orderNumber}");
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . "Done. Numbered: {$numbered}, flagged: {$flagged}.");

        return self::SUCCESS;
    }
}
