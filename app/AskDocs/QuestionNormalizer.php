<?php

namespace App\AskDocs;

use Normalizer;

/**
 * QuestionNormalizer v1 — stable normalization for the question-dedup /
 * future table-first lookup key (messages.normalized_question_hash).
 *
 * This is an EXACT normalized match, NOT a semantic one: "Jak dodać członka?"
 * and "W jaki sposób utworzyć członka?" stay DIFFERENT hashes. The version is
 * baked into the hash input, so a future algorithm change yields different
 * hashes (no silent collisions) — DO NOT change v1 without consciously
 * rehashing existing rows (bump to v2).
 */
class QuestionNormalizer
{
    private const VERSION = 'v1';

    /**
     * Canonical form: NFC Unicode (when intl is present), lowercased, whitespace
     * (incl. line endings) collapsed to single spaces, trailing punctuation
     * stripped so "jak X?" == "jak X".
     */
    public function normalize(string $question): string
    {
        $text = $question;

        if (class_exists(Normalizer::class)) {
            $text = Normalizer::normalize($text, Normalizer::FORM_C) ?: $text;
        }

        $text = mb_strtolower($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);
        $text = preg_replace('/[\p{P}\p{S}]+$/u', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * Versioned SHA-256 of the normalized question (64 hex chars).
     */
    public function hash(string $question): string
    {
        return hash('sha256', self::VERSION."\0".$this->normalize($question));
    }
}
