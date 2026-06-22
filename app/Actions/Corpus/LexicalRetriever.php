<?php

namespace App\Actions\Corpus;

/**
 * Staged recall (stage 1) for a small local model: narrows the full corpus to
 * the top-k most lexically-relevant answer-units before the model selects. A
 * small model (Bielik) cannot take the whole corpus — this keeps the prompt
 * tight. Lightweight lexical scoring (ASCII-folded for Polish diacritics +
 * prefix stems for inflection); bge-m3 semantic recall is the next stage.
 *
 * Composes FullCorpusRetriever (one decode/request) then scores in PHP.
 */
class LexicalRetriever implements CandidateRetriever
{
    public function __construct(private readonly FullCorpusRetriever $corpus) {}

    /**
     * @return list<array{answer_unit_id: string, content: string, content_hash: string, intents: list<string>, canonical_url: string}>
     */
    public function retrieve(string $question): array
    {
        $units = $this->corpus->retrieve($question);
        $stems = $this->stems($question);

        if ($units === [] || $stems === []) {
            return [];
        }

        $scored = [];
        foreach ($units as $unit) {
            $score = $this->score($unit, $stems);
            if ($score > 0) {
                $scored[] = ['unit' => $unit, 'score' => $score];
            }
        }

        // Highest score first; stable by answer_unit_id for deterministic output.
        usort(
            $scored,
            fn (array $a, array $b): int => $b['score'] <=> $a['score']
                ?: strcmp($a['unit']['answer_unit_id'], $b['unit']['answer_unit_id']),
        );

        return $this->withinBudget($scored);
    }

    /**
     * Take up to top_k units, but never overrun the char budget — whole units
     * only (no mid-unit truncation); always keep at least the top match.
     *
     * @param  list<array{unit: array<string, mixed>, score: int}>  $scored
     * @return list<array<string, mixed>>
     */
    private function withinBudget(array $scored): array
    {
        $topK = (int) config('askdocs.retrieval.top_k', 8);
        $maxChars = (int) config('askdocs.retrieval.max_chars', 12000);

        $out = [];
        $chars = 0;
        foreach ($scored as $row) {
            if (count($out) >= $topK) {
                break;
            }
            $length = mb_strlen((string) $row['unit']['content']);
            if ($out !== [] && $chars + $length > $maxChars) {
                continue;
            }
            $out[] = $row['unit'];
            $chars += $length;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $unit
     * @param  list<string>  $stems
     */
    private function score(array $unit, array $stems): int
    {
        $content = $this->fold((string) $unit['content']);
        // Curated intents + the id carry strong intent signal → weighted higher.
        $signal = $this->fold(implode(' ', $unit['intents'] ?? []).' '.((string) $unit['answer_unit_id']));

        $score = 0;
        foreach ($stems as $stem) {
            if (str_contains($signal, $stem)) {
                $score += 3;
            } elseif (str_contains($content, $stem)) {
                $score += 1;
            }
        }

        return $score;
    }

    /**
     * Query → distinctive prefix stems (ASCII-folded; inflection-tolerant).
     *
     * @return list<string>
     */
    private function stems(string $question): array
    {
        $tokens = preg_split('/[^a-z0-9]+/', $this->fold($question), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $stop = ['jak', 'czy', 'gdzie', 'kiedy', 'dla', 'jest', 'sie', 'oraz', 'albo', 'lub', 'the', 'and', 'jaki', 'jaka', 'jakie', 'gdy', 'aby', 'tej', 'tego'];

        $stems = [];
        foreach ($tokens as $token) {
            if (mb_strlen($token) < 4 || in_array($token, $stop, true)) {
                continue;
            }
            $stems[] = mb_substr($token, 0, 6); // prefix stem covers Polish inflection
        }

        return array_values(array_unique($stems));
    }

    private function fold(string $text): string
    {
        return strtr(mb_strtolower($text), [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
            'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
        ]);
    }
}
