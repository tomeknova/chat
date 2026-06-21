<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            // Public, opaque identifier (ULID) — never expose the auto-increment id.
            $table->char('public_id', 26)->unique();
            // Hashed anonymous owner token (cookie). RODO erasure keys off this.
            $table->string('owner_token_hash', 64)->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
