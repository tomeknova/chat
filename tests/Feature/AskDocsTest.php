<?php

namespace Tests\Feature;

use App\Actions\AskDocs;
use App\AskDocs\Adapters\OllamaChatModel;
use App\AskDocs\CircuitBreaker;
use App\AskDocs\Contracts\EndpointResolver;
use App\Enums\MessageRole;
use App\Enums\ProcessingStatus;
use App\Enums\ProductStatus;
use App\Models\Conversation;
use App\Models\Generation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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
            'askdocs.default' => 'openrouter',
            'askdocs.providers.openrouter' => [
                'driver' => 'openrouter',
                'base_url' => 'https://openrouter.ai/api/v1',
                'key' => 'test-key',
                'model' => 'openai/gpt-5.4-nano',
                'providers' => ['openai', 'azure'],
            ],
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
            ['answer_unit_id' => 'start.logowanie', 'content' => "## Logowanie\n\nWejdź na /admin i zaloguj się.", 'content_hash' => hash('sha256', 'logowanie'), 'intents' => ['Jak się zalogować?', 'Gdzie jest panel administracyjny?'], 'canonical_url' => '/start/logowanie'],
            ['answer_unit_id' => 'start.pulpit', 'content' => 'Pulpit pokazuje skróty.', 'content_hash' => hash('sha256', 'pulpit'), 'intents' => ['Co pokazuje pulpit?'], 'canonical_url' => '/start/pulpit'],
        ];

        @mkdir(dirname($this->corpusPath), 0775, true);
        file_put_contents($this->corpusPath, json_encode(['units' => $units]));
    }

    /**
     * Wider corpus where lexical recall returns a STRICT SUBSET — so escalation to
     * the full corpus genuinely adds context (drives the count-guard in escalate()).
     * Only the ticket/payment units share stems with the escalation questions; the
     * rest score 0 in lexical and are visible only via the full corpus.
     */
    private function writeWideCorpus(): void
    {
        $units = [
            ['answer_unit_id' => 'bilety.sprzedaz', 'content' => "## Sprzedaż biletów\n\nW sekcji Bilety włączysz sprzedaż biletów.", 'content_hash' => hash('sha256', 'bilety'), 'intents' => ['Jak włączyć sprzedaż biletów?'], 'canonical_url' => '/bilety/sprzedaz'],
            ['answer_unit_id' => 'platnosci.metody', 'content' => "## Płatności\n\nObsługujemy karty i przelewy.", 'content_hash' => hash('sha256', 'platnosci'), 'intents' => ['Jakie metody płatności?'], 'canonical_url' => '/platnosci/metody'],
            ['answer_unit_id' => 'wyglad.logo', 'content' => "## Logo i kolory\n\nZmień logo w Wyglądzie.", 'content_hash' => hash('sha256', 'logo'), 'intents' => ['Jak zmienić logo?'], 'canonical_url' => '/wyglad/logo'],
            ['answer_unit_id' => 'galeria.zdjecia', 'content' => "## Galeria\n\nDodaj zdjęcia do galerii.", 'content_hash' => hash('sha256', 'galeria'), 'intents' => ['Jak dodać zdjęcia?'], 'canonical_url' => '/galeria/zdjecia'],
            ['answer_unit_id' => 'kontakt.dane', 'content' => "## Kontakt\n\nUzupełnij dane kontaktowe.", 'content_hash' => hash('sha256', 'kontakt'), 'intents' => ['Gdzie podać kontakt?'], 'canonical_url' => '/kontakt/dane'],
        ];

        @mkdir(dirname($this->corpusPath), 0775, true);
        file_put_contents($this->corpusPath, json_encode(['units' => $units]));
    }

    /**
     * Configure the Bielik→OpenRouter escalation shape used by the escalation tests:
     * lexical primary retriever + a distinct fallback provider for the escalation
     * selector (so primary and escalation hit different fake URLs).
     */
    private function configureEscalation(): void
    {
        config([
            'askdocs.retrieval.driver' => 'lexical',
            'askdocs.fallback' => 'openrouter2',
            'askdocs.escalate_on_abstention' => true,
            'askdocs.providers.openrouter2' => [
                'driver' => 'openrouter',
                'base_url' => 'https://openrouter2.ai/api/v1',
                'key' => 'test-key',
                'model' => 'openai/gpt-5.4-nano',
                'providers' => ['openai'],
            ],
        ]);
    }

    /**
     * The system prompt a fake provider received (units the model actually saw).
     */
    private function systemPromptSentTo(string $urlNeedle): string
    {
        foreach (Http::recorded() as [$request]) {
            if (! str_contains($request->url(), $urlNeedle)) {
                continue;
            }
            $messages = $request->data()['messages'] ?? [];
            foreach ($messages as $message) {
                if (($message['role'] ?? null) === 'system') {
                    return (string) $message['content'];
                }
            }
        }

        return '';
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
        $this->assertSame('Logowanie', $result['sources'][0]['title']); // descriptive label = unit heading
        $this->assertSame([], $result['suggestions']); // no recovery chips on a successful answer

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
        // Recovery (Faza 7): abstention offers answerable questions from candidate intents.
        $this->assertNotEmpty($result['suggestions']);
        $this->assertContains('Jak się zalogować?', $result['suggestions']);
    }

    public function test_empty_corpus_abstains_without_calling_the_model(): void
    {
        Http::fake(); // any call would be a stray → assert none sent below

        $result = app(AskDocs::class)->handle($this->userMessage(), 'op-empty');

        $this->assertSame(ProductStatus::Abstained, $result['product_status']);
        Http::assertNothingSent();
        // One reservation row, finalized as a completed abstain — no model, no AI call.
        $this->assertDatabaseCount('generations', 1);
        $this->assertDatabaseHas('generations', ['operation_id' => 'op-empty', 'status' => 'completed', 'model' => null]);
    }

    public function test_abstention_falls_back_to_default_starters_when_no_intents_matched(): void
    {
        // Corpus with units that have no intents → suggestionsFrom() returns [] → fallback.
        $corpusData = [
            'units' => [
                ['answer_unit_id' => 'start.logowanie', 'content' => "## Logowanie\n\nWejdź na /admin.", 'content_hash' => hash('sha256', 'log'), 'intents' => [], 'canonical_url' => '/start/logowanie'],
            ],
        ];
        file_put_contents($this->corpusPath, json_encode($corpusData));

        $starters = ['Domyślne pytanie A', 'Domyślne pytanie B'];
        config(['chat.suggestions' => $starters]);

        $this->fakeModel('out_of_scope', []);

        $result = app(AskDocs::class)->handle($this->userMessage('Stolica Australii?'), 'op-fallback');

        $this->assertSame(ProductStatus::Abstained, $result['product_status']);
        $this->assertSame($starters, $result['suggestions']);
    }

    public function test_domain_escalation_recovers_when_lexical_misses_but_full_corpus_answers(): void
    {
        // Bielik (lexical, narrow) abstains; OpenRouter on the FULL corpus answers.
        $this->writeWideCorpus();
        $this->configureEscalation();

        Http::fake([
            // Primary (lexical context) abstains.
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['response_type' => 'out_of_scope', 'answer_unit_ids' => []])]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 5, 'cost' => 0.0001],
            ]),
            // Escalation (full corpus) selects the relevant unit.
            'openrouter2.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['response_type' => 'answer', 'answer_unit_ids' => ['bilety.sprzedaz']])]]],
                'usage' => ['prompt_tokens' => 300, 'completion_tokens' => 15, 'cost' => 0.0002],
            ]),
        ]);

        $result = app(AskDocs::class)->handle($this->userMessage('Jak skonfigurować sprzedaż biletów?'), 'op-escalate');

        $this->assertSame(ProductStatus::Answered, $result['product_status']);
        $this->assertStringContainsString('włączysz sprzedaż', $result['body']);
        $this->assertSame([], $result['suggestions']); // answered → no chips
        Http::assertSentCount(2); // primary + escalation

        // Context assertion (auditor F-04): the primary saw only the lexical subset,
        // the escalation saw the broader corpus — proving the second call mattered.
        $primaryPrompt = $this->systemPromptSentTo('openrouter.ai');
        $escalationPrompt = $this->systemPromptSentTo('openrouter2.ai');
        $this->assertStringContainsString('bilety.sprzedaz', $primaryPrompt);
        $this->assertStringNotContainsString('wyglad.logo', $primaryPrompt); // lexical excluded it
        $this->assertStringContainsString('wyglad.logo', $escalationPrompt); // full corpus included it
    }

    public function test_domain_escalation_merges_attempts_from_both_stages(): void
    {
        // Telemetry (auditor F-02): one generation row must record BOTH the primary
        // (abstaining) attempt and the escalation (answering) attempt.
        $this->writeWideCorpus();
        $this->configureEscalation();

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['response_type' => 'out_of_scope', 'answer_unit_ids' => []])]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 5, 'cost' => 0.0001],
            ]),
            'openrouter2.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['response_type' => 'answer', 'answer_unit_ids' => ['bilety.sprzedaz']])]]],
                'usage' => ['prompt_tokens' => 300, 'completion_tokens' => 15, 'cost' => 0.0002],
            ]),
        ]);

        app(AskDocs::class)->handle($this->userMessage('Jak skonfigurować sprzedaż biletów?'), 'op-merge');

        $generation = Generation::firstWhere('operation_id', 'op-merge');
        $providers = array_column($generation->metadata['attempts'], 'provider');
        $this->assertContains('openrouter', $providers);  // primary (Bielik slot)
        $this->assertContains('openrouter2', $providers); // escalation
        $this->assertSame('abstained', $generation->metadata['primary_outcome'] ?? null); // what the primary decided
    }

    public function test_domain_escalation_triggers_on_needs_clarification(): void
    {
        $this->writeWideCorpus();
        $this->configureEscalation();

        Http::fake([
            // Primary can't clarify.
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['response_type' => 'clarification', 'answer_unit_ids' => []])]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 5, 'cost' => 0.0001],
            ]),
            // Escalation answers with a valid unit.
            'openrouter2.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['response_type' => 'answer', 'answer_unit_ids' => ['bilety.sprzedaz']])]]],
                'usage' => ['prompt_tokens' => 300, 'completion_tokens' => 15, 'cost' => 0.0002],
            ]),
        ]);

        $result = app(AskDocs::class)->handle($this->userMessage('Jak skonfigurować sprzedaż biletów?'), 'op-clarify-escalate');

        $this->assertSame(ProductStatus::Answered, $result['product_status']);
        $this->assertStringContainsString('włączysz sprzedaż', $result['body']);
        Http::assertSentCount(2); // primary + escalation
    }

    public function test_off_topic_stays_abstained_when_escalation_also_abstains(): void
    {
        // Negative case (auditor F-04): genuinely-absent info → BOTH stages abstain,
        // nothing is fabricated, no unit rendered, recovery chips shown.
        $this->writeWideCorpus();
        $this->configureEscalation();

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['response_type' => 'out_of_scope', 'answer_unit_ids' => []])]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 5, 'cost' => 0.0001],
            ]),
            'openrouter2.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['response_type' => 'out_of_scope', 'answer_unit_ids' => []])]]],
                'usage' => ['prompt_tokens' => 300, 'completion_tokens' => 5, 'cost' => 0.0002],
            ]),
        ]);

        $result = app(AskDocs::class)->handle($this->userMessage('Czy obsługujecie płatności Bitcoin?'), 'op-offtopic');

        $this->assertSame(ProductStatus::Abstained, $result['product_status']);
        $this->assertSame([], $result['sources']);
        $this->assertNotEmpty($result['suggestions']); // recovery chips on abstention
        Http::assertSentCount(2); // primary (matched payment unit) + escalation
        $this->assertDatabaseMissing('message_units', ['validation_status' => 'accepted', 'display_ordinal' => 1]);
    }

    public function test_escalation_skipped_when_full_corpus_adds_no_context(): void
    {
        // Guard (auditor F-03): if the primary already saw the full corpus (retriever
        // = full → candidates == full set), escalation is a paid retry on identical
        // context and must be skipped — no second model call.
        $this->writeCorpus(); // 2 units
        config([
            'askdocs.retrieval.driver' => 'full', // primary already sees everything
            'askdocs.fallback' => 'openrouter2',
            'askdocs.escalate_on_abstention' => true,
            'askdocs.providers.openrouter2' => [
                'driver' => 'openrouter',
                'base_url' => 'https://openrouter2.ai/api/v1',
                'key' => 'test-key',
                'model' => 'openai/gpt-5.4-nano',
                'providers' => ['openai'],
            ],
        ]);

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['response_type' => 'out_of_scope', 'answer_unit_ids' => []])]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 5, 'cost' => 0.0001],
            ]),
            'openrouter2.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['response_type' => 'answer', 'answer_unit_ids' => ['start.logowanie']])]]],
                'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 15, 'cost' => 0.0002],
            ]),
        ]);

        $result = app(AskDocs::class)->handle($this->userMessage('Stolica Australii?'), 'op-noescalate');

        $this->assertSame(ProductStatus::Abstained, $result['product_status']);
        Http::assertSentCount(1); // escalation skipped — no second call
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openrouter2.ai'));
    }

    public function test_idempotent_on_repeated_operation_id(): void
    {
        $existing = Generation::factory()->create(['operation_id' => 'op-dup']);

        app(AskDocs::class)->handle($this->userMessage(), 'op-dup');

        Http::assertNothingSent();
        $this->assertDatabaseCount('generations', 1);
        $this->assertSame($existing->id, Generation::firstWhere('operation_id', 'op-dup')->id);
    }

    public function test_falls_back_to_openrouter_when_bielik_grounding_fails(): void
    {
        // Primary = Bielik (returns a unit ∉ context → grounding violation),
        // fallback = OpenRouter (grounds correctly). Grounding-in-attempt (Q).
        $this->writeCorpus();
        config([
            'askdocs.default' => 'bielik',
            'askdocs.fallback' => 'openrouter',
            'askdocs.providers.bielik' => [
                'driver' => 'ollama',
                'base_url' => 'http://localhost:11434/v1',
                'key' => null,
                'model' => 'bielik-11b-v3-q80:latest',
            ],
        ]);

        Http::fake([
            'localhost:11434/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['response_type' => 'answer', 'answer_unit_ids' => ['zmyslona.jednostka']])]]],
            ]),
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['response_type' => 'answer', 'answer_unit_ids' => ['start.logowanie']])]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 10, 'cost' => 0.0001],
            ]),
        ]);

        $result = app(AskDocs::class)->handle($this->userMessage(), 'op-failover');

        $this->assertSame(ProductStatus::Answered, $result['product_status']);
        $this->assertSame('/start/logowanie', $result['sources'][0]['canonical_url']);
        // Final generation = the provider that succeeded (OpenRouter), not Bielik.
        $generation = Generation::firstWhere('operation_id', 'op-failover');
        $this->assertSame('openai/gpt-5.4-nano', $generation->model);
        $this->assertSame(ProcessingStatus::Completed, $generation->status);
        // Both attempts are recorded in telemetry (Bielik grounding-fail + OpenRouter).
        $this->assertCount(2, $generation->metadata['attempts']);
    }

    public function test_skips_provider_with_open_circuit_without_calling_it(): void
    {
        $this->writeCorpus();
        Cache::flush();
        config([
            'askdocs.default' => 'bielik',
            'askdocs.fallback' => 'openrouter',
            'askdocs.breaker.threshold' => 1, // one failure opens it
            'askdocs.providers.bielik' => [
                'driver' => 'ollama',
                'base_url' => 'http://localhost:11434/v1',
                'key' => null,
                'model' => 'bielik-11b-v3-q80:latest',
            ],
        ]);

        // Trip Bielik's circuit up-front.
        (new CircuitBreaker)->recordFailure('bielik');

        // Only OpenRouter is faked: a stray call to Bielik would throw (preventStrayRequests).
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['response_type' => 'answer', 'answer_unit_ids' => ['start.logowanie']])]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 10, 'cost' => 0.0001],
            ]),
        ]);

        $result = app(AskDocs::class)->handle($this->userMessage(), 'op-open-circuit');

        $this->assertSame(ProductStatus::Answered, $result['product_status']);
        Http::assertSentCount(1); // Bielik never called
        $this->assertDatabaseHas('generations', ['operation_id' => 'op-open-circuit', 'model' => 'openai/gpt-5.4-nano']);
    }

    public function test_adapter_does_not_call_provider_when_no_budget_left(): void
    {
        Http::fake();

        $model = new OllamaChatModel([
            'driver' => 'ollama',
            'base_url' => 'http://localhost:11434/v1',
            'model' => 'bielik-11b-v3-q80:latest',
        ]);

        // Zero remaining budget → treated as a timeout, no HTTP call made.
        $result = $model->select([], 'pytanie testowe', 0);

        $this->assertFalse($result['ok']);
        Http::assertNothingSent();
    }

    public function test_ollama_adapter_is_down_when_endpoint_cannot_be_resolved(): void
    {
        Http::fake();

        $resolver = new class implements EndpointResolver
        {
            public function resolve(): ?string
            {
                return null; // unresolvable / outside allowlist
            }
        };

        $model = new OllamaChatModel(['driver' => 'ollama', 'model' => 'bielik-11b-v3-q80:latest'], $resolver);

        $result = $model->select([], 'pytanie');

        $this->assertFalse($result['ok']); // down → failover, no traffic to an unverified endpoint
        Http::assertNothingSent();
    }

    public function test_returns_busy_while_another_executor_holds_a_valid_lease(): void
    {
        Generation::factory()->create([
            'operation_id' => 'op-busy',
            'message_id' => null,
            'status' => ProcessingStatus::Processing,
            'processing_owner' => (string) Str::uuid(),
            'lease_expires_at' => now()->addSeconds(60), // valid lease
            'request_fingerprint' => null,
        ]);
        Http::fake();

        $result = app(AskDocs::class)->handle($this->userMessage(), 'op-busy');

        $this->assertSame(ProductStatus::Abstained, $result['product_status']);
        $this->assertStringContainsString('przetwarzane', $result['body']);
        Http::assertNothingSent();           // no second AI call
        $this->assertDatabaseCount('generations', 1); // no duplicate reservation
    }

    public function test_takes_over_an_expired_lease_and_answers(): void
    {
        $this->writeCorpus();
        $this->fakeModel('answer', ['start.logowanie']);

        $generation = Generation::factory()->create([
            'operation_id' => 'op-takeover',
            'message_id' => null,
            'status' => ProcessingStatus::Processing,
            'processing_owner' => (string) Str::uuid(),
            'lease_expires_at' => now()->subSeconds(10), // expired → crashed executor
            'request_fingerprint' => null,
            'model' => null,
            'response_type' => null,
            'infra_status' => null,
            'execution_attempt' => 1,
        ]);

        $result = app(AskDocs::class)->handle($this->userMessage(), 'op-takeover');

        $this->assertSame(ProductStatus::Answered, $result['product_status']);
        $this->assertDatabaseCount('generations', 1); // same row reclaimed, not duplicated

        $generation->refresh();
        $this->assertSame(ProcessingStatus::Completed, $generation->status);
        $this->assertSame(2, $generation->execution_attempt);
    }

    public function test_conflict_when_same_operation_id_carries_a_different_question(): void
    {
        Generation::factory()->create([
            'operation_id' => 'op-conflict',
            'status' => ProcessingStatus::Processing,
            'lease_expires_at' => now()->addSeconds(60),
            'request_fingerprint' => 'a-different-fingerprint',
        ]);
        Http::fake();

        $result = app(AskDocs::class)->handle($this->userMessage(), 'op-conflict');

        $this->assertStringContainsString('konflikt', mb_strtolower($result['body']));
        Http::assertNothingSent();
    }
}
