<?php

namespace Tests\Feature;

use App\AskDocs\Security\EndpointAllowlist;
use Tests\TestCase;

class EndpointAllowlistTest extends TestCase
{
    public function test_allows_ip_inside_cidr_with_correct_port(): void
    {
        $allow = new EndpointAllowlist(['allowed_cidr' => '192.168.10.0/24', 'port' => 11434]);

        $this->assertTrue($allow->allows('192.168.10.42', 11434));
    }

    public function test_rejects_ip_outside_cidr(): void
    {
        $allow = new EndpointAllowlist(['allowed_cidr' => '192.168.10.0/24', 'port' => 11434]);

        $this->assertFalse($allow->allows('10.0.0.5', 11434));
    }

    public function test_rejects_wrong_port(): void
    {
        $allow = new EndpointAllowlist(['allowed_cidr' => '192.168.10.0/24', 'port' => 11434]);

        $this->assertFalse($allow->allows('192.168.10.42', 8080));
    }

    public function test_fails_closed_without_allowlist(): void
    {
        $allow = new EndpointAllowlist(['port' => 11434]);

        $this->assertFalse($allow->allows('192.168.10.42', 11434));
    }

    public function test_supports_multiple_cidrs(): void
    {
        $allow = new EndpointAllowlist(['allowed_cidr' => '10.0.0.0/8, 192.168.10.0/24', 'port' => 11434]);

        $this->assertTrue($allow->allows('10.1.2.3', 11434));
        $this->assertTrue($allow->allows('192.168.10.9', 11434));
        $this->assertFalse($allow->allows('172.16.0.1', 11434));
    }

    public function test_rejects_ipv6(): void
    {
        $allow = new EndpointAllowlist(['allowed_cidr' => '192.168.10.0/24', 'port' => 11434]);

        $this->assertFalse($allow->allows('::1', 11434));
    }
}
