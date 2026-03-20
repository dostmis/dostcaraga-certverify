<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        // 1) Add as nullable first (so existing rows won't fail)
        Schema::table('certificates', function (Blueprint $table) {
            $table->uuid('public_token')->nullable()->after('certificate_code');
        });

        // 2) Backfill existing rows
        DB::table('certificates')
            ->whereNull('public_token')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('certificates')
                        ->where('id', $row->id)
                        ->update(['public_token' => (string) Str::uuid()]);
                }
            });

        // 3) Make it NOT NULL
        DB::statement('ALTER TABLE certificates ALTER COLUMN public_token SET NOT NULL');

        // 4) Add UNIQUE constraint
        DB::statement('ALTER TABLE certificates ADD CONSTRAINT certificates_public_token_unique UNIQUE (public_token)');
    }

    public function down(): void
    {
        // Drop constraint first, then column
        DB::statement('ALTER TABLE certificates DROP CONSTRAINT IF EXISTS certificates_public_token_unique');
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn('public_token');
        });
    }
};
