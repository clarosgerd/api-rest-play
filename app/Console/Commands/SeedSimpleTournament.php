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

class SeedSimpleTournament extends Command
{
    protected $signature = 'tournament:seed-simple';

    protected $description = 'Seed simple 4-team round-robin tournament';

    public function handle(): int
    {
        $this->info('⚽ Creando torneo simple: Cuadrangular 2026...');

        return DB::transaction(function () {
            $tournament = $this->createTournament();
            $stage = $this->createStage($tournament->tournament_id);
            $teams = $this->createTeams();
            $this->createPlayers($teams);
            $this->createMatches($tournament->tournament_id, $stage, $teams);
            $this->createTournamentGroup($stage->stage_id, $teams);
            $users = $this->createUsers();
            $group = $this->createGroup($tournament->tournament_id, $users[0]->user_id);
            $this->addMembers($group->group_id, $users);

            $this->newLine();
            $this->info('✅ Torneo simple creado exitosamente!');
            $this->newLine();
            $this->table(
                ['ID', 'Nombre'],
                [['Torneo', $tournament->tournament_id], ['Grupo', $group->group_id]],
            );

            $this->info('📊 Resumen:');
            $this->line("  • 4 equipos, todos contra todos (6 partidos)");
            $this->line("  • 48 jugadores + 4 entrenadores");
            $this->line("  • 3 jornadas (28, 29, 30 jul)");
            $this->line("  • 6 predictores (predictor1-6@demo.com / password)");
            $this->newLine();

            return 0;
        });
    }

    private function createTournament(): Tournament
    {
        $tournament = new Tournament;
        $tournament->name = 'Cuadrangular 2026';
        $tournament->season = '2026';
        $tournament->country = 'Argentina';
        $tournament->country_code = 'AR';
        $tournament->logo_url = null;
        $tournament->flag_url = null;
        $tournament->start_date = '2026-07-28';
        $tournament->end_date = '2026-07-30';
        $tournament->active = 1;
        $tournament->status = 'published';
        $tournament->is_promotional = 0;
        $tournament->promotional_tier_required = 0;
        $tournament->promotional_ends_at = now()->addYears(10);
        $tournament->save();

        $this->info("  Torneo #{$tournament->tournament_id} creado");

        return $tournament;
    }

    private function createStage(int $tournamentId): TournamentStage
    {
        $stage = new TournamentStage;
        $stage->tournament_id = $tournamentId;
        $stage->name = 'Todos contra todos';
        $stage->stage_order = 1;
        $stage->is_knockout = 0;
        $stage->save();

        $this->info("  Stage creado");

        return $stage;
    }

    private function createTeams(): array
    {
        $teamsData = [
            ['name' => 'Huracán', 'league' => 'Primera A'],
            ['name' => 'Banfield', 'league' => 'Primera A'],
            ['name' => 'Godoy Cruz', 'league' => 'Primera A'],
            ['name' => 'Argentinos Juniors', 'league' => 'Primera A'],
        ];

        $created = [];
        foreach ($teamsData as $data) {
            $team = new \App\Models\Team;
            $team->name = $data['name'];
            $team->country = 'Argentina';
            $team->league = $data['league'];
            $team->country_code = 'AR';
            $team->logo_url = null;
            $team->flag_url = null;
            $team->active = 1;
            $team->save();
            $created[] = $team;
        }

        $this->info("  4 equipos creados: " . implode(', ', array_column($teamsData, 'name')));

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

        $this->info("  " . count($allPlayers) . " personas creadas (48 jugadores + 4 entrenadores)");
    }

    private function createMatches(int $tournamentId, TournamentStage $stage, array $teams): void
    {
        // Round-robin: 4 teams = 6 matches (3 matchdays x 2 matches)
        // Teams: [0]=Huracán, [1]=Banfield, [2]=Godoy Cruz, [3]=Argentinos
        $pairs = [
            // Jornada 1
            [0, 1], [2, 3],
            // Jornada 2
            [0, 2], [1, 3],
            // Jornada 3
            [0, 3], [1, 2],
        ];

        $dates = ['2026-07-28', '2026-07-29', '2026-07-30'];
        $times = ['18:00:00', '20:00:00'];

        $allMatches = [];
        foreach ($pairs as $idx => $pair) {
            $matchday = intdiv($idx, 2);
            $slot = $idx % 2;

            $allMatches[] = [
                'home_team_id' => $teams[$pair[0]]->team_id,
                'away_team_id' => $teams[$pair[1]]->team_id,
                'tournament_id' => $tournamentId,
                'stage_id' => $stage->stage_id,
                'match_date' => $dates[$matchday] . ' ' . $times[$slot],
                'status' => 'scheduled',
                'home_score' => 0,
                'away_score' => 0,
            ];
        }

        Matches::insert($allMatches);

        $this->info("  6 partidos creados (3 jornadas × 2)");
        $this->info('📅 Calendario:');
        $this->line("  • 28 jul: Huracán vs Banfield, Godoy Cruz vs Argentinos");
        $this->line("  • 29 jul: Huracán vs Godoy Cruz, Banfield vs Argentinos");
        $this->line("  • 30 jul: Huracán vs Argentinos, Banfield vs Godoy Cruz");
    }

    private function createTournamentGroup(int $stageId, array $teams): void
    {
        $group = new \App\Models\TournamentGroup;
        $group->stage_id = $stageId;
        $group->name = 'Todos contra todos';
        $group->group_order = 1;
        $group->status = 'approved';
        $group->save();

        $entries = [];
        foreach ($teams as $team) {
            $entries[] = [
                'group_id' => $group->group_id,
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

        \App\Models\TournamentGroupTeam::insert($entries);

        $this->info("  1 tournament group creado con 4 equipos asignados");
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

        $this->info("  6 predictores listos (predictor1-6@demo.com / password)");

        return $created;
    }

    private function createGroup(int $tournamentId, int $creatorId): Groups
    {
        $group = new Groups;
        $group->tournament_id = $tournamentId;
        $group->name = 'Cuadrangular Predictores';
        $group->description = 'Predicciones del Cuadrangular 2026. 4 equipos, todos contra todos.';
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
        $group->multiplier_semifinal = 1;
        $group->multiplier_final = 2;
        $group->created_at = now();
        $group->save();

        $this->info("  Grupo #{$group->group_id} creado (código: {$group->invitation_code})");

        return $group;
    }

    private function addMembers(int $groupId, array $users): void
    {
        $members = [];
        foreach ($users as $i => $user) {
            // Skip if already a member
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

        $this->info("  6 miembros agregados al grupo");
    }
}
