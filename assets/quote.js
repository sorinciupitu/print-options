/**
 * Cerere ofertă — repeatable product rows.
 */
(function () {
	'use strict';

	function init(root) {
		var form = root.querySelector('.pov-quote-form');
		var list = root.querySelector('[data-pov-items]');
		var tpl = root.querySelector('template[data-pov-template]');
		if (!form || !list || !tpl) {
			return;
		}

		var config = window.povQuote || {};
		var max = parseInt(config.max, 10) || 10;
		var badge = root.querySelector('[data-pov-count]');
		var addBtn = root.querySelector('[data-pov-add]');
		var counter = 1000;

		function rows() {
			return Array.prototype.slice.call(list.querySelectorAll('[data-pov-item]'));
		}

		function refresh() {
			var items = rows();
			if (badge) {
				badge.textContent = String(items.length);
			}
			items.forEach(function (item, index) {
				var remove = item.querySelector('[data-pov-remove]');
				if (remove) {
					remove.hidden = items.length < 2;
					remove.setAttribute('aria-label', 'Șterge produsul ' + (index + 1));
				}
			});
			if (addBtn) {
				addBtn.disabled = items.length >= max;
				addBtn.title = items.length >= max ? (config.error || '') : '';
			}
		}

		function clearRow(item) {
			item.querySelectorAll('input[type="text"], textarea').forEach(function (field) {
				field.value = '';
			});
			var qty = item.querySelector('input[type="number"]');
			if (qty) {
				qty.value = '1';
			}
			var file = item.querySelector('input[type="file"]');
			if (file) {
				file.value = '';
				updateFiles(item);
			}
		}

		function updateFiles(item) {
			var input = item.querySelector('[data-pov-file]');
			var out = item.querySelector('[data-pov-files]');
			if (!input || !out) {
				return;
			}
			out.innerHTML = '';
			Array.prototype.slice.call(input.files || []).forEach(function (file) {
				var chip = document.createElement('span');
				chip.textContent = file.name;
				out.appendChild(chip);
			});
		}

		function addRow() {
			if (rows().length >= max) {
				return;
			}
			counter += 1;
			var key = 'n' + counter;
			list.insertAdjacentHTML('beforeend', tpl.innerHTML.split('__KEY__').join(key));
			var item = list.lastElementChild;
			refresh();
			var field = item ? item.querySelector('input[type="text"]') : null;
			if (field) {
				field.focus();
				item.scrollIntoView({ behavior: 'smooth', block: 'center' });
			}
		}

		if (addBtn) {
			addBtn.addEventListener('click', function (event) {
				event.preventDefault();
				addRow();
			});
		}

		list.addEventListener('click', function (event) {
			var remove = event.target.closest('[data-pov-remove]');
			var plus = event.target.closest('[data-pov-plus]');

			if (remove) {
				event.preventDefault();
				var item = remove.closest('[data-pov-item]');
				if (!item) {
					return;
				}
				if (rows().length < 2) {
					clearRow(item);
					return;
				}
				item.parentNode.removeChild(item);
				refresh();
				return;
			}

			if (plus) {
				event.preventDefault();
				var row = plus.closest('[data-pov-item]');
				var qty = row ? row.querySelector('input[type="number"]') : null;
				if (qty) {
					qty.value = String(Math.max(1, (parseInt(qty.value, 10) || 0) + 1));
					qty.dispatchEvent(new Event('change', { bubbles: true }));
				}
			}
		});

		list.addEventListener('change', function (event) {
			var input = event.target;

			if (input.matches('[data-pov-file]')) {
				updateFiles(input.closest('[data-pov-item]'));
				return;
			}

			if (input.matches('input[type="number"]')) {
				var value = parseInt(input.value, 10);
				if (isNaN(value) || value < 1) {
					input.value = '1';
				}
			}
		});

		form.addEventListener('submit', function (event) {
			var filled = rows().filter(function (item) {
				var name = item.querySelector('input[type="text"]');
				var desc = item.querySelector('textarea');
				var file = item.querySelector('[data-pov-file]');
				return (
					(name && name.value.trim() !== '') ||
					(desc && desc.value.trim() !== '') ||
					(file && file.files && file.files.length)
				);
			});

			if (!filled.length) {
				event.preventDefault();
				var first = rows()[0];
				var input = first ? first.querySelector('input[type="text"]') : null;
				if (input) {
					input.setCustomValidity('Completează produsul sau serviciul solicitat.');
					input.reportValidity();
					input.addEventListener(
						'input',
						function () {
							input.setCustomValidity('');
						},
						{ once: true }
					);
					input.focus();
				}
				return;
			}

			var submit = form.querySelector('.pov-quote-submit');
			if (submit) {
				submit.disabled = true;
				submit.classList.add('is-loading');
			}
		});

		refresh();
	}

	function boot() {
		document.querySelectorAll('.pov-quote').forEach(init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
