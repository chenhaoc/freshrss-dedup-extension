<?php

final class RuleRepository
{
    public static function parseConfig($json)
    {
        $json = trim($json);
        if ($json === '') {
            return ['rules' => []];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_array($decoded) || !isset($decoded['rules']) || !is_array($decoded['rules'])) {
            return null;
        }

        $rules = [];
        foreach ($decoded['rules'] as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $sourceFeedIds = self::normalizeIds($rule['source_feed_ids'] ?? []);
            $targetFeedIds = self::normalizeIds($rule['target_feed_ids'] ?? []);
            $sourceFeedNames = self::normalizeNames($rule['source_feed_names'] ?? []);
            $targetFeedNames = self::normalizeNames($rule['target_feed_names'] ?? []);
            if (($sourceFeedIds === [] && $sourceFeedNames === []) || ($targetFeedIds === [] && $targetFeedNames === [])) {
                continue;
            }

            $rules[] = [
                'name' => trim((string)($rule['name'] ?? '')),
                'enabled' => (bool)($rule['enabled'] ?? true),
                'source_feed_ids' => $sourceFeedIds,
                'source_feed_names' => $sourceFeedNames,
                'target_feed_ids' => $targetFeedIds,
                'target_feed_names' => $targetFeedNames,
                'lookback_days' => max(1, (int)($rule['lookback_days'] ?? 14)),
                'normalize_whitespace' => (bool)($rule['normalize_whitespace'] ?? true),
                'normalize_case' => (bool)($rule['normalize_case'] ?? true),
                'strip_punctuation' => (bool)($rule['strip_punctuation'] ?? false),
            ];
        }

        return ['rules' => $rules];
    }

    private static function normalizeIds($ids)
    {
        if (!is_array($ids)) {
            return [];
        }

        $ids = array_map(static function ($id) {
            return (string)(int)$id;
        }, $ids);
        $ids = array_values(array_unique(array_filter($ids, static function ($id) {
            return $id !== '0';
        })));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    private static function normalizeNames($names)
    {
        if (!is_array($names)) {
            return [];
        }

        $names = array_map(static function ($name) {
            return trim((string)$name);
        }, $names);
        $names = array_values(array_unique(array_filter($names, static function ($name) {
            return $name !== '';
        })));
        sort($names, SORT_STRING);

        return $names;
    }
}
