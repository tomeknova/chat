<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the model ACTUALLY saw in the prompt — the basis of validation
        // (answer_unit_id ∈ context + matching content_hash). Immutable snapshot.
        Schema::create('generation_context', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generation_id')->constrained()->cascadeOnDelete();
            $table->string('answer_unit_id');
            $table->string('content_hash', 64);

            $table->index(['generation_id', 'answer_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_context');
    }
};
