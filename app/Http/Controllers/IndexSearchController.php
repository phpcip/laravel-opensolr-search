<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnswerRequest;
use App\Http\Requests\SearchRequest;
use App\Search\IndexSearch;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * JSON endpoints behind the "Index search" page: hybrid search over the whole index and a
 * grounded answer written by the platform's LLM from the top hits.
 *
 * Failures of the upstream API are reported and answered with a generic 502; the exception
 * text never reaches the browser.
 */
class IndexSearchController extends Controller
{
    /**
     * One page of hybrid results with highlights, facets and a spelling suggestion.
     */
    public function search(SearchRequest $request, IndexSearch $search): JsonResponse
    {
        $input = $request->validated();

        try {
            return response()->json($search->search(
                (string) $input['q'],
                (int) ($input['page'] ?? 1),
                $request->boolean('fresh'),
            ));
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => 'The search service is not available right now.'], 502);
        }
    }

    /**
     * A plain-text answer grounded on the top hybrid hits for the question.
     */
    public function answer(AnswerRequest $request, IndexSearch $search): JsonResponse
    {
        try {
            $answer = $search->answer((string) $request->validated('q'));
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => 'The answer service is not available right now.'], 502);
        }

        return response()->json(['answer' => $answer]);
    }
}
