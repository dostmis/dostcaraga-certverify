<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('certificates', 'state') && !Schema::hasColumn('certificates', 'province')) {
            DB::statement('ALTER TABLE certificates RENAME COLUMN state TO province');
            DB::statement('ALTER INDEX IF EXISTS certificates_state_index RENAME TO certificates_province_index');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('certificates', 'province') && !Schema::hasColumn('certificates', 'state')) {
            DB::statement('ALTER TABLE certificates RENAME COLUMN province TO state');
            DB::statement('ALTER INDEX IF EXISTS certificates_province_index RENAME TO certificates_state_index');
        }
    }
};
