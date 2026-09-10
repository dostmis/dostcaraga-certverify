<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generation state for the queued approval path.
     *
     * Progress itself is read from the Bus batch rather than duplicated into
     * counter columns, so only the batch id is stored. No separate
     * generation_status column: the endorsement's own `status` carries
     * `generating`, and a failed package rolls back to `endorsed` with
     * generation_error set.
     */
    public function up(): void
    {
        Schema::table('certificate_endorsements', function (Blueprint $table) {
            $table->string('generation_batch_id')->nullable()->after('generated_count');
            $table->text('generation_error')->nullable()->after('generation_batch_id');
            $table->timestamp('generation_started_at')->nullable()->after('generation_error');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_endorsements', function (Blueprint $table) {
            $table->dropColumn(['generation_batch_id', 'generation_error', 'generation_started_at']);
        });
    }
};
