<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participant_intakes', function (Blueprint $table) {
            $table->foreignId('recipient_id')
                ->nullable()
                ->after('owner_user_id')
                ->constrained('recipients')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('participant_intakes', function (Blueprint $table) {
            $table->dropForeign(['recipient_id']);
            $table->dropColumn('recipient_id');
        });
    }
};
