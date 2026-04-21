<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatWithCurzzoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:20000'],
            'conversation_id' => ['nullable', 'string', 'uuid'],
        ];
    }
}
