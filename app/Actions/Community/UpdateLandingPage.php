<?php

namespace App\Actions\Community;

use App\Models\Community;

class UpdateLandingPage
{
    public function execute(Community $community, array $data): array
    {
        // Preserve empty strings for price_note so the frontend can distinguish
        // "never set" (key absent) from "intentionally cleared" ("").
        foreach (['hero', 'cta_section'] as $section) {
            if (array_key_exists($section, $data)
                && array_key_exists('price_note', $data[$section])
                && $data[$section]['price_note'] === null) {
                $data[$section]['price_note'] = '';
            }
        }

        // Flat arrays must be fully replaced, not deep-merged.
        $sections = $data['_sections'] ?? null;
        $selectedCourses = array_key_exists('included_courses_selected', $data) ? $data['included_courses_selected'] : null;
        $customSections = array_key_exists('custom_sections', $data) ? $data['custom_sections'] : null;
        unset($data['_sections'], $data['included_courses_selected'], $data['custom_sections']);

        $merged = array_replace_recursive($community->landing_page ?? [], $data);

        if ($sections !== null) {
            $merged['_sections'] = $sections;
        }
        if ($selectedCourses !== null) {
            $merged['included_courses_selected'] = $selectedCourses;
        }
        if ($customSections !== null) {
            $merged['custom_sections'] = $customSections;
        }

        $community->update(['landing_page' => $merged]);

        return $merged;
    }
}
