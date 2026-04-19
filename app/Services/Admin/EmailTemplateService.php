<?php

namespace App\Services\Admin;

use App\Models\EmailTemplate;

class EmailTemplateService
{
    /**
     * Render an email template preview by substituting all variables
     * with placeholder values so the user can see the layout.
     */
    public function preview(string $key, string $htmlBody): string
    {
        $template = EmailTemplate::where('key', $key)->firstOrFail();

        $samples = collect($template->variables ?? [])
            ->mapWithKeys(fn ($desc, $var) => [$var => "[{$var}]"])
            ->toArray();

        foreach ($samples as $var => $value) {
            $htmlBody = str_replace('{{'.$var.'}}', $value, $htmlBody);
        }

        return $htmlBody;
    }
}
