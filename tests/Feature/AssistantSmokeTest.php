<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'askdocs.providers.openrouter' => [
                'driver' => 'openrouter',
                'base_url' => 'https://openrouter.ai/api/v1',
                'key' => 'test-key',
                'model' => 'openai/gpt-5.4-nano',
                'providers' => ['openai', 'azure'],
            ],
        ]);

        Http::preventStrayRequests();
    }

    /**
     * @param  array{response_type?: string, answer_unit_ids?: array<int, string>}  $structured
     */
    private function fake(array $structured, int $status = 200): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($structured)]]],
                'usage' => ['prompt_tokens' => 358, 'completion_tokens' => 24],
            ], $status),
        ]);
    }

    public function test_smoke_passes_on_valid_strict_json(): void
    {
        $this->fake(['response_type' => 'answer', 'answer_unit_ids' => ['start.logowanie']]);

        $this->artisan('chat:assistant-smoke')
            ->assertSuccessful();
    }

    public function test_request_uses_configured_model_schema_and_provider(): void
    {
        $this->fake(['response_type' => 'out_of_scope', 'answer_unit_ids' => []]);

        $this->artisan('chat:assistant-smoke', ['--question' => 'Cokolwiek?'])
            ->assertSuccessful();

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
                && $request['model'] === 'openai/gpt-5.4-nano'
                && data_get($request->data(), 'response_format.json_schema.name') === 'askdocs_response'
                && data_get($request->data(), 'response_format.json_schema.strict') === true
                && data_get($request->data(), 'provider.only') === ['openai', 'azure']
                && data_get($request->data(), 'provider.data_collection') === 'deny'
                && data_get($request->data(), 'messages.1.content') === 'Cokolwiek?';
        });
    }

    public function test_smoke_fails_when_unit_id_is_outside_corpus(): void
    {
        $this->fake(['response_type' => 'answer', 'answer_unit_ids' => ['nieistniejaca.jednostka']]);

        $this->artisan('chat:assistant-smoke')
            ->assertFailed();
    }

    public function test_smoke_fails_on_non_2xx_response(): void
    {
        $this->fake(['error' => 'down'], 500);

        $this->artisan('chat:assistant-smoke')
            ->assertFailed();
    }
}
