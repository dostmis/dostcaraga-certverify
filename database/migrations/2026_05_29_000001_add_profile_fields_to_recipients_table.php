<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipients', function (Blueprint $table) {
            $table->string('block_lot_purok')->nullable()->after('contact_number');
            $table->string('region')->nullable()->after('block_lot_purok');
            $table->string('province')->nullable()->after('region');
            $table->string('city_municipality')->nullable()->after('province');
            $table->string('barangay')->nullable()->after('city_municipality');
            $table->string('industry')->nullable()->after('barangay');
            $table->string('organization_name')->nullable()->after('industry');
            $table->string('position_designation')->nullable()->after('organization_name');
            $table->string('age_range')->nullable()->after('birthdate');
            $table->string('pwd_status')->nullable()->after('age_range');
            $table->string('is_4ps_beneficiary')->nullable()->after('pwd_status');
            $table->string('is_elcac_community')->nullable()->after('is_4ps_beneficiary');
            $table->json('dost_program_beneficiary')->nullable()->after('is_elcac_community');
            $table->json('directly_employed_programs')->nullable()->after('dost_program_beneficiary');
            $table->string('has_attended_dost_training')->nullable()->after('directly_employed_programs');
            $table->json('interested_dost_services')->nullable()->after('has_attended_dost_training');
            $table->string('interested_dost_services_other')->nullable()->after('interested_dost_services');
        });
    }

    public function down(): void
    {
        Schema::table('recipients', function (Blueprint $table) {
            $table->dropColumn([
                'block_lot_purok',
                'region',
                'province',
                'city_municipality',
                'barangay',
                'industry',
                'organization_name',
                'position_designation',
                'age_range',
                'pwd_status',
                'is_4ps_beneficiary',
                'is_elcac_community',
                'dost_program_beneficiary',
                'directly_employed_programs',
                'has_attended_dost_training',
                'interested_dost_services',
                'interested_dost_services_other',
            ]);
        });
    }
};
