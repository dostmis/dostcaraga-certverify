<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('activity_type', 60)->nullable()->after('training_title');
            $table->string('certificate_type', 80)->nullable()->after('activity_type');
            $table->index('activity_type');
            $table->index('certificate_type');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropIndex(['activity_type']);
            $table->dropIndex(['certificate_type']);
            $table->dropColumn(['activity_type', 'certificate_type']);
        });
    }
};
