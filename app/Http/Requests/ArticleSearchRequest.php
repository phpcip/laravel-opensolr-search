<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the Eloquent (Scout) search endpoint.
 *
 * An empty query browses every article; a category narrows it through Scout's where(),
 * which the driver turns into a Solr filter query on the article's meta_category field.
 */
class ArticleSearchRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200', 'regex:/^[^\p{C}]*$/u'],
            'category' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9 &\-]+$/'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
