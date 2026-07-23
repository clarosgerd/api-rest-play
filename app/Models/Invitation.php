<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    use HasFactory;

    protected $table = 'invitations';

    protected $primaryKey = 'invitation_id';

    public $timestamps = false;

    protected $fillable = [
        'invitation_id',
        'group_id',
        'invited_by',
        'email',
        'status',
        'expires_at',
        'created_at',
    ];

    public function group()
    {
        return $this->belongsTo('App\Models\Groups', 'group_id');
    }

    public function inviter()
    {
        return $this->belongsTo('App\Models\User', 'invited_by');
    }
}
