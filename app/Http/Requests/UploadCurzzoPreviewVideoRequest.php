<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadCurzzoPreviewVideoRequest extends FormRequest
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
            'filename' => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'string', 'in:video/mp4,video/quicktime,video/webm'],
            'size' => ['required', 'integer', 'min:1'],
        ];
    }
}
