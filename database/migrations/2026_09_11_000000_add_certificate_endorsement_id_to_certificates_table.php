<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records which endorsement package a certificate was generated from, so
     * the endorser can follow its email delivery. Nullable: certificates the
     * Regional Director creates directly have no endorsement.
     */
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->foreignId('certificate_endorsement_id')
                ->nullable()
                ->after('recipient_id')
                ->constrained('certificate_endorsements')
                ->nullOnDelete();
            $table->index('certificate_endorsement_id');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropIndex(['certificate_endorsement_id']);
            $table->dropConstrainedForeignId('certificate_endorsement_id');
        });
    }
};
