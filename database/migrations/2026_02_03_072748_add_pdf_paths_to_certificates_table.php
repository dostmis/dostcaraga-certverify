<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('source_pdf_path')->nullable()->after('remarks');
            $table->string('stamped_pdf_path')->nullable()->after('source_pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn(['source_pdf_path','stamped_pdf_path']);
        });
    }
};
