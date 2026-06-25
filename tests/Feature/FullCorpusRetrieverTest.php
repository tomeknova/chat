<?php

namespace Tests\Feature;

use App\Actions\Corpus\FullCorpusRetriever;
use Tests\TestCase;

/**
 * Active-profile resolution + isolation: a corrupt/missing artifact is
 * unavailable (empty), and the legacy corpus.json fallback is KINGS-only — a
 * clams request must NEVER be served KINGS content.
 */
class FullCorpusRetrieverTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/corpus_ret_'.uniqid();
        @mkdir($this->dir, 0775, true);
        config(['corpus.output_dir' => $this->dir]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);

        parent::tearDown();
    }

    /**
     * @param  list<array<string, mixed>>  $units
     */
    private function writeCorpus(string $file, array $units): void
    {
        file_put_contents($this->dir.'/'.$file, json_encode(['units' => $units]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unit(string $id): array
    {
        return [[
            'answer_unit_id' => $id,
            'content' => "content-{$id}",
            'content_hash' => 'h',
            'intents' => [],
            'canonical_url' => "/{$id}",
        ]];
    }

    public function test_reads_active_profile_artifact(): void
    {
        config([
            'corpus.active_profile' => 'kings5-docs',
            'corpus.output_path' => $this->dir.'/corpus-kings5-docs.json',
        ]);
        $this->writeCorpus('corpus-kings5-docs.json', $this->unit('a'));

        $units = (new FullCorpusRetriever)->retrieve('q');

        $this->assertCount(1, $units);
        $this->assertSame('a', $units[0]['answer_unit_id']);
    }

    public function test_corrupt_artifact_is_unavailable(): void
    {
        config([
            'corpus.active_profile' => 'kings5-docs',
            'corpus.output_path' => $this->dir.'/corpus-kings5-docs.json',
        ]);
        file_put_contents($this->dir.'/corpus-kings5-docs.json', '{ not valid json');

        $this->assertSame([], (new FullCorpusRetriever)->retrieve('q'));
    }

    public function test_kings_falls_back_to_legacy_corpus_json(): void
    {
        config([
            'corpus.active_profile' => 'kings5-docs',
            'corpus.output_path' => $this->dir.'/corpus-kings5-docs.json', // absent
        ]);
        $this->writeCorpus('corpus.json', $this->unit('legacy'));

        $units = (new FullCorpusRetriever)->retrieve('q');

        $this->assertCount(1, $units);
        $this->assertSame('legacy', $units[0]['answer_unit_id']);
    }

    public function test_clams_never_falls_back_to_legacy_kings(): void
    {
        config([
            'corpus.active_profile' => 'clams-docs',
            'corpus.output_path' => $this->dir.'/corpus-clams-docs.json', // absent
        ]);
        $this->writeCorpus('corpus.json', $this->unit('kings-legacy'));

        // No clams artifact + legacy KINGS present → unavailable, NOT KINGS content.
        $this->assertSame([], (new FullCorpusRetriever)->retrieve('q'));
    }
}
