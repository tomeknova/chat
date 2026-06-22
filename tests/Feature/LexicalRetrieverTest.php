<?php

namespace Tests\Feature;

use App\Actions\Corpus\FullCorpusRetriever;
use App\Actions\Corpus\LexicalRetriever;
use Tests\TestCase;

class LexicalRetrieverTest extends TestCase
{
    private string $corpusPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->corpusPath = storage_path('app/corpus/lexical-test-'.uniqid().'.json');
        @mkdir(dirname($this->corpusPath), 0775, true);

        $units = [
            ['answer_unit_id' => 'start.logowanie', 'content' => 'Aby zalogować się do panelu, podaj e-mail i hasło.', 'content_hash' => 'h1', 'intents' => ['logowanie', 'jak się zalogować'], 'canonical_url' => '/start/logowanie'],
            ['answer_unit_id' => 'wydarzenia.tworzenie', 'content' => 'Tworzenie nowego wydarzenia krok po kroku w panelu.', 'content_hash' => 'h2', 'intents' => ['utworzyć wydarzenie'], 'canonical_url' => '/wydarzenia/tworzenie'],
            ['answer_unit_id' => 'tresc.theme', 'content' => 'Theme — wygląd i branding strony wydarzenia.', 'content_hash' => 'h3', 'intents' => ['wygląd', 'kolory'], 'canonical_url' => '/tresc/theme'],
        ];

        file_put_contents($this->corpusPath, json_encode(['units' => $units]));

        config([
            'corpus.output_path' => $this->corpusPath,
            'askdocs.retrieval.top_k' => 8,
            'askdocs.retrieval.max_chars' => 12000,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->corpusPath);

        parent::tearDown();
    }

    private function retriever(): LexicalRetriever
    {
        return new LexicalRetriever(new FullCorpusRetriever);
    }

    public function test_ranks_the_most_relevant_unit_first(): void
    {
        $units = $this->retriever()->retrieve('Jak się zalogować do panelu?');

        $this->assertNotEmpty($units);
        $this->assertSame('start.logowanie', $units[0]['answer_unit_id']);
    }

    public function test_matches_polish_inflection_via_prefix_stems(): void
    {
        // "wydarzeń" must still match "wydarzenia"/"tworzenie".
        $units = $this->retriever()->retrieve('Tworzenie wydarzeń');

        $this->assertSame('wydarzenia.tworzenie', $units[0]['answer_unit_id']);
    }

    public function test_returns_empty_when_nothing_matches(): void
    {
        $this->assertSame([], $this->retriever()->retrieve('przepis na pierogi'));
    }

    public function test_respects_top_k(): void
    {
        config(['askdocs.retrieval.top_k' => 1]);

        // "wydarzenia" appears in two units (tworzenie + theme) → both score, cap to 1.
        $units = $this->retriever()->retrieve('wydarzenia');

        $this->assertCount(1, $units);
    }

    public function test_char_budget_keeps_at_least_one_whole_unit(): void
    {
        config(['askdocs.retrieval.max_chars' => 5]); // smaller than any unit

        $units = $this->retriever()->retrieve('Jak się zalogować?');

        $this->assertCount(1, $units); // first match always kept, never truncated
        $this->assertStringContainsString('zalogować', $units[0]['content']);
    }
}
