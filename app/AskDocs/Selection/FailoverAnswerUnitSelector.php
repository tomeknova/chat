<?php

namespace App\AskDocs\Selection;

use App\AskDocs\CircuitBreaker;
use App\AskDocs\Contracts\AnswerUnitSelector;
use App\AskDocs\Contracts\ChatModel;
use App\AskDocs\GroundingValidator;
use App\Enums\InfraStatus;
use App\Enums\ProductStatus;

/**
 * Tries an ordered chain of providers (e.g. Bielik → OpenRouter). Grounding runs
 * INSIDE each attempt (decision Q): a transport/protocol error OR a grounding
 * violation is fallbackable → try the next provider. A VALID result (answered /
 * out_of_scope / clarification) stops the chain. If every provider fails, the
 * outcome is a (technical) abstention. Circuit breaker + endpoint resolver slot
 * in here in later increments.
 */
class FailoverAnswerUnitSelector implements AnswerUnitSelector
{
    /**
     * @param  array<string, ChatModel>  $chain  provider-name => adapter, in order
     */
    public function __construct(
        private readonly array $chain,
        private readonly GroundingValidator $validator,
        private readonly CircuitBreaker $breaker,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return array<string, mixed>
     */
    public function select(array $candidates, string $question): array
    {
        $attempts = [];
        $lastVerdicts = [];
        $lastModel = '';
        $lastInfra = InfraStatus::ProviderTimeout;
        $gotResponse = false;

        $deadline = (int) config('askdocs.deadline', 35);
        $start = microtime(true);

        foreach ($this->chain as $provider => $model) {
            // Circuit open → skip without calling (fast fallback).
            if ($this->breaker->isOpen($provider)) {
                $attempts[] = $this->attempt($provider, '', 'circuit_open', true);

                continue;
            }

            // Deadline-aware: once the budget is spent, stop starting providers.
            $remaining = $deadline > 0 ? $deadline - (int) ceil(microtime(true) - $start) : null;
            if ($remaining !== null && $remaining <= 0) {
                $attempts[] = $this->attempt($provider, '', 'deadline_exceeded', true);

                continue;
            }

            $call = $model->select($candidates, $question, $remaining);
            $lastModel = $call['model'];
            $lastInfra = $call['infra_status'];

            // Transport/protocol failure → fallbackable.
            if (! $call['ok']) {
                $this->breaker->recordFailure($provider);
                $attempts[] = $this->attempt($provider, $call['model'], $call['infra_status']->value, true);

                continue;
            }

            $gotResponse = true;
            $validation = $this->validator->validate($call, $candidates);
            $lastVerdicts = $validation['verdicts'];

            // Grounding violation → fallbackable (next provider may ground it).
            if ($validation['grounding_failed']) {
                $this->breaker->recordFailure($provider);
                $attempts[] = $this->attempt($provider, $call['model'], 'grounding_violation', true);

                continue;
            }

            // Valid result (answered / abstained / needs_clarification) → stop.
            $this->breaker->recordSuccess($provider);
            $attempts[] = $this->attempt($provider, $call['model'], $call['infra_status']->value, false);

            return [
                'outcome' => $validation['outcome'],
                'accepted' => $validation['accepted'],
                'verdicts' => $validation['verdicts'],
                'response_type' => $call['response_type'],
                'model' => $call['model'],
                'input_tokens' => $call['input_tokens'],
                'output_tokens' => $call['output_tokens'],
                'cost' => $call['cost'],
                'infra_status' => $call['infra_status'],
                'technical' => false,
                'attempts' => $attempts,
            ];
        }

        // Every provider failed.
        return [
            'outcome' => ProductStatus::Abstained,
            'accepted' => [],
            'verdicts' => $lastVerdicts,
            'response_type' => null,
            'model' => $lastModel,
            'input_tokens' => null,
            'output_tokens' => null,
            'cost' => null,
            'infra_status' => $lastInfra,
            // Transport-only failures = technical problem; a grounding failure
            // (we did get a response) reads as "not found in docs".
            'technical' => ! $gotResponse,
            'attempts' => $attempts,
        ];
    }

    /**
     * @return array{provider: string, model: string, status: string, fallbackable: bool}
     */
    private function attempt(string $provider, string $model, string $status, bool $fallbackable): array
    {
        return ['provider' => $provider, 'model' => $model, 'status' => $status, 'fallbackable' => $fallbackable];
    }
}
