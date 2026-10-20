<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Torneos disponibles por plan + temporada (16/10/2026, pedido real del
 * usuario) — camino 1 del onboarding ("elegir torneo configurado"). Free no
 * ve ligas externas reales, solo demo/promocionales; pro ve CONMEBOL +
 * demo; premium ve todo. "Temporada" = active (ya existente).
 */
class TournamentAvailableForMeTest extends TestCase
{
    use RefreshDatabase;

    private Tournament $demo;

    private Tournament $conmebol;

    private Tournament $european;

    private Tournament $inactive;

    protected function setUp(): void
    {
        parent::setUp();

        // Demo/privado: sin external_id, sin league_category, created_by
        // null (contenido global, no el torneo privado de un usuario) —
        // status 'published' (20/10/2026: antes del fix ni se miraba, ahora
        // hace falta para aparecer en availableForMe()).
        $this->demo = Tournament::create([
            'name' => 'Liga Amistosa Demo', 'season' => '2026', 'country' => 'N/A', 'country_code' => 'NA',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'active' => true,
            'status' => 'published',
        ]);

        $this->conmebol = Tournament::create([
            'name' => 'Copa Libertadores', 'season' => '2026', 'country' => 'CONMEBOL', 'country_code' => 'CO',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'active' => true,
            'external_id' => 13, 'league_category' => 'conmebol', 'status' => 'published',
        ]);

        $this->european = Tournament::create([
            'name' => 'Premier League', 'season' => '2026', 'country' => 'England', 'country_code' => 'EN',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'active' => true,
            'external_id' => 2021, 'league_category' => 'european', 'status' => 'published',
        ]);

        // Fuera de temporada: no debe aparecer para NADIE, ni siquiera premium.
        $this->inactive = Tournament::create([
            'name' => 'Liga Vieja', 'season' => '2024', 'country' => 'Spain', 'country_code' => 'ES',
            'start_date' => '2024-01-01', 'end_date' => '2024-12-31', 'active' => false,
            'external_id' => 2014, 'league_category' => 'european', 'status' => 'published',
        ]);
    }

    private function userWithTier(string $tier): User
    {
        $user = User::create([
            'first_name' => 'Ana', 'last_name' => 'Test', 'email' => 'ana+'.$tier.'@example.com',
            'password_hash' => bcrypt('secret123'), 'subscription_tier' => $tier,
        ]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_free_only_sees_demo_tournaments(): void
    {
        $this->userWithTier('free');

        $response = $this->getJson('/api/v1/tournament/available-for-me')->assertOk();
        $ids = collect($response->json('data'))->pluck('tournament_id');

        $this->assertTrue($ids->contains($this->demo->tournament_id));
        $this->assertFalse($ids->contains($this->conmebol->tournament_id));
        $this->assertFalse($ids->contains($this->european->tournament_id));
    }

    public function test_pro_sees_conmebol_plus_demo_but_not_european(): void
    {
        $this->userWithTier('pro');

        $response = $this->getJson('/api/v1/tournament/available-for-me')->assertOk();
        $ids = collect($response->json('data'))->pluck('tournament_id');

        $this->assertTrue($ids->contains($this->demo->tournament_id));
        $this->assertTrue($ids->contains($this->conmebol->tournament_id));
        $this->assertFalse($ids->contains($this->european->tournament_id));
    }

    public function test_premium_sees_everything_in_season(): void
    {
        $this->userWithTier('premium');

        $response = $this->getJson('/api/v1/tournament/available-for-me')->assertOk();
        $ids = collect($response->json('data'))->pluck('tournament_id');

        $this->assertTrue($ids->contains($this->demo->tournament_id));
        $this->assertTrue($ids->contains($this->conmebol->tournament_id));
        $this->assertTrue($ids->contains($this->european->tournament_id));
    }

    public function test_out_of_season_tournament_never_appears(): void
    {
        $this->userWithTier('premium');

        $response = $this->getJson('/api/v1/tournament/available-for-me')->assertOk();
        $ids = collect($response->json('data'))->pluck('tournament_id');

        $this->assertFalse($ids->contains($this->inactive->tournament_id));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/tournament/available-for-me')->assertStatus(401);
    }

    /**
     * Publicar un torneo sincronizado (20/10/2026, pedido real del usuario)
     * — los comandos de sync dejan `status` en 'draft' (default de la
     * migración) sin tocarlo; antes de este fix availableForMe() ni lo
     * miraba, así que un torneo recién sincronizado ya aparecía disponible
     * sin publicación real de por medio.
     */
    public function test_draft_synced_tournament_not_visible_until_published(): void
    {
        $draft = Tournament::create([
            'name' => 'Mundial de Clubes', 'season' => '2026', 'country' => 'USA', 'country_code' => 'US',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'active' => true,
            'external_id' => 999, 'league_category' => 'conmebol', 'status' => 'draft',
        ]);

        $this->userWithTier('premium');

        $ids = collect($this->getJson('/api/v1/tournament/available-for-me')->assertOk()->json('data'))
            ->pluck('tournament_id');
        $this->assertFalse($ids->contains($draft->tournament_id));

        $draft->update(['status' => 'published']);

        $ids = collect($this->getJson('/api/v1/tournament/available-for-me')->assertOk()->json('data'))
            ->pluck('tournament_id');
        $this->assertTrue($ids->contains($draft->tournament_id));
    }

    /**
     * Bug real encontrado al implementar el punto de arriba: el torneo
     * privado de OTRO usuario aparecía en la lista de todos, contradiciendo
     * "privado = solo por invitación". El propio sigue viéndose.
     */
    public function test_other_users_private_tournament_is_hidden_but_own_is_visible(): void
    {
        $owner = User::create([
            'first_name' => 'Owner', 'last_name' => 'Test', 'email' => 'owner@example.com',
            'password_hash' => bcrypt('secret123'), 'subscription_tier' => 'premium',
        ]);
        $ownPrivate = Tournament::create([
            'name' => 'Liga Barrial', 'season' => '2026', 'country' => 'Bolivia', 'country_code' => 'BO',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'active' => true,
            'status' => 'published', 'created_by' => $owner->user_id,
        ]);

        Sanctum::actingAs($owner);
        $ids = collect($this->getJson('/api/v1/tournament/available-for-me')->assertOk()->json('data'))
            ->pluck('tournament_id');
        $this->assertTrue($ids->contains($ownPrivate->tournament_id));

        $this->userWithTier('premium');
        $ids = collect($this->getJson('/api/v1/tournament/available-for-me')->assertOk()->json('data'))
            ->pluck('tournament_id');
        $this->assertFalse($ids->contains($ownPrivate->tournament_id));
    }
}
