<?php

namespace Tests\Feature;

use App\Enums\MessageRole;
use App\Enums\ProductStatus;
use App\Enums\Rating;
use App\Livewire\Chat;
use App\Models\Conversation;
use App\Models\Generation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private string $corpusDir;

    private string $corpusPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Isolated corpus dir: the Chat component derives output_path from
        // output_dir + active profile, so the test corpus must live there as
        // corpus-kings5-docs.json (and the suite never reaches real storage).
        $this->corpusDir = sys_get_temp_dir().'/chat_corpus_'.uniqid();
        @mkdir($this->corpusDir, 0775, true);
        $this->corpusPath = $this->corpusDir.'/corpus-kings5-docs.json';

        config([
            'askdocs.default' => 'openrouter',
            'askdocs.providers.openrouter' => [
                'driver' => 'openrouter',
                'base_url' => 'https://openrouter.ai/api/v1',
                'key' => 'test-key',
                'model' => 'openai/gpt-5.4-nano',
                'providers' => ['openai', 'azure'],
            ],
            'corpus.output_dir' => $this->corpusDir,
            'corpus.output_path' => $this->corpusPath,
            'corpus.active_profile' => 'kings5-docs',
            'corpus.base_url' => '',
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->corpusDir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->corpusDir);

        parent::tearDown();
    }

    private function writeCorpus(): void
    {
        $units = [
            ['answer_unit_id' => 'start.logowanie', 'content' => "## Logowanie\n\nWejdź na /admin i zaloguj się.", 'content_hash' => hash('sha256', 'logowanie'), 'intents' => [], 'canonical_url' => '/start/logowanie'],
        ];
        @mkdir(dirname($this->corpusPath), 0775, true);
        file_put_contents($this->corpusPath, json_encode(['units' => $units]));
    }

    private function fakeAnswer(array $unitIds = ['start.logowanie']): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['response_type' => 'answer', 'answer_unit_ids' => $unitIds])]]],
                'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 15, 'cost' => 0.0001],
            ]),
        ]);
    }

    public function test_mount_shows_greeting_without_creating_conversation(): void
    {
        Livewire::test(Chat::class)
            ->assertCount('messages', 1)
            ->assertSee('Zadaj pytanie o panel KINGS');

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_grounded_answer_is_persisted_and_shown(): void
    {
        $this->writeCorpus();
        $this->fakeAnswer();

        Livewire::test(Chat::class)
            ->set('question', 'Jak się zalogować?')
            ->call('sendMessage')
            ->assertSet('question', '')
            ->assertSee('Wejdź na /admin')
            ->assertSee('Źródło: Logowanie'); // descriptive label = unit heading

        $this->assertDatabaseHas('messages', ['role' => 'user', 'content' => 'Jak się zalogować?']);
        $this->assertDatabaseHas('messages', ['role' => 'assistant', 'product_status' => 'answered']);
    }

    public function test_question_pii_is_redacted_before_storage(): void
    {
        $this->writeCorpus();
        $this->fakeAnswer();

        Livewire::test(Chat::class)
            ->set('question', 'Mój email to jan.kowalski@example.com proszę o pomoc')
            ->call('sendMessage');

        $user = Message::where('role', MessageRole::User->value)->firstOrFail();
        $this->assertStringNotContainsString('@example.com', $user->content);
        $this->assertStringContainsString('[email]', $user->content);
    }

    public function test_conversation_is_keyed_by_owner_token_hash(): void
    {
        $this->writeCorpus();
        $this->fakeAnswer();

        Livewire::withCookies(['kings_chat_owner' => 'token-abc'])
            ->test(Chat::class)
            ->set('question', 'Jak się zalogować?')
            ->call('sendMessage');

        // Raw token never stored; only its peppered hash.
        $expected = hash('sha256', (string) config('chat.owner_token_pepper').'token-abc');
        $this->assertDatabaseHas('conversations', ['owner_token_hash' => $expected]);
        $this->assertDatabaseMissing('conversations', ['owner_token_hash' => 'token-abc']);
    }

    public function test_rating_sets_and_toggles(): void
    {
        $this->writeCorpus();
        $this->fakeAnswer();

        $component = Livewire::test(Chat::class)
            ->set('question', 'Jak się zalogować?')
            ->call('sendMessage');

        $assistant = Message::where('role', MessageRole::Assistant->value)->firstOrFail();

        $component->call('rate', $assistant->id, 'up');
        $this->assertSame(Rating::Up, $assistant->refresh()->rating);

        $component->call('rate', $assistant->id, 'up');
        $this->assertNull($assistant->refresh()->rating);
    }

    public function test_short_question_is_rejected(): void
    {
        Livewire::test(Chat::class)
            ->set('question', 'a')
            ->call('sendMessage')
            ->assertHasErrors(['question']);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_send_is_blocked_when_ai_is_disabled(): void
    {
        config(['chat.ai_enabled' => false]);

        Livewire::test(Chat::class)
            ->set('question', 'Jak się zalogować?')
            ->call('sendMessage')
            ->assertHasErrors(['question']);

        $this->assertDatabaseMissing('messages', ['content' => 'Jak się zalogować?']);
    }

    public function test_send_is_blocked_when_daily_budget_exhausted(): void
    {
        config(['chat.ai_enabled' => true, 'chat.daily_budget_usd' => 0.5]);
        Generation::factory()->create(['cost' => 0.9]);

        Livewire::test(Chat::class)
            ->set('question', 'Pytanie ponad budżet?')
            ->call('sendMessage')
            ->assertHasErrors(['question']);

        $this->assertDatabaseMissing('messages', ['content' => 'Pytanie ponad budżet?']);
    }

    public function test_welcome_shows_starter_suggestions(): void
    {
        config(['chat.suggestions' => ['Jak utworzyć nowe wydarzenie?']]);

        Livewire::test(Chat::class)
            ->assertSee('Jak utworzyć nowe wydarzenie?'); // starter chip under the greeting
    }

    public function test_clicking_a_suggestion_sends_it(): void
    {
        $this->writeCorpus();
        $this->fakeAnswer();

        Livewire::test(Chat::class)
            ->call('ask', 'Jak się zalogować?')
            ->assertSee('Wejdź na /admin');

        $this->assertDatabaseHas('messages', ['role' => 'user', 'content' => 'Jak się zalogować?']);
    }

    public function test_reset_starts_a_fresh_conversation_keeping_history(): void
    {
        $this->writeCorpus();
        $this->fakeAnswer();

        $component = Livewire::test(Chat::class)
            ->set('question', 'Jak się zalogować?')
            ->call('sendMessage');

        $this->assertDatabaseCount('messages', 2); // user + assistant
        $firstConversationId = $component->get('conversationId');

        $component->call('resetChat')->assertSee('Witaj!');

        // Non-destructive: window cleared, but the old conversation + messages stay
        // (history for curation); a NEW conversation becomes active.
        $this->assertDatabaseCount('messages', 2);
        $this->assertDatabaseCount('conversations', 2);
        $newConversationId = $component->get('conversationId');
        $this->assertNotNull($newConversationId);
        $this->assertNotSame($firstConversationId, $newConversationId);
    }

    public function test_returning_user_gets_starter_suggestions_on_history(): void
    {
        config(['chat.suggestions' => ['Jak utworzyć nowe wydarzenie?']]);

        $hash = hash('sha256', (string) config('chat.owner_token_pepper').'token-ret');
        $conversation = Conversation::factory()->create(['owner_token_hash' => $hash]);
        $conversation->messages()->create(['profile' => $conversation->profile, 'role' => MessageRole::User, 'content' => 'pytanie', 'normalized_question_hash' => 'h']);
        $conversation->messages()->create(['profile' => $conversation->profile, 'role' => MessageRole::Assistant, 'content' => 'odpowiedź', 'product_status' => ProductStatus::Abstained]);

        // Returning user: greeting is skipped, so starters attach to the last assistant bubble.
        Livewire::withCookies(['kings_chat_owner' => 'token-ret'])
            ->test(Chat::class)
            ->assertSee('Jak utworzyć nowe wydarzenie?');
    }
}
