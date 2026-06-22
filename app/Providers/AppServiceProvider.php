<?php

namespace App\Providers;

use App\Actions\Corpus\CandidateRetriever;
use App\Actions\Corpus\FullCorpusRetriever;
use App\Actions\Corpus\LexicalRetriever;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton so the corpus file is decoded once per request.
        $this->app->singleton(FullCorpusRetriever::class);

        // Retrieval stage selectable by config: `full` (big-context providers)
        // or `lexical` top-k (required for the small local Bielik).
        $this->app->singleton(CandidateRetriever::class, function ($app): CandidateRetriever {
            return match (config('askdocs.retrieval.driver', 'full')) {
                'lexical' => new LexicalRetriever($app->make(FullCorpusRetriever::class)),
                default => $app->make(FullCorpusRetriever::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
