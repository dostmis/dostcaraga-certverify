<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 40)->default('organizer')->after('is_admin');
            $table->index('role');
        });

        DB::table('users')
            ->where('is_admin', true)
            ->update(['role' => 'regional_director']);

        DB::table('users')
            ->whereNull('role')
            ->update(['role' => 'organizer']);

        Schema::table('participant_intakes', function (Blueprint $table) {
            $table->timestamp('endorsed_at')->nullable()->after('status');
            $table->foreignId('endorsed_by')->nullable()->after('endorsed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rd_approved_at')->nullable()->after('endorsed_by');
            $table->foreignId('rd_approved_by')->nullable()->after('rd_approved_at')->constrained('users')->nullOnDelete();
            $table->index(['status', 'endorsed_at']);
            $table->index(['status', 'rd_approved_at']);
        });

        DB::table('participant_intakes')
            ->where('status', 'approved')
            ->update([
                'status' => 'rd_approved',
                'endorsed_at' => DB::raw('COALESCE(endorsed_at, created_at)'),
                'rd_approved_at' => DB::raw('COALESCE(rd_approved_at, reviewed_at, created_at)'),
                'rd_approved_by' => DB::raw('COALESCE(rd_approved_by, reviewed_by)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('participant_intakes', function (Blueprint $table) {
            $table->dropIndex('participant_intakes_status_endorsed_at_index');
            $table->dropIndex('participant_intakes_status_rd_approved_at_index');
            $table->dropConstrainedForeignId('rd_approved_by');
            $table->dropColumn('rd_approved_at');
            $table->dropConstrainedForeignId('endorsed_by');
            $table->dropColumn('endorsed_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_index');
            $table->dropColumn('role');
        });
    }
};

