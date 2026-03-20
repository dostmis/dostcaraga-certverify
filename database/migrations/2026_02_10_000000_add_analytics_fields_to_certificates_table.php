<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('topic')->nullable()->after('training_title');
            $table->string('gender', 10)->nullable()->after('participant_name');
            $table->unsignedTinyInteger('age')->nullable()->after('gender');
            $table->string('address')->nullable()->after('age');
            $table->string('industry')->nullable()->after('address');

            $table->index('topic');
            $table->index('gender');
            $table->index('industry');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropIndex(['topic']);
            $table->dropIndex(['gender']);
            $table->dropIndex(['industry']);

            $table->dropColumn(['topic', 'gender', 'age', 'address', 'industry']);
        });
    }
};
