<?php
declare(strict_types=1);

/**
 * Database access for Title Dedup.
 *
 * New entries live in `_entrytmp` until FreshRSS commits them to `_entry`
 * at the end of a refresh, so title lookups must cover both tables.
 */
final class TitleDedupDAO extends Minz_ModelPdo
{
    /**
     * Titles of all entries of the source feeds and of the unread entries of the target feeds
     * published since $since, from both the main and the temporary table.
     *
     * @param list<int> $sourceFeedIds
     * @param list<int> $targetFeedIds
     * @return list<array{id:string,title:string,id_feed:int,is_read:int,tmp:int}>
     */
    public function fetchTitles(array $sourceFeedIds, array $targetFeedIds, int $since, bool $withTmp = true): array
    {
        $values = [':since' => $since];
        $src = $this->placeholders('s', $sourceFeedIds, $values);
        $tgt = $this->placeholders('t', $targetFeedIds, $values);

        $where = "date >= :since AND (id_feed IN ({$src}) OR (id_feed IN ({$tgt}) AND is_read = 0))";
        $sql = "SELECT id, title, id_feed, is_read, 0 AS tmp FROM `_entry` WHERE {$where}";
        if ($withTmp) {
            $sql .= " UNION ALL SELECT id, title, id_feed, is_read, 1 AS tmp FROM `_entrytmp` WHERE {$where}";
        }
        // Arrival order: the backfill replays entries in this order.
        $sql .= ' ORDER BY id';

        $rows = $this->query($sql, $values);
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id' => (string)$row['id'],
                'title' => (string)$row['title'],
                'id_feed' => (int)$row['id_feed'],
                'is_read' => (int)$row['is_read'],
                'tmp' => (int)$row['tmp'],
            ];
        }
        return $result;
    }

    /**
     * Mark entries that are still in the temporary table as read.
     * Counters are recomputed by FreshRSS when the entries are committed.
     *
     * @param list<string> $ids
     */
    public function markTmpRead(array $ids): void
    {
        // Stay below SQLite's limit on bound parameters.
        foreach (array_chunk($ids, 500) as $chunk) {
            $values = [];
            $in = $this->placeholders('i', $chunk, $values);
            $this->execute("UPDATE `_entrytmp` SET is_read = 1 WHERE is_read = 0 AND id IN ({$in})", $values);
        }
    }

    /**
     * @param list<int|string> $items
     * @param array<string,int|string> $values
     */
    private function placeholders(string $prefix, array $items, array &$values): string
    {
        if ($items === []) {
            return 'NULL';
        }
        $names = [];
        foreach (array_values($items) as $i => $item) {
            $name = ':' . $prefix . $i;
            $names[] = $name;
            $values[$name] = $item;
        }
        return implode(',', $names);
    }

    /**
     * @param array<string,int|string> $values
     * @return list<array<string,mixed>>
     */
    private function query(string $sql, array $values): array
    {
        $stm = $this->execute($sql, $values);
        if ($stm === null) {
            return [];
        }
        $rows = $stm->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /** @param array<string,int|string> $values */
    private function execute(string $sql, array $values): ?PDOStatement
    {
        $stm = $this->pdo->prepare($sql);
        if ($stm === false) {
            Minz_Log::error('Title Dedup: SQL prepare error ' . json_encode($this->pdo->errorInfo()));
            return null;
        }
        foreach ($values as $name => $value) {
            $stm->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if (!$stm->execute()) {
            Minz_Log::error('Title Dedup: SQL error ' . json_encode($stm->errorInfo()));
            return null;
        }
        return $stm;
    }
}
