<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipients', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('middle_initial', 10)->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_initial');
        });
    }

    public function down(): void
    {
        Schema::table('recipients', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'middle_initial', 'last_name']);
        });
    }
};
