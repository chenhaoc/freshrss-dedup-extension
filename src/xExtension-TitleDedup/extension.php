<?php

require_once __DIR__ . '/lib/TitleNormalizer.php';
require_once __DIR__ . '/lib/RuleRepository.php';
require_once __DIR__ . '/lib/DedupService.php';

final class TitleDedupExtension extends Minz_Extension
{
    private const CONFIG_KEY = 'title_dedup_rules_json';

    private $cachedRules = null;
    private $feedOptionsCache = null;
    private $feedNameMapCache = null;

    public function init()
    {
        parent::init();

        $this->registerHook('entry_before_insert', [$this, 'onEntryBeforeInsert']);
        $this->registerHook('freshrss_user_maintenance', [$this, 'onUserMaintenance']);
    }

    public function handleConfigureAction()
    {
        if (!Minz_Request::isPost()) {
            return;
        }

        $rulesPost = $_POST['rules'] ?? null;
        if (is_array($rulesPost) && isset($_POST['rule_action'])) {
            $rulesPost = $this->applyUiAction($rulesPost, (string)$_POST['rule_action']);
        }
        $parsed = is_array($rulesPost)
            ? RuleRepository::buildConfigFromForm($rulesPost)
            : null;
        if ($parsed === null) {
            $rulesJson = Minz_Request::paramString('rules_json', true);
            $parsed = RuleRepository::parseConfig($rulesJson);
        }
        if ($parsed === null) {
            Minz_Request::setBadNotification('规则配置无效，未保存。');
            return;
        }

        $config = $this->readSystemConfiguration();
        $config[self::CONFIG_KEY] = json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->setSystemConfiguration($config);
        Minz_Request::setGoodNotification('去重规则已保存。');
    }

    private function applyUiAction($rulesPost, $action)
    {
        if (!is_array($rulesPost)) {
            return $rulesPost;
        }

        if ($action === 'add_rule') {
            $rulesPost[] = $this->blankRulePost();
            return $rulesPost;
        }

        if (strpos($action, 'remove_rule:') === 0) {
            $ruleIndex = (int)substr($action, strlen('remove_rule:'));
            if (isset($rulesPost[$ruleIndex])) {
                unset($rulesPost[$ruleIndex]);
                $rulesPost = array_values($rulesPost);
            }
            return $rulesPost;
        }

        if (strpos($action, 'add_') === 0) {
            $payload = explode(':', substr($action, 4), 2);
            $side = $payload[0] ?? '';
            $ruleIndex = isset($payload[1]) ? (int)$payload[1] : null;
            if ($ruleIndex !== null && isset($rulesPost[$ruleIndex]) && in_array($side, ['source', 'target'], true)) {
                $pickerKey = $side . '_feed_picker';
                $pickedId = (string)($rulesPost[$ruleIndex][$pickerKey] ?? '');
                if ($pickedId !== '') {
                    $idsKey = $side . '_feed_ids';
                    $rulesPost[$ruleIndex][$idsKey] = $this->uniqueIds(array_merge(
                        $rulesPost[$ruleIndex][$idsKey] ?? [],
                        [$pickedId]
                    ));
                }
            }
            return $rulesPost;
        }

        if (strpos($action, 'remove_') === 0) {
            $payload = explode(':', substr($action, 7), 3);
            $side = $payload[0] ?? '';
            $ruleIndex = isset($payload[1]) ? (int)$payload[1] : null;
            $feedId = $payload[2] ?? null;
            if ($ruleIndex !== null && isset($rulesPost[$ruleIndex]) && in_array($side, ['source', 'target'], true) && $feedId !== null) {
                $idsKey = $side . '_feed_ids';
                $currentIds = $rulesPost[$ruleIndex][$idsKey] ?? [];
                if (is_array($currentIds)) {
                    $rulesPost[$ruleIndex][$idsKey] = array_values(array_filter($currentIds, static function ($id) use ($feedId) {
                        return (string)$id !== (string)$feedId;
                    }));
                }
            }
            return $rulesPost;
        }

        return $rulesPost;
    }

    private function blankRulePost()
    {
        return [
            'name' => '',
            'enabled' => 1,
            'source_feed_ids' => [],
            'source_feed_picker' => '',
            'target_feed_ids' => [],
            'target_feed_picker' => '',
            'lookback_days' => 14,
            'normalize_all' => 1,
            'normalize_whitespace' => 1,
            'normalize_case' => 1,
            'strip_punctuation' => 1,
        ];
    }

    public function onEntryBeforeInsert(FreshRSS_Entry $entry)
    {
        $this->applyRules($entry, false);
        return $entry;
    }

    public function onUserMaintenance()
    {
        $service = $this->service();
        foreach ($service->rules() as $rule) {
            $service->backfillRule($rule);
        }
    }

    private function applyRules(FreshRSS_Entry $entry, $maintenance)
    {
        $service = $this->service();
        $service->applyEntry($entry, $maintenance);
    }

    private function service()
    {
        if ($this->cachedRules === null) {
            $config = $this->readSystemConfiguration();
            $rulesJson = (string)($config[self::CONFIG_KEY] ?? '');
            $this->cachedRules = RuleRepository::parseConfig($rulesJson) ?? ['rules' => []];
        }

        return new DedupService($this->cachedRules);
    }

    public function currentRules()
    {
        $config = $this->readSystemConfiguration();
        $rulesJson = (string)($config[self::CONFIG_KEY] ?? '');
        $parsed = RuleRepository::parseConfig($rulesJson);
        return $parsed['rules'] ?? [];
    }

    public function feedOptions()
    {
        if ($this->feedOptionsCache !== null) {
            return $this->feedOptionsCache;
        }

        $feedDao = FreshRSS_Factory::createFeedDao();
        $feeds = [];
        foreach ($feedDao->listFeeds() as $feed) {
            $name = (string)$feed->name(true);
            $feeds[] = [
                'id' => (string)$feed->id(),
                'name' => $name,
                'label' => $name,
            ];
        }

        usort($feeds, static function ($left, $right) {
            return strcasecmp($left['label'], $right['label']);
        });

        $this->feedOptionsCache = $feeds;
        return $this->feedOptionsCache;
    }

    public function selectedFeedIds($rule, $prefix)
    {
        $ids = [];
        $idField = $prefix . '_feed_ids';
        $nameField = $prefix . '_feed_names';

        if (isset($rule[$idField]) && is_array($rule[$idField])) {
            $ids = array_merge($ids, $rule[$idField]);
        }

        if (isset($rule[$nameField]) && is_array($rule[$nameField])) {
            foreach ($rule[$nameField] as $feedName) {
                $ids = array_merge($ids, $this->findFeedIdsByName($feedName));
            }
        }

        return $this->uniqueIds($ids);
    }

    private function readSystemConfiguration()
    {
        $config = $this->getSystemConfiguration();
        if (is_array($config)) {
            return $config;
        }
        if (is_object($config)) {
            return get_object_vars($config);
        }

        return [];
    }

    private function findFeedIdsByName($feedName)
    {
        $map = $this->feedNameMap();
        return $map[$this->normalizeFeedName($feedName)] ?? [];
    }

    private function feedNameMap()
    {
        if ($this->feedNameMapCache !== null) {
            return $this->feedNameMapCache;
        }

        $map = [];
        foreach ($this->feedOptions() as $feed) {
            $normalizedName = $this->normalizeFeedName($feed['name']);
            if ($normalizedName === '') {
                continue;
            }
            if (!isset($map[$normalizedName])) {
                $map[$normalizedName] = [];
            }
            $map[$normalizedName][] = $feed['id'];
        }

        $this->feedNameMapCache = [];
        foreach ($map as $name => $ids) {
            $this->feedNameMapCache[$name] = $this->uniqueIds($ids);
        }

        return $this->feedNameMapCache;
    }

    private function normalizeFeedName($feedName)
    {
        return mb_strtolower(trim((string)$feedName), 'UTF-8');
    }

    private function uniqueIds($ids)
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
}
