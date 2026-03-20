<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParticipantIntake extends Model
{
    protected $fillable = [
        'participant_intake_event_id',
        'owner_user_id',
        'participant_name',
        'last_name',
        'first_name',
        'middle_initial',
        'email',
        'contact_number',
        'gender',
        'age',
        'age_range',
        'pwd_status',
        'is_4ps_beneficiary',
        'is_elcac_community',
        'dost_program_beneficiary',
        'directly_employed_programs',
        'has_attended_dost_training',
        'interested_dost_services',
        'interested_dost_services_other',
        'industry',
        'organization_name',
        'position_designation',
        'region',
        'province',
        'city_municipality',
        'barangay',
        'block_lot_purok',
        'status',
        'endorsed_at',
        'endorsed_by',
        'rd_approved_at',
        'rd_approved_by',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'participant_intake_event_id' => 'integer',
        'owner_user_id' => 'integer',
        'endorsed_at' => 'datetime',
        'rd_approved_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'dost_program_beneficiary' => 'array',
        'directly_employed_programs' => 'array',
        'interested_dost_services' => 'array',
    ];

    public function intakeEvent()
    {
        return $this->belongsTo(ParticipantIntakeEvent::class, 'participant_intake_event_id');
    }
}
