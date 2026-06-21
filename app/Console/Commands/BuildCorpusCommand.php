<?php

namespace App\Console\Commands;

use App\Actions\BuildCorpus;
use Illuminate\Console\Command;

class BuildCorpusCommand extends Command
{
    protected $signature = 'chat:build-corpus';

    protected $description = 'Buduje korpus jednostek z zatwierdzonych docs (kings5-docs) do pliku JSON';

    public function handle(BuildCorpus $action): int
    {
        $stats = $action->handle();

        $this->info('Korpus zbudowany.');
        $this->line('Strony przeskanowane:  '.$stats['pages_scanned']);
        $this->line('Strony zatwierdzone:   '.$stats['pages_approved']);
        $this->line('Jednostki:             '.$stats['units']);
        $this->line('Plik:                  '.$stats['output']);

        if ($stats['pages_approved'] === 0) {
            $this->warn('Brak zatwierdzonych stron (frontmatter `'.config('corpus.approval_key').': true`). Korpus pusty — to bezpieczny stan domyślny.');
        }

        return self::SUCCESS;
    }
}
