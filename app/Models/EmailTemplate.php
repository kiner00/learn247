<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = ['key', 'name', 'subject', 'html_body', 'variables'];

    protected $casts = ['variables' => 'array'];

    public static function render(string $key, array $vars): ?array
    {
        $template = static::where('key', $key)->first();
        if (! $template) {
            return null;
        }

        return [
            'subject' => static::interpolate($template->subject, $vars),
            'html'    => static::interpolate($template->html_body, $vars),
        ];
    }

    private static function interpolate(string $text, array $vars): string
    {
        foreach ($vars as $var => $value) {
            $text = str_replace('{{' . $var . '}}', (string) $value, $text);
        }
        return $text;
    }
}
