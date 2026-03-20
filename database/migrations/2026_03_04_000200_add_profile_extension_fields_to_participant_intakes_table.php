<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('participant_intakes', function (Blueprint $table) {
            $table->string('pwd_status', 3)->nullable()->after('age_range');
            $table->string('is_4ps_beneficiary', 3)->nullable()->after('pwd_status');
            $table->string('is_elcac_community', 3)->nullable()->after('is_4ps_beneficiary');
            $table->json('dost_program_beneficiary')->nullable()->after('is_elcac_community');
            $table->json('directly_employed_programs')->nullable()->after('dost_program_beneficiary');
            $table->string('has_attended_dost_training', 3)->nullable()->after('directly_employed_programs');
            $table->json('interested_dost_services')->nullable()->after('has_attended_dost_training');
            $table->string('interested_dost_services_other')->nullable()->after('interested_dost_services');
            $table->string('position_designation')->nullable()->after('industry');
        });
    }

    public function down(): void
    {
        Schema::table('participant_intakes', function (Blueprint $table) {
            $table->dropColumn([
                'pwd_status',
                'is_4ps_beneficiary',
                'is_elcac_community',
                'dost_program_beneficiary',
                'directly_employed_programs',
                'has_attended_dost_training',
                'interested_dost_services',
                'interested_dost_services_other',
                'position_designation',
            ]);
        });
    }
};
