<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('dost_project')->nullable()->after('dost_program');
            $table->string('project_code', 30)->nullable()->after('dost_project');
            $table->index('project_code');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropIndex(['project_code']);
            $table->dropColumn(['dost_project', 'project_code']);
        });
    }
};
