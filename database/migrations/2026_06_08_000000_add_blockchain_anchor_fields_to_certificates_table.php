<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds Hedera Consensus Service (HCS) anchoring metadata. All columns are
     * additive and nullable; existing certificates and flows are unaffected.
     */
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // SHA-256 of the canonical certificate payload that was anchored.
            $table->string('blockchain_payload_hash', 64)->nullable()->after('remarks');
            // HCS topic the message was submitted to (e.g. 0.0.123456).
            $table->string('blockchain_topic_id', 32)->nullable()->after('blockchain_payload_hash');
            // Topic message sequence number returned by Hedera.
            $table->unsignedBigInteger('blockchain_sequence_number')->nullable()->after('blockchain_topic_id');
            // Consensus timestamp string (e.g. 1718289127.123456789).
            $table->string('blockchain_consensus_timestamp', 40)->nullable()->after('blockchain_sequence_number');
            // Hedera transaction id (e.g. 0.0.1234@1718289120.000000000).
            $table->string('blockchain_transaction_id', 64)->nullable()->after('blockchain_consensus_timestamp');
            // pending | anchored | failed | disabled
            $table->string('blockchain_status', 20)->nullable()->after('blockchain_transaction_id');
            $table->text('blockchain_error')->nullable()->after('blockchain_status');
            $table->timestamp('blockchain_anchored_at')->nullable()->after('blockchain_error');

            $table->index('blockchain_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropIndex(['blockchain_status']);
            $table->dropColumn([
                'blockchain_payload_hash',
                'blockchain_topic_id',
                'blockchain_sequence_number',
                'blockchain_consensus_timestamp',
                'blockchain_transaction_id',
                'blockchain_status',
                'blockchain_error',
                'blockchain_anchored_at',
            ]);
        });
    }
};
