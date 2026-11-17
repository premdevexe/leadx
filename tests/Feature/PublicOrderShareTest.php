<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\PublicOrderShare;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Services\OrderCredentialService;
use App\Services\PublicOrderShareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicOrderShareTest extends TestCase
{
    use RefreshDatabase;

    private function shareEligibleOrder(): array
    {
        $team = Team::factory()->create();
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'order_status' => 'active',
            'payment_status' => 'paid',
            'customer_phone' => '+15551234567',
        ]);
        Subscription::factory()->create([
            'order_id' => $order->id,
            'team_id' => $team->id,
            'status' => 'active',
            'started_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        return [$team, $order];
    }

    // ── Public page: access control ────────────────────────────────

    public function test_live_token_renders_the_order_share_page(): void
    {
        [, $order] = $this->shareEligibleOrder();
        $share = PublicOrderShare::createForOrder($order, null);

        $response = $this->get(route('public.orders.show', $share->token));

        $response->assertOk();
        $response->assertSee($order->order_number);
        $response->assertSee($order->customer_name);
        $response->assertSee('Shared view');
    }

    public function test_revoked_token_is_gone(): void
    {
        [, $order] = $this->shareEligibleOrder();
        $share = PublicOrderShare::createForOrder($order, null);
        $share->revoke(null);

        $this->get(route('public.orders.show', $share->token))->assertNotFound();
    }

    public function test_expired_token_is_gone(): void
    {
        [, $order] = $this->shareEligibleOrder();
        $share = PublicOrderShare::factory()->create([
            'order_id' => $order->id,
            'expires_at' => now()->subHour(),
        ]);

        $this->get(route('public.orders.show', $share->token))->assertNotFound();
    }

    public function test_unknown_token_is_gone(): void
    {
        $this->get(route('public.orders.show', str_repeat('x', PublicOrderShare::TOKEN_LENGTH)))
            ->assertNotFound();
    }

    public function test_wrong_length_token_is_rejected_without_query(): void
    {
        [, $order] = $this->shareEligibleOrder();
        $share = PublicOrderShare::createForOrder($order, null);

        $response = $this->get(route('public.orders.show', 'short'));

        $response->assertNotFound();
        $this->assertDatabaseCount('public_order_shares', 1);
    }

    // ── API key on the public page ────────────────────────────────

    public function test_opt_in_key_is_masked_until_explicit_reveal(): void
    {
        [, $order] = $this->shareEligibleOrder();
        $admin = Admin::factory()->create();
        app(OrderCredentialService::class)->store($order, 'sk_live_public_page_test', $admin);

        $share = PublicOrderShare::createForOrder($order, $admin, true);

        // First view: the masked indicator shows (the "sk_" head of the
        // mask) plus the reveal affordance, but never the plaintext key.
        $first = $this->get(route('public.orders.show', $share->token));
        $first->assertOk();
        $first->assertSee('sk_', false);
        $first->assertSee('Reveal my API key', false);
        $first->assertDontSee('sk_live_public_page_test');

        // Explicit reveal: shown exactly once.
        $this->post(route('public.orders.api-key.reveal', $share->token));
        $second = $this->get(route('public.orders.show', $share->token));
        $second->assertSee('sk_live_public_page_test');

        // And then it is cleared again.
        $third = $this->get(route('public.orders.show', $share->token));
        $third->assertDontSee('sk_live_public_page_test');
    }

    public function test_key_is_never_surfaced_when_order_is_not_share_eligible(): void
    {
        $team = Team::factory()->create();
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'order_status' => 'cancelled', // not share-eligible
        ]);
        $admin = Admin::factory()->create();
        app(OrderCredentialService::class)->store($order, 'sk_live_no_leak', $admin);

        $share = PublicOrderShare::createForOrder($order, $admin, true);

        $page = $this->get(route('public.orders.show', $share->token));
        $page->assertOk();
        $page->assertDontSee('sk_live_no_leak');
        $page->assertDontSee('Your API Key', false);

        // Even the reveal endpoint stays closed.
        $this->post(route('public.orders.api-key.reveal', $share->token));
        $again = $this->get(route('public.orders.show', $share->token));
        $again->assertDontSee('sk_live_no_leak');
    }

    public function test_status_only_share_never_shows_a_key_block(): void
    {
        [, $order] = $this->shareEligibleOrder();
        $admin = Admin::factory()->create();
        app(OrderCredentialService::class)->store($order, 'sk_live_status_only', $admin);

        $share = PublicOrderShare::createForOrder($order, $admin, false);
        $page = $this->get(route('public.orders.show', $share->token));

        $page->assertOk();
        $page->assertDontSee('Your API Key', false);
        $page->assertDontSee('sk_live_status_only');
    }

    // ── Link lifecycle management ──────────────────────────────────

    public function test_new_link_retires_the_previous_one(): void
    {
        [, $order] = $this->shareEligibleOrder();
        $admin = Admin::factory()->create();
        $service = app(PublicOrderShareService::class);

        // The service mints one canonical link per order, retiring any
        // previous live link in the same transaction.
        $first = $service->createForOrder($order, $admin);
        $second = $service->createForOrder($order, $admin);

        $this->assertNotEquals($first->token, $second->token);
        $this->assertTrue($second->isLive());
        $this->assertFalse($first->fresh()->isLive());

        // The old URL no longer works; the new one does.
        $this->get(route('public.orders.show', $first->token))->assertNotFound();
        $this->get(route('public.orders.show', $second->token))->assertOk();
    }

    public function test_admin_can_generate_and_revoke_a_public_link(): void
    {
        [, $order] = $this->shareEligibleOrder();
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.public-share.store', $order), []);

        $response->assertRedirect(route('admin.orders.show', $order));
        $this->assertDatabaseHas('public_order_shares', [
            'order_id' => $order->id,
            'created_by_type' => Admin::class,
        ]);

        $revoke = $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.public-share.revoke', $order));
        $revoke->assertRedirect();

        $share = PublicOrderShare::where('order_id', $order->id)->first();
        $this->assertNotNull($share->revoked_at);
        $this->assertFalse($share->isLive());
    }

    public function test_admin_generation_gates_key_opt_in_on_an_actual_key(): void
    {
        [, $order] = $this->shareEligibleOrder();
        $admin = Admin::factory()->create();

        // No key stored: opt-in must be silently dropped.
        $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.public-share.store', $order), ['include_api_key' => 1]);

        $share = PublicOrderShare::where('order_id', $order->id)->first();
        $this->assertFalse($share->include_api_key);
    }

    public function test_employee_can_only_link_their_own_orders(): void
    {
        [, $order] = $this->shareEligibleOrder();
        $owner = User::factory()->create(['team_id' => $order->team_id]);
        $order->update(['assigned_employee_id' => $owner->id]);
        $other = User::factory()->create(['team_id' => $order->team_id]);

        $denied = $this->actingAs($other, 'employee')
            ->post(route('employee.orders.public-share.store', $order), []);
        $denied->assertForbidden();

        $allowed = $this->actingAs($owner, 'employee')
            ->post(route('employee.orders.public-share.store', $order), []);
        $allowed->assertRedirect();

        $this->assertDatabaseHas('public_order_shares', [
            'order_id' => $order->id,
            'created_by_type' => User::class,
        ]);
    }

    public function test_team_can_link_their_own_orders_but_never_with_a_key(): void
    {
        $team = Team::factory()->create();
        $order = SalesOrder::factory()->create(['team_id' => $team->id, 'order_status' => 'active']);
        $admin = Admin::factory()->create();
        app(OrderCredentialService::class)->store($order, 'sk_live_team_token', $admin);

        $response = $this->actingAsTeam($team)
            ->post(route('team.orders.public-share.store', $order), ['include_api_key' => 1]);

        $response->assertRedirect();
        $share = PublicOrderShare::where('order_id', $order->id)->first();
        // Team tokens may not fold the customer key into a link, even if the
        // client submits the flag.
        $this->assertFalse($share->include_api_key);
        $this->assertSame(Team::class, $share->created_by_type);
    }

    private function actingAsTeam(Team $team): self
    {
        $this->withSession(['team_id' => $team->id]);

        return $this;
    }

    // ── Service unit checks ────────────────────────────────────────

    public function test_service_resolve_requires_exactly_one_token_of_the_fixed_length(): void
    {
        $service = app(PublicOrderShareService::class);

        $this->assertNull($service->resolve(''));
        $this->assertNull($service->resolve(str_repeat('a', PublicOrderShare::TOKEN_LENGTH - 1)));
    }
}
