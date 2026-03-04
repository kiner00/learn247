<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class CreateCommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:100',
            'slug'        => 'nullable|string|max:100|unique:communities,slug|regex:/^[a-z0-9-]+$/',
            'description' => 'nullable|string|max:2000',
            'avatar'      => 'nullable|url',
            'is_private'  => 'boolean',
            'price'       => 'nullable|numeric|min:0',
            'currency'    => 'nullable|string|in:PHP,USD',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->slug && $this->name) {
            $this->merge(['slug' => Str::slug($this->name)]);
        }
    }
}
