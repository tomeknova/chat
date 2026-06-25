<?php

use App\Enums\CorpusProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profile = the doc instruction a conversation belongs to. Authoritative on
     * the conversation; denormalized onto messages as the question-identity
     * namespace (profile, normalized_question_hash) so the same question text in
     * two instructions never collides. Safe pattern: nullable → backfill → NOT
     * NULL (NOT an env-baked schema default).
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('profile')->nullable()->after('owner_token_hash');
        });

        // All history so far belongs to the kings5-docs instruction.
        DB::table('conversations')->whereNull('profile')->update(['profile' => CorpusProfile::Kings5Docs->value]);

        Schema::table('conversations', function (Blueprint $table) {
            $table->string('profile')->nullable(false)->change();
            $table->index('profile');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('profile')->nullable()->after('conversation_id');
        });

        // Copy from the owning conversation (invariant: message.profile == conversation.profile).
        // Correlated subquery — portable across mysql and sqlite.
        DB::statement('UPDATE messages SET profile = (SELECT profile FROM conversations WHERE conversations.id = messages.conversation_id) WHERE profile IS NULL');
        // Orphans with no conversation → default.
        DB::table('messages')->whereNull('profile')->update(['profile' => CorpusProfile::Kings5Docs->value]);

        Schema::table('messages', function (Blueprint $table) {
            $table->string('profile')->nullable(false)->change();
            $table->index(['profile', 'normalized_question_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['profile']);
            $table->dropColumn('profile');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['profile', 'normalized_question_hash']);
            $table->dropColumn('profile');
        });
    }
};
