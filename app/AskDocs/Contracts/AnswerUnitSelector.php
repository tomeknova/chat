<?php

namespace App\AskDocs\Contracts;

use App\Enums\InfraStatus;
use App\Enums\ProductStatus;
use App\Enums\ResponseType;
use App\Enums\ValidationStatus;

/**
 * Domain port: given the candidate answer-units, return a VALIDATED selection
 * (grounded ∈ context) or an abstention. Hides the provider/transport detail
 * and the failover policy from AskDocs — the domain asks for an answer-unit
 * decision, not a raw chat (decision M).
 */
interface AnswerUnitSelector
{
    /**
     * @param  list<array{answer_unit_id: string, content: string, content_hash: string, intents: list<string>, canonical_url: string}>  $candidates
     * @return array{outcome: ProductStatus, accepted: list<array<string, mixed>>, verdicts: list<array{answer_unit_id: string, validation_status: ValidationStatus}>, response_type: ?ResponseType, model: string, input_tokens: ?int, output_tokens: ?int, cost: ?float, infra_status: InfraStatus, technical: bool, attempts: list<array<string, mixed>>}
     */
    public function select(array $candidates, string $question): array;
}
