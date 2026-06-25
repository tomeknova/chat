<?php

namespace App\Console\Commands;

use App\Actions\BuildCorpus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * chat:build-corpus — builds the answer-unit corpus for the docs instructions.
 *
 * Without --profile (cron/deploy) it refreshes ALL enabled profiles, each into
 * its own corpus-{profile}.json, then writes the corpus-master.json manifest.
 * With --profile=X it refreshes only X and merges its entry into the manifest.
 *
 * Guarantees (audit-hardened, see docs/MULTI_CORPUS.md):
 *  - one global lock for every variant (shared corpus-master.json),
 *  - validate BEFORE publishing; a failed build never overwrites the last good
 *    artifact (last-known-good → manifest status stale/unavailable + reason),
 *  - atomic publication (tmp + rename) for every corpus-*.json AND the manifest,
 *  - exit code scoped to the REQUESTED profile set.
 */
class BuildCorpusCommand extends Command
{
    protected $signature = 'chat:build-corpus
        {--profile= : Build only this profile (e.g. kings5-docs|clams-docs); default = all enabled}';

    protected $description = 'Buduje korpus jednostek z zatwierdzonych docs (wszystkie profile + manifest)';

    private const LOCK_KEY = 'chat:build-corpus';

    private const LOCK_TTL = 600; // seconds — must exceed a realistic build

    public function handle(BuildCorpus $action): int
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL);

        // Do not wait: a concurrent build (cron + deploy + manual) returns at once.
        if (! $lock->get()) {
            $this->error('Inny build trwa (lock zajęty). Pomijam.');

            return self::FAILURE;
        }

        try {
            return $this->runBuild($action);
        } finally {
            $lock->release();
        }
    }

    private function runBuild(BuildCorpus $action): int
    {
        /** @var array<string, array{enabled: bool, source_path: string}> $profiles */
        $profiles = (array) config('corpus.profiles');
        $requested = $this->option('profile');

        if ($requested !== null && ! isset($profiles[$requested])) {
            $this->error("Nieznany profil: {$requested}. Dozwolone: ".implode(', ', array_keys($profiles)).'.');

            return self::FAILURE;
        }

        $names = $requested !== null ? [$requested] : array_keys($profiles);
        $isFullBuild = $requested === null;

        $results = [];
        $allRequestedOk = true;

        foreach ($names as $name) {
            $profile = $profiles[$name];

            // enabled=false → not built, not a failure (the profile is intentionally off).
            if (! ($profile['enabled'] ?? true)) {
                $this->warn("Profil {$name}: enabled=false — pominięty.");

                continue;
            }

            $result = $this->buildProfile($action, $name, $profile);
            $results[$name] = $result;

            if ($result['status'] !== 'ok') {
                $allRequestedOk = false;
            }
        }

        // last_full_build_at advances only on a FULL build where everything is ok.
        $this->writeMaster($results, $isFullBuild && $allRequestedOk);

        if ($allRequestedOk) {
            $this->info('Korpus zbudowany. Manifest: '.$this->path('corpus-master.json'));

            return self::SUCCESS;
        }

        $this->warn('Build niepełny — co najmniej jeden żądany profil nie jest świeży (patrz manifest).');

        return self::FAILURE;
    }

    /**
     * Build one profile and publish it atomically. Returns its manifest entry.
     *
     * @param  array{source_path: string}  $profile
     * @return array{file: string, source: string, units: int|null, status: string, reason: string|null, artifact_available: bool, artifact_built_at: string|null}
     */
    private function buildProfile(BuildCorpus $action, string $name, array $profile): array
    {
        $source = (string) $profile['source_path'];
        $file = 'corpus-'.$name.'.json';
        $finalPath = $this->path($file);
        $artifactExists = is_file($finalPath);

        // Missing source (e.g. repo not cloned on this machine) → last-known-good.
        if (! is_dir($source)) {
            $this->warn("Profil {$name}: brak źródła ({$source}) — pominięty.");

            return $this->failedEntry($file, $source, 'missing-source', $artifactExists);
        }

        try {
            $stats = $action->handle($source, (string) config('corpus.approval_key'), (array) config('corpus.exclude'));
            $units = $stats['units'];

            // Non-empty source but zero approved units is almost always a misconfig
            // (wrong approval key / path) — refuse rather than publish an empty corpus.
            if ($units === []) {
                throw new \RuntimeException('zero zatwierdzonych jednostek (sprawdź `assistant: true` i ścieżkę źródła)');
            }

            $builtAt = $this->utcNow();
            $this->publish($finalPath, [
                'built_at' => $builtAt,
                'source' => $source,
                'profile' => $name,
                'count' => count($units),
                'units' => $units,
            ]);

            $this->info("Profil {$name}: OK — {$stats['pages_approved']} stron, ".count($units).' jednostek.');

            return [
                'file' => $file,
                'source' => $source,
                'units' => count($units),
                'status' => 'ok',
                'reason' => null,
                'artifact_available' => true,
                'artifact_built_at' => $builtAt,
            ];
        } catch (Throwable $e) {
            $this->error("Profil {$name}: build nieudany — {$e->getMessage()}. Poprzedni artefakt bez zmian.");

            return $this->failedEntry($file, $source, 'build-failed', $artifactExists);
        }
    }

    /**
     * @return array{file: string, source: string, units: null, status: string, reason: string, artifact_available: bool, artifact_built_at: null}
     */
    private function failedEntry(string $file, string $source, string $reason, bool $artifactExists): array
    {
        return [
            'file' => $file,
            'source' => $source,
            'units' => null,
            'status' => $artifactExists ? 'stale' : 'unavailable',
            'reason' => $reason,
            'artifact_available' => $artifactExists,
            'artifact_built_at' => null,
        ];
    }

    /**
     * Write/merge the manifest. A partial (--profile) build preserves the other
     * profiles' entries untouched (incl. their artifact_built_at).
     *
     * @param  array<string, array<string, mixed>>  $results
     */
    private function writeMaster(array $results, bool $fullSuccess): void
    {
        $path = $this->path('corpus-master.json');

        $existing = [];
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }

        $now = $this->utcNow();
        $profilesOut = is_array($existing['profiles'] ?? null) ? $existing['profiles'] : [];

        foreach ($results as $name => $r) {
            $profilesOut[$name] = [
                'file' => $r['file'],
                'source' => $r['source'],
                // Keep last good unit count when this attempt failed.
                'units' => $r['units'] ?? ($profilesOut[$name]['units'] ?? null),
                'status' => $r['status'],
                'reason' => $r['reason'],
                'artifact_available' => $r['artifact_available'],
                'artifact_built_at' => $r['artifact_built_at'] ?? ($profilesOut[$name]['artifact_built_at'] ?? null),
                'last_attempt_at' => $now,
            ];
        }

        $this->publish($path, [
            'schema_version' => 1,
            'updated_at' => $now,
            'last_full_build_at' => $fullSuccess ? $now : ($existing['last_full_build_at'] ?? null),
            'default' => config('corpus.default'),
            'profiles' => $profilesOut,
        ]);
    }

    /**
     * Atomic publish: encode → temp file → rename. The temp file is always
     * cleaned up; runtime therefore never reads a half-written JSON.
     *
     * @param  array<string, mixed>  $payload
     */
    private function publish(string $path, array $payload): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('json_encode nieudane: '.json_last_error_msg());
        }

        $tmp = $path.'.tmp';

        try {
            if (file_put_contents($tmp, $json) === false) {
                throw new \RuntimeException("zapis tymczasowy nieudany: {$tmp}");
            }
            if (! rename($tmp, $path)) {
                throw new \RuntimeException("publikacja (rename) nieudana: {$tmp} → {$path}");
            }
            // Keep readable for the web runtime (often a different OS user).
            @chmod($path, 0664);
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    private function path(string $file): string
    {
        return rtrim((string) config('corpus.output_dir'), '/').'/'.$file;
    }

    /** UTC timestamp with a literal Z (manifest/log convention). */
    private function utcNow(): string
    {
        return now()->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
