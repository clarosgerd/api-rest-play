<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'subscription_tier' => 'free',
        ]);
    }

    public function test_user_can_view_subscription_status(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/user/subscription');

        $response->assertOk()
            ->assertJson([
                'tier' => 'free',
                'is_active' => true,
            ]);
    }

    public function test_user_can_view_plans(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/user/subscription/plans');

        $response->assertOk()
            ->assertJsonStructure([
                'plans' => ['pro', 'premium'],
                'trial_days',
                'currency',
            ])
            ->assertJson([
                'trial_days' => 7,
                'currency' => 'USD',
            ]);
    }

    public function test_pro_plan_has_correct_limits(): void
    {
        $this->user->update(['subscription_tier' => 'pro', 'subscription_expires_at' => now()->addMonth()]);

        $limits = $this->user->getTierLimits();

        $this->assertEquals(5, $limits['tournaments']);
        $this->assertEquals(50, $limits['members_per_group']);
        $this->assertEquals(20, $limits['predictions_per_day']);
        $this->assertContains('conmebol', $limits['external_leagues']);
    }

    public function test_premium_plan_has_unlimited(): void
    {
        $this->user->update(['subscription_tier' => 'premium', 'subscription_expires_at' => now()->addMonth()]);

        $limits = $this->user->getTierLimits();

        $this->assertEquals(-1, $limits['tournaments']);
        $this->assertEquals(-1, $limits['members_per_group']);
        $this->assertEquals(-1, $limits['predictions_per_day']);
        $this->assertContains('all', $limits['external_leagues']);
    }

    public function test_free_plan_has_basic_limits(): void
    {
        $limits = $this->user->getTierLimits();

        $this->assertEquals(2, $limits['tournaments']);
        $this->assertEquals(10, $limits['members_per_group']);
        $this->assertEquals(5, $limits['predictions_per_day']);
        $this->assertEmpty($limits['external_leagues']);
    }

    public function test_is_pro_returns_true_when_active(): void
    {
        $this->user->update([
            'subscription_tier' => 'pro',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $this->assertTrue($this->user->isPro());
        $this->assertFalse($this->user->isPremium());
    }

    public function test_is_premium_returns_true_when_active(): void
    {
        $this->user->update([
            'subscription_tier' => 'premium',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $this->assertTrue($this->user->isPremium());
        $this->assertFalse($this->user->isPro());
    }

    public function test_subscription_not_active_when_expired(): void
    {
        $this->user->update([
            'subscription_tier' => 'pro',
            'subscription_expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($this->user->isSubscriptionActive());
        $this->assertFalse($this->user->isPro());
    }

    public function test_free_tier_always_active(): void
    {
        $this->assertTrue($this->user->isSubscriptionActive());
    }

    public function test_subscription_model_plans_structure(): void
    {
        $plans = Subscription::getPlans();

        $this->assertArrayHasKey('pro', $plans);
        $this->assertArrayHasKey('premium', $plans);
        $this->assertArrayHasKey('monthly', $plans['pro']);
        $this->assertArrayHasKey('annual', $plans['pro']);
        $this->assertEquals(4.99, $plans['pro']['monthly']['price']);
        $this->assertEquals(39.99, $plans['pro']['annual']['price']);
        $this->assertEquals(9.99, $plans['premium']['monthly']['price']);
        $this->assertEquals(79.99, $plans['premium']['annual']['price']);
    }

    public function test_subscription_model_helper_methods(): void
    {
        $subscription = Subscription::create([
            'user_id' => $this->user->user_id,
            'plan' => 'pro',
            'status' => 'active',
            'monthly_price' => 4.99,
            'annual_price' => 39.99,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->assertTrue($subscription->isActive());
        $this->assertTrue($subscription->isPro());
        $this->assertFalse($subscription->isPremium());
        $this->assertFalse($subscription->isTrial());
    }

    public function test_subscription_trial_status(): void
    {
        $subscription = Subscription::create([
            'user_id' => $this->user->user_id,
            'plan' => 'pro',
            'status' => 'trialing',
            'monthly_price' => 4.99,
            'annual_price' => 39.99,
            'current_period_start' => now(),
            'current_period_end' => now()->addWeek(),
            'trial_ends_at' => now()->addWeek(),
        ]);

        $this->assertTrue($subscription->isTrial());
        $this->assertTrue($subscription->isActive());
    }

    public function test_unauthenticated_user_cannot_access_subscription(): void
    {
        $response = $this->getJson('/api/v1/user/subscription');
        $response->assertUnauthorized();
    }

    public function test_payment_model_creation(): void
    {
        $subscription = Subscription::create([
            'user_id' => $this->user->user_id,
            'plan' => 'pro',
            'status' => 'active',
            'monthly_price' => 4.99,
            'annual_price' => 39.99,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $payment = Payment::create([
            'user_id' => $this->user->user_id,
            'subscription_id' => $subscription->subscription_id,
            'amount' => 4.99,
            'currency' => 'USD',
            'status' => 'succeeded',
            'description' => 'Pro subscription',
            'paid_at' => now(),
        ]);

        $this->assertDatabaseHas('payments', [
            'payment_id' => $payment->payment_id,
            'amount' => 4.99,
            'currency' => 'USD',
        ]);
    }
}
