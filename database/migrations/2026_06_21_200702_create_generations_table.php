<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            // Idempotency: one submit = one operation, double-click never double-charges.
            $table->string('operation_id')->unique();
            $table->string('model');
            // 'answer' | 'clarification' | 'abstention' | 'out_of_scope'.
            $table->string('response_type')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('cost', 12, 8)->nullable();
            // 'completed' | 'invalid_schema' | 'provider_timeout' | 'provider_refusal' | 'corpus_integrity_error' | ...
            $table->string('infra_status');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
