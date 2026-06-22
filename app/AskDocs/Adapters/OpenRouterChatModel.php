<?php

namespace App\AskDocs\Adapters;

/**
 * OpenRouter adapter. Adds the OpenRouter-only `provider` block: pins
 * structured-output-capable providers, requires parameter support and denies
 * data collection (decision T).
 */
class OpenRouterChatModel extends OpenAiCompatibleChatModel
{
    /**
     * @return array<string, mixed>
     */
    protected function extraPayload(): array
    {
        return [
            'provider' => [
                'only' => $this->config['providers'] ?? ['openai', 'azure'],
                'allow_fallbacks' => false,
                'require_parameters' => true,
                'data_collection' => 'deny',
            ],
        ];
    }
}
