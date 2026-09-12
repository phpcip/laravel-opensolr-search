<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the grounded AI answer endpoint.
 */
class AnswerRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:3', 'max:300', 'regex:/^[^\p{C}]+$/u'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.regex' => 'The question may not contain control characters.',
        ];
    }
}
