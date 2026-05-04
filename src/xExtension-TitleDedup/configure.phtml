<?php
declare(strict_types=1);
/** @var TitleDedupExtension $this */

$config = $this->getSystemConfiguration();
if (is_object($config)) {
    $config = get_object_vars($config);
}
if (!is_array($config)) {
    $config = [];
}
$current = (string)($config['title_dedup_rules_json'] ?? '');
if ($current === '') {
    $current = file_get_contents(__DIR__ . '/../../examples/sample-config.json') ?: '{"rules":[]}';
}
?>
<form action="<?php echo _url('extension', 'configure', 'e', urlencode($this->getName())); ?>" method="post">
    <input type="hidden" name="_csrf" value="<?php echo FreshRSS_Auth::csrfToken(); ?>" />
    <h2>Title Dedup</h2>
    <p>每个规则组配置参考源 A、目标源 B。B 命中 A 的同标题文章时会自动已读。</p>
    <textarea name="rules_json" rows="20" style="width:100%"><?php echo htmlspecialchars($current, ENT_QUOTES, 'UTF-8'); ?></textarea>
    <p>保存前请确认 JSON 有效。示例见 `examples/sample-config.json`。</p>
    <p><button type="submit">保存</button></p>
</form>
