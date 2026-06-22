<?php

namespace App\Console\Commands;

use App\Actions\Corpus\FullCorpusRetriever;
use App\Actions\Corpus\LexicalRetriever;
use Illuminate\Console\Command;

/**
 * Measures retrieval Recall@k against a golden dataset (question → expected
 * answer-unit ids). Retrieval-only — no AI call, no cost. Use it to decide
 * whether the lexical retriever is good enough or a semantic stage (bge-m3)
 * is warranted: a low Recall@k on vocabulary-divergent questions is the signal.
 */
class EvalRetrieval extends Command
{
    protected $signature = 'chat:eval
        {golden=database/eval/golden.json : path to the golden dataset JSON}
        {--retriever=lexical : lexical|full}
        {--k= : override top_k for this run}';

    protected $description = 'Measure retrieval Recall@k against a golden dataset (no AI cost)';

    public function handle(): int
    {
        $path = base_path((string) $this->argument('golden'));
        if (! is_file($path)) {
            $this->error("Golden dataset not found: {$path}");

            return self::FAILURE;
        }

        /** @var list<array{question: string, expected: list<string>}> $items */
        $items = json_decode((string) file_get_contents($path), true)['items'] ?? [];
        if ($items === []) {
            $this->error('Golden dataset is empty.');

            return self::FAILURE;
        }

        if ($this->option('k') !== null) {
            config(['askdocs.retrieval.top_k' => (int) $this->option('k')]);
        }
        $k = (int) config('askdocs.retrieval.top_k', 8);

        $retriever = $this->option('retriever') === 'full'
            ? new FullCorpusRetriever
            : new LexicalRetriever(new FullCorpusRetriever);

        $this->line("Retriever: {$this->option('retriever')} (top_k={$k})");
        $this->line('Golden items: '.count($items));
        $this->newLine();

        $hits = 0;
        foreach ($items as $item) {
            $question = (string) ($item['question'] ?? '');
            $expected = (array) ($item['expected'] ?? []);
            $ids = array_map(
                fn (array $unit): string => (string) $unit['answer_unit_id'],
                $retriever->retrieve($question),
            );
            $rank = $this->firstHitRank($expected, $ids);

            if ($rank !== null) {
                $hits++;
                $this->line(sprintf('PASS  [rank %d]  %s', $rank, $question));
            } else {
                $this->line(sprintf('FAIL           %s', $question));
                $this->line('        expected: '.implode(', ', $expected));
                $this->line('        got:      '.(implode(', ', $ids) ?: '(none)'));
            }
        }

        $total = count($items);
        $this->newLine();
        $this->line(sprintf('Recall@%d: %d/%d (%.1f%%)', $k, $hits, $total, $hits / $total * 100));

        return self::SUCCESS;
    }

    /**
     * 1-based rank of the first retrieved id that is in $expected, or null.
     *
     * @param  list<string>  $expected
     * @param  list<string>  $ids
     */
    private function firstHitRank(array $expected, array $ids): ?int
    {
        foreach ($ids as $i => $id) {
            if (in_array($id, $expected, true)) {
                return $i + 1;
            }
        }

        return null;
    }
}
