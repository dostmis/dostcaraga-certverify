<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('participant_intakes', function (Blueprint $table) {
            $table->string('organization_name')->nullable()->after('industry');
        });
    }

    public function down(): void
    {
        Schema::table('participant_intakes', function (Blueprint $table) {
            $table->dropColumn('organization_name');
        });
    }
};
