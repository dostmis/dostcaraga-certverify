<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link certificates back to the endorsement that produced them.
     *
     * Required by the asynchronous generation rollback: when a batch fails
     * part-way through, rollback must delete exactly the certificates this
     * endorsement created and nothing else. Nullable because the direct-RD
     * `store` path has no endorsement, and historic rows cannot be attributed
     * — a NULL link also permanently protects those rows from rollback, since
     * every rollback query is scoped to a non-null endorsement_id.
     */
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->foreignId('endorsement_id')
                ->nullable()
                ->after('id')
                ->constrained('certificate_endorsements')
                ->nullOnDelete();

            // The participant's zero-based position in the endorsement's
            // participants file. Identifies a row for the queued generation
            // job's idempotency guard; participant_name cannot, because one
            // package may legitimately contain two people with the same name.
            $table->unsignedInteger('endorsement_participant_index')
                ->nullable()
                ->after('endorsement_id');

            $table->index(['endorsement_id', 'participant_name'], 'certificates_endorsement_participant_index');

            // Database-level backstop for the job's check-then-insert guard:
            // a retried job cannot double-insert even if it loses the race.
            // NULLs do not collide, so the direct-RD path and historic rows
            // are unaffected.
            $table->unique(
                ['endorsement_id', 'endorsement_participant_index'],
                'certificates_endorsement_slot_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropUnique('certificates_endorsement_slot_unique');
            $table->dropIndex('certificates_endorsement_participant_index');
            $table->dropConstrainedForeignId('endorsement_id');
            $table->dropColumn('endorsement_participant_index');
        });
    }
};
