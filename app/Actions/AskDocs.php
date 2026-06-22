<?php

namespace App\Actions;

use App\Actions\Corpus\CandidateRetriever;
use App\AskDocs\Contracts\AnswerUnitSelector;
use App\Enums\MessageRole;
use App\Enums\ProductStatus;
use App\Enums\ValidationStatus;
use App\Models\Generation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

/**
 * Action: AskDocs (grounded, anti-hallucination core — SCOPE_V1)
 *
 * The model only SELECTS approved answer-units ({response_type, answer_unit_ids}).
 * The backend renders ONLY units the model actually saw (answer_unit_id ∈
 * generation_context) and never free text. Multi-unit is atomic: if any selected
 * unit fails validation the whole set is rejected → abstention. The OpenRouter
 * call happens OUTSIDE the DB transaction.
 */
class AskDocs
{
    public function __construct(
        private readonly CandidateRetriever $retriever,
        private readonly AnswerUnitSelector $selector,
    ) {}

    /**
     * Answer a (already redacted, already persisted) user message.
     *
     * @return array{message: Message, product_status: ProductStatus, body: string, sources: list<array{answer_unit_id: string, canonical_url: string}>}
     */
    public function handle(Message $userMessage, string $operationId): array
    {
        // Idempotency: a generation for this operation already exists → reuse it.
        if ($existing = Generation::where('operation_id', $operationId)->first()) {
            return $this->rebuild($existing);
        }

        $candidates = $this->retriever->retrieve($userMessage->content);

        // Empty corpus → abstain without spending an AI call.
        if ($candidates === []) {
            $assistant = $this->saveAssistantOnly($userMessage, ProductStatus::Abstained, $this->abstainBody());

            return $this->result($assistant, ProductStatus::Abstained, $assistant->content, []);
        }

        // --- grounded selection (provider failover, grounding-in-attempt) OUTSIDE any transaction ---
        $selection = $this->selector->select($candidates, $userMessage->content);

        return $this->persist($userMessage, $operationId, $candidates, $selection);
    }

    // =========================================================================
    // PERSISTENCE
    // =========================================================================

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @param  array<string, mixed>  $selection
     * @return array{message: Message, product_status: ProductStatus, body: string, sources: list<array{answer_unit_id: string, canonical_url: string}>}
     */
    private function persist(Message $userMessage, string $operationId, array $candidates, array $selection): array
    {
        $product = $selection['outcome'];
        $accepted = $selection['accepted'];
        $body = $this->body($product, $accepted, ! $selection['technical']);
        $sources = $this->sources($accepted);

        $assistant = DB::transaction(function () use ($userMessage, $operationId, $candidates, $selection, $product, $body) {
            $assistant = Message::create([
                'conversation_id' => $userMessage->conversation_id,
                'role' => MessageRole::Assistant,
                'content' => $body,
                'product_status' => $product,
            ]);

            $generation = Generation::create([
                'message_id' => $assistant->id,
                'operation_id' => $operationId,
                'model' => $selection['model'],
                'response_type' => $selection['response_type'],
                'input_tokens' => $selection['input_tokens'],
                'output_tokens' => $selection['output_tokens'],
                'cost' => $selection['cost'],
                'infra_status' => $selection['infra_status'],
            ]);

            // generation_context = exactly what the model saw (basis of validation).
            foreach ($candidates as $unit) {
                $generation->context()->create([
                    'answer_unit_id' => $unit['answer_unit_id'],
                    'content_hash' => $unit['content_hash'],
                ]);
            }

            // message_units = the grounding verdict on each selected unit.
            $ordinal = 0;
            foreach ($selection['verdicts'] as $verdict) {
                $rendered = $product === ProductStatus::Answered
                    && $verdict['validation_status'] === ValidationStatus::Accepted;

                $generation->units()->create([
                    'answer_unit_id' => $verdict['answer_unit_id'],
                    'validation_status' => $verdict['validation_status'],
                    'display_ordinal' => $rendered ? ++$ordinal : null,
                ]);
            }

            return $assistant;
        });

        return $this->result($assistant, $product, $body, $sources);
    }

    private function saveAssistantOnly(Message $userMessage, ProductStatus $product, string $body): Message
    {
        return Message::create([
            'conversation_id' => $userMessage->conversation_id,
            'role' => MessageRole::Assistant,
            'content' => $body,
            'product_status' => $product,
        ]);
    }

    // =========================================================================
    // RENDER HELPERS
    // =========================================================================

    /**
     * @param  list<array<string, mixed>>  $accepted
     */
    private function body(ProductStatus $product, array $accepted, bool $ok): string
    {
        return match ($product) {
            ProductStatus::Answered => $this->composeUnits($accepted),
            ProductStatus::NeedsClarification => 'Doprecyzuj proszę pytanie — nie jestem pewien, czego dotyczy.',
            ProductStatus::Abstained => $ok
                ? 'Nie znalazłem odpowiedzi na to pytanie w dokumentacji KINGS.'
                : 'Przepraszam, chwilowy problem techniczny. Spróbuj ponownie za moment.',
        };
    }

    /**
     * Verbatim unit content for display: strip markdown heading markers, escape later in Blade.
     *
     * @param  list<array<string, mixed>>  $accepted
     */
    private function composeUnits(array $accepted): string
    {
        $parts = array_map(
            fn (array $unit): string => trim((string) preg_replace('/^#{1,6}\s+/m', '', (string) $unit['content'])),
            $accepted,
        );

        return implode("\n\n", $parts);
    }

    /**
     * @param  list<array<string, mixed>>  $accepted
     * @return list<array{answer_unit_id: string, canonical_url: string}>
     */
    private function sources(array $accepted): array
    {
        $sources = [];
        $seen = [];

        foreach ($accepted as $unit) {
            $url = (string) $unit['canonical_url'];
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $sources[] = [
                'answer_unit_id' => (string) $unit['answer_unit_id'],
                'canonical_url' => $this->fullUrl($url),
            ];
        }

        return $sources;
    }

    private function fullUrl(string $path): string
    {
        $base = rtrim((string) config('corpus.base_url'), '/');

        return $base === '' ? $path : $base.$path;
    }

    /**
     * Rebuild a result from an already-stored generation (idempotency / history).
     *
     * @return array{message: Message, product_status: ProductStatus, body: string, sources: list<array{answer_unit_id: string, canonical_url: string}>}
     */
    private function rebuild(Generation $generation): array
    {
        $assistant = $generation->message;
        $product = $assistant->product_status ?? ProductStatus::Abstained;

        return $this->result($assistant, $product, $assistant->content, $this->sourcesFor($assistant));
    }

    /**
     * Resolve display sources (links) for an answered assistant message from its
     * validated units + the current corpus. Used for history rendering.
     *
     * @return list<array{answer_unit_id: string, canonical_url: string}>
     */
    public function sourcesFor(Message $assistant): array
    {
        if ($assistant->product_status !== ProductStatus::Answered) {
            return [];
        }

        $generation = $assistant->generations()->latest('id')->first();
        if ($generation === null) {
            return [];
        }

        $byId = [];
        foreach ($this->retriever->retrieve($assistant->content) as $unit) {
            $byId[$unit['answer_unit_id']] = $unit;
        }

        $accepted = $generation->units()
            ->where('validation_status', ValidationStatus::Accepted->value)
            ->whereNotNull('display_ordinal')
            ->orderBy('display_ordinal')
            ->get();

        $sources = [];
        $seen = [];
        foreach ($accepted as $unit) {
            $candidate = $byId[$unit->answer_unit_id] ?? null;
            if ($candidate === null) {
                continue;
            }
            $url = $this->fullUrl((string) $candidate['canonical_url']);
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $sources[] = ['answer_unit_id' => $unit->answer_unit_id, 'canonical_url' => $url];
        }

        return $sources;
    }

    /**
     * @param  list<array{answer_unit_id: string, canonical_url: string}>  $sources
     * @return array{message: Message, product_status: ProductStatus, body: string, sources: list<array{answer_unit_id: string, canonical_url: string}>}
     */
    private function result(Message $message, ProductStatus $product, string $body, array $sources): array
    {
        return [
            'message' => $message,
            'product_status' => $product,
            'body' => $body,
            'sources' => $sources,
        ];
    }

    private function abstainBody(): string
    {
        return 'Nie znalazłem odpowiedzi na to pytanie w dokumentacji KINGS.';
    }
}
