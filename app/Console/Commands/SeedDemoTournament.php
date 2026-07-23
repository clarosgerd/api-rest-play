<?php

namespace App\Console\Commands;

use App\Models\GroupMembers;
use App\Models\Groups;
use App\Models\Matches;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentStage;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedDemoTournament extends Command
{
    protected $signature = 'tournament:seed-demo';

    protected $description = 'Seed the demo tournament: Liga Amistosa 2026';

    public function handle(): int
    {
        $this->info('⚽ Creando torneo demo: Liga Amistosa 2026...');

        return DB::transaction(function () {
            $tournament = $this->createTournament();
            $stages = $this->createStages($tournament->tournament_id);
            $teams = $this->createTeams($tournament->country);
            $this->createPlayers($teams);
            $this->createMatches($tournament->tournament_id, $stages, $teams);
            $this->createTournamentGroups($stages['Fase de Grupos']->stage_id, $teams);
            $users = $this->createUsers();
            $group = $this->createGroup($tournament->tournament_id, $users[0]->user_id);
            $this->addMembers($group->group_id, $users);

            $this->newLine();
            $this->info('✅ Torneo demo creado exitosamente!');
            $this->newLine();
            $this->table(
                ['ID', 'Nombre'],
                [['Torneo', $tournament->tournament_id], ['Grupo', $group->group_id]],
            );

            $this->info('📊 Resumen:');
            $this->line("  • 12 equipos (6 Serie A + 6 Serie B)");
            $this->line("  • 144 jugadores + 12 entrenadores");
            $this->line("  • 34 partidos (30 grupos + 2 semis + 3er puesto + final)");
            $this->line("  • 6 predictores (predictor1-6@demo.com / password)");
            $this->newLine();

            return 0;
        });
    }

    private function createTournament(): Tournament
    {
        $tournament = new Tournament;
        $tournament->name = 'Liga Amistosa 2026';
        $tournament->season = '2026';
        $tournament->country = 'Argentina';
        $tournament->country_code = 'AR';
        $tournament->logo_url = null;
        $tournament->flag_url = null;
        $tournament->start_date = '2026-07-28';
        $tournament->end_date = '2026-08-01';
        $tournament->active = 1;
        $tournament->status = 'published';
        $tournament->is_promotional = 0;
        $tournament->promotional_tier_required = 0;
        $tournament->promotional_ends_at = now()->addYears(10);
        $tournament->save();

        $this->info("  Torneo #{$tournament->tournament_id} creado");

        return $tournament;
    }

    private function createStages(int $tournamentId): array
    {
        $stages = [
            ['name' => 'Fase de Grupos', 'stage_order' => 1, 'is_knockout' => 0],
            ['name' => 'Semifinales', 'stage_order' => 2, 'is_knockout' => 1],
            ['name' => 'Finales', 'stage_order' => 3, 'is_knockout' => 1],
        ];

        $created = [];
        foreach ($stages as $data) {
            $stage = new TournamentStage;
            $stage->tournament_id = $tournamentId;
            $stage->name = $data['name'];
            $stage->stage_order = $data['stage_order'];
            $stage->is_knockout = $data['is_knockout'];
            $stage->save();
            $created[$data['name']] = $stage;
        }

        $this->info("  3 stages creados");

        return $created;
    }

    private function createTeams(string $country): array
    {
        $teamsData = [
            'Serie A' => [
                ['name' => 'Boca Juniors', 'city' => 'Buenos Aires'],
                ['name' => 'River Plate', 'city' => 'Buenos Aires'],
                ['name' => 'Racing Club', 'city' => 'Avellaneda'],
                ['name' => 'Independiente', 'city' => 'Avellaneda'],
                ['name' => 'San Lorenzo', 'city' => 'Buenos Aires'],
                ['name' => 'Estudiantes', 'city' => 'La Plata'],
            ],
            'Serie B' => [
                ['name' => 'Vélez Sarsfield', 'city' => 'Buenos Aires'],
                ['name' => 'Lanús', 'city' => 'Lanús'],
                ['name' => 'Defensa y Justicia', 'city' => 'Gobernador Costa'],
                ['name' => 'Talleres', 'city' => 'Córdoba'],
                ['name' => 'Unión', 'city' => 'Santa Fe'],
                ['name' => 'Colón', 'city' => 'Santa Fe'],
            ],
        ];

        $created = [];

        foreach ($teamsData as $serie => $teams) {
            foreach ($teams as $i => $teamData) {
                $team = new \App\Models\Team;
                $team->name = $teamData['name'];
                $team->country = $country;
                $team->league = $serie;
                $team->country_code = 'AR';
                $team->logo_url = null;
                $team->flag_url = null;
                $team->active = 1;
                $team->save();

                $created[] = $team;
            }
        }

        $this->info("  12 equipos creados (6 Serie A + 6 Serie B)");

        return $created;
    }

    private function createPlayers(array $teams): void
    {
        $positions = [
            ['GK', 'GK'],
            ['DF', 'DF', 'DF', 'DF'],
            ['MF', 'MF', 'MF', 'MF'],
            ['FW', 'FW'],
        ];

        $firstNames = [
            'Juan', 'Carlos', 'Diego', 'Martín', 'Gabriel', 'Pablo', 'Andrés', 'Sergio',
            'Alejandro', 'Fernando', 'Ricardo', 'Eduardo', 'Marcelo', 'Claudio', 'Luis',
            'Roberto', 'Jorge', 'Oscar', 'Daniel', 'Miguel', 'Raúl', 'Nicolás', 'Lucas',
            'Matías', 'Agustín', 'Bruno', 'Franco', 'Ignacio', 'Maximiliano', 'Cristian',
        ];

        $lastNames = [
            'García', 'López', 'Martínez', 'Rodríguez', 'Álvarez', 'Romero', 'Fernández',
            'Díaz', 'Torres', 'Acuña', 'Pérez', 'Sánchez', 'Ramírez', 'Flores', 'Benítez',
            'Medina', 'Ruiz', 'González', 'Herrera', 'Moreno', 'Rojas', 'Vargas', 'Castillo',
            'Morales', 'Silva', 'Giménez', 'Suárez', 'Paredes', 'De Paul', 'Molina',
        ];

        $allPlayers = [];

        foreach ($teams as $team) {
            foreach ($positions as $posGroup) {
                foreach ($posGroup as $position) {
                    $fn = $firstNames[array_rand($firstNames)];
                    $ln = $lastNames[array_rand($lastNames)];

                    $allPlayers[] = [
                        'team_id' => $team->team_id,
                        'name' => "$fn $ln",
                        'first_name' => $fn,
                        'last_name' => $ln,
                        'position' => $position,
                        'nationality' => 'Argentina',
                        'birth_date' => '2000-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT),
                        'age' => rand(18, 35),
                        'photo_url' => null,
                        'external_id' => null,
                    ];
                }
            }

            // Coach
            $fn = $firstNames[array_rand($firstNames)];
            $ln = $lastNames[array_rand($lastNames)];
            $allPlayers[] = [
                'team_id' => $team->team_id,
                'name' => "$fn $ln",
                'first_name' => $fn,
                'last_name' => $ln,
                'position' => 'Coach',
                'nationality' => 'Argentina',
                'birth_date' => '1970-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT),
                'age' => rand(45, 65),
                'photo_url' => null,
                'external_id' => null,
            ];
        }

        Player::insert($allPlayers);

        $this->info("  " . count($allPlayers) . " personas creadas (144 jugadores + 12 entrenadores)");
    }

    private function createMatches(int $tournamentId, array $stages, array $teams): void
    {
        $groupId = $stages['Fase de Grupos']->stage_id;
        $semiId = $stages['Semifinales']->stage_id;
        $finalId = $stages['Finales']->stage_id;

        // Serie A teams (indices 0-5), Serie B teams (indices 6-11)
        $serieA = array_slice($teams, 0, 6);
        $serieB = array_slice($teams, 6, 6);

        $allMatches = [];

        // Round-robin: 6 teams = 15 matches per serie (5 matchdays x 3 matches)
        $serieAPairs = [
            [0, 5], [1, 4], [2, 3],
            [0, 4], [5, 3], [1, 2],
            [0, 3], [4, 2], [5, 1],
            [0, 2], [3, 1], [4, 5],
            [0, 1], [2, 5], [3, 4],
        ];

        $serieBPairs = [
            [0, 5], [1, 4], [2, 3],
            [0, 4], [5, 3], [1, 2],
            [0, 3], [4, 2], [5, 1],
            [0, 2], [3, 1], [4, 5],
            [0, 1], [2, 5], [3, 4],
        ];

        $times = ['14:00:00', '14:30:00', '15:00:00', '15:30:00', '16:00:00', '16:30:00',
            '17:00:00', '17:30:00', '18:00:00', '18:30:00', '19:00:00', '19:30:00',
            '20:00:00', '20:30:00', '21:00:00'];

        // Serie A matches (15) - 28 jul
        $timeIdx = 0;
        foreach ($serieAPairs as $pair) {
            $allMatches[] = [
                'home_team_id' => $serieA[$pair[0]]->team_id,
                'away_team_id' => $serieA[$pair[1]]->team_id,
                'tournament_id' => $tournamentId,
                'stage_id' => $groupId,
                'match_date' => "2026-07-28 " . $times[$timeIdx++],
                'status' => 'scheduled',
                'home_score' => 0,
                'away_score' => 0,
            ];
        }

        // Serie B matches (15) - 29 jul
        $timeIdx = 0;
        foreach ($serieBPairs as $pair) {
            $allMatches[] = [
                'home_team_id' => $serieB[$pair[0]]->team_id,
                'away_team_id' => $serieB[$pair[1]]->team_id,
                'tournament_id' => $tournamentId,
                'stage_id' => $groupId,
                'match_date' => "2026-07-29 " . $times[$timeIdx++],
                'status' => 'scheduled',
                'home_score' => 0,
                'away_score' => 0,
            ];
        }

        // Semifinals (30 jul)
        // SF1: 1° Serie A vs 2° Serie B
        $allMatches[] = [
            'home_team_id' => $serieA[0]->team_id,
            'away_team_id' => $serieB[1]->team_id,
            'tournament_id' => $tournamentId,
            'stage_id' => $semiId,
            'match_date' => '2026-07-30 18:00:00',
            'status' => 'scheduled',
            'home_score' => 0,
            'away_score' => 0,
        ];
        // SF2: 1° Serie B vs 2° Serie A
        $allMatches[] = [
            'home_team_id' => $serieB[0]->team_id,
            'away_team_id' => $serieA[1]->team_id,
            'tournament_id' => $tournamentId,
            'stage_id' => $semiId,
            'match_date' => '2026-07-30 21:00:00',
            'status' => 'scheduled',
            'home_score' => 0,
            'away_score' => 0,
        ];

        // 3rd place (30 jul)
        $allMatches[] = [
            'home_team_id' => $serieA[1]->team_id, // placeholder, will be loser SF1
            'away_team_id' => $serieB[0]->team_id, // placeholder, will be loser SF2
            'tournament_id' => $tournamentId,
            'stage_id' => $finalId,
            'match_date' => '2026-07-30 21:00:00',
            'status' => 'scheduled',
            'home_score' => 0,
            'away_score' => 0,
        ];

        // Final (1 aug)
        $allMatches[] = [
            'home_team_id' => $serieA[0]->team_id, // placeholder, will be winner SF1
            'away_team_id' => $serieB[0]->team_id, // placeholder, will be winner SF2
            'tournament_id' => $tournamentId,
            'stage_id' => $finalId,
            'match_date' => '2026-08-01 20:00:00',
            'status' => 'scheduled',
            'home_score' => 0,
            'away_score' => 0,
        ];

        Matches::insert($allMatches);

        $this->info("  " . count($allMatches) . " partidos creados (30 grupos + 2 semis + 3er puesto + final)");

        $this->info('📅 Calendario:');
        $this->line("  • 28 jul: 15 partidos de Serie A");
        $this->line("  • 29 jul: 15 partidos de Serie B");
        $this->line("  • 30 jul: 2 semifinales + 3er puesto");
        $this->line("  • 1 ago: final");
    }

    private function createTournamentGroups(int $stageId, array $teams): void
    {
        $serieA = array_slice($teams, 0, 6);
        $serieB = array_slice($teams, 6, 6);

        $groupA = new \App\Models\TournamentGroup;
        $groupA->stage_id = $stageId;
        $groupA->name = 'Serie A';
        $groupA->group_order = 1;
        $groupA->status = 'approved';
        $groupA->save();

        $groupB = new \App\Models\TournamentGroup;
        $groupB->stage_id = $stageId;
        $groupB->name = 'Serie B';
        $groupB->group_order = 2;
        $groupB->status = 'approved';
        $groupB->save();

        $allTeamEntries = [];
        foreach ($serieA as $order => $team) {
            $allTeamEntries[] = [
                'group_id' => $groupA->group_id,
                'team_id' => $team->team_id,
                'position_final' => null,
                'matches_played' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'goal_diff' => 0,
                'points' => 0,
            ];
        }
        foreach ($serieB as $order => $team) {
            $allTeamEntries[] = [
                'group_id' => $groupB->group_id,
                'team_id' => $team->team_id,
                'position_final' => null,
                'matches_played' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'goal_diff' => 0,
                'points' => 0,
            ];
        }

        \App\Models\TournamentGroupTeam::insert($allTeamEntries);

        $this->info("  2 tournament groups creados (Serie A + Serie B) con 12 equipos asignados");
    }

    private function createUsers(): array
    {
        $usersData = [
            ['first_name' => 'Carlos', 'last_name' => 'Pérez', 'email' => 'predictor1@demo.com'],
            ['first_name' => 'Lucía', 'last_name' => 'Martínez', 'email' => 'predictor2@demo.com'],
            ['first_name' => 'Martín', 'last_name' => 'García', 'email' => 'predictor3@demo.com'],
            ['first_name' => 'Sofía', 'last_name' => 'Rodríguez', 'email' => 'predictor4@demo.com'],
            ['first_name' => 'Diego', 'last_name' => 'López', 'email' => 'predictor5@demo.com'],
            ['first_name' => 'Valentina', 'last_name' => 'Fernández', 'email' => 'predictor6@demo.com'],
        ];

        $created = [];
        foreach ($usersData as $userData) {
            $user = new User;
            $user->first_name = $userData['first_name'];
            $user->last_name = $userData['last_name'];
            $user->email = $userData['email'];
            $user->password_hash = bcrypt('password');
            $user->role = 'user';
            $user->subscription_tier = 'free';
            $user->balance = 0;
            $user->save();
            $created[] = $user;
        }

        $this->info("  6 predictores creados (predictor1-6@demo.com / password)");

        return $created;
    }

    private function createGroup(int $tournamentId, int $creatorId): Groups
    {
        $group = new Groups;
        $group->tournament_id = $tournamentId;
        $group->name = 'Liga Amistosa Predictores';
        $group->description = 'Grupo de predicciones para la Liga Amistosa 2026. 6 predictores compitiendo.';
        $group->creator_id = $creatorId;
        $group->is_public = 0;
        $group->invitation_code = Str::upper(Str::random(7));
        $group->max_members = 10;
        $group->active = 1;
        $group->bet_amount = 0;
        $group->prize_1st_pct = 60;
        $group->prize_2nd_pct = 30;
        $group->prize_3rd_pct = 10;
        $group->pool_active = 0;
        $group->exact_score_points = 10;
        $group->match_result_points = 5;
        $group->correct_goal_points = 3;
        $group->unique_exact_points = 15;
        $group->multiplier_dieciseisavos = 1;
        $group->multiplier_octavos = 1;
        $group->multiplier_cuartos = 1;
        $group->multiplier_semifinal = 2;
        $group->multiplier_final = 3;
        $group->created_at = now();
        $group->save();

        $this->info("  Grupo #{$group->group_id} creado (código: {$group->invitation_code})");

        return $group;
    }

    private function addMembers(int $groupId, array $users): void
    {
        $members = [];
        foreach ($users as $i => $user) {
            $members[] = [
                'group_id' => $groupId,
                'user_id' => $user->user_id,
                'joined_at' => now(),
                'is_admin' => $i === 0 ? 1 : 0,
                'status' => 'accepted',
            ];
        }

        GroupMembers::insert($members);

        $this->info("  6 miembros agregados al grupo");
    }
}
