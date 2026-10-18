<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncRun extends Model
{
    protected $table = 'sync_runs';

    protected $primaryKey = 'sync_run_id';

    protected $fillable = [
        'source',
        'command',
        'arguments',
        'status',
        'log',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'arguments' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed']);
    }
}
