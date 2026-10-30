# Changelog

[Русская версия](./CHANGELOG.ru.md) · [中文](./CHANGELOG.zh-CN.md)

## 0.2.0

- Duplicates fetched in the same refresh are now detected: lookups also cover `_entrytmp`, where FreshRSS keeps new entries until the end of the refresh.
- A source entry that arrives after its target copy marks that copy as read immediately, instead of waiting for maintenance.
- Titles are loaded once per rule and per request instead of once per entry; the maintenance backfill is linear instead of quadratic.
- The maintenance backfill runs once after the rules are saved, so entries manually marked as unread are no longer marked as read again on every refresh.
- The punctuation option now works; whitespace, case and punctuation have separate checkboxes.
- Rules are stored per user (user extension). Rules from 0.1 are still read from the system configuration until saved again.
- Titles are compared after decoding HTML entities.
- Interface translated via FreshRSS i18n: English, Russian, Chinese.
- Updated entries are no longer re-checked.
- Feeds can be both source and target: the first copy of a story stays unread and later copies are marked as read. Read entries never mark other entries, so saving the rules again is safe.
- The extension sits at the repository root and can be cloned directly into `extensions/`.
- Uses the `entry_before_add` hook: only new entries are checked, after the user's filter actions.
- Title comparison: punctuation and decorative symbols, with the spaces around them, are replaced by one space (`A—B` equals `A — B`); currency and math symbols are kept (`100$` ≠ `100€`, `C++` ≠ `C`); invisible characters such as soft hyphens are ignored; `ё` equals `е` when case is ignored.
- Feed selection with checkboxes grouped by category, a filter and "Select shown" / "Clear shown" buttons; "Targets are the same as sources" option.
- Styles and script of the settings page moved to static files: FreshRSS's Content Security Policy blocked the inline ones, so they never applied.
- Settings page: pressing Enter in a text field no longer deletes the rule; the backfill re-runs only when the effective rules change (not on renaming or adding an empty rule); the rule title follows its name as you type.
- The settings page works in the sidebar of the extensions page too: its styles and script were not loaded there.
- Feed and category names with `&` or quotes are shown correctly in the settings, and feed names in rules match them.
