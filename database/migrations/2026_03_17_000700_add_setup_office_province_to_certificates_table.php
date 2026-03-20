<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const SETUP_PROGRAM = 'SETUP (Small Enterprise Technology Upgrading Program)';
    private const NOT_APPLICABLE = 'Not Applicable';

    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('setup_office_province')->nullable()->after('dost_program');
        });

        DB::table('certificates')
            ->where('dost_program', self::SETUP_PROGRAM)
            ->update([
                'setup_office_province' => DB::raw("COALESCE(NULLIF(dost_project, ''), '" . self::NOT_APPLICABLE . "')"),
                'dost_project' => self::NOT_APPLICABLE,
                'project_code' => self::NOT_APPLICABLE,
            ]);

        DB::table('certificates')
            ->where(function ($query) {
                $query
                    ->whereNull('dost_program')
                    ->orWhere('dost_program', '!=', self::SETUP_PROGRAM);
            })
            ->update([
                'setup_office_province' => self::NOT_APPLICABLE,
            ]);
    }

    public function down(): void
    {
        DB::table('certificates')
            ->where('dost_program', self::SETUP_PROGRAM)
            ->update([
                'dost_project' => DB::raw("COALESCE(NULLIF(setup_office_province, ''), '" . self::NOT_APPLICABLE . "')"),
                'project_code' => null,
            ]);

        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn('setup_office_province');
        });
    }
};
