<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The units the validator decided on, per generation. Only `accepted`
        // units are rendered (in display_ordinal order).
        Schema::create('message_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generation_id')->constrained()->cascadeOnDelete();
            $table->string('answer_unit_id');
            // 'accepted' | 'rejected_unknown_unit' | 'rejected_hash_mismatch' | 'rejected_injection'.
            $table->string('validation_status');
            // Render order; only set for accepted units.
            $table->unsignedSmallInteger('display_ordinal')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_units');
    }
};
