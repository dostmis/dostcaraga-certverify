<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_endorsements', function (Blueprint $table) {
            $table->id();
            $table->string('status', 32)->default('endorsed')->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rd_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rd_rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rd_approved_at')->nullable();
            $table->timestamp('rd_rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('participants_count')->nullable();
            $table->unsignedInteger('generated_count')->nullable();
            $table->string('participants_file_path');
            $table->string('template_pdf_path');
            $table->json('payload');
            $table->timestamps();

            $table->index(['submitted_by', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_endorsements');
    }
};
