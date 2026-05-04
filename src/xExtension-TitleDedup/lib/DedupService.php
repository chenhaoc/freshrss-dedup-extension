<?php

final class DedupService
{
    private $config;
    private $feedNameMap = null;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function rules()
    {
        return $this->config['rules'] ?? [];
    }

    public function applyEntry(FreshRSS_Entry $entry, $maintenance)
    {
        $feedId = (string)$entry->feedId();
        $title = $entry->title();
        if ($title === '') {
            return;
        }

        $entryDao = FreshRSS_Factory::createEntryDao();
        foreach ($this->rules() as $rule) {
            $rule = $this->resolveRule($rule);
            if (!$rule['enabled']) {
                continue;
            }

            $normalizedTitle = $this->normalizeTitle($title, $rule);
            if ($normalizedTitle === '') {
                continue;
            }

            if (in_array($feedId, $rule['target_feed_ids'], true)) {
                if ($this->hasMatchInSourceFeeds($entryDao, $normalizedTitle, $rule, $feedId)) {
                    $entry->_isRead(true);
                }
            }

            if ($maintenance || in_array($feedId, $rule['source_feed_ids'], true)) {
                $ids = $this->findTargetMatches($entryDao, $normalizedTitle, $rule, null);
                if ($ids !== []) {
                    $entryDao->markRead($ids, true);
                }
            }
        }
    }

    public function backfillRule($rule)
    {
        if (!$rule['enabled']) {
            return;
        }

        $entryDao = FreshRSS_Factory::createEntryDao();
        $rule = $this->resolveRule($rule);
        foreach ($rule['source_feed_ids'] as $sourceFeedId) {
            $sourceEntries = $this->fetchEntriesByFeeds($entryDao, [$sourceFeedId], $rule['lookback_days']);
            foreach ($sourceEntries as $sourceEntry) {
                $normalizedTitle = $this->normalizeTitle((string)$sourceEntry['title'], $rule);
                if ($normalizedTitle === '') {
                    continue;
                }

                $ids = $this->findTargetMatches($entryDao, $normalizedTitle, $rule, (string)$sourceEntry['id']);
                if ($ids !== []) {
                    $entryDao->markRead($ids, true);
                }
            }
        }
    }

    private function hasMatchInSourceFeeds($entryDao, $normalizedTitle, $rule, $currentFeedId)
    {
        foreach ($this->fetchEntriesByFeeds($entryDao, $rule['source_feed_ids'], $rule['lookback_days']) as $row) {
            if ((string)$row['id_feed'] === $currentFeedId) {
                continue;
            }
            if ($this->normalizeTitle((string)$row['title'], $rule) === $normalizedTitle) {
                return true;
            }
        }

        return false;
    }

    private function findTargetMatches($entryDao, $normalizedTitle, $rule, $excludeEntryId)
    {
        $ids = [];
        foreach ($this->fetchEntriesByFeeds($entryDao, $rule['target_feed_ids'], $rule['lookback_days']) as $row) {
            if ($excludeEntryId !== null && (string)$row['id'] === $excludeEntryId) {
                continue;
            }
            if ($this->normalizeTitle((string)$row['title'], $rule) === $normalizedTitle) {
                $ids[] = (string)$row['id'];
            }
        }

        return array_values(array_unique($ids));
    }

    private function resolveRule($rule)
    {
        $sourceFeedIds = $rule['source_feed_ids'];
        foreach ($rule['source_feed_names'] as $feedName) {
            $sourceFeedIds = array_merge($sourceFeedIds, $this->findFeedIdsByName($feedName));
        }

        $targetFeedIds = $rule['target_feed_ids'];
        foreach ($rule['target_feed_names'] as $feedName) {
            $targetFeedIds = array_merge($targetFeedIds, $this->findFeedIdsByName($feedName));
        }

        $rule['source_feed_ids'] = $this->uniqueIds($sourceFeedIds);
        $rule['target_feed_ids'] = $this->uniqueIds($targetFeedIds);
        return $rule;
    }

    private function fetchEntriesByFeeds($entryDao, $feedIds, $lookbackDays)
    {
        if ($feedIds === []) {
            return [];
        }

        $placeholders = [];
        $values = [];
        foreach (array_values($feedIds) as $index => $feedId) {
            $placeholder = ':feed_' . $index;
            $placeholders[] = $placeholder;
            $values[$placeholder] = (int)$feedId;
        }

        $since = time() - ($lookbackDays * 86400);
        $sql = 'SELECT id, title, date, id_feed, is_read FROM `_entry` WHERE id_feed IN (' . implode(',', $placeholders) . ') AND date >= :since';
        $values[':since'] = $since;

        $rows = $entryDao->fetchAssoc($sql, $values);
        return is_array($rows) ? $rows : [];
    }

    private function normalizeTitle($title, $rule)
    {
        $options = [
            'normalize_case' => $rule['normalize_case'],
            'strip_punctuation' => $rule['strip_punctuation'],
        ];

        if (!empty($rule['normalize_whitespace'])) {
            $title = preg_replace('/\s+/u', ' ', trim($title)) ?? trim($title);
        }

        return TitleNormalizer::normalize($title, $options);
    }

    private function findFeedIdsByName($feedName)
    {
        $map = $this->feedNameMap();
        return $map[$this->normalizeFeedName($feedName)] ?? [];
    }

    private function feedNameMap()
    {
        if ($this->feedNameMap !== null) {
            return $this->feedNameMap;
        }

        $feedDao = FreshRSS_Factory::createFeedDao();
        $map = [];
        foreach ($feedDao->listFeeds() as $feed) {
            $normalizedName = $this->normalizeFeedName($feed->name(true));
            if ($normalizedName === '') {
                continue;
            }
            if (!isset($map[$normalizedName])) {
                $map[$normalizedName] = [];
            }
            $map[$normalizedName][] = (string)$feed->id();
        }

        $this->feedNameMap = [];
        foreach ($map as $name => $ids) {
            $this->feedNameMap[$name] = $this->uniqueIds($ids);
        }

        return $this->feedNameMap;
    }

    private function normalizeFeedName($feedName)
    {
        return mb_strtolower(trim((string)$feedName), 'UTF-8');
    }

    private function uniqueIds($ids)
    {
        $ids = array_map(static function ($id) {
            return (string)(int)$id;
        }, $ids);
        $ids = array_values(array_unique(array_filter($ids, static function ($id) {
            return $id !== '0';
        })));
        sort($ids, SORT_NUMERIC);
        return $ids;
    }
}
