<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'pass_score' => ['required', 'integer', 'min:1', 'max:100'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question' => ['required', 'string'],
            'questions.*.type' => ['required', 'in:multiple_choice,true_false'],
            'questions.*.options' => ['required', 'array', 'min:2'],
            'questions.*.options.*.label' => ['required', 'string'],
            'questions.*.options.*.is_correct' => ['required', 'boolean'],
        ];
    }
}
