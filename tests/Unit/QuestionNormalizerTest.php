<?php

namespace Tests\Unit;

use App\AskDocs\QuestionNormalizer;
use PHPUnit\Framework\TestCase;

class QuestionNormalizerTest extends TestCase
{
    private QuestionNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new QuestionNormalizer;
    }

    public function test_lowercases_collapses_whitespace_and_strips_trailing_punctuation(): void
    {
        $this->assertSame('jak się zalogować', $this->normalizer->normalize("  Jak   się\nZalogować??  "));
    }

    public function test_equivalent_inputs_share_a_hash(): void
    {
        $this->assertSame(
            $this->normalizer->hash('Jak się zalogować?'),
            $this->normalizer->hash('  jak SIĘ   zalogować  '),
        );
    }

    public function test_semantically_close_but_different_wording_stays_distinct(): void
    {
        // EXACT normalized match, not semantic — these stay different hashes.
        $this->assertNotSame(
            $this->normalizer->hash('Jak dodać członka?'),
            $this->normalizer->hash('W jaki sposób utworzyć członka?'),
        );
    }

    public function test_hash_is_versioned_64_hex(): void
    {
        $hash = $this->normalizer->hash('test');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
        // Versioned input → differs from a naive sha256 of the lowercased text.
        $this->assertNotSame(hash('sha256', 'test'), $hash);
    }
}
