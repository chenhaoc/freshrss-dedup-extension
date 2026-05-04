# FreshRSS Title Dedup

一个 FreshRSS 扩展，用来做“单向跨源同标题去重”。

当目标源 B 收到新文章时，如果它的标题已经在参考源 A 中出现过，这篇文章会自动标记为已读。

## 适合什么场景

- 同一条新闻会被多个资讯源重复发布
- 你想保留主信源未读，把转载源自动跳过
- 你希望规则按订阅分组，而不是全局混在一起

## 功能

- 单向规则：A 影响 B，B 不反向影响 A
- 支持多个规则组
- 每个规则组可配置多个参考源、多个目标源
- 支持标题归一化：压缩空白、忽略大小写、移除标点
- 支持维护回扫，处理“目标源先到、参考源后到”的情况
- 提供可视化配置页，直接选择现有订阅

## 安装

把 [src/xExtension-TitleDedup](src/xExtension-TitleDedup) 整个目录复制到 FreshRSS 的 `extensions/` 目录下，然后在 FreshRSS 后台启用 `Title Dedup`。

## 使用

1. 打开扩展配置页。
2. 若配置页是从侧边栏打开，先点“在完整页面中编辑”。
3. 新建规则组。
4. 选择参考源和目标源。
5. 设置回看天数与标题归一化。
6. 保存配置。

## 规则说明

- `source_feed_ids`：参考源 A
- `source_feed_names`：参考源 A 的订阅名，支持与 `source_feed_ids` 混用
- `target_feed_ids`：目标源 B
- `target_feed_names`：目标源 B 的订阅名，支持与 `target_feed_ids` 混用
- `lookback_days`：只在最近 N 天内比对
- `normalize_case`：忽略大小写
- `normalize_whitespace`：压缩连续空白
- `strip_punctuation`：移除标点后再比对

## 示例

示例配置见 [examples/sample-config.json](examples/sample-config.json)。

## 仓库结构

- `src/xExtension-TitleDedup/`：可安装的 FreshRSS 扩展目录
- `examples/`：示例配置
- `docs/`：设计说明

## License

MIT
