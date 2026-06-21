<?php

namespace App\Providers;

use App\Actions\Corpus\CandidateRetriever;
use App\Actions\Corpus\FullCorpusRetriever;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // v1 retrieval = full corpus; swap here for lexical/vector later.
        // Singleton so the corpus file is decoded once per request.
        $this->app->singleton(CandidateRetriever::class, FullCorpusRetriever::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
