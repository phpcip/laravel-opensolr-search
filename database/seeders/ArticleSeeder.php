<?php

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;
use Laravel\Scout\ModelObserver;

/**
 * Twenty short articles across five everyday topics.
 *
 * Written so that meaning-based matching has something to find: "sleepy pets" should land
 * on the cat article, "budget dining" on the cheap-eats one, even though neither phrase
 * appears in the text. Indexing is switched off while seeding so the rows go in with one
 * insert each; push them to Opensolr afterwards with `php artisan scout:import App\Models\Article`.
 */
class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        ModelObserver::disableSyncingFor(Article::class);

        Article::query()->delete();

        foreach (require __DIR__.'/data/articles.php' as $article) {
            Article::query()->create($article);
        }

        ModelObserver::enableSyncingFor(Article::class);
    }
}
