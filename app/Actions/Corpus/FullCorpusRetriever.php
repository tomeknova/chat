<?php

namespace App\Actions\Corpus;

use App\Enums\CorpusProfile;

/**
 * v1 retriever: returns the ENTIRE active-profile corpus as candidates (OK while
 * small). Reads the corpus-{profile}.json produced by chat:build-corpus for the
 * active profile. An unreadable/corrupt artifact is treated as UNAVAILABLE (empty)
 * — never a silent fallback to another instruction. The one-release legacy
 * fallback to corpus.json is allowed ONLY for kings5-docs (a clams request must
 * never resolve to KINGS content).
 */
class FullCorpusRetriever implements CandidateRetriever
{
    /**
     * Memoized corpus (decoded once per request — bound as a singleton).
     *
     * @var list<array{answer_unit_id: string, content: string, content_hash: string, intents: list<string>, canonical_url: string}>|null
     */
    private ?array $cache = null;

    /**
     * @return list<array{answer_unit_id: string, content: string, content_hash: string, intents: list<string>, canonical_url: string}>
     */
    public function retrieve(string $question): array
    {
        return $this->cache ??= $this->load();
    }

    /**
     * @return list<array{answer_unit_id: string, content: string, content_hash: string, intents: list<string>, canonical_url: string}>
     */
    private function load(): array
    {
        $path = $this->resolvePath();

        if ($path === null) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        // Corrupt/partial artifact → unavailable (NOT a silent cross-profile fallback).
        if (! is_array($decoded) || ! isset($decoded['units']) || ! is_array($decoded['units'])) {
            return [];
        }

        return array_values($decoded['units']);
    }

    /**
     * Resolve the readable artifact for the active profile, or null if none.
     * Legacy corpus.json fallback is allowed ONLY for kings5-docs.
     */
    private function resolvePath(): ?string
    {
        $path = (string) config('corpus.output_path');

        if ($path !== '' && is_file($path) && is_readable($path)) {
            return $path;
        }

        if ((string) config('corpus.active_profile') === CorpusProfile::Kings5Docs->value) {
            $legacy = rtrim((string) config('corpus.output_dir'), '/').'/corpus.json';

            if (is_file($legacy) && is_readable($legacy)) {
                return $legacy;
            }
        }

        return null;
    }
}
