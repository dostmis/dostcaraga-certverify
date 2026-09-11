<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('name_alignment', 20)->default('center')->after('participant_name');
            $table->boolean('qr_show_code')->default(true)->after('caption_alignment');
            $table->boolean('qr_show_link')->default(true)->after('qr_show_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn(['name_alignment', 'qr_show_code', 'qr_show_link']);
        });
    }
};
