<?php

namespace App\AskDocs\Contracts;

use App\Enums\InfraStatus;
use App\Enums\ResponseType;

/**
 * Provider-agnostic contract: given the candidate answer-units, ask the model
 * to SELECT the matching ones (strict JSON). Implementations are thin transport
 * adapters (Ollama, OpenRouter); all grounding/validation stays in the domain.
 *
 * Returns a normalized result array (kept as an array shape in increment 1; a
 * readonly ChatResult DTO is introduced in increment 2).
 */
interface ChatModel
{
    /**
     * @param  list<array{answer_unit_id: string, content: string, content_hash: string, intents: list<string>, canonical_url: string}>  $candidates
     * @param  int|null  $timeoutSeconds  remaining request budget; the call waits at most min(this, provider timeout). null = use the provider's own timeout.
     * @return array{ok: bool, infra_status: InfraStatus, response_type: ?ResponseType, unit_ids: list<string>, model: string, input_tokens: ?int, output_tokens: ?int, cost: ?float}
     */
    public function select(array $candidates, string $question, ?int $timeoutSeconds = null): array;
}
