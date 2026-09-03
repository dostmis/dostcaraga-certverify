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
            // Side margins of the usable text area, in millimetres. Designs with
            // artwork down one edge need an asymmetric area so the name is
            // centred on the clear space rather than on the page. The defaults
            // reproduce the previous symmetric 30mm band exactly.
            $table->smallInteger('name_margin_left')->default(30)->after('name_alignment');
            $table->smallInteger('name_margin_right')->default(30)->after('name_margin_left');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn(['name_margin_left', 'name_margin_right']);
        });
    }
};
