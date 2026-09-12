<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArticleSearchRequest;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * JSON endpoint behind the "Eloquent search" page: Laravel Scout on the Article model.
 *
 * Everything here is stock Scout. The Opensolr driver turns search() into a hybrid query
 * scoped to this model, where() into a Solr filter and paginate() into start/rows with a
 * real total.
 */
class ArticleSearchController extends Controller
{
    /**
     * Search or browse articles, optionally within one category.
     */
    public function __invoke(ArticleSearchRequest $request): JsonResponse
    {
        $input = $request->validated();
        $query = trim((string) ($input['q'] ?? ''));
        $category = (string) ($input['category'] ?? '');

        $builder = Article::search($query);
        if ($category !== '') {
            $builder->where('category', $category);
        }

        try {
            $page = $builder->paginate(Article::PER_PAGE);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => 'The search service is not available right now.'], 502);
        }

        return response()->json([
            'query' => $query,
            'category' => $category,
            'categories' => Article::categories(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'pages' => $page->lastPage(),
            'docs' => $page->getCollection()->map(fn (Article $article) => $article->toSearchResult())->values(),
        ]);
    }
}
