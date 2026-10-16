<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Analytics read-model: a dedicated `analytics` Postgres schema holding a PSGC
 * name->code crosswalk and four materialized views that power the dashboards.
 *
 * Postgres-only. Materialized views, schemas, and REFRESH CONCURRENTLY do not
 * exist on SQLite (the test driver), so every statement is guarded and the
 * migration is a no-op on any non-pgsql connection. The views are created WITH
 * DATA so that `REFRESH MATERIALIZED VIEW CONCURRENTLY` (which requires a prior
 * populated state + a unique index) works from the first `analytics:refresh`.
 *
 * Fully reversible: down() drops the whole schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('CREATE SCHEMA IF NOT EXISTS analytics');

        // Deterministic name normaliser, shared by the crosswalk's generated
        // column and every view join so both sides normalise identically.
        // Order mirrors the validated reference implementation:
        // lower -> strip parentheticals -> drop "city of "/" city" -> strip
        // non-alphanumerics -> collapse whitespace -> trim.
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION analytics.norm(t text) RETURNS text AS $$
    SELECT btrim(
        regexp_replace(
            regexp_replace(
                replace(
                    replace(
                        regexp_replace(lower(coalesce(t, '')), '\(.*?\)', ' ', 'g'),
                        'city of ', ' '
                    ),
                    ' city', ' '
                ),
                '[^a-z0-9 ]', ' ', 'g'
            ),
            '\s+', ' ', 'g'
        )
    );
$$ LANGUAGE sql IMMUTABLE;
SQL);

        // PSGC crosswalk: free-text geography name -> PSGC code, per level.
        // Municipality names repeat across provinces (e.g. "San Francisco"),
        // so municipality rows carry parent_code (the province PSGC) and joins
        // match on province + name. name_normalized is generated to guarantee
        // it is normalised exactly like the join side.
        DB::unprepared(<<<'SQL'
CREATE TABLE analytics.psgc_crosswalk (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    level          varchar(16) NOT NULL,              -- 'province' | 'municipality'
    psgc_code      bigint      NOT NULL,
    parent_code    bigint      NULL,                  -- province code for municipality rows
    name_canonical varchar(255) NOT NULL,
    name_normalized text GENERATED ALWAYS AS (analytics.norm(name_canonical)) STORED
);
CREATE UNIQUE INDEX psgc_crosswalk_level_code_uidx ON analytics.psgc_crosswalk (level, psgc_code);
CREATE INDEX psgc_crosswalk_lookup_idx ON analytics.psgc_crosswalk (level, name_normalized, parent_code);
SQL);

        // ---- mv_certificate_facts : one row per certificate (no PII) ----
        DB::unprepared(<<<'SQL'
CREATE MATERIALIZED VIEW analytics.mv_certificate_facts AS
SELECT
    c.id AS certificate_id,
    c.created_at::date AS issued_date,
    c.training_date,
    c.training_date_to,
    COALESCE(EXTRACT(YEAR FROM c.training_date)::int, 0) AS training_year,
    EXTRACT(MONTH FROM c.training_date)::int AS training_month,
    COALESCE(NULLIF(c.dost_program, ''), 'Unknown') AS program_code,
    COALESCE(NULLIF(c.activity_type, ''), 'Unknown') AS training_type,
    c.training_title,
    analytics.norm(c.training_title) AS training_title_norm,
    COALESCE(NULLIF(c.status, ''), 'unknown') AS cert_status,
    COALESCE(NULLIF(c.email_delivery_status, ''), 'none') AS email_delivery_status,
    c.recipient_id,
    COALESCE(NULLIF(c.gender, ''), 'Unknown') AS gender,
    CASE
        WHEN c.age IS NULL THEN 'Unknown'
        WHEN c.age < 18 THEN '<18'
        WHEN c.age < 25 THEN '18-24'
        WHEN c.age < 35 THEN '25-34'
        WHEN c.age < 45 THEN '35-44'
        WHEN c.age < 60 THEN '45-59'
        ELSE '60+'
    END AS age_band,
    COALESCE(xm.parent_code, xp.psgc_code) AS province_code,
    COALESCE(xpp.name_canonical, xp.name_canonical, 'Unknown') AS province_name,
    xm.psgc_code AS municipality_code,
    COALESCE(xm.name_canonical, 'Unknown') AS municipality_name,
    COALESCE(NULLIF(c.barangay, ''), 'Unknown') AS barangay_name,
    (c.recipient_id IS NOT NULL AND r.password IS NOT NULL) AS is_claimed,
    md5(COALESCE('r' || c.recipient_id::text, 'n' || analytics.norm(c.participant_name))) AS person_key,
    (COUNT(*) OVER (
        PARTITION BY md5(COALESCE('r' || c.recipient_id::text, 'n' || analytics.norm(c.participant_name)))
    ) > 1) AS is_repeat_participant
FROM certificates c
LEFT JOIN recipients r ON r.id = c.recipient_id
LEFT JOIN analytics.psgc_crosswalk xp
    ON xp.level = 'province' AND xp.name_normalized = analytics.norm(c.province)
-- Municipality is the authoritative geography: match exact, else the longest
-- word-boundary prefix (recovers "San Francisco Anao Aon" -> "San Francisco").
-- Prefer a match inside the named province, but fall back to a Caraga-wide
-- match so a mislabelled province (e.g. "Cabadbaran City" tagged Agusan del
-- Sur) still resolves; the true province is then taken from the municipality's
-- parent below.
LEFT JOIN LATERAL (
    SELECT x.psgc_code, x.name_canonical, x.parent_code
    FROM analytics.psgc_crosswalk x
    WHERE x.level = 'municipality'
      AND (
            analytics.norm(c.city_municipality) = x.name_normalized
         OR analytics.norm(c.city_municipality) LIKE x.name_normalized || ' %'
      )
    ORDER BY (x.parent_code = xp.psgc_code) DESC,
             (analytics.norm(c.city_municipality) = x.name_normalized) DESC,
             length(x.name_normalized) DESC
    LIMIT 1
) xm ON true
LEFT JOIN analytics.psgc_crosswalk xpp
    ON xpp.level = 'province' AND xpp.psgc_code = xm.parent_code
WITH DATA;
CREATE UNIQUE INDEX mv_certificate_facts_pk ON analytics.mv_certificate_facts (certificate_id);
CREATE INDEX mv_certificate_facts_dims ON analytics.mv_certificate_facts (training_year, program_code, province_code, gender, age_band);
SQL);

        // ---- mv_endorsement_facts : one row per endorsement ----
        DB::unprepared(<<<'SQL'
CREATE MATERIALIZED VIEW analytics.mv_endorsement_facts AS
SELECT
    e.id AS endorsement_id,
    COALESCE(u.role, 'unknown') AS submitter_role,
    e.submitted_by,
    COALESCE(NULLIF(e.payload->>'dost_program', ''), 'Unknown') AS program_code,
    COALESCE(e.participants_count, 0) AS participants_count,
    e.created_at AS submitted_at,
    COALESCE(e.rd_approved_at, e.rd_rejected_at) AS decided_at,
    CASE
        WHEN COALESCE(e.rd_approved_at, e.rd_rejected_at) IS NOT NULL
        THEN ROUND((EXTRACT(EPOCH FROM (COALESCE(e.rd_approved_at, e.rd_rejected_at) - e.created_at)) / 3600.0)::numeric, 2)
    END AS turnaround_hours,
    CASE
        WHEN e.status IN ('rd_approved', 'approved') THEN 'approved'
        WHEN e.status IN ('rd_rejected', 'rejected') THEN 'rejected'
        WHEN e.status = 'generating' THEN 'generating'
        ELSE 'pending'
    END AS outcome,
    (e.generation_error IS NOT NULL) AS had_generation_error
FROM certificate_endorsements e
LEFT JOIN users u ON u.id = e.submitted_by
WITH DATA;
CREATE UNIQUE INDEX mv_endorsement_facts_pk ON analytics.mv_endorsement_facts (endorsement_id);
SQL);

        // ---- mv_intake_facts : one row per intake submission (no PII) ----
        DB::unprepared(<<<'SQL'
CREATE MATERIALIZED VIEW analytics.mv_intake_facts AS
SELECT
    i.id AS intake_id,
    i.participant_intake_event_id AS event_id,
    i.owner_user_id,
    COALESCE(NULLIF(i.status, ''), 'pending') AS status,
    COALESCE(NULLIF(i.gender, ''), 'Unknown') AS gender,
    CASE
        WHEN i.age IS NULL THEN 'Unknown'
        WHEN i.age < 18 THEN '<18'
        WHEN i.age < 25 THEN '18-24'
        WHEN i.age < 35 THEN '25-34'
        WHEN i.age < 45 THEN '35-44'
        WHEN i.age < 60 THEN '45-59'
        ELSE '60+'
    END AS age_band,
    COALESCE(xm.parent_code, xp.psgc_code) AS province_code,
    COALESCE(xpp.name_canonical, xp.name_canonical, 'Unknown') AS province_name,
    xm.psgc_code AS municipality_code,
    COALESCE(xm.name_canonical, 'Unknown') AS municipality_name,
    (i.dost_program_beneficiary IS NOT NULL
        AND i.dost_program_beneficiary::text NOT IN ('null', '[]', '""', '')) AS has_program_engagement,
    (i.interested_dost_services IS NOT NULL
        AND i.interested_dost_services::text NOT IN ('null', '[]', '""', '')) AS has_service_interest,
    (i.has_attended_dost_training = 'Yes') AS attended_dost_training_before,
    EXISTS (
        SELECT 1 FROM certificates c
        WHERE (i.recipient_id IS NOT NULL AND c.recipient_id = i.recipient_id)
           OR (i.email IS NOT NULL AND c.email IS NOT NULL AND lower(c.email) = lower(i.email))
    ) AS resulted_in_certificate
FROM participant_intakes i
LEFT JOIN analytics.psgc_crosswalk xp
    ON xp.level = 'province' AND xp.name_normalized = analytics.norm(i.province)
LEFT JOIN LATERAL (
    SELECT x.psgc_code, x.name_canonical, x.parent_code
    FROM analytics.psgc_crosswalk x
    WHERE x.level = 'municipality'
      AND (
            analytics.norm(i.city_municipality) = x.name_normalized
         OR analytics.norm(i.city_municipality) LIKE x.name_normalized || ' %'
      )
    ORDER BY (x.parent_code = xp.psgc_code) DESC,
             (analytics.norm(i.city_municipality) = x.name_normalized) DESC,
             length(x.name_normalized) DESC
    LIMIT 1
) xm ON true
LEFT JOIN analytics.psgc_crosswalk xpp
    ON xpp.level = 'province' AND xpp.psgc_code = xm.parent_code
WITH DATA;
CREATE UNIQUE INDEX mv_intake_facts_pk ON analytics.mv_intake_facts (intake_id);
SQL);

        // ---- mv_public_reach : pre-aggregated, <5 suppressed, no barangay ----
        DB::unprepared(<<<'SQL'
CREATE MATERIALIZED VIEW analytics.mv_public_reach AS
SELECT
    training_year,
    COALESCE(province_code, 0) AS province_code,
    province_name,
    COALESCE(municipality_code, 0) AS municipality_code,
    municipality_name,
    program_code,
    gender,
    age_band,
    CASE WHEN COUNT(*) < 5 THEN NULL ELSE COUNT(*) END AS cert_count
FROM analytics.mv_certificate_facts
GROUP BY training_year, COALESCE(province_code, 0), province_name,
         COALESCE(municipality_code, 0), municipality_name, program_code, gender, age_band
WITH DATA;
CREATE UNIQUE INDEX mv_public_reach_pk ON analytics.mv_public_reach
    (training_year, province_code, municipality_code, program_code, gender, age_band);
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP SCHEMA IF EXISTS analytics CASCADE');
    }
};
