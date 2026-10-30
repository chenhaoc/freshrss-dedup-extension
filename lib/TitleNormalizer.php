<?php
declare(strict_types=1);

final class TitleNormalizer
{
    /**
     * @param array{normalize_whitespace?:bool,normalize_case?:bool,strip_punctuation?:bool} $options
     */
    public static function normalize(string $title, array $options): string
    {
        // FreshRSS stores titles HTML-escaped; compare the visible text.
        $value = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Invisible format characters (soft hyphen, zero-width space…) never matter.
        $value = preg_replace('/\p{Cf}+/u', '', $value) ?? $value;

        if (!empty($options['strip_punctuation'])) {
            // Punctuation and decorative symbols, together with the spaces around them, become
            // one space, so "A—B" and "A — B" compare equal. Currency and math symbols are kept:
            // "100$" is not "100€", "C++" is not "C".
            $value = preg_replace('/\s*[\p{P}\p{Sk}\p{So}]+\s*/u', ' ', $value) ?? $value;
        }

        if (!empty($options['normalize_whitespace'])) {
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        }

        if (!empty($options['normalize_case'])) {
            // Russian texts use "ё" inconsistently.
            $value = strtr(mb_strtolower($value, 'UTF-8'), ['ё' => 'е']);
        }

        return trim($value);
    }
}
