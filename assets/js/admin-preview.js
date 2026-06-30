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
		if (!tab || !document.querySelector('.lrob-cc-panel[data-panel="' + tab + '"]')) { tab = 'cookies'; }
		$('.lrob-cc-tabs .lrob-cc-tab').removeClass('is-active');
		$('.lrob-cc-tabs .lrob-cc-tab[data-tab="' + tab + '"]').addClass('is-active');
		$('.lrob-cc-panel').attr('hidden', true);
		$('.lrob-cc-panel[data-panel="' + tab + '"]').removeAttr('hidden');
		if (tab === 'banner' && typeof replayPreviewAnim === 'function') { replayPreviewAnim(); }
	}

	$('.lrob-cc-tabs .lrob-cc-tab').on('click', function (e) {
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

	// --- Master enable toggle: apply instantly (no full save needed) -----
	$('.lrob-cc-master-toggle input[data-field="enabled"]').on('change', function () {
		var on = this.checked ? 1 : 0;
		var saved = document.querySelector('.lrob-cc-master-saved');
		fetch(A.ajaxUrl, {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: 'action=lrob_cc_toggle_enabled&nonce=' + encodeURIComponent(A.toggleNonce || '') + '&enabled=' + on
		}).then(function () {
			if (saved) { saved.hidden = false; clearTimeout(saved._t); saved._t = setTimeout(function () { saved.hidden = true; }, 1500); }
		}).catch(function () {});
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
		var dReq = val('disclosure_required'), dOpt = val('disclosure_optional');
		var discOpts = document.getElementById('lrob-cc-disclosure-opts');
		if (discOpts) { discOpts.hidden = !(dReq || dOpt); }
		var discReqH = document.querySelector('.lrob-cc-disclosure-required-h');
		if (discReqH) { discReqH.hidden = !dReq; }
		var discOptH = document.querySelector('.lrob-cc-disclosure-optional-h');
		if (discOptH) { discOptH.hidden = !dOpt; }
		$('[data-theme-only="custom"]').toggle(val('theme') === 'custom');
		var revisitOpts = document.getElementById('lrob-cc-revisit-opts');
		if (revisitOpts) { revisitOpts.hidden = !val('revisit_button'); }
		var revisitRadius = document.getElementById('lrob-cc-revisit-radius');
		if (revisitRadius) { revisitRadius.hidden = val('revisit_shape') !== 'custom'; }
		var logoAlign = document.querySelector('.lrob-cc-logo-align');
		if (logoAlign) { logoAlign.hidden = val('logo_placement') === 'header'; }
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

	// Instant, client-side update of the real banner — colours, sizes, text,
	// position, alignment, animation vars, button visibility/order — so editing
	// feels snappy. Structural changes (categories/cookies) go through renderPreview.
	function txt(banner, sel, value, fieldName) {
		var el = banner.querySelector(sel);
		if (!el) { return; }
		el.textContent = value || (field(fieldName) || {}).placeholder || el.textContent;
	}
	function vis(banner, sel, on) {
		var el = banner.querySelector(sel);
		if (el) { el.style.display = on ? '' : 'none'; }
	}
	function applyLive() {
		var banner = previewStage.querySelector('#lrob-cc-preview');
		if (!banner) { return; }
		var s = A.scales || {};
		var setVar = function (n, v) { if (v || v === 0) { banner.style.setProperty('--lrob-cc-' + n, v); } };

		['bg', 'text', 'title', 'border', 'close', 'btn-bg', 'btn-text', 'btn-deny-bg', 'btn-deny-text', 'btn-hover-bg', 'btn-deny-hover-bg', 'revisit-bg', 'revisit-text'].forEach(function (k) { banner.style.removeProperty('--lrob-cc-' + k); });
		var theme = val('theme');
		if (A.palettes && A.palettes[theme]) {
			var pal = A.palettes[theme];
			Object.keys(pal).forEach(function (k) { setVar(k, pal[k]); });
		} else if (theme === 'custom') {
			var cmap = { 'bg': 'color_bg', 'text': 'color_text', 'title': 'color_title', 'border': 'color_border', 'btn-bg': 'color_btn_bg', 'btn-text': 'color_btn_text', 'btn-deny-bg': 'color_btn_deny_bg', 'btn-deny-text': 'color_btn_deny_text', 'btn-hover-bg': 'color_btn_hover_bg', 'btn-deny-hover-bg': 'color_btn_deny_hover_bg' };
			Object.keys(cmap).forEach(function (k) { setVar(k, val(cmap[k])); });
		}
		setVar('close', val('color_close'));
		setVar('revisit-bg', val('revisit_bg'));
		setVar('revisit-text', val('revisit_text_color'));

		// Logo alignment.
		var logoMap = { left: 'flex-start', center: 'center', right: 'flex-end' };
		banner.style.setProperty('--lrob-cc-logo-align', logoMap[val('logo_position')] || 'flex-start');

		// Manage-cookies bubble: shape/colours live on the stage (the bubble is a
		// sibling of the preview banner, appended on close).
		var rsh = val('revisit_shape');
		var rrad = rsh === 'square' ? '0px' : rsh === 'rounded' ? '10px' : rsh === 'pill' ? '999px'
			: (parseInt(val('revisit_radius'), 10) || 0) + 'px';
		if (previewStage) {
			previewStage.style.setProperty('--lrob-cc-revisit-radius', rrad);
			previewStage.style.setProperty('--lrob-cc-revisit-bg', val('revisit_bg') || '');
			previewStage.style.setProperty('--lrob-cc-revisit-text', val('revisit_text_color') || '');
		}

		if (s.width) { setVar('width', s.width[val('popup_size')] || s.width.small); }
		var dens = (s.density || {})[val('density')] || (s.density || {}).cozy;
		if (dens) { setVar('pad', dens.pad); setVar('gap', dens.gap); }
		var font = (s.font || {})[val('font_size')] || (s.font || {}).medium;
		if (font) { setVar('font-size', font.font); setVar('title-size', font.title); }
		if (s.radius) { setVar('radius', s.radius[val('shape')] || s.radius.rounded); }
		setVar('logo-height', (parseInt(val('logo_height'), 10) || 36) + 'px');
		setVar('dim', (parseInt(val('backdrop_dim'), 10) || 0) / 100);
		setVar('blur', (parseInt(val('backdrop_blur'), 10) || 0) + 'px');

		var presets = { snug: '12px', 'default': '24px', spacious: '44px' };
		var op = val('offset_preset'), ox, oy;
		if (presets[op]) { ox = oy = presets[op]; }
		else { var u = val('offset_unit') || 'px'; ox = (parseInt(val('offset_x'), 10) || 0) + u; oy = (parseInt(val('offset_y'), 10) || 0) + u; }
		setVar('offset-x', ox); setVar('offset-y', oy);

		setVar('anim-duration', (parseInt(val('anim_speed'), 10) || 0) + 'ms');
		banner.style.setProperty('--lrob-cc-anim-opacity', val('anim_fade') ? '0' : '1');
		var mv = val('anim_move'), ax = '0', ay = '0', asc = '1', dd = '110%';
		if (mv === 'slide') { var dir = val('anim_direction'); if (dir === 'top') { ay = '-' + dd; } else if (dir === 'left') { ax = '-' + dd; } else if (dir === 'right') { ax = dd; } else { ay = dd; } }
		else if (mv === 'zoom') { asc = '0.6'; }
		banner.style.setProperty('--lrob-cc-anim-x', ax);
		banner.style.setProperty('--lrob-cc-anim-y', ay);
		banner.style.setProperty('--lrob-cc-anim-scale', asc);

		banner.style.setProperty('--lrob-cc-align-title', val('align_title') || 'left');
		banner.style.setProperty('--lrob-cc-align-text', val('align_text') || 'left');
		banner.style.setProperty('--lrob-cc-align-footer', val('align_footer') || 'center');
		var bmap = { left: 'flex-start', center: 'center', right: 'flex-end' };
		banner.style.setProperty('--lrob-cc-align-buttons', bmap[val('align_buttons')] || 'flex-start');

		['top-left', 'top', 'top-right', 'center', 'bottom-left', 'bottom', 'bottom-right'].forEach(function (p) { banner.classList.remove('lrob-cc-pos-' + p); });
		banner.classList.add('lrob-cc-pos-' + (val('position') || 'bottom-right'));
		banner.classList.toggle('lrob-cc-bd-dim', val('backdrop') === 'dim');
		banner.classList.toggle('lrob-cc-bd-blur', val('backdrop') === 'blur');

		txt(banner, '.lrob-cc-title', val('text_header'), 'text_header');
		var msg = banner.querySelector('.lrob-cc-message');
		if (msg) { var mt = val('text_message') || (field('text_message') || {}).placeholder || ''; msg.innerHTML = escapeHtml(mt).replace(/\n/g, '<br>'); }
		txt(banner, '.lrob-cc-btn-accept', val('text_accept'), 'text_accept');
		txt(banner, '.lrob-cc-btn-deny', val('text_deny'), 'text_deny');
		txt(banner, '.lrob-cc-btn-save', val('text_save'), 'text_save');
		txt(banner, '.lrob-cc-btn-customize', val('text_customize'), 'text_customize');

		var logoEl = banner.querySelector('.lrob-cc-logo');
		if (logoEl) { var lu = val('logo'); logoEl.src = lu || ''; logoEl.style.display = lu ? '' : 'none'; }

		var collapsed = val('categories_collapsed');
		vis(banner, '.lrob-cc-btn-accept', val('show_accept'));
		vis(banner, '.lrob-cc-btn-deny', val('show_deny') && val('deny_style') === 'button');
		vis(banner, '.lrob-cc-btn-customize', collapsed && val('show_customize'));

		var order = (val('button_order') || 'accept,deny,customize').split(',');
		var bw = banner.querySelector('.lrob-cc-buttons');
		if (bw) { order.forEach(function (k) { var el = bw.querySelector('.lrob-cc-btn-' + k.trim()); if (el) { bw.appendChild(el); } }); }

		applyPreviewBackdrop();
	}
	function replayInner() {
		previewStage.classList.add('is-replaying');
		var inner = previewStage.querySelector('#lrob-cc-preview .lrob-cc-inner');
		if (inner) { inner.style.animation = 'none'; void inner.offsetWidth; inner.style.animation = ''; }
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
		applyLive();
	}
	// Structural changes (categories / cookies / layout that adds or removes
	// elements) need a server re-render; everything else updates instantly.
	var STRUCTURAL = /\[(categories|block_rules|inline_scripts|disclosure_required|disclosure_optional|disclosure_open|show_sources|cat_desc_overrides|rules_mode|footer_links|watermark|deny_style|deny_link_position|continue_align|continue_arrow|categories_collapsed|logo_placement)\]/;
	$(document).on('input change', 'form[action="options.php"] :input', function () {
		adminFieldToggles();
		if (STRUCTURAL.test(this.name || '')) { schedulePreview(); }
		else { applyLive(); }
	});
	// Animation settings replay the entrance animation in place (no re-render).
	$(document).on('input change', '[data-field^="anim_"], [name*="[anim_"]', replayInner);
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

	// Duration widget: value × unit (days/months/years) → canonical days field.
	$(document).on('input change', '.lrob-cc-duration [data-dur-value], .lrob-cc-duration [data-dur-unit]', function () {
		var span = this.closest('.lrob-cc-duration');
		if (!span) { return; }
		var v = parseFloat((span.querySelector('[data-dur-value]') || {}).value) || 0;
		var unit = parseInt((span.querySelector('[data-dur-unit]') || {}).value, 10) || 1;
		var hidden = span.querySelector('[data-dur-days]');
		if (hidden) { hidden.value = Math.round(v * unit); $(hidden).trigger('change'); }
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

	// Warn when proof retention is shorter than the longest consent lifetime.
	function checkRetention() {
		var warn = document.getElementById('lrob-cc-retention-warn');
		if (!warn) { return; }
		var a = parseInt((document.querySelector('[name="' + A.optionName + '[accept_days]"]') || {}).value, 10) || 0;
		var d = parseInt((document.querySelector('[name="' + A.optionName + '[deny_days]"]') || {}).value, 10) || 0;
		var consent = Math.max(a, d);
		var retain = parseInt((document.querySelector('[name="' + A.optionName + '[log_retention_days]"]') || {}).value, 10);
		warn.hidden = !(retain > 0 && consent > 0 && retain < consent);
	}
	$(document).on('input change', '[name="' + A.optionName + '[accept_days]"],[name="' + A.optionName + '[deny_days]"],[name="' + A.optionName + '[log_retention_days]"]', checkRetention);
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

	// Highlight exactly one button in a preset row.
	function markActivePreset(btn) {
		var row = btn.closest('.lrob-cc-preset-row');
		if (!row) { return; }
		row.querySelectorAll('.lrob-cc-preset').forEach(function (b) { b.classList.remove('is-active'); });
		btn.classList.add('is-active');
	}
	// Reflect "custom" for a group: clear all, then highlight its Custom button.
	function setPresetCustom(group) {
		var row = document.querySelector('.lrob-cc-preset-row[data-preset-group="' + group + '"]');
		if (!row) { return; }
		row.querySelectorAll('.lrob-cc-preset').forEach(function (b) { b.classList.remove('is-active'); });
		var custom = row.querySelector('.lrob-cc-preset-custom');
		if (custom) { custom.classList.add('is-active'); }
	}

	$('.lrob-cc-preset-row[data-preset-group="text"] .lrob-cc-preset').on('click', function () {
		var id = this.getAttribute('data-preset-id');
		markActivePreset(this);
		if (id === 'custom') { setField('text_preset', 'custom'); return; }
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
		setField('text_preset', 'custom');
		setPresetCustom('text');
	});

	$('.lrob-cc-preset-row[data-preset-group="colors"] .lrob-cc-preset').on('click', function () {
		var id = this.getAttribute('data-preset-id');
		var preset = (A.colorPresets || []).filter(function (p) { return p.id === id; })[0];
		if (preset && preset.options) { applyOptions(preset.options); }
		markActivePreset(this);
	});

	// Layout presets: one click sets position/size/spacing/corners/backdrop/anim.
	$('.lrob-cc-preset-row[data-preset-group="layout"] .lrob-cc-preset').on('click', function () {
		var id = this.getAttribute('data-preset-id'), btn = this;
		if (id === 'custom') { setField('layout_preset', 'custom'); markActivePreset(btn); return; }
		var preset = (A.layoutPresets || []).filter(function (p) { return p.id === id; })[0];
		if (preset && preset.options) { applyOptions(preset.options); }
		setField('layout_preset', id);
		markActivePreset(btn); // after applyOptions so the change-handler below doesn't re-mark custom
		schedulePreview(true); // backdrop/animation are structural in the preview
	});

	// Touching any layout control drops the layout preset back to "custom".
	$(document).on('change', '[data-field="position"],[data-field="popup_size"],[data-field="density"],[data-field="font_size"],[data-field="shape"],[data-field="backdrop"],[data-field="offset_preset"],[data-field^="anim_"],[data-field^="align_"]', function () {
		var el = document.querySelector('[data-field="layout_preset"]');
		if (el) { el.value = 'custom'; }
		setPresetCustom('layout');
	});

	// Initial highlight from the saved preset ids.
	(function () {
		[['layout', (document.querySelector('[data-field="layout_preset"]') || {}).value],
			['text', (document.getElementById('lrob-cc-text-preset') || {}).value]].forEach(function (pair) {
			if (!pair[1]) { return; }
			var b = document.querySelector('.lrob-cc-preset-row[data-preset-group="' + pair[0] + '"] .lrob-cc-preset[data-preset-id="' + pair[1] + '"]');
			if (b) { b.classList.add('is-active'); }
		});
	})();

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
		schedulePreview(); // rules drive which categories/listings the banner shows
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

	// A "?" tooltip matching the server-rendered $help markup (for JS-built UI).
	function tipHtml(text) {
		return '<span class="lrob-cc-tip" tabindex="0" role="note" aria-label="' + escapeHtml(A.i18n.help || 'Help') +
			'"><span class="lrob-cc-tip-i" aria-hidden="true">?</span><span class="lrob-cc-tip-bubble">' + escapeHtml(text) + '</span></span>';
	}

	// --- Site scan: DB-first, results accumulate; optional parallel HTTP deep scan ---
	var scanResults = document.getElementById('lrob-cc-scan-results');
	var scanSummaryEl = document.getElementById('lrob-cc-scan-summary');
	var cookieResultsEl = document.getElementById('lrob-cc-cookie-results');
	var scanStartedAt = 0;
	var httpDoneCb = null; // chains the real-cookie scan after the page-visit pass
	function nowTs() { return (window.performance && performance.now) ? performance.now() : Date.now(); }
	function scanSummary(pages) {
		if (!scanSummaryEl) { return; }
		var secs = scanStartedAt ? Math.max(1, Math.round((nowTs() - scanStartedAt) / 1000)) : 0;
		/* translators: %1$d: pages scanned, %2$d: seconds taken. */
		var tpl = (A.i18n.scannedSummary || 'Scanned %1$d pages in %2$ds.');
		scanSummaryEl.textContent = tpl.replace('%1$d', pages).replace('%2$d', secs);
		scanSummaryEl.hidden = false;
	}

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
	var scanBtn = document.getElementById('lrob-cc-scan-btn');
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

	// Shown once if a page takes >1s to fetch — slow host, points to LRob.
	function slowHostNotice() {
		if (document.getElementById('lrob-cc-scan-slow') || !scanResults) { return; }
		var box = document.createElement('div');
		box.id = 'lrob-cc-scan-slow';
		box.className = 'lrob-cc-hint lrob-cc-hint-warning';
		box.appendChild(document.createTextNode((A.i18n.slowHost || 'Scanning is slow (over 1s per page). A faster web host would help. ') + ' '));
		var a = document.createElement('a');
		a.href = 'https://www.lrob.fr/'; a.target = '_blank'; a.rel = 'noopener';
		a.textContent = A.i18n.slowHostLink || 'See LRob hosting';
		box.appendChild(a);
		scanResults.parentNode.insertBefore(box, scanResults);
	}

	// Declare the site's own WordPress cookies (shared by the button + tickbox).
	function declareWpCookies() {
		toStructuredMode();
		(A.wpCookies || []).forEach(function (c) {
			if (!ruleRowByPattern(c.pattern)) { addRuleRow(c.pattern, c.category, c.service); }
		});
		serializeRules();
		update();
	}

	function pathOf(u) { try { var x = new URL(u, location.href); return x.pathname + x.search; } catch (e) { return u; } }

	function setScanBusy(on) {
		if (scanBtn) { scanBtn.disabled = on; }
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
			var count = parseInt(row.getAttribute('data-count'), 10) || 0;
			var lim = Math.max(0, parseInt((row.querySelector('.lrob-cc-scan-type-limit') || {}).value, 10) || 0);
			cfg.push({
				type: row.getAttribute('data-type'),
				limit: lim >= count ? 0 : lim, // slider at max = all
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

	// Limit slider: show "all" at the top, and hide the priority dropdown when
	// scanning everything (order is irrelevant then).
	function syncLimitRow(slider) {
		var row = slider.closest('.lrob-cc-scan-type');
		if (!row) { return; }
		var max = parseInt(slider.max, 10) || 0, v = parseInt(slider.value, 10) || 0;
		var valEl = row.querySelector('.lrob-cc-scan-limit-val');
		if (valEl) { valEl.textContent = (v >= max) ? (A.i18n.all || 'all') : v; }
		var orderCell = row.querySelector('.lrob-cc-scan-order-cell');
		if (orderCell) { orderCell.hidden = (v >= max); }
	}
	$(document).on('input', '.lrob-cc-scan-type-limit', function () { syncLimitRow(this); });
	document.querySelectorAll('.lrob-cc-scan-type-limit').forEach(syncLimitRow);

	if (scanSpeed) {
		scanSpeed.addEventListener('input', function () {
			if (scanSpeedVal) { scanSpeedVal.textContent = scanSpeed.value; }
			if (httpPump) { httpPump(); } // ramp up live mid-scan
		});
	}

	// --- Database scan (fast, batched). onDone chains the page-visit pass. ----
	function runDbScan(onDone) {
		(function batch(offset) {
			scanAjax('lrob_cc_scan_db', { offset: offset }).then(function (json) {
				if (!json.success || !json.data) {
					setScanBusy(false);
					scanNotice((json && json.data && json.data.message) || (A.i18n.scanError || 'Scan failed.'), true);
					return;
				}
				var d = json.data, changed = false;
				(d.resources || []).forEach(function (r) { if (mergeRes(r)) { changed = true; } });
				setProgress(Math.min(d.processed || 0, d.total || 0), d.total || 0, null, A.i18n.scanPhaseDb || 'Reading your content…');
				if (changed) { scheduleRender(); }
				if (!d.done) { batch(d.processed); return; }
				if (onDone) { onDone(); } else { setScanBusy(false); renderResults(); }
			});
		})(0);
	}

	// Full scan: content (DB) first, then an anonymous page visit — both passes,
	// every time, so nothing relies on the user remembering to run a second step.
	function runFullScan() {
		clearScanNotice();
		setScanBusy(true);
		if (scanSummaryEl) { scanSummaryEl.hidden = true; }
		if (cookieResultsEl) { cookieResultsEl.innerHTML = ''; }
		cookieFound = {};
		scanStartedAt = nowTs();
		runDbScan(function () {
			scanAjax('lrob_cc_scan_targets', { types: JSON.stringify(scanTypeConfig()) }).then(function (json) {
				var urls = (json.success && json.data && json.data.urls) || [];
				if (!urls.length) { setScanBusy(false); renderResults(); scanSummary(0); runCookieScan(); return; }
				httpDoneCb = runCookieScan;
				httpPool(urls, false); // its finish() clears busy + renders + chains the cookie scan
			});
		});
	}

	// --- HTTP "visit pages" scan: worker pool with live concurrency, a
	// front-side ETA, and auto-backoff when the host 5xx's / times out. ------
	function httpPool(urls, insecure) {
		var queue = urls.map(function (u) { return { url: u, tries: 0 }; });
		var total = queue.length, done = 0, active = 0, stopped = false, fatal = false;
		var times = [], sslErrors = 0, consecFail = 0, slowShown = false, poolStart = nowTs();

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
			scanSummary(done);
			if (httpDoneCb) { var _cb = httpDoneCb; httpDoneCb = null; _cb(); }
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
				// Throughput-based: warn only if the real wall-clock per page is slow
				// (accounts for concurrency — 200 pages in 36s is NOT slow).
				if (!slowShown && done >= 5 && (nowTs() - poolStart) / done > 1200) { slowShown = true; slowHostNotice(); }
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

	if (scanBtn) { scanBtn.addEventListener('click', runFullScan); }
	if (scanStartOver) {
		scanStartOver.addEventListener('click', function () {
			scanAgg = {}; scanCookies = []; cookieFound = {};
			clearScanNotice();
			if (cookieResultsEl) { cookieResultsEl.innerHTML = ''; }
			renderResults();
		});
	}
	if (scanSpeed && scanSpeedVal) { scanSpeedVal.textContent = scanSpeed.value; }
	if (scanTotalEl) { updateHttpUi(); }

	// --- Phase 3: real-browser cookie scan ------------------------------
	// Loads a minimal set of the site's own pages in hidden, same-origin iframes
	// (our blocking bypassed via nonce) and reads document.cookie — the only way
	// to see JS-set cookies. Each name is classified against the known-cookie map.
	var cookieFound = {};

	// Greedy set-cover: the fewest pages that together cover every detected host.
	function cookieScanPages() {
		var hostPages = {};
		aggArray().forEach(function (r) {
			var host = r.host || r.pattern;
			(r.pages || []).forEach(function (p) {
				hostPages[host] = hostPages[host] || [];
				if (hostPages[host].indexOf(p) === -1) { hostPages[host].push(p); }
			});
		});
		var uncovered = Object.keys(hostPages), chosen = [], guard = 0;
		while (uncovered.length && chosen.length < 12 && guard++ < 60) {
			var score = {};
			uncovered.forEach(function (host) { hostPages[host].forEach(function (p) { score[p] = (score[p] || 0) + 1; }); });
			var best = null, bestN = 0;
			Object.keys(score).forEach(function (p) { if (score[p] > bestN) { bestN = score[p]; best = p; } });
			if (!best) { break; }
			chosen.push(best);
			uncovered = uncovered.filter(function (host) { return hostPages[host].indexOf(best) === -1; });
		}
		var home = A.homeUrl || (location.origin + '/');
		if (chosen.indexOf(home) === -1) { chosen.unshift(home); }
		return chosen.slice(0, 12);
	}

	function withScanParam(url) {
		return url + (url.indexOf('?') === -1 ? '?' : '&') + 'lrob_cc_scan=' + encodeURIComponent(A.cookieScanNonce || '');
	}

	function readCookieNames(doc) {
		var out = [];
		try {
			(doc.cookie || '').split(';').forEach(function (pair) { var n = pair.split('=')[0].trim(); if (n) { out.push(n); } });
		} catch (e) {}
		return out;
	}

	function classifyCookie(name) {
		var best = null, bestLen = -1;
		(A.knownCookies || []).forEach(function (c) {
			var hit = c.prefix ? name.indexOf(c.match) === 0 : name.toLowerCase() === String(c.match).toLowerCase();
			if (hit && String(c.match).length > bestLen) { best = c; bestLen = String(c.match).length; }
		});
		return best;
	}

	function ingestCookieNames(names) {
		names.forEach(function (name) {
			if (cookieFound[name]) { return; }
			var m = classifyCookie(name);
			cookieFound[name] = { name: name, service: m ? m.service : '', category: m ? m.category : '', party: m ? m.party : 'first', desc: m ? m.desc : '', known: !!m };
		});
	}

	function loadPageAndReadCookies(url) {
		return new Promise(function (resolve) {
			var iframe = document.createElement('iframe');
			iframe.style.cssText = 'position:absolute;width:1024px;height:768px;left:-9999px;top:-9999px;border:0;';
			var settled = false;
			var timer = setTimeout(finish, 9000);
			function finish() {
				if (settled) { return; }
				settled = true; clearTimeout(timer);
				try { ingestCookieNames(readCookieNames(iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document))); } catch (e) {}
				if (iframe.parentNode) { iframe.parentNode.removeChild(iframe); }
				resolve();
			}
			iframe.addEventListener('load', function () {
				setTimeout(function () {
					try { var w = iframe.contentWindow; if (w) { w.scrollTo(0, 600); w.dispatchEvent(new Event('scroll')); w.dispatchEvent(new Event('mousemove')); } } catch (e) {}
					setTimeout(finish, 1800); // re-read after interaction nudge
				}, 1500);
			});
			iframe.src = withScanParam(url);
			document.body.appendChild(iframe);
		});
	}

	function runCookieScan() {
		setScanBusy(true);
		if (scanProgressText) { scanProgressText.textContent = ''; }
		ingestCookieNames(readCookieNames(document)); // baseline: cookies already in this same-origin page
		var pages = cookieScanPages(), i = 0;
		(function next() {
			if (i >= pages.length) { setScanBusy(false); renderCookieResults(); return; }
			if (scanCurrent) { scanCurrent.textContent = (A.i18n.scanPhaseCookies || '') + ' ' + (i + 1) + '/' + pages.length; }
			loadPageAndReadCookies(pages[i++]).then(function () { renderCookieResults(); next(); });
		})();
	}

	function renderCookieResults() {
		if (!cookieResultsEl) { return; }
		var declared = {};
		document.querySelectorAll('#lrob-cc-cookies input[name*="[name]"]').forEach(function (inp) { if (inp.value) { declared[inp.value] = true; } });
		var cmp = A.cmpActive || [];
		var warn = cmp.length
			? '<div class="lrob-cc-hint lrob-cc-hint-warning">' + escapeHtml((A.i18n.cmpWarn || 'Another consent plugin is active (%s).').replace('%s', cmp.join(', '))) + '</div>'
			: '<p class="description">' + escapeHtml(A.i18n.cmpWarnGeneric || '') + '</p>';
		var names = Object.keys(cookieFound);
		if (!names.length) {
			cookieResultsEl.innerHTML = warn + '<p class="description">' + escapeHtml(A.i18n.cookiesNone || '') + '</p>';
			return;
		}
		var rows = names.map(function (name) {
			var c = cookieFound[name], added = !!declared[name];
			return '<tr' + (added ? ' class="lrob-cc-scan-done"' : '') + '>' +
				'<td><code>' + escapeHtml(name) + '</code></td>' +
				'<td>' + escapeHtml(c.service || '') + '</td>' +
				'<td>' + escapeHtml(c.party === 'third' ? (A.i18n.partyThird || 'external') : (A.i18n.partyFirst || 'this site')) + '</td>' +
				'<td>' + (c.known ? '<span class="lrob-cc-badge is-known">' + escapeHtml(c.category || '') + '</span>' : '<span class="lrob-cc-badge">' + escapeHtml(A.i18n.cookieUnknown || 'review') + '</span>') + '</td>' +
				'<td>' + (added ? '<span class="lrob-cc-badge is-added">' + escapeHtml(A.i18n.cookieAdded || 'declared') + '</span>' : '<button type="button" class="button button-small lrob-cc-cookie-declare" data-name="' + escapeHtml(name) + '">+</button>') + '</td>' +
				'</tr>';
		}).join('');
		cookieResultsEl.innerHTML = warn +
			'<p class="lrob-cc-field-label">' + escapeHtml(A.i18n.cookiesFound || 'Cookies actually set:') + ' <button type="button" class="button button-small" id="lrob-cc-cookie-declare-all">' + escapeHtml(A.i18n.cookieAddAll || 'Declare all') + '</button></p>' +
			'<table class="widefat striped lrob-cc-cookie-table"><thead><tr><th>cookie</th><th>service</th><th>set by</th><th>category</th><th></th></tr></thead><tbody>' + rows + '</tbody></table>';
	}

	// --- Declared-cookies repeater --------------------------------------
	var cookiesWrap = document.getElementById('lrob-cc-cookies');
	var cookieTpl = document.getElementById('lrob-cc-cookie-template');

	function cookieRowExists(name) {
		var found = false;
		document.querySelectorAll('#lrob-cc-cookies input[name*="[name]"]').forEach(function (inp) { if (inp.value === name) { found = true; } });
		return found;
	}
	function declareCookie(c) {
		if (!c || !cookiesWrap || !cookieTpl || (c.name && cookieRowExists(c.name))) { return; }
		var base = cookiesWrap.getAttribute('data-name');
		var i = Date.now() + Math.floor(Math.random() * 1000);
		var node = cookieTpl.content.firstElementChild.cloneNode(true);
		var map = { '.lrob-cc-ck-name': 'name', '.lrob-cc-ck-party': 'party', '.lrob-cc-ck-service': 'service', '.lrob-cc-ck-category': 'category', '.lrob-cc-ck-desc': 'desc' };
		Object.keys(map).forEach(function (sel) {
			var el = node.querySelector(sel); if (!el) { return; }
			el.setAttribute('name', base + '[cookies][' + i + '][' + map[sel] + ']');
			var v = c[map[sel]];
			if (v !== undefined && v !== '') { el.value = v; }
		});
		cookiesWrap.appendChild(node);
	}
	$('#lrob-cc-cookie-add').on('click', function () { declareCookie({ name: '', party: 'first' }); });
	$(document).on('click', '.lrob-cc-cookie-remove', function () { $(this).closest('.lrob-cc-cookie-row').remove(); });
	$(document).on('click', '.lrob-cc-cookie-declare', function () { declareCookie(cookieFound[this.getAttribute('data-name')]); renderCookieResults(); });
	$(document).on('click', '#lrob-cc-cookie-declare-all', function () { Object.keys(cookieFound).forEach(function (n) { declareCookie(cookieFound[n]); }); renderCookieResults(); });

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

	// --- Declare the site's own WordPress cookies (functional, never blocked) ---
	$('#lrob-cc-add-wp-cookies').on('click', declareWpCookies);

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
		// Live preview pane (the real banner) inside the wizard.
		function wizPreview(box) {
			if (!box) { return; }
			box.innerHTML = '<p class="description">' + escapeHtml(A.i18n.loading || 'Loading…') + '</p>';
			var form = document.querySelector('form[action="options.php"]');
			if (!form) { return; }
			var reqBody = $(form).serialize() + '&action=lrob_cc_preview&nonce=' + encodeURIComponent(A.previewNonce || '');
			fetch(A.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: reqBody })
				.then(function (r) { return r.json(); }).then(function (json) {
					if (json && json.success && json.data) {
						box.innerHTML = '<style>' + (json.data.css || '') + '</style><div class="lrob-cc-preview-stage lrob-cc-wiz-stage">' + (json.data.html || '') + '</div>';
						var bn = box.querySelector('#lrob-cc-preview'); if (bn) { bn.hidden = false; }
					}
				}).catch(function () {});
		}

		// Append a live preview pane to a step and return its refresh function.
		function attachPreview(b) {
			var box = document.createElement('div');
			box.className = 'lrob-cc-wiz-preview';
			b.appendChild(box);
			var fn = function () { wizPreview(box); };
			fn();
			return fn;
		}

		// Duration control (value + unit) for a *_days field, reading its current value.
		function wizDuration(key, label, tip) {
			var days = parseInt((document.querySelector('[name="' + A.optionName + '[' + key + ']"]') || {}).value, 10) || 0;
			var unit = 1, disp = days;
			if (days > 0 && days % 365 === 0) { unit = 365; disp = days / 365; }
			else if (days > 0 && days % 30 === 0) { unit = 30; disp = days / 30; }
			var units = [[1, A.i18n.durDays || 'days'], [30, A.i18n.durMonths || 'months'], [365, A.i18n.durYears || 'years']];
			var opts = units.map(function (u) { return '<option value="' + u[0] + '"' + (u[0] === unit ? ' selected' : '') + '>' + escapeHtml(u[1]) + '</option>'; }).join('');
			return '<p><label>' + escapeHtml(label) + ' ' + tipHtml(tip) +
				' <input type="number" min="0" class="small-text" data-wizdur-value data-wizdur-key="' + key + '" value="' + disp + '" />' +
				' <select data-wizdur-unit data-wizdur-key="' + key + '">' + opts + '</select></label></p>';
		}
		function wizDurApply(b, key) {
			var v = parseFloat((b.querySelector('[data-wizdur-value][data-wizdur-key="' + key + '"]') || {}).value) || 0;
			var u = parseInt((b.querySelector('[data-wizdur-unit][data-wizdur-key="' + key + '"]') || {}).value, 10) || 1;
			setField(key, Math.round(v * u));
		}

		// 1. Layout — preset + position. A preset re-renders the step so the
		// position radio stays in sync (no more incoherent selections).
		screens.push({
			title: A.i18n.wizStepLayout || 'Choose a layout',
			render: function (b) {
				var html = '<p class="lrob-cc-field-label">' + escapeHtml(A.i18n.wizLayout || 'Layout') + '</p><div class="lrob-cc-preset-row">';
				(A.layoutPresets || []).forEach(function (p) {
					html += '<button type="button" class="button lrob-cc-wiz-layout' + (val('layout_preset') === p.id ? ' is-active' : '') + '" data-id="' + escapeHtml(p.id) + '">' + escapeHtml(p.label) + '</button>';
				});
				html += '</div><p class="lrob-cc-field-label">' + escapeHtml((WS.look && WS.look.positionLabel) || 'Position') + '</p>' + radioGroup('position', (WS.look && WS.look.positions) || [], val('position'));
				b.innerHTML = html;
				var refresh = attachPreview(b);
				b.querySelectorAll('.lrob-cc-wiz-layout').forEach(function (btn) {
					btn.addEventListener('click', function () {
						var p = (A.layoutPresets || []).filter(function (x) { return x.id === btn.getAttribute('data-id'); })[0];
						if (p) { applyOptions(p.options); setField('layout_preset', p.id); }
						render(); // re-render: keeps the position radio coherent with the preset
					});
				});
				bindRadio(b, 'position', function (v) { setField('position', v); setField('layout_preset', 'custom'); refresh(); });
			},
			apply: function () {}
		});

		// 2. Colours — theme palette.
		screens.push({
			title: A.i18n.wizStepColours || 'Pick your colours',
			render: function (b) {
				b.innerHTML = '<p class="lrob-cc-field-label">' + escapeHtml((WS.look && WS.look.colorsLabel) || 'Colors') + '</p>' + radioGroup('theme', (WS.look && WS.look.colors) || [], val('theme'));
				var refresh = attachPreview(b);
				bindRadio(b, 'theme', function (v) { setField('theme', v); refresh(); });
			},
			apply: function () {}
		});

		// 3. Wording — text preset. Detects existing custom wording and pre-selects
		// a "keep my wording" option so a re-run doesn't silently overwrite it.
		if ((A.texts || []).length) {
			screens.push({
				title: (WS.tone && WS.tone.question) || 'Choose your wording',
				render: function (b) {
					var hasCustomText = !!(val('text_header') || val('text_message') || val('text_accept') || val('text_deny') || val('text_save'));
					var current = val('text_preset') || (hasCustomText ? 'custom' : '');
					var choices = (A.texts || []).map(function (p) { return { v: p.id, l: p.label }; });
					if (hasCustomText || current === 'custom') { choices.unshift({ v: 'custom', l: A.i18n.wizKeepWording || 'Keep my current wording' }); }
					b.innerHTML = radioGroup('tone', choices, current);
					var refresh = attachPreview(b);
					bindRadio(b, 'tone', function (v) {
						if (v === 'custom') { setField('text_preset', 'custom'); refresh(); return; }
						var p = (A.texts || []).filter(function (x) { return x.id === v; })[0];
						if (p) { ['header', 'message', 'accept', 'deny', 'save'].forEach(function (k) { if (p[k] !== undefined) { setField('text_' + k, p[k]); } }); setField('text_preset', v); }
						refresh();
					});
				},
				apply: function () {}
			});
		}

		// 2. Scan — run the real database scan and tick what to block.
		var scanFound = {};
		screens.push({
			title: A.i18n.wizScanTitle || 'Scan your site for trackers',
			hint: A.i18n.wizScanHint || 'We look through your content for third-party scripts and embeds you may need to block.',
			render: function (b) {
				b.innerHTML =
					'<p><button type="button" class="button button-primary" data-wiz-scan>' + escapeHtml(A.i18n.wizScanBtn || 'Scan my site') + '</button> <span data-wiz-scan-status class="description"></span></p>' +
					'<p class="lrob-cc-scan-speed-wrap"><label>' + escapeHtml(A.i18n.scanSpeed || 'Scan speed') + ' <input type="range" data-wiz-speed min="1" max="8" value="2" step="1" /></label> <span data-wiz-speed-val>2</span> ' + escapeHtml(A.i18n.scanSpeedUnit || 'pages at once') + ' ' + tipHtml(A.i18n.scanSpeedTip || 'How many pages to fetch at once. The scan slows itself down if your host cannot keep up.') + '</p>' +
					'<div class="lrob-cc-wiz-progress" data-wiz-progress hidden><progress data-wiz-bar max="100" value="0"></progress><span data-wiz-eta class="description"></span></div>' +
					'<div data-wiz-scan-results></div>';
				var status = b.querySelector('[data-wiz-scan-status]');
				var resEl = b.querySelector('[data-wiz-scan-results]');
				var speed = b.querySelector('[data-wiz-speed]');
				var speedVal = b.querySelector('[data-wiz-speed-val]');
				var progBox = b.querySelector('[data-wiz-progress]');
				var bar = b.querySelector('[data-wiz-bar]');
				var etaEl = b.querySelector('[data-wiz-eta]');
				if (speed && speedVal) { speed.addEventListener('input', function () { speedVal.textContent = speed.value; }); }
				function nowMs() { return (window.performance && performance.now) ? performance.now() : Date.now(); }
				function ingest(list) { (list || []).forEach(function (r) { if (!scanFound[r.pattern]) { scanFound[r.pattern] = r; serviceSel[r.pattern] = r; } }); }
				function renderHits() {
					var keys = Object.keys(scanFound);
					if (!keys.length) { resEl.innerHTML = '<p class="description">' + escapeHtml(A.i18n.noneFound || 'No third-party resources found.') + '</p>'; return; }
					var html = '<p class="lrob-cc-field-label">' + escapeHtml(A.i18n.wizScanFound || 'Found — tick what to block:') + '</p><div class="lrob-cc-wiz-options">';
					keys.forEach(function (p) {
						var r = scanFound[p];
						html += '<label class="lrob-cc-check"><input type="checkbox" class="lrob-cc-wiz-svc" data-pattern="' + escapeHtml(p) + '"' + (serviceSel[p] ? ' checked' : '') + '/> ' +
							escapeHtml(r.service || r.host || p) + ' <span class="lrob-cc-badge' + (r.known ? ' is-known' : '') + '">' + escapeHtml(r.category || '') + '</span></label>';
					});
					resEl.innerHTML = html + '</div>';
					resEl.querySelectorAll('.lrob-cc-wiz-svc').forEach(function (cb) {
						cb.addEventListener('change', function () {
							var p = cb.getAttribute('data-pattern');
							if (cb.checked) { serviceSel[p] = scanFound[p]; } else { delete serviceSel[p]; }
						});
					});
				}
				if (Object.keys(scanFound).length) { renderHits(); }
				b.querySelector('[data-wiz-scan]').addEventListener('click', function () {
					var btn = this; btn.disabled = true; status.textContent = A.i18n.scanPhaseDb || 'Reading your content…';
					// Phase 1: content (DB).
					(function batch(offset) {
						scanAjax('lrob_cc_scan_db', { offset: offset }).then(function (json) {
							if (!json.success || !json.data) { status.textContent = A.i18n.scanError || 'Scan failed.'; btn.disabled = false; return; }
							ingest(json.data.resources); renderHits();
							if (!json.data.done) { batch(json.data.processed); return; }
							// Phase 2: anonymous page visits (solid default: scan everything).
							status.textContent = A.i18n.scanPhasePages || 'Visiting your pages…';
							scanAjax('lrob_cc_scan_targets', { types: JSON.stringify(scanTypeConfig()) }).then(function (tj) {
								var urls = (tj.success && tj.data && tj.data.urls) || [];
								if (!urls.length) { status.textContent = A.i18n.scanComplete || 'Scan complete.'; btn.disabled = false; return; }
								var queue = urls.slice(), total = urls.length, done = 0, active = 0, times = [];
								if (progBox) { progBox.hidden = false; }
								function eta() { if (!times.length) { return null; } var avg = times.reduce(function (a, c) { return a + c; }, 0) / times.length; return Math.ceil((queue.length + active) * avg / conc() / 1000); }
								function tick() { if (bar) { bar.max = total; bar.value = done; } if (etaEl) { var e = eta(); etaEl.textContent = done + ' / ' + total + ((e != null && e > 0) ? '  ' + (A.i18n.secondsLeft || '~%ds left').replace('%d', e) : ''); } }
								function conc() { return Math.max(1, parseInt(speed ? speed.value : 2, 10) || 2); }
								function one(url) {
									active++;
									var t0 = nowMs();
										scanAjax('lrob_cc_scan_url', { url: url, insecure: 0 }, { timeout: 20000 }).then(function (j) {
										times.push(nowMs() - t0); if (times.length > 8) { times.shift(); }
										if (j.success && j.data) { ingest(j.data.resources); }
										done++; active--;
										status.textContent = (A.i18n.scanPhasePages || 'Visiting your pages…') + ' ' + done + '/' + total;
										tick(); renderHits(); pump();
									});
								}
								function pump() {
									while (active < conc() && queue.length) { one(queue.shift()); }
									if (active === 0 && queue.length === 0) { status.textContent = A.i18n.scanComplete || 'Scan complete.'; if (progBox) { progBox.hidden = true; } btn.disabled = false; }
								}
								pump();
							});
						});
					})(0);
				});
			},
			apply: function () {}
		});

		// 3. Logging — applied live so the summary reflects it.
		if (WS.logging) {
			screens.push({
				title: WS.logging.question, hint: WS.logging.hint,
				render: function (b) {
					b.innerHTML = radioGroup('log', [
						{ v: '1', l: A.i18n.wizYesKeep || 'Yes' },
						{ v: '0', l: A.i18n.wizNoKeep || 'No' }
					], val('log_consent') ? '1' : '0') +
						'<div data-wiz-retention' + (val('log_consent') ? '' : ' hidden') + '>' +
							'<p class="lrob-cc-field-label">' + escapeHtml(A.i18n.wizRetention || 'How long should consent logs be kept in the database?') + '</p>' +
							wizDuration('log_retention_days', A.i18n.wizRetentionLabel || 'Keep logs for', A.i18n.wizRetentionTip || 'Logs are deleted automatically after this. Important on large sites — keep at least as long as a consent lasts. 0 = keep forever.') +
						'</div>';
					bindRadio(b, 'log', function (v) {
						setField('log_consent', v === '1');
						var r = b.querySelector('[data-wiz-retention]');
						if (r) { r.hidden = v !== '1'; }
					});
					b.querySelectorAll('[data-wizdur-value], [data-wizdur-unit]').forEach(function (el) {
						var handler = function () { wizDurApply(b, el.getAttribute('data-wizdur-key')); };
						el.addEventListener('input', handler);
						el.addEventListener('change', handler);
					});
				},
				apply: function () {}
			});
		}

		// 4. Summary — review everything, go Back to change, then Finish.
		screens.push({
			title: A.i18n.wizSummary || 'Ready to go',
			render: function (b) {
				var lay = val('layout_preset');
				var rows = [
					[A.i18n.wizSumLook || 'Look', ((lay && lay !== 'custom') ? lay + ' · ' : '') + val('theme') + ' · ' + val('position')],
					[A.i18n.wizSumTone || 'Wording', val('text_preset') || 'custom'],
					[A.i18n.wizSumRules || 'Trackers to block', String(Object.keys(serviceSel).length)],
					[A.i18n.wizSumLog || 'Proof of consent', val('log_consent') ? (A.i18n.wizYes || 'Yes') : (A.i18n.wizNo || 'No / skip')]
				];
				var html = '<table class="widefat striped lrob-cc-wiz-summary"><tbody>';
				rows.forEach(function (r) { html += '<tr><th>' + escapeHtml(r[0]) + '</th><td>' + escapeHtml(r[1]) + '</td></tr>'; });
				html += '</tbody></table><div class="lrob-cc-wiz-preview" data-wiz-preview></div>';
				b.innerHTML = html;
				wizPreview(b.querySelector('[data-wiz-preview]'));
			},
			apply: function () {}
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
			setField('enabled', true); // running the wizard turns the plugin on

			// Add every ticked detection as a block rule (skip ones already present).
			// Existing custom rules are never removed.
			Object.keys(serviceSel).forEach(function (p) {
				var svc = serviceSel[p] || {};
				if (!ruleRowByPattern(p)) { addRuleRow(p, svc.category || '', svc.service || svc.host || ''); }
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
	renderPreview(); // initial server render of the real banner

	// Auto-dismiss the "Settings saved" notice after a few seconds (this script
	// only loads on our settings page, so scoping to .notice is safe here).
	setTimeout(function () {
		document.querySelectorAll('.notice.is-dismissible, .notice-success, .settings-error').forEach(function (n) {
			n.style.transition = 'opacity .6s ease';
			n.style.opacity = '0';
			setTimeout(function () { if (n.parentNode) { n.parentNode.removeChild(n); } }, 600);
		});
	}, 4000);
})(jQuery);
