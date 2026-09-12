<?php

namespace App\Providers;

use App\Search\IndexSearch;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Opensolr\ScoutOpensolr\OpensolrClient;

/**
 * Application wiring: the Opensolr API client, the index search service and the rate limits.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * One OpensolrClient per request, built from the same config the Scout driver reads,
     * so the Eloquent search and the raw index search always talk to the same account.
     */
    public function register(): void
    {
        $this->app->singleton(OpensolrClient::class, function (): OpensolrClient {
            return new OpensolrClient(
                (string) config('scout-opensolr.email'),
                (string) config('scout-opensolr.api_key'),
            );
        });

        $this->app->bind(IndexSearch::class, function (): IndexSearch {
            return new IndexSearch(
                $this->app->make(OpensolrClient::class),
                (string) config('scout-opensolr.index'),
            );
        });
    }

    /**
     * Rate limits keyed by client address. A search is one Solr round-trip; an AI answer is a
     * GPU embedding plus an LLM generation billed to the index owner, so it gets a tighter cap.
     */
    public function boot(): void
    {
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('answer', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
