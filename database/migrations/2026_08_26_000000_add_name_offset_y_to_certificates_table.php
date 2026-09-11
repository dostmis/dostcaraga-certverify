<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // Vertical nudge in CSS pixels (1/96 inch), applied to the name
            // baseline. Negative moves the name up the page, positive down.
            // Uploaded templates place their sub-heading at their own height, so
            // the automatic fit is not always where an operator wants the name.
            $table->smallInteger('name_offset_y')->default(0)->after('name_offset_x');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn('name_offset_y');
        });
    }
};
