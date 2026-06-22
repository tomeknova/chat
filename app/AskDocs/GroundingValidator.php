<?php

namespace App\AskDocs;

use App\Enums\ProductStatus;
use App\Enums\ResponseType;
use App\Enums\ValidationStatus;

/**
 * Anti-hallucination core (SCOPE_V1 / decision Q): validates a model selection
 * against the EXACT candidates it saw (answer_unit_id ∈ context, atomic). Runs
 * INSIDE each provider attempt — a grounding violation is a fallbackable failure,
 * not a silently-accepted answer. Provider-independent.
 */
class GroundingValidator
{
    /**
     * @param  array{response_type: ?ResponseType, unit_ids: list<string>, ...}  $call  successful model call (ok=true)
     * @param  list<array<string, mixed>>  $candidates  units the model actually saw
     * @return array{outcome: ProductStatus, accepted: list<array<string, mixed>>, verdicts: list<array{answer_unit_id: string, validation_status: ValidationStatus}>, grounding_failed: bool}
     */
    public function validate(array $call, array $candidates): array
    {
        $byId = [];
        foreach ($candidates as $unit) {
            $byId[$unit['answer_unit_id']] = $unit;
        }

        $verdicts = [];
        $accepted = [];
        $allAccepted = $call['unit_ids'] !== [];

        foreach ($call['unit_ids'] as $id) {
            if (isset($byId[$id])) {
                $verdicts[] = ['answer_unit_id' => $id, 'validation_status' => ValidationStatus::Accepted];
                $accepted[] = $byId[$id];
            } else {
                $verdicts[] = ['answer_unit_id' => $id, 'validation_status' => ValidationStatus::RejectedUnknownUnit];
                $allAccepted = false;
            }
        }

        // response_type=answer but a selected unit is ∉ context → atomic grounding
        // violation (render nothing; the whole set is rejected) → fallbackable.
        if ($call['response_type'] === ResponseType::Answer) {
            return $allAccepted
                ? ['outcome' => ProductStatus::Answered, 'accepted' => $accepted, 'verdicts' => $verdicts, 'grounding_failed' => false]
                : ['outcome' => ProductStatus::Abstained, 'accepted' => [], 'verdicts' => $verdicts, 'grounding_failed' => true];
        }

        if ($call['response_type'] === ResponseType::Clarification) {
            return ['outcome' => ProductStatus::NeedsClarification, 'accepted' => [], 'verdicts' => $verdicts, 'grounding_failed' => false];
        }

        // abstention | out_of_scope — valid domain result (not a failure).
        return ['outcome' => ProductStatus::Abstained, 'accepted' => [], 'verdicts' => $verdicts, 'grounding_failed' => false];
    }
}
