<?php

namespace Tests\Feature\Api;

use App\Models\SalesOrder;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_numbers_attributable_orders_and_flags_unattributable_ones(): void
    {
        $team = Team::factory()->create(['team_code' => 'CLA']);

        $attributable = SalesOrder::create([
            'order_number' => null,
            'team_id' => $team->id,
            'customer_name' => 'Legacy One',
        ]);

        // Unknown team: must be flagged, not guessed.
        $orphan = SalesOrder::create([
            'order_number' => null,
            'team_id' => 99999,
            'customer_name' => 'Legacy Orphan',
        ]);

        $this->artisan('leadx:backfill-legacy-orders')->assertExitCode(0);

        $this->assertDatabaseHas('sales_orders', [
            'id' => $attributable->id,
            'order_number' => 'CLA-'.now()->format('Y').'-000001',
            'legacy_flag' => 'backfilled',
        ]);

        $this->assertDatabaseHas('sales_orders', [
            'id' => $orphan->id,
            'legacy_flag' => 'unattributed',
        ]);
        $this->assertNull($orphan->fresh()->order_number);
    }

    public function test_backfill_is_idempotent_and_does_not_touch_numbered_orders(): void
    {
        $team = Team::factory()->create(['team_code' => 'GEM']);

        $numbered = SalesOrder::create([
            'order_number' => 'GEM-2025-000042',
            'team_id' => $team->id,
            'customer_name' => 'Already Numbered',
        ]);

        $unnumbered = SalesOrder::create([
            'order_number' => null,
            'team_id' => $team->id,
            'customer_name' => 'Needs A Number',
        ]);

        $this->artisan('leadx:backfill-legacy-orders')->assertExitCode(0);
        $this->artisan('leadx:backfill-legacy-orders')->assertExitCode(0);

        $this->assertDatabaseHas('sales_orders', ['id' => $numbered->id, 'order_number' => 'GEM-2025-000042']);
        $this->assertDatabaseHas('sales_orders', [
            'id' => $unnumbered->id,
            'legacy_flag' => 'backfilled',
        ]);

        $this->assertSame(1, SalesOrder::where('legacy_flag', 'backfilled')->count());
    }

    public function test_backfill_flags_teams_with_invalid_codes(): void
    {
        $team = Team::factory()->create(['team_code' => 'invalid_code!']);

        $order = SalesOrder::create([
            'order_number' => null,
            'team_id' => $team->id,
            'customer_name' => 'Bad Team Code',
        ]);

        $this->artisan('leadx:backfill-legacy-orders')->assertExitCode(0);

        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'legacy_flag' => 'team_code_invalid',
        ]);
        $this->assertNull($order->fresh()->order_number);
    }
}
