<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    use HasFactory;

    protected $table = 'tournaments';

    protected $primaryKey = 'tournament_id';

    public $timestamps = false;

    protected $fillable = [
        'tournament_id',
        'name',
        'season',
        'country',
        'country_code',
        'logo_url',
        'flag_url',
        'start_date',
        'end_date',
        'active',
        'status',
        'created_by',
        'external_id',
        'league_category',
        'last_synced_at',
        'is_promotional',
        'promotional_tier_required',
        'promotional_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_promotional' => 'boolean',
            'promotional_ends_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function tournamentstages()
    {
        return $this->hasMany(TournamentStage::class, 'tournament_id');
    }

    public function matches()
    {
        return $this->hasMany(Matches::class, 'tournament_id');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    public function isOwner(int $userId): bool
    {
        return $this->created_by === $userId;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isCurrentlyPromotional(): bool
    {
        if (! $this->is_promotional) {
            return false;
        }

        if ($this->promotional_ends_at && $this->promotional_ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function requiredTierLevel(): int
    {
        return $this->promotional_tier_required ?? 2;
    }
}
