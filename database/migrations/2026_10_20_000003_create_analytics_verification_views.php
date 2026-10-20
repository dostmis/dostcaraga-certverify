<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Verification read-model: two materialized views over certificate_verifications.
 *
 *  - mv_verification_facts: one row per *certificate*, carrying its verification
 *    counts plus the certificate dimensions, so the existing AnalyticsFilters
 *    (program / province / municipality / gender / age band / training date)
 *    apply to verification questions unchanged.
 *  - mv_verification_daily: date x outcome volume, for the trend line.
 *
 * Postgres-only and fully guarded, matching 2026_10_17_000001: materialized
 * views do not exist on the SQLite test driver. Created WITH DATA plus a unique
 * index so REFRESH MATERIALIZED VIEW CONCURRENTLY works from the first
 * analytics:refresh.
 *
 * Note mv_verification_facts is built on analytics.mv_certificate_facts, so it
 * must refresh after it — see AnalyticsRefresh::VIEWS for the ordering.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // ---- mv_verification_facts : one row per certificate ----
        // LEFT JOIN from the certificate side on purpose: a certificate with
        // zero verifications has to appear with a 0, otherwise "share of
        // certificates ever verified" would silently be computed over verified
        // certificates only and always read 100%.
        DB::unprepared(<<<'SQL'
CREATE MATERIALIZED VIEW analytics.mv_verification_facts AS
SELECT
    cf.certificate_id,
    cf.training_date,
    cf.training_year,
    cf.training_month,
    cf.program_code,
    cf.province_code,
    cf.province_name,
    cf.municipality_code,
    cf.municipality_name,
    cf.gender,
    cf.age_band,
    cf.cert_status,
    cf.training_title,
    cf.training_title_norm,
    cf.issued_date,
    COALESCE(v.total, 0) AS verification_count,
    COALESCE(v.unique_verifiers, 0) AS unique_verifier_count,
    COALESCE(v.revoked_hits, 0) AS revoked_hit_count,
    v.first_verified_at,
    v.last_verified_at,
    (COALESCE(v.total, 0) > 0) AS is_verified,
    CASE
        WHEN v.first_verified_at IS NOT NULL
        THEN ROUND((EXTRACT(EPOCH FROM (v.first_verified_at - cf.issued_date::timestamp)) / 86400.0)::numeric, 2)
    END AS days_to_first_verification
FROM analytics.mv_certificate_facts cf
LEFT JOIN (
    SELECT
        certificate_id,
        COUNT(*) AS total,
        COUNT(DISTINCT ip_hash) AS unique_verifiers,
        COUNT(*) FILTER (WHERE outcome = 'revoked') AS revoked_hits,
        MIN(created_at) AS first_verified_at,
        MAX(created_at) AS last_verified_at
    FROM certificate_verifications
    WHERE certificate_id IS NOT NULL
    GROUP BY certificate_id
) v ON v.certificate_id = cf.certificate_id
WITH DATA;
CREATE UNIQUE INDEX mv_verification_facts_pk ON analytics.mv_verification_facts (certificate_id);
CREATE INDEX mv_verification_facts_dims ON analytics.mv_verification_facts (training_year, program_code, province_code);
SQL);

        // ---- mv_verification_daily : date x outcome volume ----
        DB::unprepared(<<<'SQL'
CREATE MATERIALIZED VIEW analytics.mv_verification_daily AS
SELECT
    created_at::date AS verified_date,
    outcome,
    COUNT(*) AS total,
    COUNT(DISTINCT ip_hash) AS unique_verifiers
FROM certificate_verifications
GROUP BY created_at::date, outcome
WITH DATA;
CREATE UNIQUE INDEX mv_verification_daily_pk ON analytics.mv_verification_daily (verified_date, outcome);
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP MATERIALIZED VIEW IF EXISTS analytics.mv_verification_daily');
        DB::unprepared('DROP MATERIALIZED VIEW IF EXISTS analytics.mv_verification_facts');
    }
};
