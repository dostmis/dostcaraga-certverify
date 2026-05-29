<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Recipient extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'user_id',
        'name',
        'first_name',
        'middle_initial',
        'last_name',
        'email',
        'contact_number',
        'block_lot_purok',
        'region',
        'province',
        'city_municipality',
        'barangay',
        'industry',
        'organization_name',
        'position_designation',
        'gender',
        'birthdate',
        'age_range',
        'pwd_status',
        'is_4ps_beneficiary',
        'is_elcac_community',
        'dost_program_beneficiary',
        'directly_employed_programs',
        'has_attended_dost_training',
        'interested_dost_services',
        'interested_dost_services_other',
        'password',
        'claim_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'claim_token',
    ];

    protected $casts = [
        'birthdate' => 'date',
        'password' => 'hashed',
        'dost_program_beneficiary' => 'array',
        'directly_employed_programs' => 'array',
        'interested_dost_services' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Recipient $recipient): void {
            if (empty($recipient->claim_token)) {
                $recipient->claim_token = (string) Str::uuid();
            }

            if (empty($recipient->user_id) && ! empty($recipient->email)) {
                $user = User::where('email', $recipient->email)->first();
                if ($user) {
                    $recipient->user_id = $user->id;
                }
            }
        });
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function certificates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function participantIntakes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ParticipantIntake::class);
    }

    public function hasPassword(): bool
    {
        return $this->password !== null;
    }

    public function isClaimed(): bool
    {
        return $this->hasPassword();
    }
}
