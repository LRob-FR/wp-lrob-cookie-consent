/**
 * LRob Cookie Consent — front consent state machine.
 * Public API on window.lrobCc. Config injected as window.lrobCcData.
 */
(function () {
	'use strict';

	var D = window.lrobCcData || {};
	var OPTIONAL = D.optional || ['preferences', 'statistics', 'marketing'];
	var COOKIE = D.cookieName || 'lrob_cc_consent';
	var STATUS = D.statusCookie || 'lrob_cc_status';
	var VERSION = D.version || '';
	var DAYS = parseInt(D.cookieDays, 10) || 365;

	// --- Cookies ---------------------------------------------------------
	function setCookie(name, value, days) {
		var d = new Date();
		d.setTime(d.getTime() + days * 864e5);
		var secure = location.protocol === 'https:' ? '; Secure' : '';
		document.cookie = name + '=' + encodeURIComponent(value) +
			'; expires=' + d.toUTCString() + '; path=/; SameSite=Lax' + secure;
	}

	function getCookie(name) {
		var match = document.cookie.match('(?:^|; )' + name + '=([^;]*)');
		return match ? decodeURIComponent(match[1]) : null;
	}

	// --- State -----------------------------------------------------------
	function emptyConsent() {
		var c = { functional: true };
		OPTIONAL.forEach(function (cat) { c[cat] = false; });
		return c;
	}

	// Stored decision, or null when absent / stale (config version changed).
	function stored() {
		var raw = getCookie(COOKIE);
		if (!raw) { return null; }
		try {
			var data = JSON.parse(raw);
			if (data.version !== VERSION) { return null; }
			return data;
		} catch (e) {
			return null;
		}
	}

	function hasConsent(category) {
		if (category === 'functional') { return true; }
		var data = stored();
		return data ? !!data[category] : false;
	}

	function acceptedCategories() {
		return ['functional'].concat(OPTIONAL.filter(function (cat) {
			return hasConsent(cat);
		}));
	}

	// --- Script / iframe activation -------------------------------------
	function activateScripts(category) {
		var nodes = document.querySelectorAll(
			'script[type="text/plain"][data-category="' + category + '"]'
		);
		nodes.forEach(function (el) {
			var s = document.createElement('script');
			for (var i = 0; i < el.attributes.length; i++) {
				var a = el.attributes[i];
				if (a.name === 'type' || a.name === 'data-category' || a.name === 'data-service') { continue; }
				if (a.name === 'data-src') { s.setAttribute('src', a.value); continue; }
				if (a.name === 'data-script-type') { s.setAttribute('type', a.value); continue; }
				s.setAttribute(a.name, a.value);
			}
			s.innerHTML = el.innerHTML;
			el.parentNode.insertBefore(s, el);
			el.parentNode.removeChild(el);
		});
	}

	function activateIframes(category) {
		var frames = document.querySelectorAll(
			'iframe.lrob-cc-blocked[data-category="' + category + '"]'
		);
		frames.forEach(function (el) {
			var ph = el.parentNode.querySelector('.lrob-cc-placeholder[data-category="' + category + '"]');
			if (ph) { ph.parentNode.removeChild(ph); }
			el.classList.remove('lrob-cc-blocked');
			if (el.getAttribute('data-src')) {
				el.setAttribute('src', el.getAttribute('data-src'));
				el.removeAttribute('data-src');
			}
		});
	}

	function enableCategory(category) {
		activateScripts(category);
		activateIframes(category);
		document.body.classList.add('lrob-cc-' + category);
		document.dispatchEvent(new CustomEvent('lrob_cc_enable_category', {
			detail: { category: category, categories: acceptedCategories() }
		}));
	}

	function syncBodyClasses(consent) {
		document.body.classList.add('lrob-cc-functional');
		OPTIONAL.forEach(function (cat) {
			document.body.classList.toggle('lrob-cc-' + cat, !!consent[cat]);
		});
	}

	function mirrorConsentApi(consent) {
		if (typeof window.wp_set_consent !== 'function') { return; }
		window.wp_set_consent('functional', 'allow');
		OPTIONAL.forEach(function (cat) {
			window.wp_set_consent(cat, consent[cat] ? 'allow' : 'deny');
		});
	}

	// --- Iframe placeholders (built client-side, pre-consent) ------------
	function buildPlaceholders() {
		document.querySelectorAll('iframe.lrob-cc-blocked').forEach(function (el) {
			var category = el.getAttribute('data-category');
			if (!category || hasConsent(category)) { enableCategory(category); return; }
			if (el.parentNode.querySelector('.lrob-cc-placeholder[data-category="' + category + '"]')) { return; }

			var service = el.getAttribute('data-service') || '';
			var catLabel = (D.catLabels && D.catLabels[category]) || category;
			var src = el.getAttribute('data-src') || '';
			var host = '';
			try { host = src ? new URL(src, location.href).hostname : ''; } catch (e) { host = src; }

			var ph = document.createElement('div');
			ph.className = 'lrob-cc-placeholder';
			ph.setAttribute('data-category', category);
			// Match the blocked iframe's footprint so it doesn't leave a larger void.
			var w = el.getAttribute('width');
			var h = el.getAttribute('height');
			if (w && /^\d+$/.test(w)) { ph.style.maxWidth = w + 'px'; }
			if (h && /^\d+$/.test(h)) { ph.style.minHeight = Math.min(parseInt(h, 10), 240) + 'px'; }

			ph.innerHTML =
				'<span class="lrob-cc-ph-title"></span>' +
				'<span class="lrob-cc-ph-url"></span>' +
				'<button type="button" class="lrob-cc-placeholder-btn"></button>' +
				'<span class="lrob-cc-ph-note"></span>';
			ph.querySelector('.lrob-cc-ph-title').textContent =
				((D.i18n && D.i18n.embedTitle) || '%s content blocked').replace('%s', service || catLabel);
			ph.querySelector('.lrob-cc-ph-url').textContent = host;
			ph.querySelector('.lrob-cc-placeholder-btn').textContent = (D.i18n && D.i18n.acceptLoad) || 'Accept & load';
			ph.querySelector('.lrob-cc-ph-note').textContent =
				((D.i18n && D.i18n.embedNote) || 'Loads once you accept “%s”.').replace('%s', catLabel);

			// Only the button accepts — not the whole placeholder area.
			ph.querySelector('.lrob-cc-placeholder-btn').addEventListener('click', function () {
				setConsent(category, 'allow');
			});
			el.parentNode.insertBefore(ph, el);
		});
	}

	function sameDecision(a, b) {
		if (!a || !b || a.version !== b.version) { return false; }
		return OPTIONAL.every(function (cat) { return !!a[cat] === !!b[cat]; });
	}

	function isWithdrawal(prior, cur) {
		return OPTIONAL.some(function (cat) { return prior[cat] && !cur[cat]; });
	}

	function genId() {
		var s = '';
		if (window.crypto && crypto.getRandomValues) {
			var a = new Uint8Array(16);
			crypto.getRandomValues(a);
			for (var i = 0; i < 16; i++) { s += ('0' + a[i].toString(16)).slice(-2); }
		} else {
			for (var j = 0; j < 32; j++) { s += Math.floor(Math.random() * 16).toString(16); }
		}
		return s;
	}

	function choicesMap(consent) {
		var m = {};
		OPTIONAL.forEach(function (cat) { m[cat] = !!consent[cat]; });
		return m;
	}

	// --- Persisting a decision ------------------------------------------
	function persist(consent, method) {
		var prior = stored(); // before we overwrite the cookie
		consent.functional = true;
		// Anonymous subject identifier — stable per browser, reused across events.
		consent.cid = (prior && prior.cid) ? prior.cid : (consent.cid || genId());
		consent.ts = Math.floor(Date.now() / 1000);
		consent.version = VERSION;
		setCookie(COOKIE, JSON.stringify(consent), DAYS);
		setCookie(STATUS, 'dismissed', DAYS);

		OPTIONAL.forEach(function (cat) { if (consent[cat]) { enableCategory(cat); } });
		syncBodyClasses(consent);
		mirrorConsentApi(consent);
		buildPlaceholders();

		document.dispatchEvent(new CustomEvent('lrob_cc_status_change', {
			detail: { categories: acceptedCategories(), consent: consent }
		}));
		// Don't re-log an unchanged decision; withdrawals/updates are logged.
		if (!sameDecision(prior, consent)) {
			var ev = !prior ? 'consent' : (isWithdrawal(prior, consent) ? 'withdraw' : 'update');
			logConsent(consent, method || 'save', ev);
		}
	}

	function logConsent(consent, method, ev) {
		if (!D.rest || !D.rest.logConsent || !D.rest.url) { return; }
		try {
			fetch(D.rest.url, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': D.rest.nonce || '' },
				credentials: 'same-origin',
				body: JSON.stringify({
					consent_id: consent.cid,
					method: method,
					event: ev,
					choices: choicesMap(consent),
					version: VERSION,
					banner_version: D.bannerVersion || ''
				})
			}).catch(function () {});
		} catch (e) {}
	}

	// --- Public actions --------------------------------------------------
	function acceptAll() {
		var c = emptyConsent();
		OPTIONAL.forEach(function (cat) { c[cat] = true; });
		persist(c, 'accept_all');
		hideBanner();
	}

	function denyAll() {
		persist(emptyConsent(), 'deny_all');
		hideBanner();
	}

	function setConsent(category, value) {
		var data = stored() || emptyConsent();
		if (category !== 'functional') { data[category] = (value === 'allow'); }
		persist(data, 'service');
	}

	function savePreferences() {
		var c = emptyConsent();
		OPTIONAL.forEach(function (cat) {
			var box = document.querySelector('#lrob-cc-banner input[data-category="' + cat + '"]');
			c[cat] = box ? box.checked : false;
		});
		persist(c);
		hideBanner();
	}

	// --- Banner UI -------------------------------------------------------
	var banner, lastFocus;

	function isDntOn() {
		return navigator.doNotTrack === '1' || window.doNotTrack === '1' ||
			navigator.msDoNotTrack === '1' || navigator.globalPrivacyControl === true;
	}

	function revealCategories() {
		var cats = document.getElementById('lrob-cc-categories');
		if (cats) { cats.hidden = false; }
		var toggle = banner.querySelector('[data-lrob-cc-action="customize"]');
		if (toggle) { toggle.setAttribute('aria-expanded', 'true'); toggle.hidden = true; }
		var save = banner.querySelector('[data-lrob-cc-action="save"]');
		if (save) { save.hidden = false; }
	}

	function revisitCorner() {
		var pos = D.position || 'bottom';
		var v = pos.indexOf('top') === 0 ? 'top' : 'bottom';
		var h = pos.indexOf('left') !== -1 ? 'left' : 'right';
		return v + '-' + h;
	}

	function ensureRevisitButton() {
		if (!D.revisitButton) { return; }
		var existing = document.getElementById('lrob-cc-revisit');
		if (existing) { existing.hidden = false; return; } // unhide (was hidden while banner open)
		var b = document.createElement('button');
		b.id = 'lrob-cc-revisit';
		b.type = 'button';
		b.className = 'lrob-cc-revisit lrob-cc-revisit-' + revisitCorner();
		b.textContent = D.revisitText || (D.i18n && D.i18n.manageCookies) || 'Manage cookies';
		b.addEventListener('click', function () { showBanner(); });
		document.body.appendChild(b);
	}

	function showBanner() {
		banner = document.getElementById('lrob-cc-banner');
		if (!banner) { return; }
		var revisit = document.getElementById('lrob-cc-revisit');
		if (revisit) { revisit.hidden = true; }
		lastFocus = document.activeElement;
		banner.hidden = false;
		banner.classList.add('lrob-cc-visible');
		document.body.classList.add('lrob-cc-banner-open');
		var focusable = banner.querySelector('button, [href], input, [tabindex]:not([tabindex="-1"])');
		if (focusable) { focusable.focus(); }
		banner.addEventListener('keydown', onKeydown);
	}

	function hideBanner() {
		if (!banner) { return; }
		banner.classList.remove('lrob-cc-visible');
		banner.hidden = true;
		document.body.classList.remove('lrob-cc-banner-open');
		banner.removeEventListener('keydown', onKeydown);
		if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
		ensureRevisitButton();
	}

	function onKeydown(e) {
		if (e.key === 'Escape') { e.preventDefault(); denyAll(); return; }
		if (e.key !== 'Tab') { return; }
		var items = banner.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), [tabindex]:not([tabindex="-1"])');
		if (!items.length) { return; }
		var first = items[0], last = items[items.length - 1];
		if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
		else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
	}

	function bindBanner() {
		banner = document.getElementById('lrob-cc-banner');
		if (!banner) { return; }

		banner.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-lrob-cc-action]');
			if (!btn) { return; }
			e.preventDefault();
			var action = btn.getAttribute('data-lrob-cc-action');
			if (action === 'accept-all') { acceptAll(); }
			else if (action === 'deny-all') { denyAll(); }
			else if (action === 'save') { savePreferences(); }
			else if (action === 'close') { denyAll(); }
			else if (action === 'customize') { revealCategories(); }
		});

		// Reflect stored choices in the checkboxes.
		var data = stored();
		if (data) {
			OPTIONAL.forEach(function (cat) {
				var box = banner.querySelector('input[data-category="' + cat + '"]');
				if (box) { box.checked = !!data[cat]; }
			});
		}

		// Re-open links anywhere on the page ([lrob_cc_manage] shortcode).
		document.addEventListener('click', function (e) {
			var opener = e.target.closest('[data-lrob-cc-open]');
			if (opener) { e.preventDefault(); showBanner(); }
		});
	}

	// --- Init ------------------------------------------------------------
	function init() {
		bindBanner();

		var data = stored();
		if (data) {
			// Returning visitor with a current decision — apply it silently.
			OPTIONAL.forEach(function (cat) { if (data[cat]) { enableCategory(cat); } });
			syncBodyClasses(data);
			mirrorConsentApi(data);
			buildPlaceholders();
			ensureRevisitButton();
			return;
		}

		document.body.classList.add('lrob-cc-functional');
		mirrorConsentApi(emptyConsent());
		buildPlaceholders();

		if (D.respectDnt && isDntOn()) {
			if (D.dntHideBanner) { denyAll(); return; }
		}
		showBanner();
	}

	// --- Public API ------------------------------------------------------
	window.lrobCc = {
		hasConsent: hasConsent,
		acceptedCategories: acceptedCategories,
		acceptAll: acceptAll,
		denyAll: denyAll,
		setConsent: setConsent,
		showBanner: function () { showBanner(); },
		hideBanner: function () { hideBanner(); }
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
