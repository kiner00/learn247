<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'community_id' => 'required|exists:communities,id',
            'title'        => 'nullable|string|max:255',
            'content'      => 'required|string|max:10000',
        ];
    }
}
