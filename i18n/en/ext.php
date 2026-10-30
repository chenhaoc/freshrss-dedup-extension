<?php

return [
	'title_dedup' => [
		'intro' => 'Marks entries of the target feeds as read when an entry with the same title already exists in the source feeds.',
		'open_full' => 'Edit in full page',
		'backfill_hint' => 'After saving, the next refresh checks all existing entries within the lookback window once. After that, only new entries are checked, so entries you mark as unread again stay unread.',
		'rule' => [
			'name' => 'Rule name',
			'name_placeholder' => 'e.g. 3DNews: all sections',
			'enabled' => 'Enabled',
			'remove' => 'Delete rule',
			'untitled' => 'Untitled rule',
			'targets_same' => 'Targets are the same as sources',
			'targets_same_help' => 'Use it to deduplicate a group of feeds among themselves: the first copy of each story stays unread.',
			'add' => 'Add rule',
		],
		'source' => [
			'title' => 'Source feeds',
			'help' => 'Titles from these feeds are used as the reference for duplicates.',
		],
		'target' => [
			'title' => 'Target feeds',
			'help' => 'Matching entries in these feeds are marked as read.',
		],
		'feeds' => [
			'selected' => 'Selected',
			'filter' => 'Filter feeds or categories…',
			'select_visible' => 'Select shown',
			'clear_visible' => 'Clear shown',
		],
		'lookback' => 'Lookback (days)',
		'normalize' => [
			'title' => 'Title comparison',
			'whitespace' => 'Collapse whitespace',
			'case' => 'Ignore case (and ё/е)',
			'punctuation' => 'Ignore punctuation and symbols',
		],
		'save' => 'Save',
		'notify' => [
			'saved' => 'Deduplication rules saved.',
			'invalid' => 'Invalid rule configuration, nothing was saved.',
		],
	],
];
