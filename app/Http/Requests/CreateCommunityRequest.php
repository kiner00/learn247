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
        if ($this->hasFile('cover_image')) {
            $file = $this->file('cover_image');
            \Log::info('Cover image upload debug', [
                'isValid'       => $file->isValid(),
                'error'         => $file->getError(),
                'errorMessage'  => $file->getErrorMessage(),
                'size'          => $file->getSize(),
                'mimeType'      => $file->getMimeType(),
                'clientOriginal'=> $file->getClientOriginalName(),
                'tmpPath'       => $file->getPathname(),
                'tmpExists'     => file_exists($file->getPathname()),
            ]);
        } else {
            \Log::info('Cover image upload debug: no file received');
        }

        return [
            'name'        => 'required|string|max:100',
            'slug'        => 'nullable|string|max:100|unique:communities,slug|regex:/^[a-z0-9-]+$/',
            'description' => 'nullable|string|max:2000',
            'category'    => 'nullable|string|in:Tech,Business,Design,Health,Education,Finance,Other',
            'avatar'      => 'nullable|image|max:15360',
            'cover_image' => 'nullable|image|max:15360',
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
            $base = Str::slug($this->name);
            $slug = $base;
            $i = 1;
            while (\App\Models\Community::where('slug', $slug)->exists()) {
                $slug = $base . '-' . $i++;
            }
            $this->merge(['slug' => $slug]);
        }
    }
}
