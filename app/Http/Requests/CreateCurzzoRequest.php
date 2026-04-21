<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCurzzoRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'instructions' => ['required', 'string', 'max:20000'],
            'personality' => ['nullable', 'array'],
            'personality.tone' => ['nullable', 'string', 'in:friendly,professional,casual,formal'],
            'personality.expertise' => ['nullable', 'string', 'max:5000'],
            'personality.response_style' => ['nullable', 'string', 'in:concise,detailed,conversational'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'cover_image' => ['nullable', 'image', 'max:10240'],
            'preview_video' => ['nullable', 'string', 'max:1000'],
            'preview_video_sound' => ['nullable', 'boolean'],
            'access_type' => ['required', 'string', 'in:free,inclusive,paid_once,paid_monthly,member_once'],
            'model_tier' => ['sometimes', 'string', Rule::in(array_keys(config('curzzos.tiers')))],
            'price' => ['nullable', 'numeric', 'min:0', 'required_if:access_type,paid_once', 'required_if:access_type,paid_monthly'],
            'currency' => ['nullable', 'string', 'in:PHP,USD'],
            'billing_type' => ['nullable', 'string', 'in:one_time,monthly'],
            'affiliate_commission_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
