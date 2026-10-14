<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('certificates', 'state') && ! Schema::hasColumn('certificates', 'province')) {
            Schema::table('certificates', function (Blueprint $table) {
                $table->renameColumn('state', 'province');
            });

            // ALTER INDEX is Postgres-only; SQLite renames the index with the
            // column, so there is nothing to do there.
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER INDEX IF EXISTS certificates_state_index RENAME TO certificates_province_index');
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('certificates', 'province') && ! Schema::hasColumn('certificates', 'state')) {
            Schema::table('certificates', function (Blueprint $table) {
                $table->renameColumn('province', 'state');
            });

            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER INDEX IF EXISTS certificates_province_index RENAME TO certificates_state_index');
            }
        }
    }
};
