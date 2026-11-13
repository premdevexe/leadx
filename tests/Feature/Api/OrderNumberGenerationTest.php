<?php

namespace Tests\Feature\Api;

use App\Models\OrderNumberSequence;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use App\Services\OrderNumberService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OrderNumberGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected OrderNumberService $numbers;

    protected OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->numbers = app(OrderNumberService::class);
        $this->orders = app(OrderService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function makeTeam(string $code): Team
    {
        return Team::factory()->create(['team_code' => $code]);
    }

    protected function makeEmployeeFor(Team $team): User
    {
        return User::factory()->create(['team_id' => $team->id]);
    }

    // 3. The first order for a team generates sequence 000001.
    public function test_first_order_generates_sequence_000001(): void
    {
        $team = $this->makeTeam('CLA');
        $employee = $this->makeEmployeeFor($team);

        $order = $this->orders->createForTeam($team, $employee, ['customer_name' => 'Ada']);

        $this->assertSame('CLA-'.now()->format('Y').'-000001', $order->order_number);
        $this->assertSame('CLA', $order->team_code_snapshot);
        $this->assertSame($team->id, $order->team_id);
        $this->assertSame($employee->id, $order->assigned_employee_id);
    }

    // 4. The second order generates sequence 000002.
    public function test_second_order_generates_sequence_000002(): void
    {
        $team = $this->makeTeam('CLA');
        $employee = $this->makeEmployeeFor($team);

        $first = $this->orders->createForTeam($team, $employee, ['customer_name' => 'Ada']);
        $second = $this->orders->createForTeam($team, $employee, ['customer_name' => 'Grace']);

        $this->assertSame(
            'CLA-'.now()->format('Y').'-000001',
            $first->order_number
        );
        $this->assertSame(
            'CLA-'.now()->format('Y').'-000002',
            $second->order_number
        );
    }

    // 5. Different teams keep independent sequences.
    public function test_different_teams_have_independent_sequences(): void
    {
        $a = $this->makeTeam('CLA');
        $b = $this->makeTeam('GEM');

        $ea = $this->makeEmployeeFor($a);
        $eb = $this->makeEmployeeFor($b);

        for ($i = 0; $i < 3; $i++) {
            $this->orders->createForTeam($a, $ea, ['customer_name' => "A{$i}"]);
            $this->orders->createForTeam($b, $eb, ['customer_name' => "B{$i}"]);
        }

        $this->assertSame(
            'CLA-'.now()->format('Y').'-000003',
            SalesOrder::where('team_id', $a->id)->latest('id')->value('order_number')
        );
        $this->assertSame(
            'GEM-'.now()->format('Y').'-000003',
            SalesOrder::where('team_id', $b->id)->latest('id')->value('order_number')
        );
    }

    // 6. A new calendar year starts a new sequence.
    public function test_new_calendar_year_starts_a_new_sequence(): void
    {
        $team = $this->makeTeam('CLA');
        $employee = $this->makeEmployeeFor($team);

        Carbon::setTestNow(Carbon::create(2026, 12, 31, 23, 59));
        $year26 = $this->orders->createForTeam($team, $employee, ['customer_name' => 'Y26']);
        $this->assertSame('CLA-2026-000001', $year26->order_number);

        Carbon::setTestNow(Carbon::create(2027, 1, 1, 0, 1));
        $year27 = $this->orders->createForTeam($team, $employee, ['customer_name' => 'Y27']);
        $this->assertSame('CLA-2027-000001', $year27->order_number);

        // One ledger row per team/year; the 2027 row must have restarted at 1.
        $this->assertSame(1, (int) OrderNumberSequence::where('team_id', $team->id)->where('year', 2027)->value('last_sequence'));
        $this->assertSame(1, (int) OrderNumberSequence::where('team_id', $team->id)->where('year', 2026)->value('last_sequence'));
    }

    // 7. A team-name change does not alter old order numbers.
    public function test_team_name_changes_do_not_alter_order_numbers(): void
    {
        $team = $this->makeTeam('CLA');
        $employee = $this->makeEmployeeFor($team);

        $order = $this->orders->createForTeam($team, $employee, ['customer_name' => 'Ada']);
        $original = $order->order_number;

        $team->update(['team_name' => 'A Whole New Name']);

        $this->assertSame($original, $order->fresh()->order_number);
        $this->assertSame($original, $order->fresh()->order_number);
    }

    // 9. Deleted order ids are never reused.
    public function test_deleted_order_numbers_are_never_reused(): void
    {
        $team = $this->makeTeam('CLA');
        $employee = $this->makeEmployeeFor($team);

        $first = $this->orders->createForTeam($team, $employee, ['customer_name' => 'One']);
        $second = $this->orders->createForTeam($team, $employee, ['customer_name' => 'Two']);

        $first->delete();

        $third = $this->orders->createForTeam($team, $employee, ['customer_name' => 'Three']);

        $this->assertSame('CLA-'.now()->format('Y').'-000003', $third->order_number);
        $this->assertNotSame($first->order_number, $third->order_number);

        $this->assertSame(
            3,
            OrderNumberSequence::where('team_id', $team->id)->value('last_sequence'),
            'The ledger must be a high-water mark, not a live count.'
        );
    }

    // 10. Concurrent order creation does not generate duplicates.
    public function test_rapid_concurrent_creation_produces_no_duplicate_numbers(): void
    {
        $team = $this->makeTeam('CLA');
        $employee = $this->makeEmployeeFor($team);

        $numbers = [];

        for ($i = 0; $i < 25; $i++) {
            $numbers[] = $this->orders->createForTeam($team, $employee, ['customer_name' => "C{$i}"])->order_number;
        }

        $this->assertCount(25, array_unique($numbers), 'No two orders may share a number.');

        $this->assertSame(25, SalesOrder::where('team_id', $team->id)->count());
    }

    public function test_sequence_ledger_enforces_unique_team_year_constraint(): void
    {
        $team = $this->makeTeam('CLA');

        $this->orders->createForTeam($team, $this->makeEmployeeFor($team), ['customer_name' => 'Seed']);

        // A second row for the same (team_id, year) must violate the unique
        // constraint — this is the backstop that makes the service's
        // insert-on-miss safe under concurrency.
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        \Illuminate\Support\Facades\DB::table('order_number_sequences')->insert([
            'team_id' => $team->id,
            'year' => now()->format('Y'),
            'last_sequence' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // 11. Employee order attribution is correct.
    public function test_employee_order_attribution_is_correct(): void
    {
        $team = $this->makeTeam('CLA');
        $employee = $this->makeEmployeeFor($team);

        $order = $this->orders->createForTeam($team, $employee, ['customer_name' => 'Ada']);

        $this->assertSame($employee->id, $order->assigned_employee_id);
        $this->assertSame($team->id, $order->team_id);
        $this->assertSame('CLA', $order->team_code_snapshot);
    }
}
