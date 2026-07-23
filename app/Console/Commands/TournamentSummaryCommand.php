<?php

namespace App\Console\Commands;

use App\Models\GroupMembers;
use App\Models\PredictionResults;
use App\Models\Tournament;
use App\Notifications\TournamentSummaryNotification;
use Illuminate\Console\Command;

class TournamentSummaryCommand extends Command
{
    protected $signature = 'tournament:summary
        {tournament_id : Tournament ID}
        {--group_id= : Specific group ID (default: all groups)}';

    protected $description = 'Send tournament summary emails to all group members';

    public function handle(): int
    {
        $tournamentId = $this->argument('tournament_id');
        $groupId = $this->option('group_id');

        $tournament = Tournament::find($tournamentId);
        if (! $tournament) {
            $this->error('Tournament not found.');

            return 1;
        }

        $query = GroupMembers::with(['group', 'user']);

        if ($groupId) {
            $query->where('group_id', $groupId);
        }

        $members = $query->get();

        $sent = 0;

        foreach ($members as $member) {
            $user = $member->user;
            if (! $user || ! $user->email) {
                continue;
            }

            $group = $member->group;

            $results = PredictionResults::whereHas('prediction', function ($q) use ($user, $tournamentId) {
                $q->where('user_id', $user->user_id)
                    ->whereHas('match', function ($q2) use ($tournamentId) {
                        $q2->where('tournament_id', $tournamentId);
                    });
            })->get();

            $totalPoints = $results->sum('points_earned');
            $totalPredictions = $results->count();
            $correctPredictions = $results->where('points_earned', '>', 0)->count();

            $allScores = GroupMembers::where('group_id', $group->group_id)
                ->with('user')
                ->get()
                ->map(function ($m) use ($tournamentId) {
                    return [
                        'user_id' => $m->user_id,
                        'points' => PredictionResults::whereHas('prediction', function ($q) use ($m, $tournamentId) {
                            $q->where('user_id', $m->user_id)
                                ->whereHas('match', function ($q2) use ($tournamentId) {
                                    $q2->where('tournament_id', $tournamentId);
                                });
                        })->sum('points_earned'),
                    ];
                })
                ->sortByDesc('points')
                ->values();

            $position = $allScores->search(function ($s) use ($user) {
                return $s['user_id'] === $user->user_id;
            });

            $position = is_int($position) ? $position + 1 : $allScores->count() + 1;

            try {
                $user->notify(new TournamentSummaryNotification(
                    $tournament,
                    $group,
                    $totalPoints,
                    $totalPredictions,
                    $correctPredictions,
                    $position
                ));
                $sent++;
            } catch (\Exception $e) {
                $this->warn("  Failed to send to {$user->email}: {$e->getMessage()}");
            }
        }

        $this->info("Sent {$sent} tournament summary emails.");

        return 0;
    }
}
