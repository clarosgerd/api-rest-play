<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionalTournamentTest extends TestCase
{
    use RefreshDatabase;

    private User $freeUser;

    private Tournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freeUser = User::create([
            'first_name' => 'Free',
            'last_name' => 'User',
            'email' => 'free@example.com',
            'password_hash' => bcrypt('secret123'),
            'subscription_tier' => 'free',
        ]);

        $this->tournament = Tournament::create([
            'name' => 'Copa Libertadores',
            'season' => '2026',
            'country' => 'South America',
            'country_code' => 'SA',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'active' => true,
            'is_promotional' => true,
            'promotional_tier_required' => 2,
            'promotional_ends_at' => Carbon::now()->addMonths(2),
        ]);
    }

    public function test_create_promotional_tournament(): void
    {
        $this->assertTrue($this->tournament->is_promotional);
        $this->assertTrue($this->tournament->isCurrentlyPromotional());
        $this->assertEquals(2, $this->tournament->requiredTierLevel());
    }

    public function test_promotional_tournament_not_expired(): void
    {
        $this->tournament->update([
            'promotional_ends_at' => Carbon::now()->addMonth(),
        ]);

        $this->assertTrue($this->tournament->isCurrentlyPromotional());
    }

    public function test_promotional_tournament_expired(): void
    {
        $this->tournament->update([
            'promotional_ends_at' => Carbon::now()->subDay(),
        ]);

        $this->assertFalse($this->tournament->isCurrentlyPromotional());
    }

    public function test_non_promotional_tournament(): void
    {
        $this->tournament->update(['is_promotional' => false]);

        $this->assertFalse($this->tournament->isCurrentlyPromotional());
    }

    public function test_free_user_access_promotional_via_api(): void
    {
        $response = $this->actingAs($this->freeUser)
            ->getJson("/api/v1/tournament/{$this->tournament->tournament_id}");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'is_promotional' => true,
                    'is_currently_promotional' => true,
                ],
            ]);
    }

    /**
     * El bypass promocional ahora también cubre crear GRUPO (20/10/2026,
     * mismo criterio que ya existía para crear torneo) — un free sin plan no
     * podría crear un grupo propio en general (members_per_group=0), pero sí
     * para un torneo marcado como promocional. Necesita `tournament_id` en el
     * payload para que CheckGroupLimits lo reconozca.
     */
    public function test_free_user_can_create_group_for_promotional(): void
    {
        $response = $this->actingAs($this->freeUser)
            ->postJson('/api/v1/group', [
                'name' => 'Free User Group',
                'description' => 'Testing promo access',
                'creator_id' => $this->freeUser->user_id,
                'tournament_id' => $this->tournament->tournament_id,
                'is_public' => true,
                'max_members' => 10,
            ]);

        $response->assertCreated();
    }

    public function test_expire_promotions_command(): void
    {
        $this->tournament->update([
            'promotional_ends_at' => Carbon::now()->subDays(5),
        ]);

        $exitCode = \Illuminate\Support\Facades\Artisan::call('promotions:expire');

        $this->assertEquals(0, $exitCode);

        $this->tournament->refresh();
        $this->assertFalse($this->tournament->is_promotional);
    }

    public function test_expire_promotions_command_skips_active(): void
    {
        $exitCode = \Illuminate\Support\Facades\Artisan::call('promotions:expire');

        $this->tournament->refresh();
        $this->assertTrue($this->tournament->is_promotional);
    }

    public function test_promotional_tournament_api_resource(): void
    {
        $response = $this->getJson('/api/v1/tournament/'.$this->tournament->tournament_id);

        $response->assertOk()
            ->assertJsonPath('data.is_promotional', true)
            ->assertJsonPath('data.is_currently_promotional', true);
    }
}
