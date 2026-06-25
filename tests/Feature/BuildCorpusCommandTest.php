<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * chat:build-corpus — multi-profile build, manifest, isolation, last-known-good.
 */
class BuildCorpusCommandTest extends TestCase
{
    private string $tmp;

    private string $out;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmp = sys_get_temp_dir().'/corpus_cmd_'.uniqid();
        $this->out = $this->tmp.'/out';
        @mkdir($this->out, 0775, true);

        // Cache::lock without relying on a DB cache table.
        config(['cache.default' => 'array']);
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

    private function writeDoc(string $base, string $relative, string $contents): void
    {
        $path = $base.'/'.$relative;
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $contents);
    }

    /**
     * @return array{0: string, 1: string} kings + clams source dirs
     */
    private function configureProfiles(bool $createClams = true): array
    {
        $kings = $this->tmp.'/kings';
        $clams = $this->tmp.'/clams';
        $this->writeDoc($kings, 'index.md', "---\nassistant: true\n---\n# K\n\nKINGS-ONLY content.");
        if ($createClams) {
            $this->writeDoc($clams, 'index.md', "---\nassistant: true\n---\n# C\n\nCLAMS-ONLY content.");
        }

        config([
            'corpus.default' => 'kings5-docs',
            'corpus.active_profile' => 'kings5-docs',
            'corpus.output_dir' => $this->out,
            'corpus.approval_key' => 'assistant',
            'corpus.exclude' => ['README.md', 'DEPLOY-SERVER.md', 'CLAUDE.md'],
            'corpus.profiles' => [
                'kings5-docs' => ['enabled' => true, 'source_path' => $kings],
                'clams-docs' => ['enabled' => true, 'source_path' => $clams],
            ],
        ]);

        return [$kings, $clams];
    }

    /**
     * @return array<string, mixed>
     */
    private function master(): array
    {
        return json_decode((string) file_get_contents($this->out.'/corpus-master.json'), true);
    }

    public function test_full_build_writes_both_artifacts_and_master(): void
    {
        $this->configureProfiles();

        $code = Artisan::call('chat:build-corpus');

        $this->assertSame(0, $code);
        $this->assertFileExists($this->out.'/corpus-kings5-docs.json');
        $this->assertFileExists($this->out.'/corpus-clams-docs.json');
        $this->assertFileExists($this->out.'/corpus-master.json');

        $master = $this->master();
        $this->assertSame('ok', $master['profiles']['kings5-docs']['status']);
        $this->assertSame('ok', $master['profiles']['clams-docs']['status']);
        $this->assertNotNull($master['last_full_build_at']);
    }

    public function test_profiles_are_isolated(): void
    {
        // Same answer_unit_id ('index') in both, but content lands in separate files.
        $this->configureProfiles();

        Artisan::call('chat:build-corpus');

        $kingsJson = (string) file_get_contents($this->out.'/corpus-kings5-docs.json');
        $clamsJson = (string) file_get_contents($this->out.'/corpus-clams-docs.json');

        $this->assertStringContainsString('KINGS-ONLY', $kingsJson);
        $this->assertStringNotContainsString('CLAMS-ONLY', $kingsJson);
        $this->assertStringContainsString('CLAMS-ONLY', $clamsJson);
        $this->assertStringNotContainsString('KINGS-ONLY', $clamsJson);
    }

    public function test_missing_source_without_prior_artifact_is_unavailable_and_fails_exit(): void
    {
        $this->configureProfiles(createClams: false);

        $code = Artisan::call('chat:build-corpus');

        $this->assertSame(1, $code); // a requested enabled profile is not fresh
        $this->assertFileExists($this->out.'/corpus-kings5-docs.json');
        $this->assertFileDoesNotExist($this->out.'/corpus-clams-docs.json');

        $master = $this->master();
        $this->assertSame('ok', $master['profiles']['kings5-docs']['status']);
        $this->assertSame('unavailable', $master['profiles']['clams-docs']['status']);
        $this->assertSame('missing-source', $master['profiles']['clams-docs']['reason']);
    }

    public function test_missing_source_keeps_last_known_good_as_stale(): void
    {
        [, $clams] = $this->configureProfiles();
        Artisan::call('chat:build-corpus'); // both ok
        $clamsBefore = (string) file_get_contents($this->out.'/corpus-clams-docs.json');

        $this->deleteDir($clams); // source disappears
        Artisan::call('chat:build-corpus');

        // Old artifact untouched; manifest marks it stale + missing-source.
        $this->assertSame($clamsBefore, (string) file_get_contents($this->out.'/corpus-clams-docs.json'));
        $master = $this->master();
        $this->assertSame('stale', $master['profiles']['clams-docs']['status']);
        $this->assertSame('missing-source', $master['profiles']['clams-docs']['reason']);
    }

    public function test_profile_option_builds_one_and_preserves_other_master_entry(): void
    {
        $this->configureProfiles();
        Artisan::call('chat:build-corpus'); // full
        $clamsBuiltAt = $this->master()['profiles']['clams-docs']['artifact_built_at'];

        $code = Artisan::call('chat:build-corpus', ['--profile' => 'kings5-docs']);

        $this->assertSame(0, $code);
        $master = $this->master();
        $this->assertSame($clamsBuiltAt, $master['profiles']['clams-docs']['artifact_built_at']);
        $this->assertSame('ok', $master['profiles']['kings5-docs']['status']);
    }

    public function test_unknown_profile_option_fails(): void
    {
        $this->configureProfiles();

        $this->assertSame(1, Artisan::call('chat:build-corpus', ['--profile' => 'nope']));
    }

    public function test_disabled_profile_is_skipped(): void
    {
        [$kings, $clams] = $this->configureProfiles();
        config(['corpus.profiles' => [
            'kings5-docs' => ['enabled' => true, 'source_path' => $kings],
            'clams-docs' => ['enabled' => false, 'source_path' => $clams],
        ]]);

        $code = Artisan::call('chat:build-corpus');

        $this->assertSame(0, $code); // disabled is not a failure
        $this->assertFileExists($this->out.'/corpus-kings5-docs.json');
        $this->assertFileDoesNotExist($this->out.'/corpus-clams-docs.json');
        $this->assertArrayNotHasKey('clams-docs', $this->master()['profiles']);
    }
}
