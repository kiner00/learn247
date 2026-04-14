<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLandingPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $community = $this->route('community');
        $user = $this->user();

        return $user !== null
            && $community !== null
            && ($community->owner_id === $user->id || $user->is_super_admin);
    }

    public function rules(): array
    {
        return [
            'hero.headline'              => 'required|string|max:500',
            'hero.pre_headline'          => 'nullable|string|max:300',
            'hero.subheadline'           => 'required|string|max:500',
            'hero.cta_label'             => 'required|string|max:50',
            'hero.vsl_url'               => 'nullable|url|max:500',
            'hero.video_type'            => 'nullable|string|in:vsl,upload,embed',
            'hero.video_url'             => 'nullable|string|max:2048',
            'hero.embed_html'            => 'nullable|string|max:5000',
            'hero.headline_font_size'    => 'nullable|integer|min:24|max:80',
            'hero.subheadline_font_size' => 'nullable|integer|min:12|max:40',
            'hero.btn_bg'                => 'nullable|string|max:20',
            'hero.btn_text'              => 'nullable|string|max:20',
            'hero.price_note'            => 'nullable|string|max:100',
            'hero.bg_image'              => 'nullable|url|max:2048',

            'social_proof.stat_label'   => 'nullable|string|max:100',
            'social_proof.trust_line'   => 'nullable|string|max:100',
            'social_proof.bg_color'     => 'nullable|string|max:20',
            'social_proof.hide_avatars' => 'nullable|boolean',

            'benefits.headline'      => 'nullable|string|max:100',
            'benefits.items'         => 'nullable|array|max:6',
            'benefits.items.*.icon'  => 'nullable|string|max:10',
            'benefits.items.*.title' => 'nullable|string|max:80',
            'benefits.items.*.body'  => 'nullable|string|max:300',

            'for_you.headline' => 'nullable|string|max:100',
            'for_you.points'   => 'nullable|array|max:6',
            'for_you.points.*' => 'nullable|string|max:120',

            'creator.headline' => 'nullable|string|max:80',
            'creator.bio'      => 'nullable|string|max:500',
            'creator.name'     => 'nullable|string|max:80',
            'creator.photo'    => 'nullable|url|max:2048',
            'creator.bg_color' => 'nullable|string|max:20',

            'testimonials_type'       => 'nullable|string|in:manual,embed',
            'testimonials_embed_html' => 'nullable|string|max:5000',
            'testimonials'            => 'nullable|array|max:6',
            'testimonials.*.name'     => 'nullable|string|max:80',
            'testimonials.*.role'     => 'nullable|string|max:80',
            'testimonials.*.quote'    => 'nullable|string|max:300',

            'faq'            => 'nullable|array|max:10',
            'faq.*.question' => 'nullable|string|max:200',
            'faq.*.answer'   => 'nullable|string|max:1000',

            'cta_section.headline'   => 'nullable|string|max:120',
            'cta_section.subtext'    => 'nullable|string|max:150',
            'cta_section.cta_label'  => 'nullable|string|max:50',
            'cta_section.bg_image'   => 'nullable|url|max:2048',
            'cta_section.btn_bg'     => 'nullable|string|max:20',
            'cta_section.btn_text'   => 'nullable|string|max:20',
            'cta_section.price_note' => 'nullable|string|max:100',

            'offer_stack.headline'            => 'nullable|string|max:120',
            'offer_stack.items'               => 'nullable|array|max:8',
            'offer_stack.items.*.name'        => 'nullable|string|max:500',
            'offer_stack.items.*.value'       => 'nullable|string|max:40',
            'offer_stack.items.*.description' => 'nullable|string|max:500',
            'offer_stack.total_value'         => 'nullable|string|max:40',
            'offer_stack.price'               => 'nullable|string|max:40',
            'offer_stack.price_note'          => 'nullable|string|max:60',
            'offer_stack.bg_color'            => 'nullable|string|max:20',
            'offer_stack.price_color'         => 'nullable|string|max:20',
            'offer_stack.btn_bg'              => 'nullable|string|max:20',
            'offer_stack.btn_text'            => 'nullable|string|max:20',
            'offer_stack.cta_label'           => 'nullable|string|max:50',

            'video_creator.embed_html'      => 'nullable|string|max:5000',
            'video_creator.video_url'       => 'nullable|string|max:500',
            'video_testimonials.embed_html' => 'nullable|string|max:5000',
            'video_testimonials.video_url'  => 'nullable|string|max:500',
            'video_courses.embed_html'      => 'nullable|string|max:5000',
            'video_courses.video_url'       => 'nullable|string|max:500',

            'included_courses_headline'   => 'nullable|string|max:200',
            'included_courses_subtitle'   => 'nullable|string|max:300',
            'included_courses_bg_color'   => 'nullable|string|max:20',
            'included_courses_btn_bg'     => 'nullable|string|max:20',
            'included_courses_btn_text'   => 'nullable|string|max:20',
            'included_courses_selected'   => 'nullable|array',
            'included_courses_selected.*' => 'integer',

            'certifications_headline' => 'nullable|string|max:200',

            'guarantee.headline' => 'nullable|string|max:100',
            'guarantee.days'     => 'nullable|integer|min:1|max:365',
            'guarantee.body'     => 'nullable|string|max:400',

            'price_justification.headline'              => 'nullable|string|max:100',
            'price_justification.options'               => 'nullable|array|max:5',
            'price_justification.options.*.label'       => 'nullable|string|max:80',
            'price_justification.options.*.description' => 'nullable|string|max:300',

            'custom_sections'                   => 'nullable|array',
            'custom_sections.*'                 => 'nullable|array',
            'custom_sections.*.kind'            => 'nullable|string|in:carousel',
            'custom_sections.*.title'           => 'nullable|string|max:200',
            'custom_sections.*.subtitle'        => 'nullable|string|max:300',
            'custom_sections.*.text'            => 'nullable|string|max:5000',
            'custom_sections.*.image_url'       => 'nullable|url|max:2048',
            'custom_sections.*.video_url'       => 'nullable|string|max:500',
            'custom_sections.*.embed_html'      => 'nullable|string|max:5000',
            'custom_sections.*.bg_color'        => 'nullable|string|max:20',
            'custom_sections.*.bg_image'        => 'nullable|url|max:2048',
            'custom_sections.*.text_color'      => 'nullable|string|max:20',
            'custom_sections.*.subtitle_color'  => 'nullable|string|max:20',
            'custom_sections.*.cta_label'       => 'nullable|string|max:80',
            'custom_sections.*.cta_url'         => 'nullable|url|max:2048',
            'custom_sections.*.btn_bg'          => 'nullable|string|max:20',
            'custom_sections.*.btn_text'        => 'nullable|string|max:20',
            'custom_sections.*.slides'          => 'nullable|array|max:12',
            'custom_sections.*.slides.*.image_url' => 'nullable|url|max:2048',
            'custom_sections.*.slides.*.alt'    => 'nullable|string|max:200',

            '_sections'           => 'nullable|array',
            '_sections.*.type'    => 'nullable|string|max:50',
            '_sections.*.visible' => 'nullable|boolean',

            '_curzzos_introduced' => 'nullable|boolean',
        ];
    }
}
