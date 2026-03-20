<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('participant_intakes', function (Blueprint $table) {
            $table->string('last_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_initial', 10)->nullable();
            $table->string('contact_number', 30)->nullable();
            $table->string('age_range')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('participant_intakes', function (Blueprint $table) {
            $table->dropColumn([
                'last_name',
                'first_name',
                'middle_initial',
                'contact_number',
                'age_range',
            ]);
        });
    }
};

