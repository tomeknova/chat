<?php

namespace App\AskDocs\Adapters;

/**
 * Ollama adapter (local Bielik via the OpenAI-compatible /v1 endpoint).
 * No OpenRouter `provider` block — Ollama does not accept it. Verified:
 * Ollama /v1 honors response_format json_schema for the AskDocs contract.
 */
class OllamaChatModel extends OpenAiCompatibleChatModel
{
    /**
     * @return array<string, mixed>
     */
    protected function extraPayload(): array
    {
        return [];
    }
}
