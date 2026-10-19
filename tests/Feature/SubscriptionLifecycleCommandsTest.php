<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionExpiredNotification;
use App\Notifications\SubscriptionExpiringNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * subscription:check-expired y subscription:remind-expiring (19/10/2026) —
 * recién agendados en routes/console.php, antes dependían de ejecución
 * manual. Cubre el fix de la ventana de remind-expiring (antes "entre ahora
 * y +3 días", reenviaba el mismo recordatorio varios días seguidos con un
 * cron diario).
 */
class SubscriptionLifecycleCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function crearSuscripcion(array $overrides = []): Subscription
    {
        $user = User::factory()->create(['subscription_tier' => 'pro']);

        return Subscription::create(array_merge([
            'user_id' => $user->user_id,
            'plan' => 'pro',
            'status' => 'active',
            'monthly_price' => 35,
            'annual_price' => 279,
        ], $overrides));
    }

    public function test_remind_expiring_notifica_solo_lo_que_vence_en_exactamente_3_dias(): void
    {
        Notification::fake();

        $enTresDias = $this->crearSuscripcion(['current_period_end' => Carbon::now()->addDays(3)->setTime(12, 0)]);
        $enUnDia = $this->crearSuscripcion(['current_period_end' => Carbon::now()->addDay()]);
        $enCincoDias = $this->crearSuscripcion(['current_period_end' => Carbon::now()->addDays(5)]);

        $this->artisan('subscription:remind-expiring')->assertExitCode(0);

        Notification::assertSentTo($enTresDias->user, SubscriptionExpiringNotification::class);
        Notification::assertNotSentTo($enUnDia->user, SubscriptionExpiringNotification::class);
        Notification::assertNotSentTo($enCincoDias->user, SubscriptionExpiringNotification::class);
    }

    public function test_remind_expiring_no_toca_una_suscripcion_ya_cancelada_o_expirada(): void
    {
        Notification::fake();

        $expirada = $this->crearSuscripcion([
            'status' => 'expired',
            'current_period_end' => Carbon::now()->addDays(3)->setTime(12, 0),
        ]);

        $this->artisan('subscription:remind-expiring')->assertExitCode(0);

        Notification::assertNotSentTo($expirada->user, SubscriptionExpiringNotification::class);
    }

    public function test_check_expired_baja_a_free_y_notifica(): void
    {
        Notification::fake();

        $vencida = $this->crearSuscripcion(['current_period_end' => Carbon::now()->subDay()]);
        $vigente = $this->crearSuscripcion(['current_period_end' => Carbon::now()->addDays(10)]);

        $this->artisan('subscription:check-expired')->assertExitCode(0);

        $this->assertEquals('expired', $vencida->refresh()->status);
        $this->assertEquals('free', $vencida->user->refresh()->subscription_tier);
        Notification::assertSentTo($vencida->user, SubscriptionExpiredNotification::class);

        $this->assertEquals('active', $vigente->refresh()->status);
        Notification::assertNotSentTo($vigente->user, SubscriptionExpiredNotification::class);
    }
}
