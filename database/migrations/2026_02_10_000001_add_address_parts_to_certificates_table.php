<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('block_lot_purok')->nullable()->after('address');
            $table->string('region')->nullable()->after('block_lot_purok');
            $table->string('city_municipality')->nullable()->after('region');
            $table->string('barangay')->nullable()->after('city_municipality');
            $table->string('province')->nullable()->after('barangay');

            $table->index('region');
            $table->index('city_municipality');
            $table->index('barangay');
            $table->index('province');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropIndex(['region']);
            $table->dropIndex(['city_municipality']);
            $table->dropIndex(['barangay']);
            $table->dropIndex(['province']);

            $table->dropColumn([
                'block_lot_purok',
                'region',
                'city_municipality',
                'barangay',
                'province',
            ]);
        });
    }
};
