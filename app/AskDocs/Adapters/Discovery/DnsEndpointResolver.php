<?php

namespace App\AskDocs\Adapters\Discovery;

use App\AskDocs\Contracts\EndpointResolver;
use App\AskDocs\Security\EndpointAllowlist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Resolves the Bielik endpoint by NAME via DNS, pins it to the resolved IP
 * (the request goes to the IP, not a re-resolvable name — mitigates the TOCTOU
 * DNS-rebinding window), and rejects anything outside the CIDR allowlist before
 * returning it. Cached for a short TTL so we don't resolve on every request.
 * null = unresolvable or not allowed → caller treats Bielik as down.
 */
class DnsEndpointResolver implements EndpointResolver
{
    /**
     * @param  array<string, mixed>  $config  the provider config entry
     */
    public function __construct(
        private readonly array $config,
        private readonly EndpointAllowlist $allowlist,
    ) {}

    public function resolve(): ?string
    {
        $host = (string) ($this->config['host'] ?? '');
        if ($host === '') {
            return null;
        }

        $cacheKey = 'askdocs:bielik:endpoint:'.$host;
        $cached = Cache::get($cacheKey);
        if (is_string($cached)) {
            return $cached;
        }

        $ip = $this->lookup($host);
        if ($ip === null) {
            Log::warning('AskDocs: bielik host did not resolve', ['host' => $host]);

            return null;
        }

        $port = (int) ($this->config['port'] ?? 11434);
        if (! $this->allowlist->allows($ip, $port)) {
            Log::warning('AskDocs: bielik endpoint outside allowlist', ['host' => $host, 'ip' => $ip, 'port' => $port]);

            return null;
        }

        $scheme = (string) ($this->config['scheme'] ?? 'http');
        $path = (string) ($this->config['base_path'] ?? '/v1');
        $url = $scheme.'://'.$ip.':'.$port.rtrim($path, '/');

        Cache::put($cacheKey, $url, (int) ($this->config['resolve_ttl'] ?? 30));

        return $url;
    }

    private function lookup(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host; // already a literal IP
        }

        $ip = gethostbyname($host); // returns the input unchanged on failure

        return ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP)) ? $ip : null;
    }
}
