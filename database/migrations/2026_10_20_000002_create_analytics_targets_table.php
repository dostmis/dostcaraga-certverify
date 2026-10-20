<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Annual DOST Caraga targets, set per program by the Regional Director.
 *
 * Two deliberate shape decisions:
 *
 *  - Both metrics are columns rather than a `metric` enum + value. One row per
 *    program per year makes the edit screen a flat grid instead of a nested
 *    form, and neither metric is ever recorded without the other being at
 *    least considered.
 *  - `province_code` is in the unique index while always 0 today. Targets are
 *    per-program by requirement; carrying the column now is the difference
 *    between adding a column later and rewriting the key later. It is 0 (=
 *    region-wide) rather than NULL because Postgres treats NULLs in a unique
 *    index as distinct, which would silently permit duplicate rows for the
 *    same program and year. 0-for-region-wide is the same convention
 *    mv_public_reach already uses.
 *
 * `program_code` matches certificates.dost_program, which is varchar(255)
 * since 2026_03_04_000500 (the "Others" option is rewritten to free text).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_targets', function (Blueprint $table) {
            $table->id();

            $table->string('program_code', 255);
            $table->unsignedSmallInteger('period_year');
            // 0 = region-wide (always, today). See the class docblock.
            $table->unsignedBigInteger('province_code')->default(0);

            $table->unsignedInteger('target_certificates')->nullable();
            $table->unsignedInteger('target_people')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('updated_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['program_code', 'period_year', 'province_code'], 'analytics_targets_scope_uidx');
            $table->index('period_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_targets');
    }
};
