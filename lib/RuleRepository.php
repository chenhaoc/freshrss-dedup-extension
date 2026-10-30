<?php
declare(strict_types=1);

final class RuleRepository
{
    /**
     * Build the configuration from the settings form.
     *
     * @return array{rules:list<array<string,mixed>>}|null
     */
    public static function buildConfigFromForm(mixed $rules): ?array
    {
        if (!is_array($rules)) {
            return null;
        }

        $normalizedRules = [];
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            // Forms of version 0.1 had a single checkbox for all three options.
            $all = isset($rule['normalize_all']) ? self::normalizeBool($rule['normalize_all']) : null;

            $sourceIds = self::normalizeIds($rule['source_feed_ids'] ?? []);
            // "Targets are the same as sources": the target list is ignored.
            $targetIds = self::normalizeBool($rule['targets_same'] ?? false)
                ? $sourceIds
                : self::normalizeIds($rule['target_feed_ids'] ?? []);

            $normalizedRules[] = [
                'name' => trim((string)($rule['name'] ?? '')),
                'enabled' => self::normalizeBool($rule['enabled'] ?? false),
                'source_feed_ids' => $sourceIds,
                'source_feed_names' => [],
                'target_feed_ids' => $targetIds,
                'target_feed_names' => [],
                'lookback_days' => self::lookback($rule['lookback_days'] ?? 14),
                'normalize_whitespace' => $all ?? self::normalizeBool($rule['normalize_whitespace'] ?? false),
                'normalize_case' => $all ?? self::normalizeBool($rule['normalize_case'] ?? false),
                'strip_punctuation' => $all ?? self::normalizeBool($rule['strip_punctuation'] ?? false),
            ];
        }

        return ['rules' => $normalizedRules];
    }

    /**
     * Parse the stored JSON configuration.
     *
     * @return array{rules:list<array<string,mixed>>}|null null when the JSON is invalid
     */
    public static function parseConfig(string $json): ?array
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

            $rules[] = [
                'name' => trim((string)($rule['name'] ?? '')),
                'enabled' => (bool)($rule['enabled'] ?? true),
                'source_feed_ids' => self::normalizeIds($rule['source_feed_ids'] ?? []),
                'source_feed_names' => self::normalizeNames($rule['source_feed_names'] ?? []),
                'target_feed_ids' => self::normalizeIds($rule['target_feed_ids'] ?? []),
                'target_feed_names' => self::normalizeNames($rule['target_feed_names'] ?? []),
                'lookback_days' => self::lookback($rule['lookback_days'] ?? 14),
                'normalize_whitespace' => (bool)($rule['normalize_whitespace'] ?? true),
                'normalize_case' => (bool)($rule['normalize_case'] ?? true),
                'strip_punctuation' => (bool)($rule['strip_punctuation'] ?? false),
            ];
        }

        return ['rules' => $rules];
    }

    /**
     * What the deduplication depends on: enabled rules with feeds on both sides,
     * without their names.
     *
     * @param array{rules:list<array<string,mixed>>} $config
     */
    public static function fingerprint(array $config): string
    {
        $effective = [];
        foreach ($config['rules'] as $rule) {
            $hasSource = ($rule['source_feed_ids'] ?? []) !== [] || ($rule['source_feed_names'] ?? []) !== [];
            $hasTarget = ($rule['target_feed_ids'] ?? []) !== [] || ($rule['target_feed_names'] ?? []) !== [];
            if (empty($rule['enabled']) || !$hasSource || !$hasTarget) {
                continue;
            }
            unset($rule['name']);
            $effective[] = $rule;
        }
        return (string)json_encode($effective);
    }

    /** @return list<string> */
    public static function normalizeIds(mixed $ids): array
    {
        if (!is_array($ids)) {
            return [];
        }

        $ids = array_map(static fn($id): string => (string)(int)$id, $ids);
        $ids = array_values(array_unique(array_filter($ids, static fn(string $id): bool => $id !== '0')));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    /** @return list<string> */
    private static function normalizeNames(mixed $names): array
    {
        if (!is_array($names)) {
            return [];
        }

        $names = array_map(static fn($name): string => trim((string)$name), $names);
        $names = array_values(array_unique(array_filter($names, static fn(string $name): bool => $name !== '')));
        sort($names, SORT_STRING);

        return $names;
    }

    private static function lookback(mixed $value): int
    {
        return min(3650, max(1, (int)$value));
    }

    private static function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }

        $value = strtolower(trim((string)$value));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}
