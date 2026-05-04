# FreshRSS Dedup Extension Workspace

独立开发目录，用于实现 FreshRSS 的单向跨源同标题自动已读扩展。

## 目录

- `docs/`：设计说明
- `src/xExtension-TitleDedup/`：可安装的 FreshRSS 扩展目录
- `examples/`：示例配置与测试样例

## 安装

把 [src/xExtension-TitleDedup](</Users/hao.chen/工作文档/Work/readyou/freshrss-dedup-extension/src/xExtension-TitleDedup>) 整个目录复制到 FreshRSS 的 `extensions/` 目录下，然后在 FreshRSS 后台启用 `Title Dedup`。

## 配置

在扩展配置页直接配置。若从侧边栏打开，请先点“在完整页面中编辑”。

规则含义：

- `source_feed_ids`：参考源 A
- `source_feed_names`：参考源 A 的订阅名，支持与 `source_feed_ids` 混用
- `target_feed_ids`：目标源 B
- `target_feed_names`：目标源 B 的订阅名，支持与 `target_feed_ids` 混用
- `lookback_days`：只在最近 N 天内比对
- `normalize_case`：忽略大小写
- `normalize_whitespace`：压缩连续空白
- `strip_punctuation`：移除标点后再比对

## 当前状态

- 第一版代码已落地
- 已补配置页和维护回扫逻辑
- 未做 FreshRSS 实机联调
