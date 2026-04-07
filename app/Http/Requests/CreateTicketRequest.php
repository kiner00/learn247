<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'subject'       => ['required', 'string', 'max:255'],
            'description'   => ['required', 'string', 'max:10000'],
            'type'          => ['required', 'in:bug,suggestion,question,other'],
            'priority'      => ['sometimes', 'in:low,medium,high'],
            'attachments'   => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['image', 'max:10240'],
        ];
    }
}
