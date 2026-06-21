<?php

namespace Tests\Feature;

use App\Actions\AskDocs;
use App\Enums\MessageRole;
use App\Enums\ProductStatus;
use App\Models\Conversation;
use App\Models\Generation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AskDocsTest extends TestCase
{
    use RefreshDatabase;

    private string $corpusPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->corpusPath = storage_path('app/corpus/test-corpus-'.uniqid().'.json');
        config([
            'ai.key' => 'test-key',
            'ai.base_url' => 'https://openrouter.ai/api/v1',
            'ai.model' => 'openai/gpt-5.4-nano',
            'ai.providers' => ['openai', 'azure'],
            'corpus.output_path' => $this->corpusPath,
            'corpus.base_url' => '',
        ]);

        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        @unlink($this->corpusPath);

        parent::tearDown();
    }

    private function writeCorpus(): void
    {
        $units = [
            ['answer_unit_id' => 'start.logowanie', 'content' => "## Logowanie\n\nWejdź na /admin i zaloguj się.", 'content_hash' => hash('sha256', 'logowanie'), 'intents' => [], 'canonical_url' => '/start/logowanie'],
            ['answer_unit_id' => 'start.pulpit', 'content' => 'Pulpit pokazuje skróty.', 'content_hash' => hash('sha256', 'pulpit'), 'intents' => [], 'canonical_url' => '/start/pulpit'],
        ];

        @mkdir(dirname($this->corpusPath), 0775, true);
        file_put_contents($this->corpusPath, json_encode(['units' => $units]));
    }

    private function fakeModel(string $responseType, array $unitIds): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['response_type' => $responseType, 'answer_unit_ids' => $unitIds])]]],
                'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 15, 'cost' => 0.0001],
            ]),
        ]);
    }

    private function userMessage(string $content = 'Jak się zalogować?'): Message
    {
        return Message::factory()->create([
            'conversation_id' => Conversation::factory(),
            'role' => MessageRole::User,
            'content' => $content,
        ]);
    }

    public function test_answered_renders_only_validated_unit_and_persists_chain(): void
    {
        $this->writeCorpus();
        $this->fakeModel('answer', ['start.logowanie']);

        $result = app(AskDocs::class)->handle($this->userMessage(), 'op-answer');

        $this->assertSame(ProductStatus::Answered, $result['product_status']);
        $this->assertStringContainsString('Wejdź na /admin', $result['body']);
        $this->assertStringNotContainsString('##', $result['body']); // heading markers stripped
        $this->assertSame('/start/logowanie', $result['sources'][0]['canonical_url']);

        $this->assertDatabaseHas('messages', ['role' => 'assistant', 'product_status' => 'answered']);
        $this->assertDatabaseHas('generations', ['operation_id' => 'op-answer', 'response_type' => 'answer', 'infra_status' => 'completed']);
        $this->assertDatabaseCount('generation_context', 2); // both candidates recorded (what model saw)
        $this->assertDatabaseHas('generation_context', ['answer_unit_id' => 'start.logowanie', 'content_hash' => hash('sha256', 'logowanie')]);
        $this->assertDatabaseHas('message_units', ['answer_unit_id' => 'start.logowanie', 'validation_status' => 'accepted', 'display_ordinal' => 1]);
    }

    public function test_bracketed_unit_ids_are_normalized(): void
    {
        // The model sometimes echoes the prompt delimiter, e.g. "[start.logowanie]".
        $this->writeCorpus();
        $this->fakeModel('answer', ['[start.logowanie]']);

        $result = app(AskDocs::class)->handle($this->userMessage(), 'op-bracket');

        $this->assertSame(ProductStatus::Answered, $result['product_status']);
        $this->assertSame('/start/logowanie', $result['sources'][0]['canonical_url']);
    }

    public function test_atomic_reject_when_a_selected_unit_is_outside_context(): void
    {
        $this->writeCorpus();
        $this->fakeModel('answer', ['start.logowanie', 'zmyslona.jednostka']);

        $result = app(AskDocs::class)->handle($this->userMessage(), 'op-atomic');

        // One bad unit → whole set rejected → abstention, nothing rendered.
        $this->assertSame(ProductStatus::Abstained, $result['product_status']);
        $this->assertSame([], $result['sources']);
        $this->assertDatabaseHas('message_units', ['answer_unit_id' => 'zmyslona.jednostka', 'validation_status' => 'rejected_unknown_unit']);
        $this->assertDatabaseHas('message_units', ['answer_unit_id' => 'start.logowanie', 'validation_status' => 'accepted', 'display_ordinal' => null]);
    }

    public function test_out_of_scope_abstains(): void
    {
        $this->writeCorpus();
        $this->fakeModel('out_of_scope', []);

        $result = app(AskDocs::class)->handle($this->userMessage('Stolica Australii?'), 'op-oos');

        $this->assertSame(ProductStatus::Abstained, $result['product_status']);
        $this->assertDatabaseHas('generations', ['operation_id' => 'op-oos', 'response_type' => 'out_of_scope']);
    }

    public function test_empty_corpus_abstains_without_calling_the_model(): void
    {
        Http::fake(); // any call would be a stray → assert none sent below

        $result = app(AskDocs::class)->handle($this->userMessage(), 'op-empty');

        $this->assertSame(ProductStatus::Abstained, $result['product_status']);
        Http::assertNothingSent();
        $this->assertDatabaseCount('generations', 0);
    }

    public function test_idempotent_on_repeated_operation_id(): void
    {
        $existing = Generation::factory()->create(['operation_id' => 'op-dup']);

        app(AskDocs::class)->handle($this->userMessage(), 'op-dup');

        Http::assertNothingSent();
        $this->assertDatabaseCount('generations', 1);
        $this->assertSame($existing->id, Generation::firstWhere('operation_id', 'op-dup')->id);
    }
}
