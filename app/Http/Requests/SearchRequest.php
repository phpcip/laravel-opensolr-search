<?php

namespace App\Http\Requests;

use App\Search\IndexSearch;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the index search endpoint.
 *
 * The query is plain text of a bounded length with no control characters; page numbers are
 * capped so deep paging cannot be used to hammer the index.
 */
class SearchRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:200', 'regex:/^[^\p{C}]+$/u'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:'.IndexSearch::MAX_PAGES],
            'fresh' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.regex' => 'The query may not contain control characters.',
        ];
    }
}
