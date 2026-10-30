'use strict';
// Title Dedup settings page: feed filter, bulk selection, "targets = sources" toggle.
// Without this script the page still works: plain checkboxes, every list visible.

(function () {
	function init(form) {
		if (form.dataset.titleDedupReady) {
			return;
		}
		form.dataset.titleDedupReady = '1';
		form.classList.add('title-dedup-js');

		form.querySelectorAll('.title-dedup-feeds').forEach(initFeedList);
		form.querySelectorAll('.title-dedup-rule').forEach(initSameToggle);
		form.querySelectorAll('.title-dedup-rule').forEach(initLegend);
	}

	function initLegend(rule) {
		const legend = rule.querySelector(':scope > legend');
		const name = rule.querySelector('input[name$="[name]"]');
		if (!legend || !name) {
			return;
		}
		const untitled = legend.dataset.untitled || '';
		name.addEventListener('input', () => {
			legend.textContent = name.value.trim() || untitled;
		});
	}

	function initFeedList(details) {
		const filter = details.querySelector('.title-dedup-filter');
		const boxes = () => Array.from(details.querySelectorAll('.title-dedup-feed input[type="checkbox"]'));
		const visibleBoxes = () => boxes().filter((box) => !box.closest('.title-dedup-feed').hidden);

		function updateSummary() {
			const names = boxes()
				.filter((box) => box.checked)
				.map((box) => box.closest('.title-dedup-feed').querySelector('.title-dedup-feed-name').textContent.trim());
			details.querySelector('.title-dedup-count').textContent = String(names.length);
			details.querySelector('.title-dedup-selected-names').textContent = names.join(', ');
		}

		function applyFilter() {
			const needle = filter.value.trim().toLowerCase();
			details.querySelectorAll('.title-dedup-category').forEach((category) => {
				let shown = 0;
				category.querySelectorAll('.title-dedup-feed').forEach((feed) => {
					const name = feed.querySelector('.title-dedup-feed-name').textContent.toLowerCase();
					const legend = category.querySelector('legend').textContent.toLowerCase();
					feed.hidden = needle !== '' && !name.includes(needle) && !legend.includes(needle);
					if (!feed.hidden) {
						shown++;
					}
				});
				category.hidden = shown === 0;
			});
		}

		function setVisible(checked) {
			visibleBoxes().forEach((box) => {
				box.checked = checked;
			});
			updateSummary();
		}

		filter.addEventListener('input', applyFilter);
		// Enter in the filter must not submit the form.
		filter.addEventListener('keydown', (event) => {
			if (event.key === 'Enter') {
				event.preventDefault();
			}
		});
		details.querySelector('.title-dedup-select-visible').addEventListener('click', () => setVisible(true));
		details.querySelector('.title-dedup-clear-visible').addEventListener('click', () => setVisible(false));
		details.addEventListener('change', (event) => {
			if (event.target.matches('.title-dedup-feed input')) {
				updateSummary();
			}
		});
	}

	function initSameToggle(rule) {
		const same = rule.querySelector('.title-dedup-same');
		const targets = rule.querySelector('[data-title-dedup-side="target"]');
		if (!same || !targets) {
			return;
		}
		const update = () => targets.classList.toggle('title-dedup-hidden', same.checked);
		same.addEventListener('change', update);
		update();
	}

	function scan() {
		document.querySelectorAll('form.title-dedup-form').forEach(init);
	}

	function start() {
		scan();
		// The settings can be loaded later into the sidebar of the extensions page.
		const slider = document.getElementById('slider');
		if (slider) {
			new MutationObserver(scan).observe(slider, { childList: true, subtree: true });
		}
	}

	// The script is loaded asynchronously: the page may still be parsing.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
