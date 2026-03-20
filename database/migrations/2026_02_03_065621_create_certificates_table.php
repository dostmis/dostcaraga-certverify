<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();

            // Public certificate code (what you print / show to users)
            $table->string('certificate_code')->unique();

            $table->string('participant_name');
            $table->string('training_title');
            $table->date('training_date');
            $table->string('issuing_office');

            // valid | invalid | revoked
            $table->string('status')->default('valid');

            // optional: reason if revoked/invalid
            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index(['participant_name']);
            $table->index(['training_title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
