<?php

namespace App\AskDocs\Contracts;

/**
 * Resolves a provider's base URL at call time (decision C, Faza 2): address the
 * model by NAME, never a hardcoded IP. The resolved endpoint must already be
 * validated (allowlist) before it is returned. Returning null means "unavailable
 * / not allowed" → the caller treats the provider as down and fails over.
 *
 * Implementations: DNS (stable name) now; signed heartbeat later.
 */
interface EndpointResolver
{
    public function resolve(): ?string;
}
