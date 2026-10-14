<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * SQLite ignores VARCHAR length entirely, so this widening is a no-op
     * there and only needs to run on Postgres.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE certificates ALTER COLUMN dost_program TYPE VARCHAR(255)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE certificates ALTER COLUMN dost_program TYPE VARCHAR(20)');
    }
};
