<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verification telemetry: one row per lookup attempt on /verify, successes and
 * failures alike.
 *
 * Failures are the point as much as successes — hits on revoked certificates
 * and bursts of malformed tokens are the integrity signal, and a log of only
 * the happy path cannot show them.
 *
 * Privacy: no raw IP, no user-agent string, no PII. `ip_hash` is a peppered
 * SHA-256 (see VerificationLogger) and exists solely so "unique verifiers" can
 * be distinguished from "page loads"; it is not reversible to an address.
 * Raw rows are pruned after 24 months by `analytics:prune-verifications`; the
 * aggregates survive in the materialized views.
 *
 * Plain table (not a view), so it works on SQLite under test.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_verifications', function (Blueprint $table) {
            $table->id();

            // Null for not_found / malformed: there is no certificate to point at.
            $table->foreignId('certificate_id')->nullable()
                ->constrained('certificates')->nullOnDelete();

            // valid | revoked | invalid | not_found | malformed
            $table->string('outcome', 20);

            // sha256(ip + pepper) — 64 hex chars. Nullable: a request without a
            // resolvable IP still counts as a verification.
            $table->char('ip_hash', 64)->nullable();

            // Coarse bucket only: mobile | desktop | bot | unknown.
            $table->string('ua_class', 10)->default('unknown');

            // Host only, never the full URL (which can carry query strings).
            $table->string('referer_host', 255)->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
            $table->index(['certificate_id', 'created_at']);
            $table->index(['outcome', 'created_at']);
            $table->index('ip_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_verifications');
    }
};
