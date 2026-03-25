<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string', 'max:2000'],
            'media'   => ['nullable', 'file', 'mimes:jpeg,jpg,png,gif,webp,mp4,mov,avi,webm', 'max:51200'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (! $this->filled('content') && ! $this->hasFile('media')) {
                $v->errors()->add('content', 'A message or media file is required.');
            }
        });
    }
}
