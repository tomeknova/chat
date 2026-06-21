<?php

namespace App\Actions\Corpus;

/**
 * Selects the corpus answer-units to put in front of the model for a question.
 *
 * v1 = full corpus (see FullCorpusRetriever). When the corpus grows past the
 * cost / lost-in-the-middle threshold this is swapped for a lexical, then a
 * vector retriever — WITHOUT touching AskDocs, the validator or the UI. The
 * anti-hallucination core (∈ context + content_hash) is independent of which
 * retriever is used.
 */
interface CandidateRetriever
{
    /**
     * @return list<array{answer_unit_id: string, content: string, content_hash: string, intents: list<string>, canonical_url: string}>
     */
    public function retrieve(string $question): array;
}
