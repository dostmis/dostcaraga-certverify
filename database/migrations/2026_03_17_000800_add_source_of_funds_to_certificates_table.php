<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const NOT_APPLICABLE = 'Not Applicable';

    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('source_of_funds')->nullable()->after('project_code');
        });

        DB::table('certificates')
            ->update([
                'source_of_funds' => self::NOT_APPLICABLE,
            ]);
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn('source_of_funds');
        });
    }
};
