<?php

namespace App\Services\Mailing;

class MailText
{
    public static function fromHtml(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        $text = preg_replace('~(?<!<)(https?://[^\s<]+)~i', '<$1>', $text) ?? $text;

        return trim($text);
    }
}
