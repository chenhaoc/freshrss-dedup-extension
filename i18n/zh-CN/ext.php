<?php

return [
	'title_dedup' => [
		'intro' => '用于按标题把目标源中已在参考源出现过的文章自动标记为已读。',
		'open_full' => '在完整页面中编辑',
		'backfill_hint' => '保存后，下一次刷新会对回看天数内的已有文章检查一次。此后只检查新文章，手动标为未读的文章将保持未读。',
		'rule' => [
			'name' => '规则名',
			'name_placeholder' => '例如：36Kr-AI 去重',
			'enabled' => '启用',
			'remove' => '删除规则组',
			'untitled' => '未命名规则',
			'targets_same' => '目标源与参考源相同',
			'targets_same_help' => '用于在一组订阅之间互相去重：每条新闻的第一份保持未读。',
			'add' => '新增规则组',
		],
		'source' => [
			'title' => '参考源',
			'help' => '这些源里出现过的同标题文章，会作为去重依据',
		],
		'target' => [
			'title' => '目标源',
			'help' => '命中的文章会自动已读',
		],
		'feeds' => [
			'selected' => '已选',
			'filter' => '按订阅或分类筛选…',
			'select_visible' => '选中显示的',
			'clear_visible' => '取消显示的',
		],
		'lookback' => '回看天数',
		'normalize' => [
			'title' => '归一化',
			'whitespace' => '压缩空白',
			'case' => '忽略大小写（含 ё/е）',
			'punctuation' => '删除标点符号',
		],
		'save' => '保存',
		'notify' => [
			'saved' => '去重规则已保存。',
			'invalid' => '规则配置无效，未保存。',
		],
	],
];
