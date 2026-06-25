<?php

namespace Tests\Feature;

use App\Actions\BuildCorpus;
use Tests\TestCase;

class BuildCorpusTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmp = sys_get_temp_dir().'/corpus_test_'.uniqid();
        @mkdir($this->tmp.'/start', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tmp);

        parent::tearDown();
    }

    private function deleteDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            is_dir($path) ? $this->deleteDir($path) : @unlink($path);
        }

        @rmdir($dir);
    }

    private function writeDoc(string $relative, string $contents): void
    {
        $path = $this->tmp.'/'.$relative;
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $contents);
    }

    // ---- Pure cutting logic (no filesystem) -------------------------------

    public function test_single_section_page_becomes_one_unit(): void
    {
        $units = app(BuildCorpus::class)->unitsFromPage(
            "# Logowanie\n\nWejdź na /admin i zaloguj się.",
            'start.logowanie',
            '/start/logowanie',
        );

        $this->assertCount(1, $units);
        $this->assertSame('start.logowanie', $units[0]['answer_unit_id']);
        $this->assertSame('/start/logowanie', $units[0]['canonical_url']);
        $this->assertStringContainsString('Wejdź na /admin', $units[0]['content']);
        $this->assertStringNotContainsString('# Logowanie', $units[0]['content']);
        $this->assertSame(hash('sha256', $units[0]['content']), $units[0]['content_hash']);
    }

    public function test_multi_section_page_is_cut_by_h2_h3(): void
    {
        $markdown = "# Tytuł\n\nWstęp strony.\n\n## Krok 1\n\nTreść A.\n\n### Szczegół\n\nTreść B.";

        $units = app(BuildCorpus::class)->unitsFromPage($markdown, 'wydarzenia.tworzenie', '/wydarzenia/tworzenie', ['tworzenie']);

        $ids = array_column($units, 'answer_unit_id');
        $this->assertSame(['wydarzenia.tworzenie', 'wydarzenia.tworzenie.krok-1', 'wydarzenia.tworzenie.szczegol'], $ids);

        // Intro unit = page-level; section units get the H2/H3 anchor (#slug).
        $this->assertSame('/wydarzenia/tworzenie', $units[0]['canonical_url']);
        $this->assertSame('/wydarzenia/tworzenie#krok-1', $units[1]['canonical_url']);
        $this->assertSame('/wydarzenia/tworzenie#szczegol', $units[2]['canonical_url']);
        $this->assertSame(['tworzenie'], $units[1]['intents']);
    }

    public function test_index_maps_to_root_url(): void
    {
        $units = app(BuildCorpus::class)->unitsFromPage("# Start\n\nWprowadzenie.", 'index', '/');

        $this->assertSame('/', $units[0]['canonical_url']);
    }

    // ---- Integration over a fixture tree ----------------------------------

    public function test_only_approved_pages_enter_the_corpus(): void
    {
        $this->writeDoc('start/logowanie.md', "---\nassistant: true\n---\n# Logowanie\n\nZaloguj się na /admin.");
        $this->writeDoc('start/szkic.md', "# Szkic\n\nNiezatwierdzona strona (bez frontmatter).");
        $this->writeDoc('README.md', "---\nassistant: true\n---\n# Readme\n\nNie treść docs.");

        $stats = app(BuildCorpus::class)->handle($this->tmp, 'assistant', ['README.md', 'DEPLOY-SERVER.md']);

        $this->assertSame(2, $stats['pages_scanned']); // README excluded from scan
        $this->assertSame(1, $stats['pages_approved']);
        $this->assertCount(1, $stats['units']);
        $this->assertSame('start.logowanie', $stats['units'][0]['answer_unit_id']);
    }

    public function test_reports_empty_when_nothing_is_approved(): void
    {
        $this->writeDoc('start/logowanie.md', "# Logowanie\n\nBez frontmatter = niezatwierdzone.");

        $stats = app(BuildCorpus::class)->handle($this->tmp, 'assistant', ['README.md', 'DEPLOY-SERVER.md']);

        $this->assertSame(0, $stats['pages_approved']);
        $this->assertCount(0, $stats['units']);
    }
}
