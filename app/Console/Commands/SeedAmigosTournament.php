<?php

namespace App\Console\Commands;

use App\Models\GroupMembers;
use App\Models\Groups;
use App\Models\Matches;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentAdvancementRule;
use App\Models\TournamentGroup;
use App\Models\TournamentGroupTeam;
use App\Models\TournamentStage;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedAmigosTournament extends Command
{
    protected $signature = 'tournament:seed-amigos';

    protected $description = 'Seed the Amigos tournament: Liga AMIGOS por Siempre';

    public function handle(): int
    {
        $this->info('⚽ Creando torneo demo: Liga AMIGOS por Siempre...');

        return DB::transaction(function () {
            $tournament = $this->createTournament();
            $stages = $this->createStages($tournament->tournament_id);
            $teams = $this->createTeams($tournament->country);
            $this->createPlayers($teams);
            $this->createTournamentGroups($stages['Fase de Grupos']->stage_id, $teams);
            $this->createAdvancementRules($stages);
            $this->createMatches($tournament->tournament_id, $stages, $teams);
            $users = $this->getOrCreateUsers();
            $group = $this->createGroup($tournament->tournament_id, $users[0]->user_id);
            $this->addMembers($group->group_id, $users);

            $this->newLine();
            $this->info('✅ Torneo AMIGOS creado exitosamente!');
            $this->newLine();
            $this->table(
                ['ID', 'Nombre'],
                [['Torneo', $tournament->tournament_id], ['Grupo', $group->group_id]],
            );

            $this->info('📊 Resumen:');
            $this->line('  • 6 equipos (3 Grupo A + 3 Grupo B)');
            $this->line('  • 72 jugadores + 6 entrenadores');
            $this->line('  • 15 partidos (12 grupos ida/vuelta + 2 semis + 1 final)');
            $this->line('  • 6 predictores (predictor1-6@demo.com / password)');
            $this->newLine();

            return 0;
        });
    }

    private function createTournament(): Tournament
    {
        $tournament = new Tournament;
        $tournament->name = 'Liga AMIGOS por Siempre';
        $tournament->season = '2026';
        $tournament->country = 'Argentina';
        $tournament->country_code = 'AR';
        $tournament->logo_url = null;
        $tournament->flag_url = null;
        $tournament->start_date = '2026-08-08';
        $tournament->end_date = '2026-08-12';
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
            ['name' => 'Final', 'stage_order' => 3, 'is_knockout' => 1],
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

        $this->info('  3 stages creados');

        return $created;
    }

    private function createTeams(string $country): array
    {
        $teamsData = [
            'Grupo A' => [
                ['name' => 'Los Cracks'],
                ['name' => 'Los Pibes'],
                ['name' => 'Los Veteranos'],
            ],
            'Grupo B' => [
                ['name' => 'Los Galácticos'],
                ['name' => 'Los Rayos'],
                ['name' => 'Los Titanes'],
            ],
        ];

        $created = [];

        foreach ($teamsData as $group => $teams) {
            foreach ($teams as $teamData) {
                $team = new \App\Models\Team;
                $team->name = $teamData['name'];
                $team->country = $country;
                $team->league = $group;
                $team->country_code = 'AR';
                $team->logo_url = null;
                $team->flag_url = null;
                $team->active = 1;
                $team->save();

                $created[] = $team;
            }
        }

        $this->info('  6 equipos creados (3 Grupo A + 3 Grupo B)');

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
                        'birth_date' => '2000-'.str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT).'-'.str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT),
                        'age' => rand(18, 35),
                        'photo_url' => null,
                        'external_id' => null,
                    ];
                }
            }

            $fn = $firstNames[array_rand($firstNames)];
            $ln = $lastNames[array_rand($lastNames)];
            $allPlayers[] = [
                'team_id' => $team->team_id,
                'name' => "$fn $ln",
                'first_name' => $fn,
                'last_name' => $ln,
                'position' => 'Coach',
                'nationality' => 'Argentina',
                'birth_date' => '1970-'.str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT).'-'.str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT),
                'age' => rand(45, 65),
                'photo_url' => null,
                'external_id' => null,
            ];
        }

        Player::insert($allPlayers);

        $this->info('  '.count($allPlayers).' personas creadas (72 jugadores + 6 entrenadores)');
    }

    private function createTournamentGroups(int $stageId, array $teams): void
    {
        $groupATeams = array_slice($teams, 0, 3);
        $groupBTeams = array_slice($teams, 3, 3);

        $groupA = new TournamentGroup;
        $groupA->stage_id = $stageId;
        $groupA->name = 'Grupo A';
        $groupA->group_order = 1;
        $groupA->status = 'approved';
        $groupA->save();

        $groupB = new TournamentGroup;
        $groupB->stage_id = $stageId;
        $groupB->name = 'Grupo B';
        $groupB->group_order = 2;
        $groupB->status = 'approved';
        $groupB->save();

        $allTeamEntries = [];
        foreach ($groupATeams as $team) {
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
        foreach ($groupBTeams as $team) {
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

        TournamentGroupTeam::insert($allTeamEntries);

        $this->info('  2 tournament groups creados (Grupo A + Grupo B) con 6 equipos asignados');
    }

    private function createAdvancementRules(array $stages): void
    {
        $groupId = $stages['Fase de Grupos']->stage_id;
        $semiId = $stages['Semifinales']->stage_id;
        $finalId = $stages['Final']->stage_id;

        $rule1 = new TournamentAdvancementRule;
        $rule1->stage_id = $groupId;
        $rule1->next_stage_id = $semiId;
        $rule1->rule_type = 'top_n';
        $rule1->rule_value = 2;
        $rule1->description = 'Los 2 primeros de cada grupo avanzan a semifinales cruzadas (1°A vs 2°B, 1°B vs 2°A)';
        $rule1->save();

        $rule2 = new TournamentAdvancementRule;
        $rule2->stage_id = $semiId;
        $rule2->next_stage_id = $finalId;
        $rule2->rule_type = 'top_n';
        $rule2->rule_value = 1;
        $rule2->description = 'El ganador de cada semifinal avanza a la final';
        $rule2->save();

        $this->info('  2 reglas de avance creadas (top 2 → semis, top 1 → final)');
    }

    private function createMatches(int $tournamentId, array $stages, array $teams): void
    {
        $groupId = $stages['Fase de Grupos']->stage_id;
        $semiId = $stages['Semifinales']->stage_id;
        $finalId = $stages['Final']->stage_id;

        $groupA = array_slice($teams, 0, 3);
        $groupB = array_slice($teams, 3, 3);

        $allMatches = [];

        $pairs = [
            [0, 1], [0, 2], [1, 2],
        ];
        $pairsReverse = [
            [1, 0], [2, 0], [2, 1],
        ];

        $times = ['14:00:00', '15:00:00', '16:00:00', '17:00:00', '18:00:00', '19:00:00'];

        $timeIdx = 0;
        foreach ($pairs as $pair) {
            $allMatches[] = [
                'home_team_id' => $groupA[$pair[0]]->team_id,
                'away_team_id' => $groupA[$pair[1]]->team_id,
                'tournament_id' => $tournamentId,
                'stage_id' => $groupId,
                'match_date' => '2026-08-08 '.$times[$timeIdx],
                'status' => 'scheduled',
                'home_score' => 0,
                'away_score' => 0,
            ];
            $allMatches[] = [
                'home_team_id' => $groupB[$pair[0]]->team_id,
                'away_team_id' => $groupB[$pair[1]]->team_id,
                'tournament_id' => $tournamentId,
                'stage_id' => $groupId,
                'match_date' => '2026-08-08 '.$times[$timeIdx],
                'status' => 'scheduled',
                'home_score' => 0,
                'away_score' => 0,
            ];
            $timeIdx++;
        }

        $timeIdx = 0;
        foreach ($pairsReverse as $pair) {
            $allMatches[] = [
                'home_team_id' => $groupA[$pair[0]]->team_id,
                'away_team_id' => $groupA[$pair[1]]->team_id,
                'tournament_id' => $tournamentId,
                'stage_id' => $groupId,
                'match_date' => '2026-08-09 '.$times[$timeIdx],
                'status' => 'scheduled',
                'home_score' => 0,
                'away_score' => 0,
            ];
            $allMatches[] = [
                'home_team_id' => $groupB[$pair[0]]->team_id,
                'away_team_id' => $groupB[$pair[1]]->team_id,
                'tournament_id' => $tournamentId,
                'stage_id' => $groupId,
                'match_date' => '2026-08-09 '.$times[$timeIdx],
                'status' => 'scheduled',
                'home_score' => 0,
                'away_score' => 0,
            ];
            $timeIdx++;
        }

        $allMatches[] = [
            'home_team_id' => null,
            'away_team_id' => null,
            'tournament_id' => $tournamentId,
            'stage_id' => $semiId,
            'match_date' => '2026-08-11 18:00:00',
            'status' => 'scheduled',
            'home_score' => 0,
            'away_score' => 0,
        ];
        $allMatches[] = [
            'home_team_id' => null,
            'away_team_id' => null,
            'tournament_id' => $tournamentId,
            'stage_id' => $semiId,
            'match_date' => '2026-08-11 21:00:00',
            'status' => 'scheduled',
            'home_score' => 0,
            'away_score' => 0,
        ];

        $allMatches[] = [
            'home_team_id' => null,
            'away_team_id' => null,
            'tournament_id' => $tournamentId,
            'stage_id' => $finalId,
            'match_date' => '2026-08-12 20:00:00',
            'status' => 'scheduled',
            'home_score' => 0,
            'away_score' => 0,
        ];

        Matches::insert($allMatches);

        $this->info('  '.count($allMatches).' partidos creados (12 grupos ida/vuelta + 2 semis + 1 final)');

        $this->info('📅 Calendario:');
        $this->line('  • 8 ago: 6 partidos ida (3 Grupo A + 3 Grupo B)');
        $this->line('  • 9 ago: 6 partidos vuelta (3 Grupo A + 3 Grupo B)');
        $this->line('  • 11 ago: 2 semifinales');
        $this->line('  • 12 ago: final');
    }

    private function getOrCreateUsers(): array
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
            $user = User::where('email', $userData['email'])->first();
            if (! $user) {
                $user = new User;
                $user->first_name = $userData['first_name'];
                $user->last_name = $userData['last_name'];
                $user->email = $userData['email'];
                $user->password_hash = bcrypt('password');
                $user->role = 'user';
                $user->subscription_tier = 'free';
                $user->balance = 0;
                $user->save();
            }
            $created[] = $user;
        }

        $this->info('  6 predictores listos (predictor1-6@demo.com / password)');

        return $created;
    }

    private function createGroup(int $tournamentId, int $creatorId): Groups
    {
        $group = new Groups;
        $group->tournament_id = $tournamentId;
        $group->name = 'Amigos por Siempre Predictores';
        $group->description = 'Grupo de predicciones para la Liga AMIGOS por Siempre. 6 predictores compitiendo.';
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
            $exists = GroupMembers::where('group_id', $groupId)
                ->where('user_id', $user->user_id)
                ->exists();

            if (! $exists) {
                $members[] = [
                    'group_id' => $groupId,
                    'user_id' => $user->user_id,
                    'joined_at' => now(),
                    'is_admin' => $i === 0 ? 1 : 0,
                    'status' => 'accepted',
                ];
            }
        }

        if (! empty($members)) {
            GroupMembers::insert($members);
        }

        $this->info('  6 miembros agregados al grupo');
    }
}
