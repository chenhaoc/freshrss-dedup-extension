# FreshRSS Title Dedup

FreshRSS extension for cross-feed deduplication by title.

Other languages: [Русский](./README.ru.md) · [中文](./README.zh-CN.md)

When a new entry arrives in target feed B, the extension marks it as read if the same title has already appeared in source feed A.

## Use Cases

- Multiple feeds publish the same story repeatedly
- You want to keep a primary source unread while skipping reposting feeds
- You want rules scoped to selected feeds instead of one global dedup rule

## Features

- One-way rules: A affects B, but B does not affect A
- Feeds can be both source and target: list all sections of a site on both sides to keep only the first copy of each story unread
- Multiple rule groups
- Multiple source feeds and target feeds per rule
- Title normalization: whitespace, case, punctuation (each can be toggled separately)
- Works in both directions in time: a target entry is marked read whether its source copy arrived before it, in the same refresh, or later
- One-off backfill of existing entries after the rules are saved
- Per-user rules
- Settings page with feeds grouped by category, a filter and bulk selection; translated (English, Russian, Chinese)

## How it works

- When a new entry is fetched, its title is compared with the titles of the source feeds within the lookback window, including entries fetched earlier in the same refresh and not yet committed by FreshRSS. A match marks the new target entry as read.
- When a new unread source entry is fetched, unread target entries with the same title are marked as read. A read entry only serves as a reference and never marks other entries.
- Titles are loaded once per rule and per request, so a refresh costs one query per rule.
- After the rules are saved, the next refresh checks the existing entries within the lookback window once. Later refreshes only look at new entries, so an entry you mark as unread again stays unread.

## Installation

The repository root is the extension itself. Clone it into your FreshRSS `extensions/` directory, then enable `Title Dedup` in the user's extension settings:

```sh
cd /path/to/FreshRSS/extensions
git clone https://github.com/chenhaoc/freshrss-dedup-extension.git xExtension-TitleDedup
```

Update with `git pull` in `extensions/xExtension-TitleDedup`.

Tested with FreshRSS 1.30.4 (PHP 8.3, PostgreSQL).

### Upgrading from 0.1

Version 0.1 was a system extension; 0.2 is a user extension. After upgrading, enable it again in each user's extension settings. Existing rules are read from the old system configuration until the user saves the settings once.

## Usage

1. Open the extension settings, in the sidebar panel or with "Edit in full page" for more room.
2. Tick the source feeds. Type in the filter (a feed or category name, e.g. a site name) and use "Select shown" to tick all matching feeds at once. When closed, the list shows the selected feeds.
3. Tick the target feeds, or check "Targets are the same as sources" to deduplicate a group of feeds among themselves.
4. Set the lookback period and the title comparison options.
5. Save. "Add rule" adds another rule.

## Rule Fields

Rules are stored as JSON in the user's extension configuration (see [examples/sample-config.json](./examples/sample-config.json)). Each rule has:

- `name`: rule name, shown in the settings only
- `enabled`: default `true`
- `source_feed_ids`: source feeds A
- `source_feed_names`: source feed names, can be mixed with `source_feed_ids`
- `target_feed_ids`: target feeds B
- `target_feed_names`: target feed names, can be mixed with `target_feed_ids`
- `lookback_days`: compare only within the last N days, 1 to 3650, default 14
- `normalize_whitespace`: collapse repeated whitespace, default `true`
- `normalize_case`: ignore letter case, `ё` equals `е`, default `true`
- `strip_punctuation`: ignore punctuation and decorative symbols, default `false` in JSON; a new rule in the settings page has it on

A rule without source or target feeds does nothing. Feed names are matched case-insensitively when the rules are read. Saving from the settings page stores feed IDs only: names of feeds that do not exist at that moment are dropped.

## Repository Layout

- `extension.php`, `configure.php(tml)`, `metadata.json`, `lib/`, `i18n/`, `static/`: the extension
- `examples/`: sample configuration
- `docs/`: design notes

## License

MIT
