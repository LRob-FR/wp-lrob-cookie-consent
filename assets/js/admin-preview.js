/**
 * LRob Cookie Consent — settings page behaviour: tabs, segmented controls,
 * colour pickers, live banner preview, presets, quick-add services,
 * inline-script repeater, confirm dialog.
 */
(function ($) {
	'use strict';

	var A = window.lrobCcAdmin || {};

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
		if (tab === 'banner' && typeof replayPreviewAnim === 'function') { replayPreviewAnim(); }
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
	var previewStage = document.getElementById('lrob-cc-preview-stage');
	var previewStyle = document.getElementById('lrob-cc-preview-style');

	// Admin-side field visibility (reveal/hide settings rows) — separate from the
	// preview render.
	function adminFieldToggles() {
		var denyStyle = val('deny_style');
		var linkOpts = document.querySelector('.lrob-cc-deny-link-opts');
		if (linkOpts) { linkOpts.hidden = denyStyle !== 'link'; }
		var refuseRow = document.getElementById('lrob-cc-refuse-row');
		if (refuseRow) { refuseRow.hidden = !val('show_deny'); }
		var saveRow = document.getElementById('lrob-cc-save-row');
		if (saveRow) { saveRow.hidden = val('categories_collapsed') && !val('show_customize'); }
		var bd = val('backdrop');
		var dimOpt = document.getElementById('lrob-cc-backdrop-dim');
		if (dimOpt) { dimOpt.hidden = bd !== 'dim' && bd !== 'blur'; }
		var bdBlurOpt = document.getElementById('lrob-cc-backdrop-blur');
		if (bdBlurOpt) { bdBlurOpt.hidden = bd !== 'blur'; }
		var discOpts = document.getElementById('lrob-cc-disclosure-opts');
		if (discOpts) { discOpts.hidden = val('disclosure') === 'off'; }
		var discMand = document.querySelector('.lrob-cc-disclosure-mandatory');
		if (discMand) { discMand.hidden = val('disclosure') !== 'two'; }
		$('[data-theme-only="custom"]').toggle(val('theme') === 'custom');
	}

	// The preview IS the real banner: a debounced server re-render from the current
	// (unsaved) form values, byte-identical to the front.
	var previewTimer, previewBusy = false, previewAgain = false, previewReplay = false;
	function schedulePreview(replay) {
		if (replay) { previewReplay = true; }
		clearTimeout(previewTimer);
		previewTimer = setTimeout(function () { renderPreview(); }, 250);
	}
	function renderPreview(replayNow) {
		if (!previewStage) { return; }
		if (previewBusy) { previewAgain = true; if (replayNow) { previewReplay = true; } return; }
		var form = document.querySelector('form[action="options.php"]');
		if (!form) { return; }
		var replay = !!(replayNow || previewReplay);
		previewReplay = false;
		previewBusy = true;
		var body = $(form).serialize() + '&action=lrob_cc_preview&nonce=' + encodeURIComponent(A.previewNonce || '');
		fetch(A.ajaxUrl, {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body
		}).then(function (r) { return r.json(); }).then(function (json) {
			previewBusy = false;
			if (json && json.success && json.data) {
				if (previewStyle) { previewStyle.textContent = json.data.css || ''; }
				// Replay the entrance animation only when asked (animation settings or
				// refresh) — ordinary tweaks update in place so you can see the change.
				previewStage.classList.toggle('is-replaying', replay);
				previewStage.innerHTML = json.data.html || '';
				var banner = previewStage.querySelector('#lrob-cc-preview');
				if (banner) { banner.hidden = false; } // server marks it hidden; reveal it here
				applyPreviewBackdrop();
			}
			if (previewAgain) { previewAgain = false; schedulePreview(); }
		}).catch(function () { previewBusy = false; });
	}
	function replayPreviewAnim() { renderPreview(true); }

	// Lift the (fixed, full-screen) backdrop to the stage so dim/blur shows inside
	// the panel rather than over the admin page.
	function applyPreviewBackdrop() {
		var banner = previewStage.querySelector('#lrob-cc-preview');
		var bdEl = previewStage.querySelector('.lrob-cc-backdrop');
		if (bdEl && bdEl.parentNode !== previewStage) { previewStage.insertBefore(bdEl, banner); }
		if (!bdEl) { return; }
		var bd = val('backdrop');
		if (bd === 'dim' || bd === 'blur') {
			bdEl.style.display = 'block';
			bdEl.style.background = 'rgba(0,0,0,' + ((parseInt(val('backdrop_dim'), 10) || 0) / 100) + ')';
			var bp = (bd === 'blur' ? (parseInt(val('backdrop_blur'), 10) || 0) : 0) + 'px';
			bdEl.style.backdropFilter = 'blur(' + bp + ')';
			bdEl.style.webkitBackdropFilter = 'blur(' + bp + ')';
		} else {
			bdEl.style.display = 'none';
		}
	}

	// Interactions on the real banner (preview only: no cookies/logging/blocking).
	$(previewStage).on('click', '[data-lrob-cc-action]', function (e) {
		e.preventDefault();
		var banner = previewStage.querySelector('#lrob-cc-preview');
		if (!banner) { return; }
		if (this.getAttribute('data-lrob-cc-action') === 'customize') {
			var cats = banner.querySelector('.lrob-cc-categories');
			if (cats) { cats.hidden = false; }
			this.hidden = true;
			var saveBtn = banner.querySelector('.lrob-cc-btn-save');
			if (saveBtn) { saveBtn.hidden = false; }
		} else {
			banner.hidden = true; // accept / deny / save / close → dismiss
			showPreviewRevisit();
		}
	});
	$(previewStage).on('click', '.lrob-cc-revisit', function () { renderPreview(); });

	function showPreviewRevisit() {
		if (!val('revisit_button') || previewStage.querySelector('.lrob-cc-revisit')) { return; }
		var rp = val('revisit_position');
		if (!rp || rp === 'follow') { rp = val('position') || 'bottom-right'; }
		var v = rp.indexOf('top') === 0 ? 'top' : 'bottom';
		var h = rp.indexOf('left') !== -1 ? 'left' : 'right';
		var b = document.createElement('button');
		b.type = 'button';
		b.className = 'lrob-cc-revisit lrob-cc-revisit-' + v + '-' + h;
		b.textContent = val('revisit_text') || (A.i18n && A.i18n.manageCookies) || 'Manage cookies';
		previewStage.appendChild(b);
	}

	function field(name) {
		return document.querySelector('[data-field="' + name + '"]');
	}

	function update() {
		adminFieldToggles();
		schedulePreview();
	}
	$(document).on('input change', '[data-field]', update);
	// Catch everything else (rules, categories, footer links, inline scripts…).
	$(document).on('input change', 'form[action="options.php"] :input', function () { schedulePreview(); });
	// Animation settings replay the entrance animation; the refresh button too.
	$(document).on('input change', '[data-field^="anim_"], [name*="[anim_"]', function () { schedulePreview(true); });
	$('#lrob-cc-preview-refresh').on('click', function () { renderPreview(true); });

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

	// Pencil toggles the built-in category's description editor (click again to exit).
	$(document).on('click', '.lrob-cc-cat-edit', function () {
		var body = this.closest('.lrob-cc-cat-card-body');
		if (!body) { return; }
		var ta = body.querySelector('.lrob-cc-cat-card-desc-input');
		var desc = body.querySelector('.lrob-cc-cat-card-desc');
		if (!ta) { return; }
		var show = ta.hidden; // currently hidden → reveal it
		ta.hidden = !show;
		if (desc) { desc.hidden = show; }
		this.classList.toggle('is-active', show);
		if (show) { ta.focus(); }
	});

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

	// "Slide from" only matters for the Slide movement.
	function updateAnimDir() {
		var el = document.getElementById('lrob-cc-anim-dir-field');
		if (!el) { return; }
		var v = (document.querySelector('[name="' + A.optionName + '[anim_move]"]:checked') || {}).value;
		el.hidden = v !== 'slide';
	}
	$(document).on('change', '[name="' + A.optionName + '[anim_move]"]', updateAnimDir);
	updateAnimDir();

	// Replay button re-renders the banner fresh (which replays the entrance animation).
	$('#lrob-cc-anim-replay').on('click', function () { renderPreview(true); });

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

	// --- Site scan: DB-first, results accumulate; optional parallel HTTP deep scan ---
	var scanResults = document.getElementById('lrob-cc-scan-results');

	// Persistent aggregate — DB and HTTP scans both merge here and never reset
	// until "Start over". category + picked live here so repaints keep edits.
	var scanAgg = {};
	var scanCookies = [];
	var httpPump = null; // set to the worker-pool pump while an HTTP scan runs

	function aggArray() { return Object.keys(scanAgg).map(function (k) { return scanAgg[k]; }); }

	// Merge one detection; returns true if a new resource or page was added.
	function mergeRes(r) {
		var e = scanAgg[r.pattern], changed = false;
		if (!e) {
			e = scanAgg[r.pattern] = { pattern: r.pattern, host: r.host, type: r.type, category: r.category, service: r.service, known: r.known, pages: [], pageCount: 0, picked: true };
			changed = true;
		}
		(r.pages || []).forEach(function (p) {
			if (e.pages.indexOf(p) === -1) { e.pageCount++; if (e.pages.length < 100) { e.pages.push(p); } changed = true; }
		});
		return changed;
	}
	function mergeCookies(list) {
		var changed = false;
		(list || []).forEach(function (c) { if (scanCookies.indexOf(c) === -1) { scanCookies.push(c); changed = true; } });
		return changed;
	}

	// Throttle repaints so a fast parallel scan doesn't thrash the DOM.
	var renderPending = false;
	function scheduleRender() {
		if (renderPending) { return; }
		renderPending = true;
		setTimeout(function () { renderPending = false; renderResults(); }, 200);
	}

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

	function renderResults() {
		scanResults.innerHTML = '';
		var res = aggArray();
		if (scanStartOver) { scanStartOver.hidden = !(res.length || scanCookies.length); }
		if (!res.length && !scanCookies.length) { return; }

		if (res.length) {
			// Include functional so payment/necessary services (Stripe…) keep their category.
			var catList = A.catChoices || A.catList || (A.optional || []).map(function (s) { return { slug: s, label: s }; });
			var optionsHtml = catList.map(function (c) { return '<option value="' + escapeHtml(c.slug) + '">' + escapeHtml(c.label) + '</option>'; }).join('');
			var rowsHtml = '';
			res.forEach(function (r, idx) {
				var added = !!(typeof ruleRowByPattern === 'function' && ruleRowByPattern(r.pattern));
				var count = r.pageCount || (r.pages ? r.pages.length : 0);
				rowsHtml += '<tr' + (added ? ' class="lrob-cc-scan-done"' : '') + '>' +
					'<td><input type="checkbox" class="lrob-cc-scan-pick" data-i="' + idx + '"' + (added ? ' disabled' : (r.picked ? ' checked' : '')) + '/></td>' +
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
			// Persist table edits into the aggregate so repaints keep them.
			scanResults.querySelectorAll('.lrob-cc-scan-cat').forEach(function (sel) {
				sel.addEventListener('change', function () { var r = res[sel.getAttribute('data-i')]; if (r) { r.category = sel.value; } });
			});
			scanResults.querySelectorAll('.lrob-cc-scan-pick').forEach(function (cb) {
				cb.addEventListener('change', function () { var r = res[cb.getAttribute('data-i')]; if (r) { r.picked = cb.checked; } });
			});
			scanResults.querySelectorAll('.lrob-cc-scan-pages').forEach(function (a) {
				a.addEventListener('click', function (e) { e.preventDefault(); openPagesPopup(res[a.getAttribute('data-i')]); });
			});
			var master = scanResults.querySelector('.lrob-cc-scan-all');
			if (master) {
				master.addEventListener('change', function () {
					scanResults.querySelectorAll('.lrob-cc-scan-pick:not(:disabled)').forEach(function (cb) {
						cb.checked = master.checked;
						var r = res[cb.getAttribute('data-i')]; if (r) { r.picked = cb.checked; }
					});
				});
			}

			var add = document.createElement('button');
			add.type = 'button';
			add.className = 'button button-primary';
			add.textContent = A.i18n.addSelected || 'Add selected as rules';
			add.addEventListener('click', function () {
				var lastRow = null;
				res.forEach(function (r, i) {
					var cb = scanResults.querySelector('.lrob-cc-scan-pick[data-i="' + i + '"]');
					if (!cb || !cb.checked || cb.disabled) { return; }
					if (typeof ruleRowByPattern === 'function' && ruleRowByPattern(r.pattern)) { return; } // never duplicate
					var sel = scanResults.querySelector('.lrob-cc-scan-cat[data-i="' + i + '"]');
					addRuleRow(r.pattern, sel ? sel.value : (r.category || ''), r.service || '');
					lastRow = rulesRows ? rulesRows.lastElementChild : null;
				});
				serializeRules();
				toStructuredMode();
				renderResults(); // re-mark the rows now flagged "added"
				if (lastRow && lastRow.scrollIntoView) {
					lastRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
					var firstInput = lastRow.querySelector('input');
					if (firstInput) { firstInput.focus(); }
				}
			});
			scanResults.appendChild(add);
		} else {
			var none = document.createElement('p');
			none.textContent = A.i18n.noneFound || 'Nothing found.';
			scanResults.appendChild(none);
		}

		if (scanCookies.length) {
			var c = document.createElement('p');
			c.className = 'description';
			c.textContent = (A.i18n.cookiesSeen || 'Cookies set:') + ' ' + scanCookies.join(', ');
			scanResults.appendChild(c);
		}
	}

	var scanProgress = document.getElementById('lrob-cc-scan-progress');
	var scanBar = document.getElementById('lrob-cc-scan-bar');
	var scanProgressText = document.getElementById('lrob-cc-scan-progress-text');
	var scanCurrent = document.getElementById('lrob-cc-scan-current');
	var scanDbBtn = document.getElementById('lrob-cc-scan-db-btn');
	var scanHttpBtn = document.getElementById('lrob-cc-scan-http-btn');
	var scanHttpCard = document.getElementById('lrob-cc-scan-http-card');
	var scanStartOver = document.getElementById('lrob-cc-scan-startover');
	var scanAllTypes = document.getElementById('lrob-cc-scan-all-types');
	var scanSpeed = document.getElementById('lrob-cc-scan-speed');
	var scanSpeedVal = document.getElementById('lrob-cc-scan-speed-val');
	var scanTotalEl = document.getElementById('lrob-cc-scan-total');
	var scanManyWarn = document.getElementById('lrob-cc-scan-many-warn');

	// opts.timeout (ms) aborts via AbortController; the result carries httpStatus
	// + timedOut so the worker pool can detect host overload.
	function scanAjax(action, params, opts) {
		opts = opts || {};
		var body = 'action=' + action + '&nonce=' + encodeURIComponent(A.scanNonce || '');
		Object.keys(params || {}).forEach(function (k) { body += '&' + k + '=' + encodeURIComponent(params[k]); });
		var ctrl = (opts.timeout && window.AbortController) ? new AbortController() : null;
		var timer = ctrl ? setTimeout(function () { ctrl.abort(); }, opts.timeout) : null;
		return fetch(A.ajaxUrl, {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body, signal: ctrl ? ctrl.signal : undefined
		}).then(function (r) {
			var status = r.status;
			return r.text().then(function (t) {
				if (timer) { clearTimeout(timer); }
				var json;
				try { json = JSON.parse(t); } catch (e) { json = { success: false, data: { message: t.slice(0, 300) } }; }
				json.httpStatus = status;
				return json;
			});
		}).catch(function (e) {
			if (timer) { clearTimeout(timer); }
			return { success: false, data: {}, httpStatus: 0, timedOut: !!(ctrl && e && e.name === 'AbortError') };
		});
	}

	// One notice line above the results (slowdown / SSL retry / host failure).
	function scanNotice(msg, isError, btnLabel, btnFn) {
		var box = document.getElementById('lrob-cc-scan-notice');
		if (!box) {
			box = document.createElement('div');
			box.id = 'lrob-cc-scan-notice';
			scanResults.parentNode.insertBefore(box, scanResults);
		}
		box.className = 'lrob-cc-hint' + (isError ? ' lrob-cc-hint-warning' : '');
		box.textContent = msg + ' ';
		if (btnLabel && btnFn) {
			var b = document.createElement('button');
			b.type = 'button'; b.className = 'button';
			b.textContent = btnLabel;
			b.addEventListener('click', function () { clearScanNotice(); btnFn(); });
			box.appendChild(b);
		}
	}
	function clearScanNotice() { var box = document.getElementById('lrob-cc-scan-notice'); if (box) { box.parentNode.removeChild(box); } }

	function pathOf(u) { try { var x = new URL(u, location.href); return x.pathname + x.search; } catch (e) { return u; } }

	function setScanBusy(on) {
		if (scanDbBtn) { scanDbBtn.disabled = on; }
		if (scanHttpBtn) { scanHttpBtn.disabled = on; }
		if (scanProgress) { scanProgress.hidden = !on; }
		if (scanCurrent) { scanCurrent.textContent = ''; }
	}
	function setProgress(done, total, etaSecs, current) {
		if (scanBar) { scanBar.max = total || 1; scanBar.value = Math.min(done, total || 0); }
		if (scanProgressText) {
			var t = done + ' / ' + (total || 0);
			if (etaSecs != null && etaSecs > 0) { t += '  ' + (A.i18n.secondsLeft || '~%ds left').replace('%d', etaSecs); }
			scanProgressText.textContent = t;
		}
		if (scanCurrent && current != null) { scanCurrent.textContent = current; }
	}

	// --- Granular "visit pages" selection -------------------------------
	function scanTypeConfig() {
		var cfg = [];
		document.querySelectorAll('#lrob-cc-scan-http-card .lrob-cc-scan-type').forEach(function (row) {
			if (row.getAttribute('data-type') === 'home') { return; }
			var on = row.querySelector('.lrob-cc-scan-type-on');
			if (!on || !on.checked) { return; }
			cfg.push({
				type: row.getAttribute('data-type'),
				limit: Math.max(0, parseInt((row.querySelector('.lrob-cc-scan-type-limit') || {}).value, 10) || 0),
				order: (row.querySelector('.lrob-cc-scan-type-order') || {}).value || 'newest'
			});
		});
		return cfg;
	}
	function scanTotal() {
		var total = 1; // home is always scanned
		scanTypeConfig().forEach(function (c) {
			var row = document.querySelector('#lrob-cc-scan-http-card .lrob-cc-scan-type[data-type="' + c.type + '"]');
			var count = row ? (parseInt(row.getAttribute('data-count'), 10) || 0) : 0;
			total += (c.limit > 0 && c.limit < count) ? c.limit : count;
		});
		return total;
	}
	function updateHttpUi() {
		var total = scanTotal();
		if (scanTotalEl) { scanTotalEl.textContent = (A.i18n.pagesToScan || '%d pages to scan.').replace('%d', total); }
		if (scanManyWarn) { scanManyWarn.hidden = total <= 20; }
	}
	if (scanAllTypes) {
		scanAllTypes.addEventListener('change', function () {
			document.querySelectorAll('#lrob-cc-scan-http-card .lrob-cc-scan-type-on').forEach(function (cb) { cb.checked = scanAllTypes.checked; });
			updateHttpUi();
		});
	}
	$(document).on('change input', '#lrob-cc-scan-http-card .lrob-cc-scan-type-on, #lrob-cc-scan-http-card .lrob-cc-scan-type-limit, #lrob-cc-scan-http-card .lrob-cc-scan-type-order', updateHttpUi);
	if (scanSpeed) {
		scanSpeed.addEventListener('input', function () {
			if (scanSpeedVal) { scanSpeedVal.textContent = scanSpeed.value; }
			if (httpPump) { httpPump(); } // ramp up live mid-scan
		});
	}

	// --- Database scan (fast, batched) ----------------------------------
	function runDbScan() {
		clearScanNotice();
		setScanBusy(true);
		(function batch(offset) {
			scanAjax('lrob_cc_scan_db', { offset: offset }).then(function (json) {
				if (!json.success || !json.data) {
					setScanBusy(false);
					scanNotice((json && json.data && json.data.message) || (A.i18n.scanError || 'Scan failed.'), true);
					return;
				}
				var d = json.data, changed = false;
				(d.resources || []).forEach(function (r) { if (mergeRes(r)) { changed = true; } });
				setProgress(Math.min(d.processed || 0, d.total || 0), d.total || 0);
				if (changed) { scheduleRender(); }
				if (!d.done) { batch(d.processed); return; }
				setScanBusy(false);
				renderResults();
				if (scanHttpCard) { scanHttpCard.hidden = false; updateHttpUi(); }
			});
		})(0);
	}

	// --- HTTP "visit pages" scan: worker pool with live concurrency, a
	// front-side ETA, and auto-backoff when the host 5xx's / times out. ------
	function runHttpScan() {
		clearScanNotice();
		setScanBusy(true);
		scanAjax('lrob_cc_scan_targets', { types: JSON.stringify(scanTypeConfig()) }).then(function (json) {
			if (!json.success || !json.data || !json.data.urls || !json.data.urls.length) {
				setScanBusy(false);
				scanNotice((json && json.data && json.data.message) || (A.i18n.scanError || 'Scan failed.'), true);
				return;
			}
			httpPool(json.data.urls, false);
		});
	}

	function httpPool(urls, insecure) {
		var queue = urls.map(function (u) { return { url: u, tries: 0 }; });
		var total = queue.length, done = 0, active = 0, stopped = false, fatal = false;
		var times = [], sslErrors = 0, consecFail = 0;

		function conc() { return Math.max(1, parseInt(scanSpeed ? scanSpeed.value : 2, 10) || 2); }
		function eta() {
			if (!times.length) { return null; }
			var avg = times.reduce(function (a, b) { return a + b; }, 0) / times.length;
			return Math.ceil((queue.length + active) * avg / conc() / 1000);
		}
		function backoff() {
			if (scanSpeed && parseInt(scanSpeed.value, 10) > 1) {
				scanSpeed.value = String(Math.max(1, Math.floor(parseInt(scanSpeed.value, 10) / 2)));
				if (scanSpeedVal) { scanSpeedVal.textContent = scanSpeed.value; }
			}
			scanNotice(A.i18n.hostSlowdown || 'Slowing the scan down.', false);
		}
		function finish() {
			if (stopped) { return; }
			stopped = true; httpPump = null;
			setScanBusy(false);
			renderResults();
			clearScanNotice();
			if (fatal) { scanNotice(A.i18n.hostFailed || 'Host could not complete the scan.', true); }
			else if (sslErrors > 0 && !insecure) {
				scanNotice(A.i18n.sslFailed || 'Some pages had an SSL error.', true,
					A.i18n.sslRetry || 'Retry ignoring SSL', function () { setScanBusy(true); httpPool(urls, true); });
			}
		}
		function runOne(item) {
			active++;
			setProgress(done, total, eta(), pathOf(item.url));
			var t0 = (window.performance && performance.now) ? performance.now() : Date.now();
			scanAjax('lrob_cc_scan_url', { url: item.url, insecure: insecure ? 1 : 0 }, { timeout: 20000 }).then(function (json) {
				var dt = ((window.performance && performance.now) ? performance.now() : Date.now()) - t0;
				var overloaded = json.timedOut || json.httpStatus === 429 || json.httpStatus === 502 || json.httpStatus === 503 || json.httpStatus === 504;
				if (overloaded) {
					consecFail++;
					backoff();
					if (item.tries < 1) { item.tries++; queue.push(item); } // retry once, at the back
					if (consecFail >= 3 && conc() <= 1) { fatal = true; queue.length = 0; } // host can't cope even serial
				} else {
					consecFail = 0;
					times.push(dt); if (times.length > 8) { times.shift(); }
					if (json.success && json.data) {
						if (json.data.error === 'ssl') { sslErrors++; }
						var changed = false;
						(json.data.resources || []).forEach(function (r) { if (mergeRes(r)) { changed = true; } });
						if (mergeCookies(json.data.cookies || [])) { changed = true; }
						if (changed) { scheduleRender(); }
					}
				}
				done++;
				active--;
				setProgress(done, total, eta());
				pump();
			});
		}
		function pump() {
			if (fatal) { if (active === 0) { finish(); } return; }
			while (active < conc() && queue.length) { runOne(queue.shift()); }
			if (active === 0 && queue.length === 0) { finish(); }
		}
		httpPump = pump;
		pump();
	}

	if (scanDbBtn) { scanDbBtn.addEventListener('click', runDbScan); }
	if (scanHttpBtn) { scanHttpBtn.addEventListener('click', runHttpScan); }
	if (scanStartOver) {
		scanStartOver.addEventListener('click', function () {
			scanAgg = {}; scanCookies = [];
			clearScanNotice();
			if (scanHttpCard) { scanHttpCard.hidden = true; }
			renderResults();
		});
	}
	if (scanSpeed && scanSpeedVal) { scanSpeedVal.textContent = scanSpeed.value; }

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
			update();
		});
		logoFrame.open();
	});

	$(logoRemove).on('click', function () {
		if (logoInput) { logoInput.value = ''; }
		if (logoPreview) { logoPreview.src = ''; logoPreview.hidden = true; }
		this.hidden = true;
		update();
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
			'<input type="text" class="lrob-cc-inline-name" name="' + name + '[inline_scripts][' + i + '][name]" placeholder="' + (A.i18n.serviceName || 'Service name (shown to visitors)') + '" />' +
			'<textarea rows="3" class="large-text code" name="' + name + '[inline_scripts][' + i + '][code]"></textarea>' +
			'<button type="button" class="button lrob-cc-inline-remove">' + (A.i18n.removeRow || 'Remove') + '</button>';
		wrap.appendChild(row);
	});

	$(document).on('click', '.lrob-cc-inline-remove', function () {
		$(this).closest('.lrob-cc-inline-row').remove();
	});

	// --- Button order: drag to reorder ----------------------------------
	(function () {
		var list = document.getElementById('lrob-cc-btn-order');
		var hidden = document.getElementById('lrob-cc-btn-order-input');
		if (!list || !hidden) { return; }
		var dragged = null;
		function serialize() {
			hidden.value = [].map.call(list.querySelectorAll('[data-key]'), function (li) { return li.getAttribute('data-key'); }).join(',');
			update();
		}
		list.addEventListener('dragstart', function (e) {
			var li = e.target.closest('[data-key]');
			if (!li) { return; }
			dragged = li;
			li.classList.add('is-dragging');
			e.dataTransfer.effectAllowed = 'move';
		});
		list.addEventListener('dragend', function () {
			if (dragged) { dragged.classList.remove('is-dragging'); }
			dragged = null;
		});
		list.addEventListener('dragover', function (e) {
			e.preventDefault();
			var li = e.target.closest('[data-key]');
			if (!li || li === dragged || !dragged) { return; }
			var rect = li.getBoundingClientRect();
			list.insertBefore(dragged, (e.clientX - rect.left) > rect.width / 2 ? li.nextSibling : li);
		});
		list.addEventListener('drop', function (e) { e.preventDefault(); serialize(); });
	})();

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
