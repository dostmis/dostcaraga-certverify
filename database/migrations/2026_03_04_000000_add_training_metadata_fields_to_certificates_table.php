<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('venue')->nullable();
            $table->unsignedSmallInteger('number_of_training_hours')->nullable();
            $table->string('dost_program', 20)->nullable();
            $table->string('pillar', 80)->nullable();
            $table->unsignedInteger('expected_number_of_participants')->nullable();

            $table->index('venue');
            $table->index('dost_program');
            $table->index('pillar');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropIndex(['venue']);
            $table->dropIndex(['dost_program']);
            $table->dropIndex(['pillar']);

            $table->dropColumn([
                'venue',
                'number_of_training_hours',
                'dost_program',
                'pillar',
                'expected_number_of_participants',
            ]);
        });
    }
};
