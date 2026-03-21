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
            'category'    => 'nullable|string|in:Tech,Business,Design,Health,Education,Finance,Other',
            'avatar'      => 'nullable|image|max:10240',
            'cover_image' => 'required|image|max:10240',
            'is_private'               => 'boolean',
            'price'                    => 'nullable|numeric|min:0',
            'currency'                 => 'nullable|string|in:PHP,USD',
            'billing_type'             => 'nullable|string|in:monthly,one_time',
            'affiliate_commission_rate' => 'nullable|integer|min:0|max:100',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->slug && $this->name) {
            $this->merge(['slug' => Str::slug($this->name)]);
        }
    }
}
