<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
}
