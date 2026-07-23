<?php

namespace App\Console\Commands;

use App\Models\GroupMembers;
use App\Models\Groups;
use App\Models\Matches;
use App\Models\Predictions;
use App\Notifications\PredictionReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendPredictionRemindersCommand extends Command
{
    protected $signature = 'predictions:remind
        {--hours=24 : Send reminders for matches within N hours}';

    protected $description = 'Send email reminders for upcoming matches that lack predictions';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $now = Carbon::now();
        $cutoff = $now->copy()->addHours($hours);

        $matches = Matches::where('status', 'scheduled')
            ->whereBetween('match_date', [$now, $cutoff])
            ->with(['home', 'away', 'tournament'])
            ->get();

        if ($matches->isEmpty()) {
            $this->info('No upcoming matches in the next '.$hours.' hours.');

            return 0;
        }

        $sent = 0;

        foreach ($matches as $match) {
            $groupIds = GroupMembers::pluck('group_id')->unique();

            foreach ($groupIds as $groupId) {
                $members = GroupMembers::where('group_id', $groupId)
                    ->with('user')
                    ->get();

                foreach ($members as $member) {
                    $user = $member->user;
                    if (! $user || ! $user->email) {
                        continue;
                    }

                    $hasPrediction = Predictions::where('user_id', $user->user_id)
                        ->where('match_id', $match->match_id)
                        ->exists();

                    if ($hasPrediction) {
                        continue;
                    }

                    $group = Groups::find($groupId);
                    if (! $group) {
                        continue;
                    }

                    try {
                        $user->notify(new PredictionReminderNotification($match, $group));
                        $sent++;
                    } catch (\Exception $e) {
                        $this->warn("  Failed to send to {$user->email}: {$e->getMessage()}");
                    }
                }
            }
        }

        $this->info("Sent {$sent} prediction reminders.");

        return 0;
    }
}
