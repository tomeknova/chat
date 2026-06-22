<?php

namespace App\AskDocs\Adapters;

use App\AskDocs\Contracts\EndpointResolver;

/**
 * Ollama adapter (local Bielik via the OpenAI-compatible /v1 endpoint).
 * No OpenRouter `provider` block — Ollama does not accept it. Verified:
 * Ollama /v1 honors response_format json_schema for the AskDocs contract.
 *
 * In prod the endpoint is resolved by name (DNS + allowlist) via the optional
 * resolver; locally (no resolver) it uses the static config base_url.
 */
class OllamaChatModel extends OpenAiCompatibleChatModel
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config, private readonly ?EndpointResolver $resolver = null)
    {
        parent::__construct($config);
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraPayload(): array
    {
        return [];
    }

    protected function resolveBaseUrl(): ?string
    {
        return $this->resolver !== null ? $this->resolver->resolve() : parent::resolveBaseUrl();
    }
}
