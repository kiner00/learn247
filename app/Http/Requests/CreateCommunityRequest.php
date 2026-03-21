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
            'avatar'                   => 'nullable|image|max:10240|dimensions:min_width=100,min_height=100',
            'cover_image'              => 'required|image|max:10240|dimensions:min_width=720,min_height=383,ratio=16/9',
            'is_private'               => 'boolean',
            'price'                    => 'nullable|numeric|min:0',
            'currency'                 => 'nullable|string|in:PHP,USD',
            'billing_type'             => 'nullable|string|in:monthly,one_time',
            'affiliate_commission_rate' => 'nullable|integer|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'cover_image.dimensions' => 'Banner image must be at least 720×383 px and 16:9 ratio (e.g. 1280×720, 1920×1080).',
            'avatar.dimensions'      => 'Logo image must be at least 100×100 px.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->slug && $this->name) {
            $this->merge(['slug' => Str::slug($this->name)]);
        }
    }
}
