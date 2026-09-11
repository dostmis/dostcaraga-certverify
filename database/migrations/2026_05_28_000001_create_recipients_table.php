<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard: on databases migrated before this file was reordered, the
        // `recipients` table already exists (it was previously created by a
        // later-dated migration). Skip creation there so this reordered
        // migration is a safe no-op.
        if (Schema::hasTable('recipients')) {
            return;
        }

        Schema::create('recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('contact_number', 30)->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('birthdate')->nullable();
            $table->string('password')->nullable();
            $table->uuid('claim_token')->unique()->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipients');
    }
};
