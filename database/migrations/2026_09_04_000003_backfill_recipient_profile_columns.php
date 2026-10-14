<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repair for a migration ordering bug that broke fresh databases.
     *
     * `2026_05_29_000001_add_profile_fields_to_recipients_table` and
     * `2026_05_29_000002_add_name_parts_to_recipients_table` sort before
     * `2026_06_01_000001_create_recipients_table`, so on a fresh database they
     * ran against a table that did not exist yet and `migrate` aborted. Those
     * two now skip when the table is absent, and this migration adds the same
     * columns afterwards.
     *
     * Every column is guarded by hasColumn(), so this is a no-op on an
     * already-migrated environment where the original two succeeded.
     */
    public function up(): void
    {
        if (! Schema::hasTable('recipients')) {
            return;
        }

        Schema::table('recipients', function (Blueprint $table) {
            foreach ($this->stringColumns() as $column) {
                if (! Schema::hasColumn('recipients', $column)) {
                    $table->string($column)->nullable();
                }
            }

            foreach ($this->jsonColumns() as $column) {
                if (! Schema::hasColumn('recipients', $column)) {
                    $table->json($column)->nullable();
                }
            }

            if (! Schema::hasColumn('recipients', 'middle_initial')) {
                $table->string('middle_initial', 10)->nullable();
            }
        });
    }

    /**
     * Deliberately empty.
     *
     * These columns belong to the two 2026_05_29 migrations; their own down()
     * methods drop them. Dropping them here as well would fail on a rollback
     * of an already-migrated environment.
     */
    public function down(): void {}

    /**
     * @return array<int, string>
     */
    private function stringColumns(): array
    {
        return [
            'first_name',
            'last_name',
            'block_lot_purok',
            'region',
            'province',
            'city_municipality',
            'barangay',
            'industry',
            'organization_name',
            'position_designation',
            'age_range',
            'pwd_status',
            'is_4ps_beneficiary',
            'is_elcac_community',
            'has_attended_dost_training',
            'interested_dost_services_other',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function jsonColumns(): array
    {
        return [
            'dost_program_beneficiary',
            'directly_employed_programs',
            'interested_dost_services',
        ];
    }
};
