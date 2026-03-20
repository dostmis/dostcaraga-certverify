<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParticipantIntakeEvent extends Model
{
    protected $fillable = [
        'user_id',
        'event_name',
        'public_token',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
