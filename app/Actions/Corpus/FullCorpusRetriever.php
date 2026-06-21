<?php

namespace App\Actions\Corpus;

/**
 * v1 retriever: returns the ENTIRE corpus as candidates (OK while small).
 * Reads the JSON file produced by chat:build-corpus.
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
        $path = (string) config('corpus.output_path');

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded) || ! isset($decoded['units']) || ! is_array($decoded['units'])) {
            return [];
        }

        return array_values($decoded['units']);
    }
}
