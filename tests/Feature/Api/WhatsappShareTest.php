<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappShareLog;
use App\Services\WhatsappShareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Concerns\ProvidesApiTokens;
use Tests\TestCase;

class WhatsappShareTest extends TestCase
{
    use RefreshDatabase;
    use ProvidesApiTokens;

    protected function makeActiveOrder(Team $team, ?string $phone = '+15551234567'): SalesOrder
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'customer_phone' => $phone,
            'order_status' => 'active',
            'payment_status' => 'paid',
            'closing_value' => 149.99,
        ]);

        Subscription::factory()->create([
            'order_id' => $order->id,
            'team_id' => $team->id,
            'status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);

        return $order;
    }

    // 14. WhatsApp share is available for eligible active orders.
    public function test_share_is_available_for_eligible_active_orders(): void
    {
        $team = Team::factory()->create(['team_code' => 'GEM']);
        $order = $this->makeActiveOrder($team);

        $response = $this->getJson("/api/v1/orders/{$order->id}/whatsapp-share", $this->actingAsToken($this->teamToken($team)))
            ->assertOk()
            ->assertJsonPath('eligible', true)
            ->assertJsonPath('phone', '+15551234567');

        $url = $response->json('url');
        $this->assertNotNull($url);
        $this->assertMatchesRegularExpression('#^https://wa\.me/15551234567\?text=.#', $url);
        $this->assertStringContainsString('Order ID: '.$order->order_number, urldecode($url));
    }

    // 15. Share is rejected for ineligible orders.
    public function test_share_is_rejected_for_ineligible_orders(): void
    {
        $team = Team::factory()->create(['team_code' => 'GEM']);

        // Pending order, no subscription.
        $pending = SalesOrder::factory()->create(['team_id' => $team->id]);
        $this->getJson("/api/v1/orders/{$pending->id}/whatsapp-share", $this->actingAsToken($this->teamToken($team)))
            ->assertOk()
            ->assertJsonPath('eligible', false);

        // Active order but no active subscription.
        $noSub = SalesOrder::factory()->create(['team_id' => $team->id, 'order_status' => 'active']);
        $this->getJson("/api/v1/orders/{$noSub->id}/whatsapp-share", $this->actingAsToken($this->teamToken($team)))
            ->assertOk()
            ->assertJsonPath('eligible', false);

        // Active + active subscription but no phone.
        $noPhone = $this->makeActiveOrder($team, phone: null);
        $this->getJson("/api/v1/orders/{$noPhone->id}/whatsapp-share", $this->actingAsToken($this->teamToken($team)))
            ->assertOk()
            ->assertJsonPath('eligible', false);
    }

    // 16. The message contains the correct order information.
    public function test_message_contains_correct_order_information(): void
    {
        $team = Team::factory()->create(['team_code' => 'GEM', 'team_name' => 'Gemini Sales']);
        $order = $this->makeActiveOrder($team);
        $order->forceFill([
            'customer_name' => 'Nadia K.',
            'product_name' => 'Pro Plan',
        ])->save();

        $service = app(WhatsappShareService::class);
        $payload = $service->prepare($order->refresh());

        $this->assertTrue($payload['eligible']);
        $message = $payload['message'];

        $this->assertStringContainsString($order->order_number, $message);
        $this->assertStringContainsString('Nadia K.', $message);
        $this->assertStringContainsString('Gemini Sales', $message);
        $this->assertStringContainsString('Pro Plan', $message);
        $this->assertStringContainsString('149.99', $message);
        $this->assertStringContainsString('Paid', $message);
        $this->assertStringContainsString('Active', $message);
        $this->assertStringContainsString($order->subscription->expires_at->toDateString(), $message);
    }

    // 17. Sensitive credentials never appear in share messages.
    public function test_credentials_are_never_embedded_in_share_messages(): void
    {
        $team = Team::factory()->create(['team_code' => 'GEM']);
        $order = $this->makeActiveOrder($team);

        $service = app(WhatsappShareService::class);

        // Even if an employee tries to sneak a secret into the edited message,
        // it is scrubbed before being persisted.
        $log = $service->logShare($order, Admin::factory()->create(), "hi api_key=abcdef123456 password=hunter2 Bearer abcdef123");

        // Secrets are gone; the scrub replaced them with a redaction marker.
        $this->assertStringNotContainsString('abcdef123456', $log->message);
        $this->assertStringNotContainsString('hunter2', $log->message);
        $this->assertStringContainsString('[redacted]', $log->message);
        $this->assertSame('recorded', $log->status);
    }

    public function test_share_activity_is_recorded_and_never_claims_delivery(): void
    {
        $team = Team::factory()->create(['team_code' => 'GEM']);
        $order = $this->makeActiveOrder($team);
        $employee = User::factory()->create(['team_id' => $team->id]);

        $this->postJson("/api/v1/orders/{$order->id}/whatsapp-share-log", [], $this->actingAsToken($this->employeeToken($employee)))
            ->assertCreated()
            ->assertJsonPath('status', 'recorded');

        $this->assertDatabaseHas('whatsapp_share_logs', [
            'order_id' => $order->id,
            'normalized_phone' => '+15551234567',
            'status' => 'recorded',
        ]);

        $this->assertSame(0, WhatsappShareLog::where('status', 'delivered')->count());
    }

    public function test_share_log_is_rejected_for_ineligible_orders(): void
    {
        $team = Team::factory()->create(['team_code' => 'GEM']);
        $pending = SalesOrder::factory()->create(['team_id' => $team->id]);

        $this->postJson("/api/v1/orders/{$pending->id}/whatsapp-share-log", [], $this->actingAsToken($this->teamToken($team)))
            ->assertStatus(422);
    }

    public function test_phone_numbers_are_normalized_to_international_format(): void
    {
        $service = app(WhatsappShareService::class);

        $this->assertSame('+15551234567', $service->normalizePhone('+15551234567'));
        $this->assertSame('+15551234567', $service->normalizePhone('555-123-4567'));
        $this->assertSame('+15551234567', $service->normalizePhone('05551234567'));
        $this->assertNull($service->normalizePhone(''));
        $this->assertNull($service->normalizePhone('abc'));
    }

    public function test_only_authorized_roles_can_record_a_share(): void
    {
        $team = Team::factory()->create(['team_code' => 'GEM']);
        $order = $this->makeActiveOrder($team);
        $stranger = Team::factory()->create(['team_code' => 'XYZ']);

        // Another team is not allowed to log a share for this order.
        $this->postJson("/api/v1/orders/{$order->id}/whatsapp-share-log", [], $this->actingAsToken($this->teamToken($stranger)))
            ->assertStatus(422);
    }
}
