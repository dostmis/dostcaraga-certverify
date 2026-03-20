<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('email_delivery_status', 40)->nullable()->after('stamped_pdf_path');
            $table->timestamp('email_queued_at')->nullable()->after('email_delivery_status');
            $table->timestamp('email_last_attempt_at')->nullable()->after('email_queued_at');
            $table->timestamp('email_sent_at')->nullable()->after('email_last_attempt_at');
            $table->timestamp('email_failed_at')->nullable()->after('email_sent_at');
            $table->text('email_failure_message')->nullable()->after('email_failed_at');

            $table->index('email_delivery_status');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropIndex(['email_delivery_status']);
            $table->dropColumn([
                'email_delivery_status',
                'email_queued_at',
                'email_last_attempt_at',
                'email_sent_at',
                'email_failed_at',
                'email_failure_message',
            ]);
        });
    }
};
