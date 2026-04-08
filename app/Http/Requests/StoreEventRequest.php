<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($file = $this->file('cover_image')) {
            Log::info('Event cover_image upload debug', [
                'error_code'   => $file->getError(),
                'error_msg'    => $file->getErrorMessage(),
                'size'         => $file->getSize(),
                'isValid'      => $file->isValid(),
                'originalName' => $file->getClientOriginalName(),
                'mimeType'     => $file->getClientMimeType(),
            ]);
        } else {
            Log::info('Event cover_image: no file received in request', [
                'has_file'   => $this->hasFile('cover_image'),
                'all_files'  => array_keys($this->allFiles()),
                'content_type' => $this->header('Content-Type'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'start_at'    => 'required|date',
            'end_at'      => 'nullable|date|after:start_at',
            'timezone'    => 'required|string|timezone',
            'url'         => 'nullable|url|max:500',
            'cover_image' => 'nullable|image|max:10240',
            'visibility'  => 'nullable|in:public,free,paid',
        ];
    }

    public function messages(): array
    {
        return [
            'cover_image.uploaded' => 'The cover image failed to upload. The file may be too large — please use an image under 10 MB.',
            'cover_image.max'      => 'The cover image must be under 10 MB.',
        ];
    }
}
