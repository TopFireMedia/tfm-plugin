/**
 * TFM Tracking Consent — frontend controller.
 *
 * Dependency-free. Shows the banner when no valid consent exists,
 * records the visitor's choice in a first-party cookie, updates
 * Google Consent Mode v2, and re-activates blocked scripts/iframes
 * for granted categories only.
 *
 * @package TFM_Tracking_Consent
 */
(function () {
	'use strict';

	var cfg = window.tfmTcConfig || {};
	var COOKIE = 'tfm_tc_consent';
	var CATS = ['functional', 'analytics', 'advertising', 'personalization'];
	var root, overlay, gtmLoaded = false;
	// Categories currently granted; empty until a stored/explicit decision.
	var granted = {};

	/* ------------------------------------------------------------------ *
	 * Cookie helpers
	 * ------------------------------------------------------------------ */

	function readConsent() {
		var m = document.cookie.match(/(?:^|;\s*)tfm_tc_consent=([^;]+)/);
		if (!m) { return null; }
		try {
			var c = JSON.parse(decodeURIComponent(m[1]));
			if (!c || c.v !== cfg.version || !c.cats) { return null; }
			return c;
		} catch (e) {
			return null;
		}
	}

	function writeConsent(cats) {
		var value = { v: cfg.version, ts: Math.floor(Date.now() / 1000), cats: cats };
		var maxAge = (cfg.expiryDays || 365) * 86400;
		var cookie = COOKIE + '=' + encodeURIComponent(JSON.stringify(value)) +
			'; path=/; max-age=' + maxAge + '; SameSite=Lax';
		if (location.protocol === 'https:') { cookie += '; Secure'; }
		document.cookie = cookie;
		return value;
	}

	function gpcActive() {
		return !!(cfg.respectGpc && navigator.globalPrivacyControl);
	}

	/* ------------------------------------------------------------------ *
	 * Applying consent
	 * ------------------------------------------------------------------ */

	function updateConsentMode(cats) {
		if (!cfg.consentMode || typeof window.gtag !== 'function') { return; }
		window.gtag('consent', 'update', {
			analytics_storage: cats.analytics ? 'granted' : 'denied',
			functionality_storage: cats.functional ? 'granted' : 'denied',
			personalization_storage: cats.personalization ? 'granted' : 'denied',
			ad_storage: cats.advertising ? 'granted' : 'denied',
			ad_user_data: cats.advertising ? 'granted' : 'denied',
			ad_personalization: cats.advertising ? 'granted' : 'denied'
		});
	}

	function unblockScripts(cats) {
		var blocked = document.querySelectorAll('script[type="text/plain"][data-tfm-tc-category]');
		Array.prototype.forEach.call(blocked, function (old) {
			var cat = old.getAttribute('data-tfm-tc-category');
			if (!cats[cat] || old.getAttribute('data-tfm-tc-done')) { return; }
			old.setAttribute('data-tfm-tc-done', '1');

			var s = document.createElement('script');
			for (var i = 0; i < old.attributes.length; i++) {
				var a = old.attributes[i];
				if (a.name === 'type' || a.name.indexOf('data-tfm-tc-') === 0) { continue; }
				s.setAttribute(a.name, a.value);
			}
			var src = old.getAttribute('data-tfm-tc-src');
			if (src) {
				s.src = src;
				s.async = false; // Preserve execution order between unblocked scripts.
			} else {
				s.text = old.text || old.textContent;
			}
			old.parentNode.insertBefore(s, old.nextSibling);
		});
	}

	function unblockIframes(cats) {
		var frames = document.querySelectorAll('iframe[data-tfm-tc-src][data-tfm-tc-category]');
		Array.prototype.forEach.call(frames, function (frame) {
			var cat = frame.getAttribute('data-tfm-tc-category');
			var holder = frame.previousElementSibling;
			if (cats[cat]) {
				frame.src = frame.getAttribute('data-tfm-tc-src');
				frame.removeAttribute('data-tfm-tc-src');
				if (holder && holder.className.indexOf('tfm-tc-placeholder') !== -1) {
					holder.parentNode.removeChild(holder);
				}
			} else if (!holder || holder.className.indexOf('tfm-tc-placeholder') === -1) {
				addPlaceholder(frame, cat);
			}
		});
	}

	function addPlaceholder(frame, cat) {
		var holder = document.createElement('div');
		holder.className = 'tfm-tc-placeholder';
		var text = document.createElement('p');
		text.textContent = cfg.iframeText || 'This embedded content is blocked until you allow the related cookies.';
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'tfm-tc-btn tfm-tc-btn-primary';
		btn.textContent = cfg.iframeBtn || 'Allow and load';
		btn.addEventListener('click', function () {
			var consent = readConsent();
			var cats = consent ? consent.cats : defaultCats();
			cats[cat] = true;
			api.save(cats, 'save_preferences');
		});
		holder.appendChild(text);
		holder.appendChild(btn);
		frame.parentNode.insertBefore(holder, frame);
	}

	function maybeLoadGtm(cats) {
		if (!cfg.gtmId || gtmLoaded || cfg.gtmMode !== 'blocked') { return; }
		// Strict mode: only load the container once a measurable category
		// is granted; a full rejection means GTM never loads at all.
		if (!cats.analytics && !cats.advertising) { return; }
		gtmLoaded = true;
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
		var s = document.createElement('script');
		s.async = true;
		s.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(cfg.gtmId);
		document.head.appendChild(s);
	}

	/* ------------------------------------------------------------------ *
	 * Dynamic-injection guard
	 *
	 * The server-side blocker only sees markup present in the HTML.
	 * Trackers injected later by other JavaScript (page-builder video
	 * widgets, chat loaders, tag containers) are caught here instead.
	 * ------------------------------------------------------------------ */

	function matchCategory(url) {
		if (!cfg.patterns || !url) { return ''; }
		var haystack = String(url).toLowerCase();
		if (haystack.indexOf('tfm-tracking-consent/assets') !== -1) { return ''; }
		for (var pattern in cfg.patterns) {
			if (Object.prototype.hasOwnProperty.call(cfg.patterns, pattern) &&
				haystack.indexOf(pattern.toLowerCase()) !== -1) {
				return cfg.patterns[pattern];
			}
		}
		return '';
	}

	function ensurePlaceholder(el, cat) {
		if (!el.parentNode) { return; }
		var prev = el.previousElementSibling;
		if (prev && prev.className.indexOf('tfm-tc-placeholder') !== -1) { return; }
		addPlaceholder(el, cat);
	}

	function neutralizeElement(el) {
		if (el.getAttribute('data-tfm-tc-ignore') || el.getAttribute('data-tfm-tc-done')) { return; }

		var cat = el.getAttribute('data-tfm-tc-category');
		if (cat) {
			// Already neutralized (server-side or pre-insertion); blocked
			// iframes entering the DOM still need their placeholder.
			if (el.tagName === 'IFRAME' && !granted[cat]) { ensurePlaceholder(el, cat); }
			return;
		}

		var src = el.getAttribute('src');
		cat = matchCategory(src);
		if (!cat || granted[cat]) { return; }

		el.setAttribute('data-tfm-tc-src', src);
		el.setAttribute('data-tfm-tc-category', cat);
		if (el.tagName === 'SCRIPT') {
			el.type = 'text/plain';
			el.removeAttribute('src');
		} else {
			// Navigating to about:blank cancels the pending document load.
			el.src = 'about:blank';
			el.removeAttribute('src');
			ensurePlaceholder(el, cat);
		}
	}

	function sweep(node) {
		if (!node || node.nodeType !== 1) { return; }
		if (node.tagName === 'SCRIPT' || node.tagName === 'IFRAME') {
			neutralizeElement(node);
			return;
		}
		if (node.querySelectorAll) {
			var found = node.querySelectorAll('script[src], iframe[src]');
			for (var i = 0; i < found.length; i++) { neutralizeElement(found[i]); }
		}
	}

	/**
	 * Gate src assignments on dynamically created scripts/iframes BEFORE
	 * they are inserted — a MutationObserver fires too late to stop a
	 * script fetch, so this is the only reliable interception point.
	 */
	function patchCreateElement() {
		if (!cfg.dynamic || !document.createElement) { return; }
		var orig = document.createElement;
		document.createElement = function (name) {
			var el = orig.apply(document, arguments);
			var tag = String(name).toLowerCase();
			if (tag !== 'script' && tag !== 'iframe') { return el; }

			function gate(value, setter) {
				var cat = matchCategory(value);
				if (cat && !granted[cat] && !el.getAttribute('data-tfm-tc-ignore')) {
					el.setAttribute('data-tfm-tc-src', value);
					el.setAttribute('data-tfm-tc-category', cat);
					if (tag === 'script') { el.type = 'text/plain'; }
					return;
				}
				setter(value);
			}

			try {
				var origSetAttribute = el.setAttribute.bind(el);
				Object.defineProperty(el, 'src', {
					configurable: true,
					get: function () { return el.getAttribute('src') || ''; },
					set: function (value) { gate(String(value), function (v) { origSetAttribute('src', v); }); }
				});
				el.setAttribute = function (attr, value) {
					if (String(attr).toLowerCase() === 'src') {
						gate(String(value), function (v) { origSetAttribute('src', v); });
						return;
					}
					return origSetAttribute(attr, value);
				};
			} catch (e) { /* Property descriptors unsupported — observer still guards. */ }
			return el;
		};
	}

	function watchDynamicInjections() {
		if (!cfg.dynamic || !window.MutationObserver) { return; }
		var observer = new MutationObserver(function (mutations) {
			for (var i = 0; i < mutations.length; i++) {
				var added = mutations[i].addedNodes;
				for (var j = 0; j < added.length; j++) { sweep(added[j]); }
			}
		});
		observer.observe(document.documentElement, { childList: true, subtree: true });
		sweep(document.documentElement);
	}

	function applyConsent(cats) {
		granted = cats;
		updateConsentMode(cats);
		unblockScripts(cats);
		unblockIframes(cats);
		maybeLoadGtm(cats);

		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({
			event: 'tfm_consent_update',
			tfm_consent: {
				functional: !!cats.functional,
				analytics: !!cats.analytics,
				advertising: !!cats.advertising,
				personalization: !!cats.personalization
			}
		});

		try {
			document.dispatchEvent(new CustomEvent('tfm:consent', { detail: { categories: cats } }));
		} catch (e) { /* CustomEvent unsupported — non-critical. */ }
	}

	function logReceipt(cats, action) {
		if (!cfg.logging || !cfg.restUrl || !window.fetch) { return; }
		try {
			fetch(cfg.restUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ action: action, categories: cats }),
				keepalive: true
			}).catch(function () {});
		} catch (e) { /* Logging must never break the page. */ }
	}

	/* ------------------------------------------------------------------ *
	 * UI
	 * ------------------------------------------------------------------ */

	function defaultCats() {
		var gpc = gpcActive();
		return {
			necessary: true,
			functional: false,
			analytics: false,
			advertising: false,
			personalization: false,
			// GPC only forces ad/personalization off; it never grants anything.
			gpc: gpc
		};
	}

	function decide(cats, action) {
		cats.necessary = true;
		if (gpcActive() && action !== 'save_preferences' && action !== 'accept_all') {
			cats.advertising = false;
			cats.personalization = false;
		}
		writeConsent(cats);
		applyConsent(cats);
		logReceipt(cats, action);
		hideAll();
	}

	function showBanner() {
		if (root) { root.hidden = false; }
	}

	function hideAll() {
		if (overlay) { overlay.hidden = true; }
		if (root) { root.hidden = true; }
	}

	function openPreferences() {
		if (!root || !overlay) { return; }
		root.hidden = false;
		overlay.hidden = false;

		var consent = readConsent();
		var cats = consent ? consent.cats : defaultCats();
		CATS.forEach(function (cat) {
			var toggle = root.querySelector('[data-tfm-tc-category-toggle="' + cat + '"]');
			if (toggle) { toggle.checked = !!cats[cat]; }
		});

		var note = root.querySelector('[data-tfm-tc-gpc-note]');
		if (note) { note.hidden = !gpcActive(); }

		var first = overlay.querySelector('button');
		if (first) { first.focus(); }
	}

	function collectToggles() {
		var cats = { necessary: true };
		CATS.forEach(function (cat) {
			var toggle = root ? root.querySelector('[data-tfm-tc-category-toggle="' + cat + '"]') : null;
			cats[cat] = !!(toggle && toggle.checked);
		});
		return cats;
	}

	/**
	 * CCPA/CPRA "Do Not Sell or Share" — a single click must effect the opt-out.
	 *
	 * Withdraws only the categories that constitute a sale or share
	 * (advertising, personalization) and preserves whatever the visitor already
	 * chose for the rest, since analytics and functional are business purposes.
	 * Because there may be no panel open, it confirms out loud — otherwise the
	 * link would appear to do nothing.
	 */
	function doNotSell() {
		var existing = readConsent();
		var current = existing ? existing.cats : {};
		var next = { necessary: true };
		CATS.forEach(function (cat) {
			next[cat] = (cat === 'advertising' || cat === 'personalization') ? false : !!current[cat];
		});
		decide(next, 'do_not_sell');
		announce(cfg.optOutText || 'You have been opted out of the sale and sharing of your personal information.');
	}

	/**
	 * Transient, screen-reader-announced confirmation. Built here rather than
	 * templated so it works on any page the shortcode is dropped on.
	 */
	function announce(message) {
		var el = document.getElementById('tfm-tc-toast');
		if (!el) {
			el = document.createElement('div');
			el.id = 'tfm-tc-toast';
			el.className = 'tfm-tc-toast';
			el.setAttribute('role', 'status');
			el.setAttribute('aria-live', 'polite');
			document.body.appendChild(el);
		}
		el.textContent = message;
		el.classList.add('is-visible');
		if (el._t) { clearTimeout(el._t); }
		el._t = setTimeout(function () { el.classList.remove('is-visible'); }, 6000);
	}

	function onAction(action) {
		switch (action) {
			case 'accept':
				decide({ necessary: true, functional: true, analytics: true, advertising: true, personalization: true }, 'accept_all');
				break;
			case 'reject':
				decide({ necessary: true, functional: false, analytics: false, advertising: false, personalization: false }, 'reject_all');
				break;
			case 'save':
				decide(collectToggles(), 'save_preferences');
				break;
			case 'optout':
				doNotSell();
				break;
			case 'preferences':
				openPreferences();
				break;
			case 'close':
				if (overlay) { overlay.hidden = true; }
				// If no decision exists yet, the banner stays visible:
				// closing the panel is never treated as consent.
				if (!readConsent() && root) { root.hidden = false; }
				break;
		}
	}

	function bindEvents() {
		document.addEventListener('click', function (e) {
			var el = e.target.closest ? e.target.closest('[data-tfm-tc-action]') : null;
			if (!el) { return; }
			e.preventDefault();
			onAction(el.getAttribute('data-tfm-tc-action'));
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && overlay && !overlay.hidden) {
				onAction('close');
			}
		});
	}

	/* ------------------------------------------------------------------ *
	 * Public API + boot
	 * ------------------------------------------------------------------ */

	var api = {
		get: readConsent,
		openPreferences: openPreferences,
		acceptAll: function () { onAction('accept'); },
		rejectAll: function () { onAction('reject'); },
		save: function (cats, action) { decide(cats, action || 'save_preferences'); }
	};
	window.tfmConsent = api;

	function init() {
		root = document.getElementById('tfm-tc-root');
		overlay = root ? root.querySelector('.tfm-tc-overlay') : null;
		bindEvents();
		patchCreateElement();
		watchDynamicInjections();

		var consent = readConsent();
		if (consent) {
			applyConsent(consent.cats);
		} else {
			// Prior consent model: nothing non-essential runs. GPC visitors
			// additionally get an explicit opt-out receipt for ad categories.
			if (gpcActive()) { logReceipt(defaultCats(), 'gpc'); }
			unblockIframes({}); // Draw placeholders over blocked embeds.
			showBanner();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
