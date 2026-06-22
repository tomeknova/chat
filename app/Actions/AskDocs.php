<?php

namespace App\Actions;

use App\Actions\Corpus\CandidateRetriever;
use App\AskDocs\Contracts\AnswerUnitSelector;
use App\Enums\MessageRole;
use App\Enums\ProcessingStatus;
use App\Enums\ProductStatus;
use App\Enums\ValidationStatus;
use App\Models\Generation;
use App\Models\Message;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

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
     * One submit = one generation row + at most one active executor (decision R):
     * atomically reserve the operation, call the model OUTSIDE any transaction,
     * then finalize. A crashed executor (expired lease) is reclaimed via CAS.
     *
     * @return array{message: Message, product_status: ProductStatus, body: string, sources: list<array{answer_unit_id: string, title: string, canonical_url: string}>}
     */
    public function handle(Message $userMessage, string $operationId): array
    {
        [$action, $generation] = $this->reserve($operationId, $this->fingerprint($userMessage));

        return match ($action) {
            'completed' => $this->rebuild($generation),
            'busy' => $this->busy($userMessage),
            'conflict' => $this->busy($userMessage, conflict: true),
            default => $this->process($userMessage, $generation),
        };
    }

    // =========================================================================
    // RESERVATION (decision R — CAS + lease takeover)
    // =========================================================================

    /**
     * Atomically reserve the operation.
     *
     * @return array{0: string, 1: Generation|null} action: acquired|completed|busy|conflict
     */
    private function reserve(string $operationId, string $fingerprint): array
    {
        $lease = (int) config('askdocs.lease', 60);

        try {
            $generation = Generation::create([
                'operation_id' => $operationId,
                'status' => ProcessingStatus::Processing,
                'processing_owner' => (string) Str::uuid(),
                'processing_started_at' => now(),
                'lease_expires_at' => now()->addSeconds($lease),
                'request_fingerprint' => $fingerprint,
                'execution_attempt' => 1,
            ]);

            return ['acquired', $generation];
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }
        }

        $existing = Generation::where('operation_id', $operationId)->firstOrFail();

        // Same operation_id, different question → conflict (decision R: 409 analog).
        if ($existing->request_fingerprint !== null && ! hash_equals($existing->request_fingerprint, $fingerprint)) {
            return ['conflict', $existing];
        }

        // Completed (or legacy rows with no status) → replay the stored result.
        if ($existing->status === ProcessingStatus::Completed || $existing->status === null) {
            return ['completed', $existing];
        }

        // processing + valid lease → another executor owns it; expired lease or a
        // failed attempt → reclaim via CAS and run.
        if ($this->takeover($existing, $lease)) {
            return ['acquired', $existing->refresh()];
        }

        return ['busy', $existing];
    }

    /**
     * CAS reclaim: only succeeds while the row is still in the observed state and
     * its lease has expired (crashed executor) or the previous attempt failed.
     */
    private function takeover(Generation $existing, int $lease): bool
    {
        $affected = Generation::where('id', $existing->id)
            ->where('status', $existing->status?->value)
            ->where('processing_owner', $existing->processing_owner)
            ->where(function ($query) {
                $query->where('lease_expires_at', '<', now())
                    ->orWhere('status', ProcessingStatus::Failed->value);
            })
            ->update([
                'status' => ProcessingStatus::Processing,
                'processing_owner' => (string) Str::uuid(),
                'processing_started_at' => now(),
                'lease_expires_at' => now()->addSeconds($lease),
                'execution_attempt' => $existing->execution_attempt + 1,
            ]);

        return $affected === 1;
    }

    /**
     * We own the reservation: retrieve, select (OUTSIDE any transaction), finalize.
     *
     * @return array{message: Message, product_status: ProductStatus, body: string, sources: list<array{answer_unit_id: string, title: string, canonical_url: string}>}
     */
    private function process(Message $userMessage, Generation $generation): array
    {
        try {
            $candidates = $this->retriever->retrieve($userMessage->content);

            $selection = $candidates === []
                ? $this->emptyCorpusSelection()
                : $this->selector->select($candidates, $userMessage->content);

            return $this->finalize($generation, $userMessage, $candidates, $selection);
        } catch (Throwable $e) {
            $generation->update(['status' => ProcessingStatus::Failed]);

            throw $e;
        }
    }

    // =========================================================================
    // FINALIZE (UPDATE the reserved row + persist the chain)
    // =========================================================================

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @param  array<string, mixed>  $selection
     * @return array{message: Message, product_status: ProductStatus, body: string, sources: list<array{answer_unit_id: string, title: string, canonical_url: string}>}
     */
    private function finalize(Generation $generation, Message $userMessage, array $candidates, array $selection): array
    {
        $product = $selection['outcome'];
        $accepted = $selection['accepted'];
        $body = $this->body($product, $accepted, ! $selection['technical']);
        $sources = $this->sources($accepted);

        $assistant = DB::transaction(function () use ($generation, $userMessage, $candidates, $selection, $product, $body) {
            $assistant = Message::create([
                'conversation_id' => $userMessage->conversation_id,
                'role' => MessageRole::Assistant,
                'content' => $body,
                'product_status' => $product,
            ]);

            $generation->update([
                'message_id' => $assistant->id,
                'model' => $selection['model'],
                'response_type' => $selection['response_type'],
                'input_tokens' => $selection['input_tokens'],
                'output_tokens' => $selection['output_tokens'],
                'cost' => $selection['cost'],
                'infra_status' => $selection['infra_status'],
                'status' => ProcessingStatus::Completed,
                'metadata' => ['attempts' => $selection['attempts']],
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

    /**
     * Empty corpus → abstain without spending an AI call.
     *
     * @return array<string, mixed>
     */
    private function emptyCorpusSelection(): array
    {
        return [
            'outcome' => ProductStatus::Abstained,
            'accepted' => [],
            'verdicts' => [],
            'response_type' => null,
            'model' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'cost' => null,
            'infra_status' => null,
            'technical' => false,
            'attempts' => [],
        ];
    }

    private function fingerprint(Message $userMessage): string
    {
        $payload = implode('|', [
            (string) $userMessage->conversation_id,
            (string) ($userMessage->normalized_question_hash ?? $userMessage->content),
            'askdocs-v1',
        ]);

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    /**
     * Another executor holds a valid lease (or the operation_id conflicts): return
     * a transient (unstored) degradation — the real answer comes from that executor.
     *
     * @return array{message: Message, product_status: ProductStatus, body: string, sources: list<array{answer_unit_id: string, title: string, canonical_url: string}>}
     */
    private function busy(Message $userMessage, bool $conflict = false): array
    {
        $body = $conflict
            ? 'Wystąpił konflikt operacji. Odśwież stronę i spróbuj ponownie.'
            : 'To pytanie jest właśnie przetwarzane. Spróbuj ponownie za chwilę.';

        $message = new Message([
            'conversation_id' => $userMessage->conversation_id,
            'role' => MessageRole::Assistant,
            'content' => $body,
            'product_status' => ProductStatus::Abstained,
        ]);

        return $this->result($message, ProductStatus::Abstained, $body, []);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062 // MySQL / MariaDB
            || (string) $e->getCode() === '23000'
            || str_contains($e->getMessage(), 'UNIQUE constraint failed'); // SQLite
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
     * @return list<array{answer_unit_id: string, title: string, canonical_url: string}>
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
                'title' => $this->titleOf((string) $unit['content']),
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
     * Descriptive source label = the unit's first markdown heading (fallback: first line).
     */
    private function titleOf(string $content): string
    {
        if (preg_match('/^#{1,6}\s+(.+)$/m', $content, $matches) === 1) {
            return trim($matches[1]);
        }

        $first = trim((string) strtok($content, "\n"));

        return $first === '' ? 'dokumentacja' : mb_strimwidth($first, 0, 80, '…');
    }

    /**
     * Rebuild a result from an already-stored generation (idempotency / history).
     *
     * @return array{message: Message, product_status: ProductStatus, body: string, sources: list<array{answer_unit_id: string, title: string, canonical_url: string}>}
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
     * @return list<array{answer_unit_id: string, title: string, canonical_url: string}>
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
            $sources[] = [
                'answer_unit_id' => $unit->answer_unit_id,
                'title' => $this->titleOf((string) $candidate['content']),
                'canonical_url' => $url,
            ];
        }

        return $sources;
    }

    /**
     * @param  list<array{answer_unit_id: string, title: string, canonical_url: string}>  $sources
     * @return array{message: Message, product_status: ProductStatus, body: string, sources: list<array{answer_unit_id: string, title: string, canonical_url: string}>}
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
}
