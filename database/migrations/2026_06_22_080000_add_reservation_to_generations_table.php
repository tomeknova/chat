<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reservation + reproducible telemetry on generations (decision R):
 * one generation row + at most one active executor, lease-based CAS takeover,
 * per-attempt history in metadata. The row is created BEFORE the AI call, so
 * message_id / model / infra_status become nullable (set on finalize).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->foreignId('message_id')->nullable()->change();
            $table->string('model')->nullable()->change();
            $table->string('infra_status')->nullable()->change();

            $table->string('status')->nullable()->after('infra_status'); // pending | processing | completed | failed
            $table->uuid('processing_owner')->nullable()->after('status');
            $table->timestamp('processing_started_at')->nullable()->after('processing_owner');
            $table->timestamp('lease_expires_at')->nullable()->after('processing_started_at');
            $table->string('request_fingerprint')->nullable()->after('lease_expires_at');
            $table->unsignedSmallInteger('execution_attempt')->default(1)->after('request_fingerprint');
            $table->json('metadata')->nullable()->after('execution_attempt');

            // Orphan-release scan: expired `processing` rows.
            $table->index(['status', 'lease_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->dropIndex(['status', 'lease_expires_at']);
            $table->dropColumn([
                'status', 'processing_owner', 'processing_started_at',
                'lease_expires_at', 'request_fingerprint', 'execution_attempt', 'metadata',
            ]);
        });
    }
};
