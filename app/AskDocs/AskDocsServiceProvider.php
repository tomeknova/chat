<?php

namespace App\AskDocs;

use App\AskDocs\Adapters\Discovery\DnsEndpointResolver;
use App\AskDocs\Adapters\OllamaChatModel;
use App\AskDocs\Adapters\OpenRouterChatModel;
use App\AskDocs\Contracts\AnswerUnitSelector;
use App\AskDocs\Contracts\ChatModel;
use App\AskDocs\Contracts\EndpointResolver;
use App\AskDocs\Security\EndpointAllowlist;
use App\AskDocs\Selection\FailoverAnswerUnitSelector;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the AskDocs module. Binds the AnswerUnitSelector port to a failover
 * chain of provider adapters (config('askdocs.default') → fallback), each
 * grounded per attempt. AskDocs depends on the port, never on a concrete provider.
 */
class AskDocsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AnswerUnitSelector::class, function ($app): AnswerUnitSelector {
            $chain = [];
            foreach ($this->chainProviderNames() as $name) {
                /** @var array<string, mixed> $cfg */
                $cfg = (array) config("askdocs.providers.{$name}", []);
                if ($cfg === []) {
                    continue;
                }
                $chain[$name] = $this->adapter($cfg);
            }

            return new FailoverAnswerUnitSelector(
                $chain,
                $app->make(GroundingValidator::class),
                $app->make(CircuitBreaker::class),
            );
        });

        // Escalation selector: fallback provider only (e.g. OpenRouter), used when
        // the primary (Bielik) abstains and escalate_on_abstention is enabled.
        $this->app->bind('askdocs.escalation-selector', function ($app): ?AnswerUnitSelector {
            $fallback = config('askdocs.fallback');
            if (! $fallback) {
                return null;
            }
            /** @var array<string, mixed> $cfg */
            $cfg = (array) config("askdocs.providers.{$fallback}", []);
            if ($cfg === []) {
                return null;
            }

            return new FailoverAnswerUnitSelector(
                [$fallback => $this->adapter($cfg)],
                $app->make(GroundingValidator::class),
                $app->make(CircuitBreaker::class),
            );
        });
    }

    /**
     * Ordered, de-duplicated provider chain: primary then optional fallback.
     *
     * @return list<string>
     */
    private function chainProviderNames(): array
    {
        return array_values(array_unique(array_filter([
            config('askdocs.default'),
            config('askdocs.fallback'),
        ])));
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    private function adapter(array $cfg): ChatModel
    {
        return match ($cfg['driver'] ?? 'openrouter') {
            'ollama' => new OllamaChatModel($cfg, $this->resolverFor($cfg)),
            default => new OpenRouterChatModel($cfg),
        };
    }

    /**
     * Resolve by name (DNS + allowlist) only when a host is configured (prod);
     * otherwise the adapter uses the static config base_url (local dev).
     *
     * @param  array<string, mixed>  $cfg
     */
    private function resolverFor(array $cfg): ?EndpointResolver
    {
        if (empty($cfg['host'])) {
            return null;
        }

        return new DnsEndpointResolver($cfg, new EndpointAllowlist($cfg));
    }
}
