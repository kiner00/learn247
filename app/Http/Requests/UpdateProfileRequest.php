<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('users')->ignore($this->user()->id)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:300'],
            'location' => ['nullable', 'string', 'max:255'],
            'social_links' => ['nullable', 'array'],
            'social_links.*' => ['nullable', 'string', 'max:500'],
            'hide_from_search' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'image', 'max:10240'],
        ];
    }
}
