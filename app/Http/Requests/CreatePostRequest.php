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
        $rules = [
            'title'     => ['nullable', 'string', 'max:255'],
            'content'   => ['required', 'string', 'max:10000'],
            'image'     => ['nullable', 'image', 'max:10240'],
            'video_url' => ['nullable', 'url', 'max:500'],
        ];

        if (! $this->route('community')) {
            $rules['community_id']   = ['required_without:community_slug', 'nullable', 'exists:communities,id'];
            $rules['community_slug'] = ['required_without:community_id', 'nullable', 'exists:communities,slug'];
        }

        return $rules;
    }
}
