# Title Dedup: design notes

[Русская версия](./spec.ru.md) · [中文](./spec.zh-CN.md)

## Goal

When a new entry arrives in a target feed and an entry with the same normalized title already exists in a source feed of the same rule, mark the target entry as read.

## Scope

- Several rules, each with several source feeds and several target feeds
- One-way by default: sources affect targets, not the other way round
- A feed may be both source and target. Listing all sections of a site on both sides keeps the first copy of each story unread and marks every later copy as read
- Exact match after title normalization

## Out of scope

- Full-text similarity
- Cross-language deduplication
- Changes to the FreshRSS core
- Client-side deduplication

## How it works

### New entries (`entry_before_add` hook)

The hook runs for new entries only, after the user's filter actions, so an entry already marked as read by a filter is treated as read.

FreshRSS keeps new entries in `_entrytmp` until the end of a refresh, then moves them to `_entry`. Lookups therefore cover both tables, so copies fetched in the same refresh are compared too.

On the first new entry of a request that concerns a rule, the titles of the rule's feeds within the lookback window are loaded once, then kept up to date in memory. For each new entry, in arrival order:

1. If the entry belongs to a target feed, is unread, and its title is already known from another source feed, it is a duplicate and is marked as read.
2. If the entry belongs to a source feed, is unread and is not a duplicate, unread target entries with the same title in other feeds are marked as read (the source copy arrived after them).
3. The title is remembered as known for its feed; an unread target entry that is not a duplicate is remembered in case its source arrives later.

A read entry only serves as a reference: it never marks other entries. Otherwise a copy marked by an earlier pass could mark the surviving original.

Updated versions of known entries are ignored.

### Title normalization

HTML entities are decoded and invisible format characters (soft hyphen, zero-width space) removed. Then, depending on the rule:

- whitespace: runs of whitespace collapse to one space;
- case: lower case, and `ё` compares equal to `е`;
- punctuation: punctuation and decorative symbols, with the spaces around them, become one space, so `A—B` and `A — B` compare equal. Currency and math symbols are kept, so `100$` and `100€`, or `C++` and `C`, stay different.

### One-off backfill (`freshrss_user_maintenance` hook)

After the effective rules change (renaming a rule or adding an empty one does not count), the next maintenance run replays the existing entries within the lookback window in arrival order (by entry ID), with the same logic as above. Later maintenance runs do nothing, so an entry the user marks as unread again stays unread. Each later change of the rules repeats the backfill, which leaves already deduplicated entries unchanged.

## Configuration

Stored per user, as JSON, in the extension's user configuration. Each rule has:

- `name`
- `enabled`
- `source_feed_ids`, `source_feed_names`
- `target_feed_ids`, `target_feed_names`
- `lookback_days`
- `normalize_whitespace`
- `normalize_case`
- `strip_punctuation`

Feed names are resolved to IDs when the rules are read, case-insensitively and after decoding HTML entities. The settings page stores feed IDs only.

Version 0.1 stored the rules in the system configuration; they are still read from there until the user saves the settings.

## Files

- `metadata.json`
- `extension.php`: hooks, settings form handling, configuration storage
- `configure.php`, `configure.phtml`: settings page
- `static/configure.css`, `static/configure.js`: styles and script of the settings page, as files because FreshRSS's Content Security Policy blocks inline ones; the script is also loaded on the extensions page, so the settings work in the sidebar
- `lib/DedupService.php`: deduplication logic
- `lib/DedupDAO.php`: database queries
- `lib/TitleNormalizer.php`
- `lib/RuleRepository.php`: parsing and validation of the rules
- `i18n/`: translations
- `examples/sample-config.json`

## Risks

- Identical titles do not always mean the same story, so unrelated entries may be marked as read
- Reposts with a rewritten title are not detected
