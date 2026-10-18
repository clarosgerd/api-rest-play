<?php

namespace App\Jobs;

use App\Models\SyncRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * El worker de colas tiene su propio timeout (default 60s, igual al de
     * PHP-FPM) — sin esto, encolar el sync seguiría fallando por el mismo
     * motivo que falla hoy de forma síncrona.
     */
    public int $timeout = 600;

    public function __construct(public int $syncRunId) {}

    public function handle(): void
    {
        // sync:all (football:sync + espn:sync) sobre miles de equipos/partidos
        // ya acumulados excede el memory_limit default de PHP (128M) y mata
        // el worker entero con un fatal no capturable, no solo este job.
        ini_set('memory_limit', '512M');

        $run = SyncRun::find($this->syncRunId);

        if (! $run) {
            return;
        }

        $run->update(['status' => 'running', 'started_at' => now()]);

        try {
            $exitCode = Artisan::call($run->command, $run->arguments ?? []);

            $run->update([
                'status' => $exitCode === 0 ? 'completed' : 'failed',
                'log' => Artisan::output(),
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'log' => Artisan::output(),
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            Log::error("RunSyncJob falló (run {$run->sync_run_id}, {$run->command}): {$e->getMessage()}");
        }
    }
}
