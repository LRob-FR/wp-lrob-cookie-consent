/**
 * LRob Cookie Consent — settings page behaviour: tabs, segmented controls,
 * colour pickers, live banner preview, presets, quick-add services,
 * inline-script repeater, confirm dialog.
 */
(function ($) {
	'use strict';

	var A = window.lrobCcAdmin || {};
	var preview = document.getElementById('lrob-cc-preview');

	function val(name) {
		var els = document.querySelectorAll('[data-field="' + name + '"]');
		if (!els.length) { return ''; }
		if (els[0].type === 'radio') {
			for (var i = 0; i < els.length; i++) { if (els[i].checked) { return els[i].value; } }
			return '';
		}
		if (els[0].type === 'checkbox') { return els[0].checked; }
		return els[0].value;
	}

	// --- Tabs ------------------------------------------------------------
	$('.lrob-cc-tabs .nav-tab').on('click', function (e) {
		e.preventDefault();
		var tab = this.getAttribute('data-tab');
		$('.lrob-cc-tabs .nav-tab').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');
		$('.lrob-cc-panel').attr('hidden', true);
		$('.lrob-cc-panel[data-panel="' + tab + '"]').removeAttr('hidden');
	});

	// --- Segmented controls: reflect active state ------------------------
	$(document).on('change', '.lrob-cc-segmented input[type="radio"]', function () {
		$(this).closest('.lrob-cc-segmented').find('.lrob-cc-seg').removeClass('is-active');
		$(this).closest('.lrob-cc-seg').addClass('is-active');
	});

	// --- Live preview ----------------------------------------------------
	function setText(slot, value, fallbackEl) {
		var node = preview.querySelector('[data-preview="' + slot + '"]');
		if (!node) { return; }
		var ph = fallbackEl ? fallbackEl.getAttribute('placeholder') : '';
		node.textContent = value || ph || '';
	}

	function show(slot, on) {
		var node = preview.querySelector('[data-preview="' + slot + '"]');
		if (node) { node.style.display = on ? '' : 'none'; }
	}

	function applyColors() {
		var keys = ['bg', 'text', 'title', 'border', 'btn-bg', 'btn-text', 'btn-deny-bg', 'btn-deny-text'];
		keys.forEach(function (k) { preview.style.removeProperty('--lrob-cc-' + k); });

		var theme = val('theme');
		if (theme === 'light' || theme === 'dark') {
			var pal = A.palettes[theme] || {};
			Object.keys(pal).forEach(function (k) { preview.style.setProperty('--lrob-cc-' + k, pal[k]); });
		} else if (theme === 'custom') {
			var map = {
				'bg': 'color_bg', 'text': 'color_text', 'title': 'color_title', 'border': 'color_border',
				'btn-bg': 'color_btn_bg', 'btn-text': 'color_btn_text',
				'btn-deny-bg': 'color_btn_deny_bg', 'btn-deny-text': 'color_btn_deny_text'
			};
			Object.keys(map).forEach(function (k) {
				var v = val(map[k]);
				if (v) { preview.style.setProperty('--lrob-cc-' + k, v); }
			});
		}
	}

	function update() {
		if (!preview) { return; }
		var s = A.scales || {};

		setText('header', val('text_header'), field('text_header'));
		setText('message', val('text_message'), field('text_message'));
		setText('accept', val('text_accept'), field('text_accept'));
		setText('deny', val('text_deny'), field('text_deny'));
		setText('save', val('text_save'), field('text_save'));

		var collapsed = val('categories_collapsed');
		show('deny', val('show_deny'));
		show('customize', collapsed);
		show('save', !collapsed && val('show_save'));
		var cats = preview.querySelector('[data-preview="cats"]');
		if (cats) { cats.style.display = collapsed ? 'none' : ''; }

		applyColors();
		if (s.width) { preview.style.setProperty('--lrob-cc-width', s.width[val('popup_size')] || s.width.small); }
		var dens = (s.density || {})[val('density')] || (s.density || {}).cozy;
		if (dens) { preview.style.setProperty('--lrob-cc-pad', dens.pad); preview.style.setProperty('--lrob-cc-gap', dens.gap); }
		var font = (s.font || {})[val('font_size')] || (s.font || {}).medium;
		if (font) { preview.style.setProperty('--lrob-cc-font-size', font.font); preview.style.setProperty('--lrob-cc-title-size', font.title); }
		if (s.radius) { preview.style.setProperty('--lrob-cc-radius', s.radius[val('shape')] || s.radius.rounded); }

		$('[data-theme-only="custom"]').toggle(val('theme') === 'custom');
	}

	function field(name) {
		return document.querySelector('[data-field="' + name + '"]');
	}

	$(document).on('input change', '[data-field]', update);

	// --- Colour pickers --------------------------------------------------
	$('.lrob-cc-color').wpColorPicker({
		change: function () { setTimeout(update, 50); },
		clear: function () { setTimeout(update, 50); }
	});

	// --- Presets ---------------------------------------------------------
	function setField(key, value) {
		var els = document.querySelectorAll('[data-field="' + key + '"]');
		if (!els.length) {
			var byName = document.querySelector('[name="' + A.optionName + '[' + key + ']"]');
			if (byName) { els = [byName]; }
		}
		for (var i = 0; i < els.length; i++) {
			var el = els[i];
			if ($(el).hasClass('lrob-cc-color')) { $(el).wpColorPicker('color', value); }
			else if (el.type === 'radio') { el.checked = (el.value === String(value)); if (el.checked) { $(el).trigger('change'); } }
			else if (el.type === 'checkbox') { el.checked = !!value; }
			else { el.value = value; }
		}
	}

	function applyOptions(opts) {
		Object.keys(opts).forEach(function (key) { setField(key, opts[key]); });
		update();
	}

	$('.lrob-cc-preset-row[data-preset-group="text"] .lrob-cc-preset').on('click', function () {
		var id = this.getAttribute('data-preset-id');
		var preset = (A.texts || []).filter(function (p) { return p.id === id; })[0];
		if (!preset) { return; }
		['header', 'message', 'accept', 'deny', 'save'].forEach(function (k) {
			if (preset[k] !== undefined) { setField('text_' + k, preset[k]); }
		});
		update();
	});

	$('.lrob-cc-preset-row[data-preset-group="colors"] .lrob-cc-preset').on('click', function () {
		var id = this.getAttribute('data-preset-id');
		var preset = (A.colorPresets || []).filter(function (p) { return p.id === id; })[0];
		if (preset && preset.options) { applyOptions(preset.options); }
	});

	// --- Block-rule editor: guided rows <-> raw textarea ----------------
	var rulesTextarea = document.getElementById('lrob-cc-block-rules');
	var rulesRows = document.getElementById('lrob-cc-rule-rows');
	var ruleTemplate = document.getElementById('lrob-cc-rule-template');

	function serializeRules() {
		if (!rulesRows || !rulesTextarea) { return; }
		var lines = [];
		rulesRows.querySelectorAll('.lrob-cc-rule-row').forEach(function (row) {
			var pattern = row.querySelector('.lrob-cc-rule-pattern').value.trim();
			var category = row.querySelector('.lrob-cc-rule-category').value;
			var service = row.querySelector('.lrob-cc-rule-service').value.trim();
			if (pattern) { lines.push(pattern + ' | ' + category + ' | ' + service); }
		});
		rulesTextarea.value = lines.join('\n');
	}

	function addRuleRow(pattern, category, service) {
		if (!ruleTemplate || !rulesRows) { return; }
		var row = ruleTemplate.content.cloneNode(true).querySelector('.lrob-cc-rule-row');
		row.querySelector('.lrob-cc-rule-pattern').value = pattern || '';
		if (category) { row.querySelector('.lrob-cc-rule-category').value = category; }
		row.querySelector('.lrob-cc-rule-service').value = service || '';
		rulesRows.appendChild(row);
	}

	function parseRulesToRows() {
		if (!rulesRows || !rulesTextarea) { return; }
		rulesRows.innerHTML = '';
		rulesTextarea.value.split('\n').forEach(function (line) {
			line = line.trim();
			if (!line || line.charAt(0) === '#') { return; }
			var p = line.split('|').map(function (s) { return s.trim(); });
			addRuleRow(p[0] || '', p[1] || '', p[2] || '');
		});
	}

	$(document).on('input change', '.lrob-cc-rule-row input, .lrob-cc-rule-row select', serializeRules);
	$(document).on('click', '.lrob-cc-rule-remove', function () {
		$(this).closest('.lrob-cc-rule-row').remove();
		serializeRules();
	});
	$('#lrob-cc-rule-add').on('click', function () {
		addRuleRow('', (A.optional || [])[0] || '', '');
	});
	$('.lrob-cc-service').on('click', function () {
		addRuleRow(this.getAttribute('data-pattern'), this.getAttribute('data-category'), this.getAttribute('data-service'));
		serializeRules();
	});

	// Mode toggle: keep both views in sync.
	$(document).on('change', 'input[data-field="rules_mode"]', function () {
		var mode = this.value;
		if (mode === 'raw') { serializeRules(); } else { parseRulesToRows(); }
		$('[data-rules-panel="structured"]').attr('hidden', mode !== 'structured');
		$('[data-rules-panel="raw"]').attr('hidden', mode !== 'raw');
	});

	// The textarea is the saved field — make sure guided edits land in it on submit.
	if (rulesRows) {
		$(rulesRows).closest('form').on('submit', function () {
			var checked = document.querySelector('input[data-field="rules_mode"]:checked');
			if (!checked || checked.value === 'structured') { serializeRules(); }
		});
	}

	function toStructuredMode() {
		var structured = document.querySelector('input[data-field="rules_mode"][value="structured"]');
		if (structured && !structured.checked) { structured.checked = true; $(structured).trigger('change'); }
	}

	function escapeHtml(s) {
		return String(s).replace(/[&<>"']/g, function (m) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
		});
	}

	// --- Site scan -------------------------------------------------------
	var scanBtn = document.getElementById('lrob-cc-scan-btn');
	var scanResults = document.getElementById('lrob-cc-scan-results');

	function renderScan(data) {
		scanResults.innerHTML = '';
		if (data.urls && data.urls.length) {
			var u = document.createElement('p');
			u.className = 'description';
			u.textContent = (A.i18n.scannedUrls || 'Scanned:') + ' ' + data.urls.join(', ');
			scanResults.appendChild(u);
		}

		var res = data.resources || [];
		if (!res.length) {
			var none = document.createElement('p');
			none.textContent = A.i18n.noneFound || 'Nothing found.';
			scanResults.appendChild(none);
		} else {
			var optionsHtml = (A.optional || []).map(function (c) { return '<option value="' + c + '">' + c + '</option>'; }).join('');
			var rowsHtml = '';
			res.forEach(function (r, idx) {
				rowsHtml += '<tr>' +
					'<td><input type="checkbox" class="lrob-cc-scan-pick" data-i="' + idx + '"' + (r.known ? ' checked' : '') + '/></td>' +
					'<td><code>' + escapeHtml(r.pattern) + '</code></td>' +
					'<td>' + escapeHtml(r.type) + '</td>' +
					'<td><select class="lrob-cc-scan-cat" data-i="' + idx + '">' + optionsHtml + '</select></td>' +
					'<td>' + escapeHtml(r.service || '') + '</td>' +
					'<td>' + (r.known
						? '<span class="lrob-cc-badge is-known">' + (A.i18n.known || 'known') + '</span>'
						: '<span class="lrob-cc-badge">' + (A.i18n.unknown || 'review') + '</span>') + '</td>' +
					'</tr>';
			});
			var table = document.createElement('table');
			table.className = 'widefat striped lrob-cc-scan-table';
			table.innerHTML = '<thead><tr><th></th><th>pattern</th><th>type</th><th>category</th><th>service</th><th></th></tr></thead><tbody>' + rowsHtml + '</tbody>';
			scanResults.appendChild(table);
			res.forEach(function (r, idx) {
				var sel = scanResults.querySelector('.lrob-cc-scan-cat[data-i="' + idx + '"]');
				if (sel && r.category) { sel.value = r.category; }
			});

			var add = document.createElement('button');
			add.type = 'button';
			add.className = 'button button-primary';
			add.textContent = A.i18n.addSelected || 'Add selected as rules';
			add.addEventListener('click', function () {
				scanResults.querySelectorAll('.lrob-cc-scan-pick:checked').forEach(function (cb) {
					var i = cb.getAttribute('data-i');
					var r = res[i];
					var cat = scanResults.querySelector('.lrob-cc-scan-cat[data-i="' + i + '"]').value;
					addRuleRow(r.pattern, cat, r.service || '');
				});
				serializeRules();
				toStructuredMode();
			});
			scanResults.appendChild(add);
		}

		if (data.cookies && data.cookies.length) {
			var c = document.createElement('p');
			c.className = 'description';
			c.textContent = (A.i18n.cookiesSeen || 'Cookies set:') + ' ' + data.cookies.join(', ');
			scanResults.appendChild(c);
		}
	}

	if (scanBtn) {
		scanBtn.addEventListener('click', function () {
			scanBtn.disabled = true;
			scanBtn.textContent = A.i18n.scanning || 'Scanning…';
			scanResults.innerHTML = '';
			var body = 'action=lrob_cc_scan&provider=local&nonce=' + encodeURIComponent(A.scanNonce || '');
			fetch(A.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body
			}).then(function (r) { return r.json(); }).then(function (json) {
				scanBtn.disabled = false;
				scanBtn.textContent = A.i18n.scanAgain || 'Scan again';
				if (!json || !json.success) { scanResults.textContent = A.i18n.scanError || 'Scan failed.'; return; }
				renderScan(json.data);
			}).catch(function () {
				scanBtn.disabled = false;
				scanBtn.textContent = A.i18n.scanAgain || 'Scan again';
				scanResults.textContent = A.i18n.scanError || 'Scan failed.';
			});
		});
	}

	// --- Logo: WordPress media library ----------------------------------
	var logoFrame;
	var logoInput = document.getElementById('lrob-cc-logo-input');
	var logoPreview = document.getElementById('lrob-cc-logo-preview');
	var logoRemove = document.getElementById('lrob-cc-logo-remove');

	$('#lrob-cc-logo-select').on('click', function (e) {
		e.preventDefault();
		if (!window.wp || !wp.media) { return; }
		if (logoFrame) { logoFrame.open(); return; }
		logoFrame = wp.media({
			title: A.i18n.selectLogo || 'Select logo',
			button: { text: A.i18n.selectLogo || 'Select' },
			library: { type: 'image' },
			multiple: false
		});
		logoFrame.on('select', function () {
			var att = logoFrame.state().get('selection').first().toJSON();
			var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
			if (logoInput) { logoInput.value = url; }
			if (logoPreview) { logoPreview.src = url; logoPreview.hidden = false; }
			if (logoRemove) { logoRemove.hidden = false; }
		});
		logoFrame.open();
	});

	$(logoRemove).on('click', function () {
		if (logoInput) { logoInput.value = ''; }
		if (logoPreview) { logoPreview.src = ''; logoPreview.hidden = true; }
		this.hidden = true;
	});

	// --- Guided wizard (question flow) ----------------------------------
	$('#lrob-cc-wizard-open').on('click', openWizard);

	function openWizard() {
		var steps = A.wizard || [];
		if (!steps.length) { return; }
		var hasExisting = rulesRows && rulesRows.querySelectorAll('.lrob-cc-rule-row').length > 0;
		var mode = 'add';
		var selected = {};
		var stepIndex = hasExisting ? -1 : 0;

		var overlay = document.createElement('div');
		overlay.className = 'lrob-cc-modal-overlay';
		overlay.innerHTML = '<div class="lrob-cc-modal" role="dialog" aria-modal="true"><div class="lrob-cc-modal-body"></div><div class="lrob-cc-modal-foot"></div></div>';
		document.body.appendChild(overlay);
		var body = overlay.querySelector('.lrob-cc-modal-body');
		var foot = overlay.querySelector('.lrob-cc-modal-foot');

		function close() { if (overlay.parentNode) { document.body.removeChild(overlay); } }
		function btn(label, cls) {
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'button ' + (cls || '');
			b.textContent = label;
			return b;
		}

		function renderIntro() {
			body.innerHTML = '<p>' + escapeHtml(A.i18n.wizExisting || '') + '</p>';
			foot.innerHTML = '';
			var fresh = btn(A.i18n.wizFresh || 'Start fresh');
			fresh.onclick = function () { mode = 'fresh'; stepIndex = 0; render(); };
			var add = btn(A.i18n.wizAddTo || 'Add to current', 'button-primary');
			add.onclick = function () { mode = 'add'; stepIndex = 0; render(); };
			foot.appendChild(fresh);
			foot.appendChild(add);
		}

		function renderStep() {
			var step = steps[stepIndex];
			var label = (A.i18n.wizStep || 'Step %1$d of %2$d').replace('%1$d', stepIndex + 1).replace('%2$d', steps.length);
			var html = '<p class="lrob-cc-wiz-step">' + escapeHtml(label) + '</p><h2>' + escapeHtml(step.question) + '</h2>';
			if (step.hint) { html += '<p class="description">' + escapeHtml(step.hint) + '</p>'; }
			html += '<div class="lrob-cc-wiz-options">';
			(step.services || []).forEach(function (svc) {
				html += '<label class="lrob-cc-check"><input type="checkbox" class="lrob-cc-wiz-svc" data-pattern="' +
					escapeHtml(svc.pattern) + '"' + (selected[svc.pattern] ? ' checked' : '') + '/> ' + escapeHtml(svc.label) + '</label>';
			});
			html += '</div>';
			body.innerHTML = html;

			body.querySelectorAll('.lrob-cc-wiz-svc').forEach(function (cb) {
				cb.addEventListener('change', function () {
					var p = cb.getAttribute('data-pattern');
					var svc = (step.services || []).filter(function (s) { return s.pattern === p; })[0];
					if (cb.checked) { selected[p] = svc; } else { delete selected[p]; }
				});
			});

			foot.innerHTML = '';
			var cancel = btn(A.i18n.wizClose || 'Close');
			cancel.onclick = close;
			var back = btn(A.i18n.wizBack || 'Back');
			back.disabled = (stepIndex === 0 && !hasExisting);
			back.onclick = function () {
				if (stepIndex === 0 && hasExisting) { stepIndex = -1; } else if (stepIndex > 0) { stepIndex--; }
				render();
			};
			var isLast = stepIndex === steps.length - 1;
			var next = btn(isLast ? (A.i18n.wizFinish || 'Finish') : (A.i18n.wizNext || 'Next'), 'button-primary');
			next.onclick = function () { if (isLast) { finish(); } else { stepIndex++; render(); } };
			foot.appendChild(cancel);
			foot.appendChild(back);
			foot.appendChild(next);
		}

		function render() { if (stepIndex < 0) { renderIntro(); } else { renderStep(); } }

		function finish() {
			if (mode === 'fresh' && rulesRows) {
				rulesRows.innerHTML = '';
				if (rulesTextarea) { rulesTextarea.value = ''; }
			}
			Object.keys(selected).forEach(function (p) {
				var s = selected[p];
				addRuleRow(s.pattern, s.category, s.service);
			});
			serializeRules();
			toStructuredMode();
			close();
		}

		render();
	}

	// --- Inline-script repeater -----------------------------------------
	var wrap = document.getElementById('lrob-cc-inline-scripts');
	$('#lrob-cc-inline-add').on('click', function () {
		if (!wrap) { return; }
		var name = wrap.getAttribute('data-name');
		var i = Date.now();
		var opts = (A.optional || []).map(function (c) {
			return '<option value="' + c + '">' + c + '</option>';
		}).join('');
		var row = document.createElement('div');
		row.className = 'lrob-cc-inline-row';
		row.innerHTML =
			'<select name="' + name + '[inline_scripts][' + i + '][category]">' + opts + '</select>' +
			'<textarea rows="3" class="large-text code" name="' + name + '[inline_scripts][' + i + '][code]"></textarea>' +
			'<button type="button" class="button lrob-cc-inline-remove">' + (A.i18n.removeRow || 'Remove') + '</button>';
		wrap.appendChild(row);
	});

	$(document).on('click', '.lrob-cc-inline-remove', function () {
		$(this).closest('.lrob-cc-inline-row').remove();
	});

	// --- Minimal confirm dialog (no window.confirm) ----------------------
	$('form[data-lrob-cc-confirm]').on('submit', function (e) {
		if (this.dataset.confirmed === '1') { return; }
		e.preventDefault();
		var form = this;
		var overlay = document.createElement('div');
		overlay.className = 'lrob-cc-confirm-overlay';
		overlay.innerHTML =
			'<div class="lrob-cc-confirm-box" role="dialog" aria-modal="true">' +
			'<p></p>' +
			'<div class="lrob-cc-confirm-actions">' +
			'<button type="button" class="button lrob-cc-confirm-cancel"></button>' +
			'<button type="button" class="button button-primary lrob-cc-confirm-ok"></button>' +
			'</div></div>';
		overlay.querySelector('p').textContent = A.i18n.confirmPurge || 'Are you sure?';
		overlay.querySelector('.lrob-cc-confirm-cancel').textContent = A.i18n.cancel || 'Cancel';
		overlay.querySelector('.lrob-cc-confirm-ok').textContent = A.i18n.confirm || 'Confirm';
		document.body.appendChild(overlay);
		overlay.querySelector('.lrob-cc-confirm-ok').focus();

		overlay.querySelector('.lrob-cc-confirm-cancel').addEventListener('click', function () {
			document.body.removeChild(overlay);
		});
		overlay.querySelector('.lrob-cc-confirm-ok').addEventListener('click', function () {
			form.dataset.confirmed = '1';
			form.submit();
		});
	});

	// --- Deep-link to a tab via #hash ------------------------------------
	if (window.location.hash) {
		var tab = window.location.hash.replace('#', '');
		var link = document.querySelector('.lrob-cc-tabs .nav-tab[data-tab="' + tab + '"]');
		if (link) { link.click(); }
	}

	update();
})(jQuery);
