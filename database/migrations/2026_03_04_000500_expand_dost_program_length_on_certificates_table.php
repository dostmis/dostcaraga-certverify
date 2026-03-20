<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE certificates ALTER COLUMN dost_program TYPE VARCHAR(255)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE certificates ALTER COLUMN dost_program TYPE VARCHAR(20)');
    }
};
