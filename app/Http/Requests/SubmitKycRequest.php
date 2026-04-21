<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'id_document' => ['required', 'image', 'max:10240'],
            'selfie' => ['required', 'image', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_document.required' => 'Please upload your government-issued ID.',
            'id_document.image' => 'The ID document must be an image file (JPG, PNG, GIF, BMP, SVG, or WebP).',
            'id_document.max' => 'The ID document must be smaller than 10 MB.',
            'id_document.uploaded' => 'The ID document failed to upload. The file may be too large — please use an image under 10 MB.',
            'selfie.required' => 'Please upload a selfie holding your ID.',
            'selfie.image' => 'The selfie must be an image file (JPG, PNG, GIF, BMP, SVG, or WebP).',
            'selfie.max' => 'The selfie must be smaller than 10 MB.',
            'selfie.uploaded' => 'The selfie failed to upload. The file may be too large — please use an image under 10 MB.',
        ];
    }
}
