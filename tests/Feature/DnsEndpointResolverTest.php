<?php

namespace Tests\Feature;

use App\AskDocs\Adapters\Discovery\DnsEndpointResolver;
use App\AskDocs\Security\EndpointAllowlist;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DnsEndpointResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function resolver(array $cfg): DnsEndpointResolver
    {
        return new DnsEndpointResolver($cfg, new EndpointAllowlist($cfg));
    }

    public function test_resolves_name_to_allowlisted_ip(): void
    {
        $url = $this->resolver(['host' => 'localhost', 'port' => 11434, 'allowed_cidr' => '127.0.0.0/8'])->resolve();

        $this->assertSame('http://127.0.0.1:11434/v1', $url);
    }

    public function test_accepts_literal_ip_host(): void
    {
        $url = $this->resolver(['host' => '127.0.0.1', 'port' => 11434, 'allowed_cidr' => '127.0.0.0/8'])->resolve();

        $this->assertSame('http://127.0.0.1:11434/v1', $url);
    }

    public function test_returns_null_when_resolved_ip_outside_allowlist(): void
    {
        $url = $this->resolver(['host' => 'localhost', 'port' => 11434, 'allowed_cidr' => '10.0.0.0/8'])->resolve();

        $this->assertNull($url);
    }

    public function test_returns_null_for_empty_host(): void
    {
        $url = $this->resolver(['host' => '', 'allowed_cidr' => '127.0.0.0/8'])->resolve();

        $this->assertNull($url);
    }
}
