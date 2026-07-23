<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupMembers extends Model
{
    /** @use HasFactory<\Database\Factories\GroupMembersFactory> */
    use HasFactory;

    protected $table = 'groupmembers';

    protected $primaryKey = 'member_id';

    public $timestamps = false;

    protected $fillable = [
        'member_id',
        'group_id',
        'user_id',
        'joined_at',
        'is_admin',
        'status',
    ];

    public function groups()
    {
        return $this->belongsTo('App\Models\Groups', 'group_id');
    }

    public function group()
    {
        return $this->belongsTo('App\Models\Groups', 'group_id');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }
}
