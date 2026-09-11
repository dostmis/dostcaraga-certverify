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
            // Horizontal nudge offsets in CSS pixels (1/96 inch), applied on top
            // of the resolved name alignment and the default signature placement.
            $table->smallInteger('name_offset_x')->default(0)->after('name_alignment');
            $table->smallInteger('signature_offset_x')->default(0)->after('name_offset_x');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn(['name_offset_x', 'signature_offset_x']);
        });
    }
};
