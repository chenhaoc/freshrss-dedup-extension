<?php

final class TitleNormalizer
{
    public static function normalize($title, $options)
    {
        $value = trim($title);

        if (!empty($options['normalize_whitespace'])) {
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        }

        if (!empty($options['strip_punctuation'])) {
            $value = preg_replace('/[\p{P}\p{S}]+/u', '', $value) ?? $value;
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        }

        if (!empty($options['normalize_case'])) {
            $value = mb_strtolower($value, 'UTF-8');
        }

        return trim($value);
    }
}
