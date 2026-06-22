<?php

namespace App\AskDocs\Security;

/**
 * Anti-SSRF guard (decision C): a resolved endpoint may only be used if its IP
 * falls inside an explicitly configured CIDR allowlist AND uses the expected
 * port. Fail-closed: no allowlist configured → nothing is allowed. IPv4 only
 * (the Bielik LAN is IPv4); IPv6 addresses are rejected.
 */
class EndpointAllowlist
{
    /** @var list<string> */
    private readonly array $cidrs;

    private readonly int $port;

    /**
     * @param  array<string, mixed>  $config  the provider config entry
     */
    public function __construct(array $config)
    {
        $this->cidrs = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($config['allowed_cidr'] ?? '')),
        )));
        $this->port = (int) ($config['port'] ?? 11434);
    }

    public function allows(string $ip, int $port): bool
    {
        if ($port !== $this->port || $this->cidrs === []) {
            return false;
        }

        foreach ($this->cidrs as $cidr) {
            if ($this->inCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private function inCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $bits] = explode('/', $cidr, 2);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false; // not IPv4
        }

        $bits = (int) $bits;
        if ($bits < 0 || $bits > 32) {
            return false;
        }

        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
