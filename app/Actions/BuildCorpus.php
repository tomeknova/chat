<?php

namespace App\Actions;

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Action: BuildCorpus
 *
 * Reads approved VitePress markdown from the docs source, cuts each page into
 * answer-units (by H2/H3) and writes them to a single JSON corpus file. Only
 * pages whose frontmatter sets the approval key truthy are included — the
 * human-review anti-injection boundary (SCOPE_V1).
 */
class BuildCorpus
{
    /**
     * Scan one profile's source repo and return its approved answer-units.
     *
     * Pure build — no file writing. The command (BuildCorpusCommand) owns
     * validation, atomic publication (tmp+rename) and the manifest, so atomicity
     * lives in ONE place (audit #3). Caller passes the profile's source/exclude
     * explicitly rather than reading global config (testability, no hidden state).
     *
     * @param  list<string>  $exclude
     * @return array{pages_scanned: int, pages_approved: int, units: list<array{answer_unit_id: string, content: string, content_hash: string, intents: list<string>, canonical_url: string}>}
     */
    public function handle(string $source, string $approvalKey, array $exclude): array
    {
        $finder = (new Finder)
            ->files()
            ->in($source)
            ->name('*.md')
            ->notPath('node_modules')
            ->notPath('docs') // meta files (authoring guide), not doc content
            ->ignoreDotFiles(true)
            ->ignoreVCS(true);

        $units = [];
        $pagesScanned = 0;
        $pagesApproved = 0;

        foreach ($finder as $file) {
            if (in_array($file->getFilename(), $exclude, true)) {
                continue;
            }

            $pagesScanned++;

            [$frontmatter, $body] = $this->splitFrontmatter($file->getContents());

            if (! $this->isApproved($frontmatter, $approvalKey)) {
                continue;
            }

            $pagesApproved++;

            $relativeNoExt = $this->relativePathWithoutExtension($file->getRelativePathname());
            $intents = $this->intents($frontmatter);

            foreach ($this->unitsFromPage($body, $this->pageId($relativeNoExt), $this->pageUrl($relativeNoExt), $intents) as $unit) {
                $units[] = $unit;
            }
        }

        return [
            'pages_scanned' => $pagesScanned,
            'pages_approved' => $pagesApproved,
            'units' => $units,
        ];
    }

    /**
     * Cut one page body into answer-units (intro + each H2/H3 section).
     *
     * @param  list<string>  $intents
     * @return list<array{answer_unit_id: string, content: string, content_hash: string, intents: list<string>, canonical_url: string}>
     */
    public function unitsFromPage(string $body, string $pageId, string $pageUrl, array $intents = []): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];

        $introLines = [];
        $sections = [];
        $current = null;
        $h1Seen = false;

        foreach ($lines as $line) {
            if (! $h1Seen && preg_match('/^#\s+\S/', $line)) {
                $h1Seen = true; // drop the page title (H1)

                continue;
            }

            if (preg_match('/^#{2,3}\s+(.+?)\s*$/', $line, $m)) {
                if ($current !== null) {
                    $sections[] = $current;
                }
                $current = ['heading' => trim($m[1]), 'lines' => [$line]];

                continue;
            }

            if ($current === null) {
                $introLines[] = $line;
            } else {
                $current['lines'][] = $line;
            }
        }

        if ($current !== null) {
            $sections[] = $current;
        }

        $units = [];
        $seenIds = [];
        $introText = trim(implode("\n", $introLines));

        // No H2/H3 → the whole page is a single unit.
        if ($sections === []) {
            if ($introText !== '') {
                $units[] = $this->makeUnit($pageId, $introText, $intents, $pageUrl);
            }

            return $units;
        }

        // Intro text above the first heading becomes the page-level unit.
        if ($introText !== '') {
            $units[] = $this->makeUnit($pageId, $introText, $intents, $pageUrl);
        }

        foreach ($sections as $section) {
            $content = trim(implode("\n", $section['lines']));
            if ($content === '') {
                continue;
            }

            $slug = Str::slug($section['heading']) ?: 'sekcja';
            $id = $this->uniqueId($pageId.'.'.$slug, $seenIds);

            // canonical_url includes the section hash — VitePress slugify matches Str::slug() (ASCII).
            $units[] = $this->makeUnit($id, $content, $intents, $pageUrl.'#'.$slug);
        }

        return $units;
    }

    /**
     * @param  list<string>  $intents
     * @return array{answer_unit_id: string, content: string, content_hash: string, intents: list<string>, canonical_url: string}
     */
    private function makeUnit(string $id, string $content, array $intents, string $url): array
    {
        return [
            'answer_unit_id' => $id,
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            'intents' => $intents,
            'canonical_url' => $url,
        ];
    }

    /**
     * Split a "--- yaml --- body" document into [frontmatter, body].
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function splitFrontmatter(string $raw): array
    {
        if (! preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)$/s', $raw, $m)) {
            return [[], $raw];
        }

        $parsed = Yaml::parse($m[1]) ?? [];

        return [is_array($parsed) ? $parsed : [], $m[2]];
    }

    /**
     * @param  array<string, mixed>  $frontmatter
     */
    private function isApproved(array $frontmatter, string $approvalKey): bool
    {
        return (bool) ($frontmatter[$approvalKey] ?? false);
    }

    /**
     * @param  array<string, mixed>  $frontmatter
     * @return list<string>
     */
    private function intents(array $frontmatter): array
    {
        $intents = $frontmatter['intents'] ?? [];

        return is_array($intents) ? array_values(array_map('strval', $intents)) : [];
    }

    private function relativePathWithoutExtension(string $relativePathname): string
    {
        return preg_replace('/\.md$/', '', str_replace('\\', '/', $relativePathname)) ?? $relativePathname;
    }

    /**
     * `start/logowanie` → `start.logowanie`; `index` → `index`.
     */
    private function pageId(string $relativeNoExt): string
    {
        return str_replace('/', '.', $relativeNoExt);
    }

    /**
     * `start/logowanie` → `/start/logowanie`; `index` → `/`.
     */
    private function pageUrl(string $relativeNoExt): string
    {
        if ($relativeNoExt === 'index') {
            return '/';
        }

        return '/'.preg_replace('/\/index$/', '', $relativeNoExt);
    }

    /**
     * @param  array<string, int>  $seen
     */
    private function uniqueId(string $id, array &$seen): string
    {
        if (! isset($seen[$id])) {
            $seen[$id] = 0;

            return $id;
        }

        $seen[$id]++;

        return $id.'-'.$seen[$id];
    }
}
