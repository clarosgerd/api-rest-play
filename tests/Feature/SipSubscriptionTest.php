<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionActivatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SipSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['subscription_tier' => 'free']);

        config([
            'sip.callback_basic_user' => 'callback_user',
            'sip.callback_basic_password' => 'callback_pass',
        ]);
    }

    private function fakeSip(string $estadoActual = 'PENDIENTE'): void
    {
        Http::fake([
            '*autenticacion*' => Http::response(['token' => 'fake-jwt-token'], 200),
            '*generaQr*' => Http::response([
                'objeto' => [
                    'imagenQr' => 'base64-fake-qr',
                    'idQr' => 'QR-123',
                    'bancoDestino' => 'Banco Fake',
                    'cuentaDestino' => '1234567890',
                    'fechaVencimiento' => now()->addDay()->format('d/m/Y'),
                ],
            ], 200),
            '*estadoTransaccion*' => Http::response([
                'objeto' => ['estadoActual' => $estadoActual],
            ], 200),
        ]);
    }

    public function test_checkout_generates_qr_and_creates_pending_subscription(): void
    {
        $this->fakeSip();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/user/subscription/checkout', ['plan' => 'pro', 'billing' => 'monthly']);

        $response->assertStatus(201)
            ->assertJson([
                'qr_image_base64' => 'base64-fake-qr',
                'monto' => 35.0,
                'moneda' => 'BOB',
                'banco_destino' => 'Banco Fake',
            ]);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $this->user->user_id,
            'plan' => 'pro',
            'status' => 'pending_payment',
            'payment_provider' => 'sip',
        ]);
    }

    public function test_checkout_cancels_previous_pending_attempt(): void
    {
        $this->fakeSip();

        $this->actingAs($this->user)
            ->postJson('/api/v1/user/subscription/checkout', ['plan' => 'pro', 'billing' => 'monthly']);
        $first = Subscription::where('user_id', $this->user->user_id)->first();

        $this->actingAs($this->user)
            ->postJson('/api/v1/user/subscription/checkout', ['plan' => 'premium', 'billing' => 'monthly']);

        $this->assertEquals('cancelled', $first->refresh()->status);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $this->user->user_id,
            'plan' => 'premium',
            'status' => 'pending_payment',
        ]);
    }

    public function test_sip_callback_without_auth_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/subscription/sip/callback', ['alias' => 'SUB-DOESNOTEXIST']);

        $response->assertStatus(401)
            ->assertJson(['codigo' => '9999']);
    }

    public function test_sip_callback_activates_subscription(): void
    {
        Notification::fake();
        $this->fakeSip();
        $this->actingAs($this->user)
            ->postJson('/api/v1/user/subscription/checkout', ['plan' => 'pro', 'billing' => 'monthly']);
        $subscription = Subscription::where('user_id', $this->user->user_id)->first();

        $response = $this->withHeader('Authorization', 'Basic '.base64_encode('callback_user:callback_pass'))
            ->postJson('/api/v1/subscription/sip/callback', [
                'alias' => $subscription->sip_referencia,
                'idQr' => 'QR-123',
                'monto' => 35,
                'moneda' => 'BOB',
            ]);

        $response->assertStatus(200)
            ->assertJson(['codigo' => '0000']);

        $subscription->refresh();
        $this->assertEquals('active', $subscription->status);
        $this->assertEquals('pro', $this->user->refresh()->subscription_tier);
        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->subscription_id,
            'currency' => 'BOB',
            'amount' => 35,
            'status' => 'succeeded',
        ]);

        Notification::assertSentTo($this->user, SubscriptionActivatedNotification::class);
    }

    public function test_sip_callback_is_idempotent(): void
    {
        $this->fakeSip();
        $this->actingAs($this->user)
            ->postJson('/api/v1/user/subscription/checkout', ['plan' => 'pro', 'billing' => 'monthly']);
        $subscription = Subscription::where('user_id', $this->user->user_id)->first();

        $authHeader = ['Authorization' => 'Basic '.base64_encode('callback_user:callback_pass')];

        $this->withHeaders($authHeader)->postJson('/api/v1/subscription/sip/callback', ['alias' => $subscription->sip_referencia]);
        $this->withHeaders($authHeader)->postJson('/api/v1/subscription/sip/callback', ['alias' => $subscription->sip_referencia])
            ->assertStatus(200)
            ->assertJson(['codigo' => '0000']);

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_sip_callback_with_unknown_alias_returns_9999_but_200(): void
    {
        $response = $this->withHeader('Authorization', 'Basic '.base64_encode('callback_user:callback_pass'))
            ->postJson('/api/v1/subscription/sip/callback', ['alias' => 'SUB-DOESNOTEXIST']);

        $response->assertStatus(200)
            ->assertJson(['codigo' => '9999']);
    }

    public function test_payment_status_reflects_local_pending_state(): void
    {
        $this->fakeSip(estadoActual: 'PENDIENTE');
        $this->actingAs($this->user)
            ->postJson('/api/v1/user/subscription/checkout', ['plan' => 'pro', 'billing' => 'monthly']);
        $subscription = Subscription::where('user_id', $this->user->user_id)->first();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/user/subscription/payment-status/{$subscription->sip_referencia}");

        $response->assertOk()->assertJson(['status' => 'pending_payment']);
    }

    public function test_payment_status_confirms_when_sip_reports_paid(): void
    {
        $this->fakeSip(estadoActual: 'PAGADO');
        $this->actingAs($this->user)
            ->postJson('/api/v1/user/subscription/checkout', ['plan' => 'pro', 'billing' => 'monthly']);
        $subscription = Subscription::where('user_id', $this->user->user_id)->first();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/user/subscription/payment-status/{$subscription->sip_referencia}");

        $response->assertOk()->assertJson(['status' => 'active']);
        $this->assertEquals('pro', $this->user->refresh()->subscription_tier);
    }

    public function test_payment_status_for_another_users_subscription_is_not_found(): void
    {
        $this->fakeSip();
        $this->actingAs($this->user)
            ->postJson('/api/v1/user/subscription/checkout', ['plan' => 'pro', 'billing' => 'monthly']);
        $subscription = Subscription::where('user_id', $this->user->user_id)->first();

        $other = User::factory()->create();

        $this->actingAs($other)
            ->getJson("/api/v1/user/subscription/payment-status/{$subscription->sip_referencia}")
            ->assertStatus(404);
    }
}
