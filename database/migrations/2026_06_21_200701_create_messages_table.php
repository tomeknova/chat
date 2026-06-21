<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            // Enum values are written literally (historical contract, not Enum::cases()).
            $table->string('role'); // 'user' | 'assistant'
            // user = REDACTED question; assistant = composed by the backend.
            $table->text('content');
            // Dedup of user questions only.
            $table->string('normalized_question_hash', 64)->nullable()->index();
            // Assistant only: 'answered' | 'abstained' | 'needs_clarification'.
            $table->string('product_status')->nullable();
            // User feedback on an assistant message.
            $table->string('rating')->nullable();          // 'up' | 'down'
            $table->string('rating_reason_code')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
