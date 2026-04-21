<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderCurzzosRequest extends FormRequest
{
    public function authorize(): bool
    {
        $community = $this->route('community');

        return $community !== null
            && $this->user()?->can('update', $community);
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ];
    }
}
