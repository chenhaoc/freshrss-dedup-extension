# FreshRSS 单向同标题自动已读扩展设计

## 目标

当目标订阅 B 新增文章时，如果它的标题与参考订阅 A 中已有文章的标题一致，则把 B 的新文章自动标记为已读。

## 范围

- 支持多个规则组
- 每个规则组包含多个参考源 A 和多个目标源 B
- 单向生效：A 影响 B，B 不反向影响 A
- 基于标题归一化后的完全一致判断

## 不做的事

- 不做全文相似度去重
- 不做跨语言语义去重
- 不修改 FreshRSS 核心
- 不做客户端侧去重

## 方案

做一个 FreshRSS 用户扩展，使用 `entry_before_add` 钩子处理新条目。

### 运行流程

1. FreshRSS 拉取到一条新文章。
2. 扩展检查该文章所属 feed 是否命中某个规则组的目标源 B。
3. 归一化标题。
4. 查询该规则组的参考源 A 中是否已有同标题文章。
5. 若命中，则将当前条目标记为已读。

### 回扫

为处理“B 先到、A 后到”的情况，增加维护回扫：

- 定期检查 B 中未读文章
- 若其标题在 A 中已存在，则补标为已读

## 配置

每个规则组包含：

- `name`
- `source_feed_ids`
- `target_feed_ids`
- `enabled`
- `lookback_days`
- `normalize_whitespace`
- `normalize_case`
- `strip_punctuation`

## 文件结构

- `src/metadata.json`
- `src/extension.php`
- `src/lib/TitleNormalizer.php`
- `src/lib/RuleRepository.php`
- `src/lib/DuplicateMatcher.php`
- `src/configure.phtml`
- `examples/sample-config.json`

## 风险

- 标题完全一致不等于同一新闻，可能误杀
- 参考源过多时查询成本会上升
- 只靠标题，难覆盖改标题转载

## 验收

- B 的新文章与 A 同标题时自动已读
- 多规则组可同时生效
- 回扫能补处理先到后到顺序反转的情况
