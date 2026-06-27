/**
 * LRob Cookie Consent — settings page behaviour: tabs, segmented controls,
 * colour pickers, live banner preview, presets, quick-add services,
 * inline-script repeater, confirm dialog.
 */
(function ($) {
	'use strict';

	var A = window.lrobCcAdmin || {};
	var preview = document.getElementById('lrob-cc-preview');

	// A submit <input name="submit"> shadows form.submit(); always submit safely.
	function submitForm(form) {
		if (form.requestSubmit) { form.requestSubmit(); }
		else { HTMLFormElement.prototype.submit.call(form); }
	}

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

	// --- Tabs: state lives in the URL hash. Survives the Settings-API save
	// by appending the hash to _wp_http_referer (WP keeps URL fragments through
	// the redirect). Naturally resets to General when you navigate elsewhere.
	function activateTab(tab) {
		if (!tab || !document.querySelector('.lrob-cc-panel[data-panel="' + tab + '"]')) { tab = 'general'; }
		$('.lrob-cc-tabs .nav-tab').removeClass('nav-tab-active');
		$('.lrob-cc-tabs .nav-tab[data-tab="' + tab + '"]').addClass('nav-tab-active');
		$('.lrob-cc-panel').attr('hidden', true);
		$('.lrob-cc-panel[data-panel="' + tab + '"]').removeAttr('hidden');
	}

	$('.lrob-cc-tabs .nav-tab').on('click', function (e) {
		e.preventDefault();
		var tab = this.getAttribute('data-tab');
		if (history.replaceState) { history.replaceState(null, '', '#' + tab); } else { window.location.hash = tab; }
		activateTab(tab);
	});

	$('form[action="options.php"]').on('submit', function () {
		var hash = window.location.hash;
		if (!hash) { return; }
		var ref = this.querySelector('input[name="_wp_http_referer"]');
		if (ref) { ref.value = ref.value.split('#')[0] + hash; }
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
		setText('customize', val('text_customize'), field('text_customize'));

		var logo = preview.querySelector('[data-preview="logo"]');
		if (logo) {
			var logoUrl = val('logo');
			logo.src = logoUrl || '';
			logo.hidden = !logoUrl;
			preview.style.setProperty('--lrob-cc-logo-height', (parseInt(val('logo_height'), 10) || 36) + 'px');
		}

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

		preview.style.setProperty('--lrob-cc-align-title', val('align_title') || 'left');
		preview.style.setProperty('--lrob-cc-align-text', val('align_text') || 'left');
		preview.style.setProperty('--lrob-cc-align-footer', val('align_footer') || 'center');
		var bmap = { left: 'flex-start', center: 'center', right: 'flex-end' };
		preview.style.setProperty('--lrob-cc-align-buttons', bmap[val('align_buttons')] || 'flex-start');

		var footerSlot = preview.querySelector('[data-preview="footer"]');
		if (footerSlot) {
			var fh = '';
			document.querySelectorAll('#lrob-cc-links .lrob-cc-link-row').forEach(function (row) {
				var l = (row.querySelector('.lrob-cc-link-label') || {}).value || '';
				var u = (row.querySelector('.lrob-cc-link-url') || {}).value || '';
				if (l && u) { fh += '<a href="#" onclick="return false">' + escapeHtml(l) + '</a> '; }
			});
			if (val('watermark')) {
				fh += '<a class="lrob-cc-watermark" href="#" onclick="return false">' + escapeHtml(A.i18n.watermark || 'Cookie Consent by LRob') + '</a>';
			}
			footerSlot.innerHTML = fh;
			footerSlot.style.display = fh ? '' : 'none';
		}

		$('[data-theme-only="custom"]').toggle(val('theme') === 'custom');
	}

	function field(name) {
		return document.querySelector('[data-field="' + name + '"]');
	}

	$(document).on('input change', '[data-field]', update);

	// Button "Show" toggles grey out their text field (readonly, so the value
	// is preserved across saves).
	function syncTextToggle(cb) {
		var target = document.querySelector('[data-field="' + cb.getAttribute('data-toggle-text') + '"]');
		if (!target) { return; }
		target.readOnly = !cb.checked;
		target.classList.toggle('lrob-cc-readonly', !cb.checked);
	}
	$('[data-toggle-text]').each(function () { syncTextToggle(this); });
	$(document).on('change', '[data-toggle-text]', function () { syncTextToggle(this); });

	// Duration "Default" buttons + restore default when a field is emptied.
	$(document).on('click', '.lrob-cc-default-btn', function () {
		var input = document.querySelector('[name="' + this.getAttribute('data-target') + '"]');
		if (input) { input.value = input.getAttribute('data-default') || ''; }
	});
	$(document).on('blur', '.lrob-cc-num-default', function () {
		if (this.value.trim() === '') { this.value = this.getAttribute('data-default') || ''; }
	});

	// Reveal the custom edge-distance controls only for the "Custom" preset.
	function updateOffsetCustom() {
		var el = document.getElementById('lrob-cc-offset-custom');
		if (!el) { return; }
		var v = (document.querySelector('[name="' + A.optionName + '[offset_preset]"]:checked') || {}).value;
		el.hidden = v !== 'custom';
	}
	$(document).on('change', '[name="' + A.optionName + '[offset_preset]"]', updateOffsetCustom);
	updateOffsetCustom();

	// Warn when proof retention is shorter than the consent lifetime.
	function checkRetention() {
		var warn = document.getElementById('lrob-cc-retention-warn');
		if (!warn) { return; }
		var consent = parseInt((document.querySelector('[name="' + A.optionName + '[cookie_days]"]') || {}).value, 10);
		var retain = parseInt((document.querySelector('[name="' + A.optionName + '[log_retention_days]"]') || {}).value, 10);
		warn.hidden = !(retain > 0 && consent > 0 && retain < consent);
	}
	$(document).on('input change', '[name="' + A.optionName + '[cookie_days]"],[name="' + A.optionName + '[log_retention_days]"]', checkRetention);
	checkRetention();

	// Clicking a consent-version link opens that version's full detail.
	$(document).on('click', '.lrob-cc-ver-link', function () {
		var det = document.querySelector(this.getAttribute('href'));
		if (det) { det.open = true; }
	});

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
		setField('text_preset', id);
		update();
	});

	// Manually editing any banner text marks the preset as "custom".
	$(document).on('input', '[data-field="text_header"],[data-field="text_message"],[data-field="text_accept"],[data-field="text_deny"],[data-field="text_save"]', function () {
		var el = document.getElementById('lrob-cc-text-preset');
		if (el) { el.value = 'custom'; }
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

	function ruleRowByPattern(pattern) {
		var found = null;
		if (rulesRows) {
			rulesRows.querySelectorAll('.lrob-cc-rule-row').forEach(function (r) {
				var inp = r.querySelector('.lrob-cc-rule-pattern');
				if (inp && inp.value.trim() === pattern) { found = r; }
			});
		}
		return found;
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

	// Popup listing the pages a detected resource was found on.
	function openPagesPopup(r) {
		var pages = r.pages || [];
		var overlay = document.createElement('div');
		overlay.className = 'lrob-cc-modal-overlay';
		var items = pages.map(function (p) {
			return '<li><a href="' + escapeHtml(p) + '" target="_blank" rel="noopener">' + escapeHtml(p) + '</a></li>';
		});
		if ((r.pageCount || pages.length) > pages.length) {
			items.push('<li class="description">' + (A.i18n.andMore || '…and %d more').replace('%d', (r.pageCount - pages.length)) + '</li>');
		}
		overlay.innerHTML = '<div class="lrob-cc-modal" role="dialog" aria-modal="true"><div class="lrob-cc-modal-body">' +
			'<h2>' + escapeHtml((A.i18n.foundOn || 'Found on these pages') + ' — ' + r.pattern) + '</h2>' +
			'<ul class="lrob-cc-pages-list">' + items.join('') + '</ul></div>' +
			'<div class="lrob-cc-modal-foot"></div></div>';
		document.body.appendChild(overlay);
		var closeBtn = document.createElement('button');
		closeBtn.type = 'button';
		closeBtn.className = 'button';
		closeBtn.textContent = A.i18n.wizClose || 'Close';
		closeBtn.onclick = function () { if (overlay.parentNode) { document.body.removeChild(overlay); } };
		overlay.querySelector('.lrob-cc-modal-foot').appendChild(closeBtn);
		overlay.addEventListener('click', function (e) { if (e.target === overlay) { document.body.removeChild(overlay); } });
	}

	function renderScan(data) {
		scanResults.innerHTML = '';
		if (data.partial) {
			var p = document.createElement('p');
			p.className = 'lrob-cc-hint';
			p.textContent = A.i18n.scanPartial || 'Still scanning — results so far:';
			scanResults.appendChild(p);
		}
		if (data.urls && data.urls.length) {
			var u = document.createElement('p');
			u.className = 'description';
			u.textContent = data.urls.length > 25
				? (A.i18n.scannedCount || 'Scanned %d pages.').replace('%d', data.urls.length)
				: (A.i18n.scannedUrls || 'Scanned:') + ' ' + data.urls.join(', ');
			scanResults.appendChild(u);
		}

		var res = data.resources || [];
		if (!res.length) {
			var none = document.createElement('p');
			none.textContent = A.i18n.noneFound || 'Nothing found.';
			scanResults.appendChild(none);
		} else {
			// Include functional so payment/necessary services (Stripe…) keep their category.
			var catList = A.catChoices || A.catList || (A.optional || []).map(function (s) { return { slug: s, label: s }; });
			var optionsHtml = catList.map(function (c) { return '<option value="' + escapeHtml(c.slug) + '">' + escapeHtml(c.label) + '</option>'; }).join('');
			var rowsHtml = '';
			res.forEach(function (r, idx) {
				var added = !!(typeof ruleRowByPattern === 'function' && ruleRowByPattern(r.pattern));
				var count = r.pageCount || (r.pages ? r.pages.length : 0);
				rowsHtml += '<tr' + (added ? ' class="lrob-cc-scan-done"' : '') + '>' +
					'<td><input type="checkbox" class="lrob-cc-scan-pick" data-i="' + idx + '"' + (added ? ' disabled' : ' checked') + '/></td>' +
					'<td><code>' + escapeHtml(r.pattern) + '</code></td>' +
					'<td>' + escapeHtml(r.type) + '</td>' +
					'<td>' + (count ? '<a href="#" class="lrob-cc-scan-pages" data-i="' + idx + '">' + count + '</a>' : '0') + '</td>' +
					'<td><select class="lrob-cc-scan-cat" data-i="' + idx + '"' + (added ? ' disabled' : '') + '>' + optionsHtml + '</select></td>' +
					'<td>' + escapeHtml(r.service || '') + '</td>' +
					'<td>' + (added
						? '<span class="lrob-cc-badge is-added">' + (A.i18n.alreadyAdded || 'added') + '</span>'
						: (r.known
							? '<span class="lrob-cc-badge is-known">' + (A.i18n.known || 'known') + '</span>'
							: '<span class="lrob-cc-badge">' + (A.i18n.unknown || 'review') + '</span>')) + '</td>' +
					'</tr>';
			});
			var table = document.createElement('table');
			table.className = 'widefat striped lrob-cc-scan-table';
			table.innerHTML = '<thead><tr><th><input type="checkbox" class="lrob-cc-scan-all" checked title="' + escapeHtml(A.i18n.selectAll || 'Select all') + '"/></th><th>pattern</th><th>type</th><th>pages</th><th>category</th><th>service</th><th></th></tr></thead><tbody>' + rowsHtml + '</tbody>';
			scanResults.appendChild(table);
			res.forEach(function (r, idx) {
				var sel = scanResults.querySelector('.lrob-cc-scan-cat[data-i="' + idx + '"]');
				if (sel && r.category) { sel.value = r.category; }
			});
			scanResults.querySelectorAll('.lrob-cc-scan-pages').forEach(function (a) {
				a.addEventListener('click', function (e) { e.preventDefault(); openPagesPopup(res[a.getAttribute('data-i')]); });
			});
			var master = scanResults.querySelector('.lrob-cc-scan-all');
			if (master) {
				master.addEventListener('change', function () {
					scanResults.querySelectorAll('.lrob-cc-scan-pick:not(:disabled)').forEach(function (cb) { cb.checked = master.checked; });
				});
			}

			var add = document.createElement('button');
			add.type = 'button';
			add.className = 'button button-primary';
			add.textContent = A.i18n.addSelected || 'Add selected as rules';
			add.addEventListener('click', function () {
				var lastRow = null;
				scanResults.querySelectorAll('.lrob-cc-scan-pick:checked').forEach(function (cb) {
					var i = cb.getAttribute('data-i');
					var r = res[i];
					if (typeof ruleRowByPattern === 'function' && ruleRowByPattern(r.pattern)) { return; } // never duplicate
					var cat = scanResults.querySelector('.lrob-cc-scan-cat[data-i="' + i + '"]').value;
					addRuleRow(r.pattern, cat, r.service || '');
					lastRow = rulesRows ? rulesRows.lastElementChild : null;
				});
				serializeRules();
				toStructuredMode();
				if (lastRow && lastRow.scrollIntoView) {
					lastRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
					var firstInput = lastRow.querySelector('input');
					if (firstInput) { firstInput.focus(); }
				}
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

	var scanProgress = document.getElementById('lrob-cc-scan-progress');
	var scanBar = document.getElementById('lrob-cc-scan-bar');
	var scanProgressText = document.getElementById('lrob-cc-scan-progress-text');
	var scanDbNote = document.getElementById('lrob-cc-scan-db-note');
	var scanPagesWarn = document.getElementById('lrob-cc-scan-pages-warn');
	var scanScopeWrap = document.getElementById('lrob-cc-scan-scope-wrap');
	var scanScope = document.getElementById('lrob-cc-scan-scope');
	var scanManyWarn = document.getElementById('lrob-cc-scan-many-warn');

	function scanScopeCount() {
		if (!scanScope) { return 0; }
		var opt = scanScope.options[scanScope.selectedIndex];
		return opt ? (parseInt(opt.getAttribute('data-count'), 10) || 0) : 0;
	}
	function updateScanUi() {
		var pages = (document.querySelector('input[name="lrob-cc-scan-method"]:checked') || {}).value === 'pages';
		if (scanDbNote) { scanDbNote.hidden = pages; }
		if (scanPagesWarn) { scanPagesWarn.hidden = !pages; }
		if (scanScopeWrap) { scanScopeWrap.hidden = !pages; }
		if (scanManyWarn) { scanManyWarn.hidden = !(pages && scanScopeCount() > 10); }
	}
	$(document).on('change', 'input[name="lrob-cc-scan-method"]', updateScanUi);
	$(scanScope).on('change', updateScanUi);
	updateScanUi();

	function scanAjax(action, params) {
		var body = 'action=' + action + '&nonce=' + encodeURIComponent(A.scanNonce || '');
		Object.keys(params || {}).forEach(function (k) { body += '&' + k + '=' + encodeURIComponent(params[k]); });
		return fetch(A.ajaxUrl, {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body
		}).then(function (r) { return r.text(); }).then(function (t) {
			try { return JSON.parse(t); } catch (e) { return { success: false, data: { message: t.slice(0, 300) } }; }
		}).catch(function () { return { success: false, data: {} }; });
	}

	function scanEnd() {
		scanBtn.disabled = false;
		scanBtn.textContent = A.i18n.scanAgain || 'Scan again';
		if (scanProgress) { scanProgress.hidden = true; }
	}

	// Merge a detection into the aggregate, unioning the pages it was found on.
	function mergeRes(agg, r) {
		var e = agg[r.pattern];
		if (!e) {
			e = agg[r.pattern] = { pattern: r.pattern, host: r.host, type: r.type, category: r.category, service: r.service, known: r.known, pages: [], pageCount: 0 };
		}
		(r.pages || []).forEach(function (p) {
			if (e.pages.indexOf(p) === -1) {
				e.pageCount++;
				if (e.pages.length < 100) { e.pages.push(p); }
			}
		});
	}

	function scanUrls(urls, insecure) {
		var agg = {}, cookies = [], sslErrors = 0, i = 0;
		if (scanBar) { scanBar.max = urls.length; scanBar.value = 0; }
		function paint(done) {
			renderScan({
				urls: urls.slice(0, i),
				resources: Object.keys(agg).map(function (k) { return agg[k]; }),
				cookies: cookies,
				partial: !done
			});
			if (done && sslErrors > 0 && !insecure) {
				var note = document.createElement('p');
				note.className = 'lrob-cc-hint lrob-cc-hint-warning';
				note.textContent = (A.i18n.sslFailed || 'Some pages had an SSL error.') + ' ';
				var retry = document.createElement('button');
				retry.type = 'button';
				retry.className = 'button';
				retry.textContent = A.i18n.sslRetry || 'Retry ignoring SSL';
				retry.addEventListener('click', function () {
					scanResults.innerHTML = '';
					scanBtn.disabled = true;
					if (scanProgress) { scanProgress.hidden = false; }
					scanUrls(urls, true);
				});
				note.appendChild(retry);
				scanResults.appendChild(note);
			}
		}
		function next() {
			if (i >= urls.length) { scanEnd(); paint(true); return; }
			if (scanProgressText) {
				scanProgressText.textContent = (A.i18n.scanProgress || 'Scanning %1$d of %2$d…')
					.replace('%1$d', i + 1).replace('%2$d', urls.length);
			}
			scanAjax('lrob_cc_scan_url', { url: urls[i], insecure: insecure ? 1 : 0 }).then(function (json) {
				var before = Object.keys(agg).length + cookies.length;
				if (json.success && json.data) {
					if (json.data.error === 'ssl') { sslErrors++; }
					(json.data.resources || []).forEach(function (r) { mergeRes(agg, r); });
					(json.data.cookies || []).forEach(function (c) { if (cookies.indexOf(c) === -1) { cookies.push(c); } });
				}
				i++;
				if (scanBar) { scanBar.value = i; }
				// Repaint only when something new surfaced — shows results as they
				// arrive and leaves partial findings on screen if a later page hangs.
				if (Object.keys(agg).length + cookies.length !== before) { paint(false); }
				next();
			});
		}
		next();
	}

	// Database scan, batched with a progress bar (mirrors the visit-pages loop).
	function scanDb(offset, agg) {
		scanAjax('lrob_cc_scan_db', { offset: offset }).then(function (json) {
			if (!json.success || !json.data) {
				scanEnd();
				scanResults.textContent = (json && json.data && json.data.message) ? json.data.message : (A.i18n.scanError || 'Scan failed.');
				return;
			}
			var d = json.data;
			var before = Object.keys(agg).length;
			(d.resources || []).forEach(function (r) { mergeRes(agg, r); });
			if (scanBar) { scanBar.max = d.total || 1; scanBar.value = Math.min(d.processed || 0, d.total || 0); }
			if (scanProgressText) {
				scanProgressText.textContent = (A.i18n.scanProgress || 'Scanning %1$d of %2$d…')
					.replace('%1$d', Math.min(d.processed || 0, d.total || 0)).replace('%2$d', d.total || 0);
			}
			if (!d.done) {
				if (Object.keys(agg).length !== before) {
					renderScan({ urls: [], resources: Object.keys(agg).map(function (k) { return agg[k]; }), cookies: [], partial: true });
				}
				scanDb(d.processed, agg);
				return;
			}
			scanEnd();
			renderScan({ urls: [], resources: Object.keys(agg).map(function (k) { return agg[k]; }), cookies: [] });
		});
	}

	if (scanBtn) {
		scanBtn.addEventListener('click', function () {
			var method = (document.querySelector('input[name="lrob-cc-scan-method"]:checked') || {}).value || 'database';
			scanBtn.disabled = true;
			scanBtn.textContent = A.i18n.scanning || 'Scanning…';
			scanResults.innerHTML = '';

			if (method === 'database') {
				if (scanProgress) { scanProgress.hidden = false; }
				scanDb(0, {});
				return;
			}

			if (scanProgress) { scanProgress.hidden = false; }
			scanAjax('lrob_cc_scan_targets', { scope: scanScope ? scanScope.value : 'pages' }).then(function (json) {
				if (!json.success || !json.data || !json.data.urls || !json.data.urls.length) {
					scanEnd();
					scanResults.textContent = (json && json.data && json.data.message) ? json.data.message : (A.i18n.scanError || 'Scan failed.');
					return;
				}
				scanUrls(json.data.urls, false);
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

	// --- Guided setup wizard (multi-section) ----------------------------
	$(document).on('click', '.lrob-cc-wizard-open', openWizard);

	function radioGroup(field, choices, current) {
		var html = '<div class="lrob-cc-wiz-radios">';
		choices.forEach(function (c) {
			html += '<label class="lrob-cc-check"><input type="radio" name="wiz-' + field + '" value="' +
				escapeHtml(c.v) + '"' + (c.v === current ? ' checked' : '') + '/> ' + escapeHtml(c.l) + '</label>';
		});
		return html + '</div>';
	}

	function openWizard() {
		var WS = A.wizardSettings || {};
		var serviceSel = {};
		var screens = [];

		// Patterns already in the rules — pre-check known services. Custom rules
		// (patterns not offered by the wizard) are never touched.
		var existingPatterns = {};
		if (rulesTextarea) {
			rulesTextarea.value.split('\n').forEach(function (l) { var p = l.split('|')[0].trim(); if (p) { existingPatterns[p] = true; } });
		}
		var allServices = [];
		(A.wizard || []).forEach(function (step) { (step.services || []).forEach(function (s) { allServices.push(s); }); });

		// 2. Tone (text preset) — remembers the last chosen preset; "Keep current"
		// is pre-selected when the texts were customised or already match.
		if (WS.tone && (A.texts || []).length) {
			var presetIds = (A.texts || []).map(function (p) { return p.id; });
			var current = val('text_preset');
			// First run = no preset chosen and no custom wording yet → there is
			// nothing to "keep", so default to the first preset instead.
			var firstRun = !current && !val('text_header') && !val('text_message');
			var toneChoices = (A.texts || []).map(function (p) { return { v: p.id, l: p.label }; });
			if (!firstRun) { toneChoices.unshift({ v: '__keep', l: A.i18n.wizKeepCurrent || 'Keep current' }); }
			var tone = { choice: presetIds.indexOf(current) !== -1 ? current : (firstRun ? (presetIds[0] || '') : '__keep') };
			screens.push({
				title: WS.tone.question, hint: WS.tone.hint,
				render: function (b) { b.innerHTML = radioGroup('tone', toneChoices, tone.choice); bindRadio(b, 'tone', function (v) { tone.choice = v; }); },
				apply: function () {
					if (tone.choice === '__keep') { return; }
					var p = (A.texts || []).filter(function (x) { return x.id === tone.choice; })[0];
					if (!p) { return; }
					['header', 'message', 'accept', 'deny', 'save'].forEach(function (k) { if (p[k] !== undefined) { setField('text_' + k, p[k]); } });
					setField('text_preset', tone.choice);
				}
			});
		}

		// 3. Look (colors + position) — pre-selected to the current settings.
		if (WS.look) {
			var look = { theme: val('theme'), position: val('position') };
			screens.push({
				title: WS.look.question,
				render: function (b) {
					b.innerHTML = '<p class="lrob-cc-field-label">' + escapeHtml(WS.look.colorsLabel || 'Colors') + '</p>' +
						radioGroup('theme', WS.look.colors || [], look.theme) +
						'<p class="lrob-cc-field-label">' + escapeHtml(WS.look.positionLabel || 'Position') + '</p>' +
						radioGroup('position', WS.look.positions || [], look.position);
					bindRadio(b, 'theme', function (v) { look.theme = v; });
					bindRadio(b, 'position', function (v) { look.position = v; });
				},
				apply: function () {
					if (look.theme) { setField('theme', look.theme); }
					if (look.position) { setField('position', look.position); }
				}
			});
		}

		// 4. Logging — pre-selected to the current setting.
		if (WS.logging) {
			var log = { choice: val('log_consent') ? '1' : '0' };
			screens.push({
				title: WS.logging.question, hint: WS.logging.hint,
				render: function (b) { b.innerHTML = radioGroup('log', [
					{ v: '1', l: A.i18n.wizYesKeep || 'Yes' },
					{ v: '0', l: A.i18n.wizNoKeep || 'No' }
				], log.choice); bindRadio(b, 'log', function (v) { log.choice = v; }); },
				apply: function () { setField('log_consent', log.choice === '1'); }
			});
		}

		// 5. Service questions (blocking) — pre-checked for rules you already have.
		(A.wizard || []).forEach(function (step) {
			(step.services || []).forEach(function (svc) { if (existingPatterns[svc.pattern]) { serviceSel[svc.pattern] = svc; } });
			screens.push({
				title: step.question, hint: step.hint,
				render: function (b) {
					var html = '<div class="lrob-cc-wiz-options">';
					(step.services || []).forEach(function (svc) {
						html += '<label class="lrob-cc-check"><input type="checkbox" class="lrob-cc-wiz-svc" data-pattern="' +
							escapeHtml(svc.pattern) + '"' + (serviceSel[svc.pattern] ? ' checked' : '') + '/> ' + escapeHtml(svc.label) + '</label>';
					});
					b.innerHTML = html + '</div>';
					b.querySelectorAll('.lrob-cc-wiz-svc').forEach(function (cb) {
						cb.addEventListener('change', function () {
							var p = cb.getAttribute('data-pattern');
							var svc = (step.services || []).filter(function (s) { return s.pattern === p; })[0];
							if (cb.checked) { serviceSel[p] = svc; } else { delete serviceSel[p]; }
						});
					});
				},
				apply: function () {}
			});
		});

		if (!screens.length) { return; }

		var idx = 0;
		var overlay = document.createElement('div');
		overlay.className = 'lrob-cc-modal-overlay';
		overlay.innerHTML = '<div class="lrob-cc-modal" role="dialog" aria-modal="true"><div class="lrob-cc-modal-body"></div><div class="lrob-cc-modal-foot"></div></div>';
		document.body.appendChild(overlay);
		var body = overlay.querySelector('.lrob-cc-modal-body');
		var foot = overlay.querySelector('.lrob-cc-modal-foot');

		function bindRadio(container, field, cb) {
			container.querySelectorAll('input[name="wiz-' + field + '"]').forEach(function (r) {
				r.addEventListener('change', function () { if (r.checked) { cb(r.value); } });
			});
		}
		function close() { if (overlay.parentNode) { document.body.removeChild(overlay); } }
		function btn(label, cls) {
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'button ' + (cls || '');
			b.textContent = label;
			return b;
		}

		function render() {
			var screen = screens[idx];
			var label = (A.i18n.wizStep || 'Step %1$d of %2$d').replace('%1$d', idx + 1).replace('%2$d', screens.length);
			body.innerHTML = '<p class="lrob-cc-wiz-step">' + escapeHtml(label) + '</p><h2>' + escapeHtml(screen.title) + '</h2>' +
				(screen.hint ? '<p class="description">' + escapeHtml(screen.hint) + '</p>' : '') + '<div class="lrob-cc-wiz-screen"></div>';
			screen.render(body.querySelector('.lrob-cc-wiz-screen'));

			foot.innerHTML = '';
			var cancel = btn(A.i18n.wizClose || 'Close');
			cancel.onclick = close;
			var back = btn(A.i18n.wizBack || 'Back');
			back.disabled = idx === 0;
			back.onclick = function () { if (idx > 0) { idx--; render(); } };
			var isLast = idx === screens.length - 1;
			var next = btn(isLast ? (A.i18n.wizFinish || 'Finish & save') : (A.i18n.wizNext || 'Next'), 'button-primary');
			next.onclick = function () { if (isLast) { finish(); } else { idx++; render(); } };
			foot.appendChild(cancel);
			foot.appendChild(back);
			foot.appendChild(next);
		}

		function finish() {
			screens.forEach(function (s) { if (s.apply) { s.apply(); } });

			// Sync only the wizard's known services: add the checked ones, remove
			// the unchecked ones. Any other (custom) rule is left untouched.
			allServices.forEach(function (svc) {
				var want = !!serviceSel[svc.pattern];
				var row = ruleRowByPattern(svc.pattern);
				if (want && !row) { addRuleRow(svc.pattern, svc.category, svc.service); }
				else if (!want && row) { row.remove(); }
			});
			serializeRules();
			var form = document.querySelector('form[action="options.php"]');
			close();
			if (form) { submitForm(form); } else { toStructuredMode(); }
		}

		render();
	}

	// --- Categories repeater --------------------------------------------
	var catsWrap = document.getElementById('lrob-cc-cats');
	$('#lrob-cc-cat-add').on('click', function () {
		if (!catsWrap) { return; }
		var name = catsWrap.getAttribute('data-name');
		var i = Date.now();
		var row = document.createElement('div');
		row.className = 'lrob-cc-cat-row';
		row.innerHTML =
			'<input type="text" class="lrob-cc-cat-slug" name="' + name + '[categories][' + i + '][slug]" placeholder="' + (A.i18n.catSlug || 'slug') + '" />' +
			'<input type="text" name="' + name + '[categories][' + i + '][label]" placeholder="' + (A.i18n.catLabel || 'Label') + '" />' +
			'<input type="text" class="lrob-cc-cat-desc" name="' + name + '[categories][' + i + '][desc]" placeholder="' + (A.i18n.catDesc || 'Description') + '" />' +
			'<button type="button" class="button lrob-cc-cat-remove" aria-label="' + (A.i18n.removeRow || 'Remove') + '">&times;</button>';
		catsWrap.appendChild(row);
	});
	$(document).on('click', '.lrob-cc-cat-remove', function () {
		$(this).closest('.lrob-cc-cat-row').remove();
	});

	// --- Footer-links repeater + page search ----------------------------
	var linksWrap = document.getElementById('lrob-cc-links');

	function addLinkRow(label, url) {
		if (!linksWrap) { return; }
		var name = linksWrap.getAttribute('data-name');
		var i = Date.now() + Math.floor(Math.random() * 1000);
		var row = document.createElement('div');
		row.className = 'lrob-cc-link-row';
		row.innerHTML =
			'<input type="text" class="lrob-cc-link-label" name="' + name + '[footer_links][' + i + '][label]" placeholder="' + (A.i18n.catLabel || 'Label') + '" />' +
			'<input type="url" class="lrob-cc-link-url" name="' + name + '[footer_links][' + i + '][url]" placeholder="https://…" />' +
			'<button type="button" class="button lrob-cc-link-remove" aria-label="' + (A.i18n.removeRow || 'Remove') + '">&times;</button>';
		linksWrap.appendChild(row);
		row.querySelector('.lrob-cc-link-label').value = label || '';
		row.querySelector('.lrob-cc-link-url').value = url || '';
		update();
	}

	$('#lrob-cc-link-add').on('click', function () { addLinkRow('', ''); });
	$(document).on('click', '.lrob-cc-link-remove', function () {
		$(this).closest('.lrob-cc-link-row').remove();
		update();
	});
	$(document).on('input', '#lrob-cc-links input', update);

	var linkSearch = document.getElementById('lrob-cc-link-search');
	var linkResults = document.getElementById('lrob-cc-link-search-results');
	var linkSearchTimer;
	if (linkSearch && linkResults) {
		linkSearch.addEventListener('input', function () {
			var q = this.value.trim();
			clearTimeout(linkSearchTimer);
			if (q.length < 2) { linkResults.hidden = true; linkResults.innerHTML = ''; return; }
			linkSearchTimer = setTimeout(function () {
				scanAjax('lrob_cc_search_pages', { q: q }).then(function (json) {
					linkResults.innerHTML = '';
					var pages = (json.success && json.data && json.data.pages) ? json.data.pages : [];
					pages.forEach(function (p) {
						var b = document.createElement('button');
						b.type = 'button';
						b.className = 'lrob-cc-link-result';
						b.textContent = p.title || p.url;
						b.addEventListener('click', function () {
							addLinkRow(p.title || '', p.url || '');
							linkResults.hidden = true;
							linkResults.innerHTML = '';
							linkSearch.value = '';
						});
						linkResults.appendChild(b);
					});
					linkResults.hidden = pages.length === 0;
				});
			}, 250);
		});
	}

	// --- Inline-script repeater -----------------------------------------
	var wrap = document.getElementById('lrob-cc-inline-scripts');
	$('#lrob-cc-inline-add').on('click', function () {
		if (!wrap) { return; }
		var name = wrap.getAttribute('data-name');
		var i = Date.now();
		var opts = (A.catChoices || (A.optional || []).map(function (s) { return { slug: s, label: s }; })).map(function (c) {
			return '<option value="' + escapeHtml(c.slug) + '">' + escapeHtml(c.label) + '</option>';
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
			submitForm(form);
		});
	});

	// --- Restore tab: URL hash, else ?tab= (server round-trips like a log
	// delete have no hash), else General.
	if (window.location.hash) {
		activateTab(window.location.hash.replace('#', ''));
	} else {
		var tabMatch = window.location.search.match(/[?&]tab=([a-z]+)/);
		if (tabMatch) { activateTab(tabMatch[1]); }
	}

	update();
})(jQuery);
