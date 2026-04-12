<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $community = $this->route('community');

        return $community !== null
            && $this->user()?->can('update', $community);
    }

    public function rules(): array
    {
        $community          = $this->route('community');
        $plan               = $this->user()->creatorPlan();
        $canUseIntegrations = in_array($plan, ['basic', 'pro'], true);
        $isPro              = $plan === 'pro';

        return [
            'name'                      => ['required', 'string', 'max:255'],
            'description'               => ['nullable', 'string', 'max:2000'],
            'category'                  => ['nullable', 'string', 'in:Tech,Business,Design,Health,Education,Finance,Other'],
            'avatar'                    => ['nullable', 'image', 'max:15360'],
            'cover_image'               => ['nullable', 'image', 'max:15360'],
            'remove_cover_image'        => ['sometimes', 'boolean'],
            'remove_avatar'             => ['sometimes', 'boolean'],
            'price'                     => ['nullable', 'numeric', 'min:0'],
            'currency'                  => ['nullable', 'string', 'in:PHP,USD'],
            'billing_type'              => ['nullable', 'string', 'in:monthly,one_time'],
            'is_private'                => ['boolean'],
            'affiliate_commission_rate' => ['nullable', 'integer', 'min:0', 'max:85'],

            'facebook_pixel_id'   => $canUseIntegrations ? ['nullable', 'string', 'max:30', 'regex:/^\d+$/']       : ['prohibited'],
            'tiktok_pixel_id'     => $canUseIntegrations ? ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9]+$/i']: ['prohibited'],
            'google_analytics_id' => $canUseIntegrations ? ['nullable', 'string', 'max:20', 'regex:/^G-[A-Z0-9]+$/i']: ['prohibited'],

            'telegram_bot_token' => $isPro ? ['nullable', 'string', 'max:100'] : ['prohibited'],
            'telegram_chat_id'   => $isPro ? ['nullable', 'string', 'max:50']  : ['prohibited'],
            'telegram_clear'     => $isPro ? ['sometimes', 'boolean']           : ['prohibited'],

            'ai_chatbot_instructions' => ['nullable', 'string', 'max:10000'],

            'brand_context'                          => ['nullable', 'array'],
            'brand_context.brand_personality'        => ['nullable', 'string', 'max:500'],
            'brand_context.target_audience'          => ['nullable', 'string', 'max:1000'],
            'brand_context.tone_of_voice'            => ['nullable', 'string', 'in:first_person,we,formal'],
            'brand_context.value_proposition'        => ['nullable', 'string', 'max:500'],
            'brand_context.primary_keywords'         => ['nullable', 'string', 'max:500'],
            'brand_context.big_problem'              => ['nullable', 'string', 'max:1000'],
            'brand_context.color_primary'            => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'brand_context.color_secondary'          => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'brand_context.color_accent'             => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'brand_context.visual_style'             => ['nullable', 'string', 'max:500'],
            'brand_context.logo_rules'               => ['nullable', 'string', 'max:500'],
            'brand_context.cta_goal'                 => ['nullable', 'string', 'max:300'],
            'brand_context.offer_details'            => ['nullable', 'string', 'max:500'],
            'brand_context.social_share_description' => ['nullable', 'string', 'max:300'],

            'subdomain' => [
                'nullable', 'string', 'max:63',
                'regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$|^[a-z0-9]$/',
                Rule::unique('communities', 'subdomain')->ignore($community->id),
            ],
            'custom_domain' => [
                Rule::prohibitedIf(! $isPro),
                'nullable', 'string', 'max:253',
                'regex:/^([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/',
                Rule::unique('communities', 'custom_domain')->ignore($community->id),
            ],
        ];
    }
}
