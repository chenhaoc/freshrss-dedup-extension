<?php
declare(strict_types=1);

/**
 * Marks entries of target feeds as read when an entry with the same (normalized) title
 * exists in one of the source feeds of the same rule.
 *
 * One instance lives for the duration of a request. Titles are loaded once per rule,
 * on the first new entry that concerns it, and then kept up to date in memory,
 * so a refresh costs one query per rule instead of one per entry.
 */
final class DedupService
{
    /** @var list<array<string,mixed>> enabled rules with resolved feed IDs */
    private array $rules = [];

    /**
     * Per rule index:
     *  - src: normalized title => [feed ID => true]
     *  - tgt: normalized title => list of unread target entries [id, tmp, feed]
     * @var array<int,array{src:array<string,array<int,true>>,tgt:array<string,list<array{0:string,1:bool,2:int}>>}>
     */
    private array $state = [];

    private ?TitleDedupDAO $dao = null;

    /** @var list<string>|null entries to mark as read at the end of the backfill */
    private ?array $batch = null;

    /** @param array{rules?:list<array<string,mixed>>} $config */
    public function __construct(array $config, callable $feedIdsByName)
    {
        foreach ($config['rules'] ?? [] as $rule) {
            if (empty($rule['enabled'])) {
                continue;
            }
            $source = $this->resolveFeeds($rule, 'source', $feedIdsByName);
            $target = $this->resolveFeeds($rule, 'target', $feedIdsByName);
            if ($source === [] || $target === []) {
                continue;
            }
            $rule['source_set'] = array_fill_keys($source, true);
            $rule['target_set'] = array_fill_keys($target, true);
            $this->rules[] = $rule;
        }
    }

    public function hasRules(): bool
    {
        return $this->rules !== [];
    }

    /**
     * Called for every new entry, before it is written to `_entrytmp`.
     */
    public function onNewEntry(FreshRSS_Entry $entry): void
    {
        if ($entry->isUpdated() === true) {
            // Updated version of an entry we already know: nothing new to deduplicate.
            return;
        }
        $feedId = $entry->feedId();
        $title = $entry->title();
        if ($title === '') {
            return;
        }

        foreach ($this->rules as $index => $rule) {
            $isSource = isset($rule['source_set'][$feedId]);
            $isTarget = isset($rule['target_set'][$feedId]);
            if (!$isSource && !$isTarget) {
                continue;
            }
            $norm = $this->normalize($title, $rule);
            if ($norm === '') {
                continue;
            }
            $this->load($index);
            if ($this->process($index, $norm, $feedId, $entry->id(), $entry->isRead() === true, true)) {
                $entry->_isRead(true);
            }
        }
    }

    /**
     * Apply a rule to one entry, in arrival order.
     *
     * When a feed is both source and target (e.g. all sections of a site listed on both
     * sides), the first copy stays unread and every later copy is marked as read.
     *
     * @return bool whether this entry is a duplicate and must be marked as read
     */
    private function process(int $index, string $norm, int $feedId, string $id, bool $isRead, bool $tmp): bool
    {
        $rule = $this->rules[$index];
        $isSource = isset($rule['source_set'][$feedId]);
        $isTarget = isset($rule['target_set'][$feedId]);
        $state = &$this->state[$index];

        // 1. A target entry whose title is already known from another source feed.
        $duplicate = $isTarget && !$isRead && $this->knownElsewhere($state['src'][$norm] ?? [], $feedId);

        if ($isSource) {
            // 2. The first source copy of a title marks target copies that arrived before it.
            //    A copy that is itself a duplicate must not, or it would mark the original.
            //    Nor must a copy that is already read: it may be a duplicate marked by an
            //    earlier pass, and a repeated backfill would then mark the original too.
            if (!$duplicate && !$isRead) {
                $this->markTargets($state, $norm, $feedId);
            }
            $state['src'][$norm][$feedId] = true;
        }

        // 3. Remember an unread target entry, in case its source arrives later.
        if ($isTarget && !$isRead && !$duplicate) {
            $state['tgt'][$norm][] = [$id, $tmp, $feedId];
        }
        return $duplicate;
    }

    /**
     * One-off pass over the whole lookback window, run by the first maintenance after
     * the rules were saved. New entries are handled by onNewEntry(), so later passes
     * are not needed, and an entry the user marked unread again stays unread.
     */
    public function backfill(): void
    {
        foreach ($this->rules as $index => $rule) {
            // Replay the existing entries in arrival order (sorted by the query), as if they were new.
            $rows = $this->dao()->fetchTitles(
                array_keys($rule['source_set']),
                array_keys($rule['target_set']),
                $this->since($rule),
                false
            );

            $this->state[$index] = ['src' => [], 'tgt' => []];
            $this->batch = [];
            foreach ($rows as $row) {
                $norm = $this->normalize($row['title'], $rule);
                if ($norm === '') {
                    continue;
                }
                if ($this->process($index, $norm, $row['id_feed'], $row['id'], $row['is_read'] !== 0, false)) {
                    $this->batch[] = $row['id'];
                }
            }
            $ids = $this->batch;
            $this->batch = null;
            $this->markRead($ids, []);
            unset($this->state[$index]);
        }
    }

    private function load(int $index, bool $withTmp = true): void
    {
        if (isset($this->state[$index])) {
            return;
        }
        $rule = $this->rules[$index];
        $state = ['src' => [], 'tgt' => []];
        $rows = $this->dao()->fetchTitles(
            array_keys($rule['source_set']),
            array_keys($rule['target_set']),
            $this->since($rule),
            $withTmp
        );
        foreach ($rows as $row) {
            $norm = $this->normalize($row['title'], $rule);
            if ($norm === '') {
                continue;
            }
            if (isset($rule['source_set'][$row['id_feed']])) {
                $state['src'][$norm][$row['id_feed']] = true;
            }
            if (isset($rule['target_set'][$row['id_feed']]) && $row['is_read'] === 0) {
                $state['tgt'][$norm][] = [$row['id'], $row['tmp'] === 1, $row['id_feed']];
            }
        }
        $this->state[$index] = $state;
    }

    /**
     * @param array{src:array<string,array<int,true>>,tgt:array<string,list<array{0:string,1:bool,2:int}>>} $state
     */
    private function markTargets(array &$state, string $norm, int $sourceFeedId): void
    {
        if (empty($state['tgt'][$norm])) {
            return;
        }
        $ids = [];
        $tmpIds = [];
        $keep = [];
        foreach ($state['tgt'][$norm] as $target) {
            [$id, $tmp, $feedId] = $target;
            if ($feedId === $sourceFeedId) {
                // A feed that is both source and target does not deduplicate against itself.
                $keep[] = $target;
            } elseif ($tmp) {
                $tmpIds[] = $id;
            } else {
                $ids[] = $id;
            }
        }
        $this->markRead($ids, $tmpIds);
        if ($keep === []) {
            unset($state['tgt'][$norm]);
        } else {
            $state['tgt'][$norm] = $keep;
        }
    }

    /**
     * @param list<string> $ids entries in `_entry`
     * @param list<string> $tmpIds entries still in `_entrytmp`
     */
    private function markRead(array $ids, array $tmpIds): void
    {
        if ($this->batch !== null) {
            array_push($this->batch, ...$ids);
            $ids = [];
        }
        if ($ids !== []) {
            FreshRSS_Factory::createEntryDao()->markRead($ids, true);
        }
        if ($tmpIds !== []) {
            $this->dao()->markTmpRead($tmpIds);
        }
    }

    /** @param array<int,true> $feeds */
    private function knownElsewhere(array $feeds, int $feedId): bool
    {
        return $feeds !== [] && ($feeds !== [$feedId => true]);
    }

    /** @param array<string,mixed> $rule */
    private function since(array $rule): int
    {
        return time() - max(1, (int)($rule['lookback_days'] ?? 14)) * 86400;
    }

    /** @param array<string,mixed> $rule */
    private function normalize(string $title, array $rule): string
    {
        return TitleNormalizer::normalize($title, [
            'normalize_whitespace' => !empty($rule['normalize_whitespace']),
            'normalize_case' => !empty($rule['normalize_case']),
            'strip_punctuation' => !empty($rule['strip_punctuation']),
        ]);
    }

    /**
     * @param array<string,mixed> $rule
     * @return list<int>
     */
    private function resolveFeeds(array $rule, string $side, callable $feedIdsByName): array
    {
        $ids = array_map('intval', $rule[$side . '_feed_ids'] ?? []);
        foreach ($rule[$side . '_feed_names'] ?? [] as $name) {
            $ids = array_merge($ids, array_map('intval', $feedIdsByName((string)$name)));
        }
        $ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
        sort($ids);
        return $ids;
    }

    private function dao(): TitleDedupDAO
    {
        return $this->dao ??= new TitleDedupDAO();
    }
}
