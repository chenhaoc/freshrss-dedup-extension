<?php

require_once __DIR__ . '/lib/TitleNormalizer.php';
require_once __DIR__ . '/lib/RuleRepository.php';
require_once __DIR__ . '/lib/DedupService.php';

final class TitleDedupExtension extends Minz_Extension
{
    private const CONFIG_KEY = 'title_dedup_rules_json';

    private $cachedRules = null;

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

        $rulesJson = Minz_Request::paramString('rules_json', true);
        $parsed = RuleRepository::parseConfig($rulesJson);
        if ($parsed === null) {
            Minz_Request::setBadNotification('规则 JSON 无效，未保存。');
            return;
        }

        $config = $this->readSystemConfiguration();
        $config[self::CONFIG_KEY] = json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->setSystemConfiguration($config);
        Minz_Request::setGoodNotification('去重规则已保存。');
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
}
