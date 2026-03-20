<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_intake_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_name', 255);
            $table->uuid('public_token')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::table('participant_intakes', function (Blueprint $table) {
            $table->foreignId('participant_intake_event_id')
                ->nullable()
                ->after('id')
                ->constrained('participant_intake_events')
                ->nullOnDelete();
            $table->foreignId('owner_user_id')
                ->nullable()
                ->after('participant_intake_event_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['owner_user_id', 'status']);
            $table->index(['participant_intake_event_id', 'status']);
        });

        DB::table('participant_intakes')
            ->whereNull('owner_user_id')
            ->update([
                'owner_user_id' => DB::raw('reviewed_by'),
            ]);
    }

    public function down(): void
    {
        Schema::table('participant_intakes', function (Blueprint $table) {
            $table->dropIndex('participant_intakes_owner_user_id_status_index');
            $table->dropIndex('participant_intakes_participant_intake_event_id_status_index');
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropConstrainedForeignId('participant_intake_event_id');
        });

        Schema::dropIfExists('participant_intake_events');
    }
};
