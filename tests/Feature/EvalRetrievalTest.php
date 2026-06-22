<?php

namespace Tests\Feature;

use Tests\TestCase;

class EvalRetrievalTest extends TestCase
{
    public function test_reports_recall_against_a_golden_dataset(): void
    {
        $corpus = storage_path('app/corpus/eval-test-'.uniqid().'.json');
        @mkdir(dirname($corpus), 0775, true);
        file_put_contents($corpus, json_encode(['units' => [
            ['answer_unit_id' => 'start.logowanie', 'content' => 'Aby zalogować się wejdź na /admin.', 'content_hash' => 'h1', 'intents' => ['logowanie'], 'canonical_url' => '/start/logowanie'],
            ['answer_unit_id' => 'inne.pierogi', 'content' => 'Zupełnie inny temat o gotowaniu pierogów.', 'content_hash' => 'h2', 'intents' => [], 'canonical_url' => '/inne'],
        ]]));

        $goldenName = 'golden-test-'.uniqid().'.json';
        $golden = storage_path('app/eval/'.$goldenName);
        @mkdir(dirname($golden), 0775, true);
        file_put_contents($golden, json_encode(['items' => [
            ['question' => 'Jak się zalogować do panelu?', 'expected' => ['start.logowanie']],
        ]]));

        config(['corpus.output_path' => $corpus, 'askdocs.retrieval.top_k' => 8]);

        $this->artisan('chat:eval', ['golden' => 'storage/app/eval/'.$goldenName, '--retriever' => 'lexical'])
            ->expectsOutputToContain('Recall@8: 1/1 (100.0%)')
            ->assertExitCode(0);

        @unlink($corpus);
        @unlink($golden);
    }
}
