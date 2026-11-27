/*! UserFlo tracker — vanilla JS, no dependencies. */
(function (w, d) {
	'use strict';
	var C = w.userfloConfig;
	if (!C || w.UserFlo) { return; }
	var n = navigator, loc = location;
	if (C.gpc && (n.globalPrivacyControl === true || n.doNotTrack === '1')) { return; }

	var MAX_Q = 50, MAX_BODY = 16000, FIRST_MS = 2000, IDLE_MS = 15000, TICK = 5000, ACT_WINDOW = 10000;
	var vid, sid, pid, seq = 0, q = [], eng = 0, sentEng = 0, maxScroll = 0, sentScroll = 0;
	var t0, lastAct = 0, started = false, firstSent = false, timer = 0, tickTimer = 0, lastUrl = '', sentDur = 0;
	var fired = {}, ckAt = 0, consentCbs = [], clickHooks = [], eventHooks = [], formHooks = [];
	var dlExt = (C.dl || '').split(',');

	// ---- storage -------------------------------------------------------
	function getCookie(k) {
		var m = d.cookie.match(new RegExp('(?:^|; )' + k + '=([^;]*)'));
		return m ? decodeURIComponent(m[1]) : '';
	}
	function setCookie(k, v, secs) {
		d.cookie = k + '=' + encodeURIComponent(v) + ';path=/;max-age=' + secs + ';SameSite=Lax' + (loc.protocol === 'https:' ? ';Secure' : '');
	}
	function rid(bytes) {
		var a = new Uint8Array(bytes), s = '', i;
		(w.crypto || w.msCrypto).getRandomValues(a);
		for (i = 0; i < a.length; i++) { s += (a[i] < 16 ? '0' : '') + a[i].toString(16); }
		return s;
	}
	function valid(v, len) { return new RegExp('^[a-f0-9]{' + len + '}$').test(v); }
	// Session-only mode (C.sm): no cookie is read or written. The two ids live in this tab's sessionStorage (gone when the tab closes), are renewed
	// after the inactivity time, and a visitor is a single tab session. Where sessionStorage is blocked they live in memory for the page only.
	function ss(k, v) {
		try { if (v === undefined) { return w.sessionStorage.getItem(k) || ''; } w.sessionStorage.setItem(k, v); } catch (e) { return ''; }
	}
	function tabIds() {
		var now = Date.now();
		vid = ss('uf_v'); sid = ss('uf_s');
		if (!valid(vid, 32) || !valid(sid, 32) || now - (+ss('uf_t') || 0) > (C.ttl || 1800) * 1000) { vid = rid(16); sid = rid(16); ss('uf_v', vid); ss('uf_s', sid); }
		['uf_vid', 'uf_sid'].forEach(function (k) { d.cookie = k + '=;path=/;max-age=0'; }); // Drop cookies an earlier setting left behind.
	}
	function touchCookies() {
		var now = Date.now();
		if (now - ckAt < 10000) { return; }
		ckAt = now;
		if (C.sm) { ss('uf_t', String(now)); return; }
		setCookie('uf_vid', vid, 34560000);
		setCookie('uf_sid', sid, C.ttl || 1800);
	}

	// ---- consent -------------------------------------------------------
	function consented() {
		if (C.cm === 'cookie') {
			var v = getCookie(C.cc);
			return !!v && !/^(0|no|false|denied|reject(ed)?)$/i.test(v) && !/(^|[^a-z])(analytics|statistics)[^,]*:\s*(false|0)/i.test(v);
		}
		if (C.cm === 'js') { return w.userfloConsent === true; }
		// Format-based presets for common consent plugins (not verified against the plugins themselves).
		if (C.cm === 'cookieyes') { return /(^|,)analytics:yes(,|$)/.test(getCookie('cookieyes-consent')); }
		if (C.cm === 'cookiebot') { return /statistics:true/.test(getCookie('CookieConsent')); }
		if (C.cm === 'borlabs') {
			try { var b = JSON.parse(getCookie('borlabs-cookie')); return !!(b && b.consents && b.consents.statistics && b.consents.statistics.length); } catch (e) { return false; }
		}
		if (C.cm === 'complianz') { return getCookie('cmplz_statistics') === 'allow' || getCookie('cmplz_consent_status') === 'allow'; }
		return true;
	}

	// ---- url / page helpers -------------------------------------------
	function cleanPath(pathname, search) {
		var keep = C.qp || [];
		var q2 = search ? search.slice(1).split('&').filter(function (p) { var k = p.split('=')[0]; return /^utm_/i.test(k) || keep.indexOf(k) > -1; }).join('&') : '';
		return (pathname + (q2 ? '?' + q2 : '')).slice(0, 500);
	}
	function pagePath() { return cleanPath(loc.pathname, loc.search); }
	function refer() {
		var r = d.referrer;
		if (!r) { return ''; }
		try {
			var u = new URL(r);
			return (u.host === loc.host ? u.pathname : u.origin + u.pathname).slice(0, 500);
		} catch (e) { return ''; }
	}

	// ---- queue / delivery ---------------------------------------------
	function add(type, name, target, value, comp) {
		if (!started || (C.off && C.off.indexOf(type) > -1)) { return; }
		var key = type + '|' + name + '|' + target;
		var now = Date.now();
		if (fired[key] && now - fired[key] < 1000) { return; } // duplicate protection
		fired[key] = now;
		if (q.length >= MAX_Q) { q.shift(); }
		q.push({ i: ++seq, k: type, n: (name || '').slice(0, 100), g: (target || '').slice(0, 255), x: (value || '').slice(0, 500), c: comp || '', t: now });
		eventHooks.forEach(function (h) { try { h({ type: type, name: name || '', target: target || '', value: value || '', component: comp || '' }); } catch (e) { /* isolated */ } });
		if (q.length >= 20) { flush(); } else { schedule(IDLE_MS); }
	}
	function schedule(ms) {
		if (!timer) { timer = setTimeout(function () { timer = 0; flush(); }, ms); }
	}
	function payload(final) {
		var now = Date.now(), ev = q.splice(0, q.length).map(function (e) {
			var o = { i: e.i, k: e.k, n: e.n, g: e.g, x: e.x, a: now - e.t }; if (e.c) { o.c = e.c; } return o;
		});
		var p = { v: 1, vid: vid, sid: sid, pid: pid, u: lastUrl, ev: ev };
		if (!firstSent) {
			p.t = d.title.slice(0, 255); p.r = refer();
			p.sw = w.screen ? w.screen.width : 0; p.sh = w.screen ? w.screen.height : 0;
		}
		p.eng = eng - sentEng; p.sc = maxScroll; p.dur = Math.round((now - t0) / 1000);
		return p;
	}
	function flush(final) {
		if (!started) { return; }
		if (idle() && !q.length && eng === sentEng) { return; } // Hidden or closed after a long idle time with nothing new: do not extend the session.
		if (timer) { clearTimeout(timer); timer = 0; }
		var dur = Math.round((Date.now() - t0) / 1000);
		// Nothing new since the last delivery (pagehide usually follows visibilitychange): skip.
		if (firstSent && !q.length && eng === sentEng && maxScroll === sentScroll && dur - sentDur < 2) { return; }
		var p = payload(final), body = JSON.stringify(p);
		while (body.length > MAX_BODY && p.ev.length > 1) { p.ev.length = Math.floor(p.ev.length / 2); body = JSON.stringify(p); }
		sentEng = eng; sentScroll = maxScroll; sentDur = dur; firstSent = true;
		touchCookies();
		send(body);
	}
	function send(body) {
		try {
			if (n.sendBeacon && n.sendBeacon(C.e, new Blob([body], { type: 'text/plain;charset=UTF-8' }))) { return; }
		} catch (e) { /* fall through */ }
		try { w.fetch(C.e, { method: 'POST', body: body, keepalive: true, credentials: 'same-origin', headers: { 'Content-Type': 'text/plain;charset=UTF-8' } }).catch(function () {}); } catch (e2) { /* drop */ }
	}

	// ---- element description ------------------------------------------
	var MEANINGFUL = 'a[href],button,input[type=button],input[type=submit],[role=button],[role=link],[role=tab],[role=menuitem],[role=switch],[aria-expanded],[aria-controls],summary,[data-userflo]' + (C.is ? ',' + C.is : '');
	var EXCLUDE = '[data-userflo-ignore]' + (C.xs ? ',' + C.xs : '');
	function safeMatch(el, sel) { try { return el.matches(sel); } catch (e) { return false; } }
	function meaningful(el) {
		for (var i = 0; el && el.nodeType === 1 && i < 6; i++, el = el.parentElement) {
			if (safeMatch(el, MEANINGFUL)) { return el.closest(EXCLUDE) ? null : el; }
		}
		return null;
	}
	// Nearest meaningful section/component: landmarks, dialogs, blocks. Builder adapters add their own via UserFlo.adapter().
	var adapters = [];
	var LANDMARK = 'header,nav,main,footer,aside,dialog,[role=dialog],[role=navigation],[role=banner],[role=contentinfo],[role=tablist],[role=menu],section[aria-label],section[id],form[id]';
	function slug(x) { return String(x || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 40); }
	function component(el) {
		var i, c;
		for (i = 0; i < adapters.length; i++) {
			try { c = adapters[i](el); } catch (e) { c = ''; }
			if (c) { return String(c).slice(0, 100); }
		}
		var lm = el.closest ? el.closest(LANDMARK) : null;
		var blk = el.closest ? el.closest('[class*="wp-block-"]') : null, bm = null;
		while (blk) { // skip generic layout wrappers
			bm = /(?:^|\s)(wp-block-[a-z0-9-]+)/.exec(blk.className);
			if (bm && !/^wp-block-(post-content|group|columns?|template|post-template|query|cover|buttons|html)$/.test(bm[1])) { break; }
			bm = null; blk = blk.parentElement ? blk.parentElement.closest('[class*="wp-block-"]') : null;
		}
		// Use the more specific (deeper) of the nearest landmark and the nearest Gutenberg block.
		if (bm && (!lm || lm.contains(blk))) { return 'gutenberg:' + bm[1].slice(9); }
		if (lm) {
			var t = lm.tagName.toLowerCase(), r = lm.getAttribute('role') || '', n = lm.id && !/\d{3,}|[0-9a-f]{8,}/i.test(lm.id) ? '#' + lm.id : '';
			return ('landmark:' + (r || t) + n).slice(0, 100);
		}
		return '';
	}

	function stableClass(c) { return /^[a-z][a-z_-]{2,40}$/i.test(c) && !/^(active|open|show|hidden|current|selected|js-)/i.test(c); }
	function part(e) {
		var t = e.tagName.toLowerCase(), id = e.id;
		if (id && !/\d{3,}|[0-9a-f]{8,}/i.test(id)) { return t + '#' + id; }
		var a = e.getAttribute('data-userflo') || e.getAttribute('data-testid') || e.getAttribute('name') || e.getAttribute('aria-controls');
		if (a) { return t + '[' + a.slice(0, 40) + ']'; }
		var cls = typeof e.className === 'string' ? e.className.split(/\s+/).filter(stableClass)[0] : '';
		return cls ? t + '.' + cls : t;
	}
	function selector(el) {
		var parts = [], depth = 0;
		while (el && el.nodeType === 1 && depth < 4 && el !== d.body) {
			var p = part(el);
			parts.unshift(p);
			if (p.indexOf('#') > -1) { break; }
			el = el.parentElement; depth++;
		}
		return parts.join(' > ').slice(0, 255);
	}
	function label(el) {
		var s = el.getAttribute('aria-label') || el.getAttribute('title') || '';
		if (!s) {
			s = el.tagName === 'INPUT' ? el.value : (el.textContent || '');
			if (!s) { var img = el.querySelector && el.querySelector('img[alt]'); s = img ? img.getAttribute('alt') : ''; }
		}
		return s.replace(/\s+/g, ' ').trim().slice(0, 80);
	}
	// Element identifiers switched off in the Exclusions screen (exact matches only; everything else is dropped on the server).
	function ignored(t) { return !!C.xt && C.xt.indexOf(t) > -1; }
	function onClick(e) {
		var el = meaningful(e.target);
		if (!el) { return; }
		var type = 'click', value = '', mail = '';
		if (el.tagName === 'A') {
			var href = el.getAttribute('href') || '';
			if (/^(javascript:|#)/i.test(href)) { value = ''; }
			else {
				try {
					var u = new URL(el.href, loc.href), ext = (u.pathname.split('.').pop() || '').toLowerCase();
					if (/^(mailto|tel):/i.test(u.protocol + '')) { value = u.protocol.toLowerCase(); mail = value === 'mailto:' ? 'Email link' : 'Phone link'; } // Intent only: neither the address nor the number is kept, not even as link text.
					else if (el.hasAttribute('download') || dlExt.indexOf(ext) > -1) { type = 'download'; value = u.pathname; }
					else if (u.host !== loc.host) { type = 'outbound'; value = u.origin + u.pathname; }
					else { value = cleanPath(u.pathname, u.search); }
				} catch (err) { value = ''; }
			}
		}
		var info = { type: type, name: mail || label(el), target: selector(el), value: value, component: component(el), el: el };
		if (ignored(info.target)) { return; }
		for (var i = 0; i < clickHooks.length; i++) {
			try { if (clickHooks[i](info) === false) { return; } } catch (err) { /* isolated */ }
		}
		add(info.type, info.name, info.target, info.value, info.component);
	}

	// ---- forms and media (never reads field values) ---------------------
	function onSubmit(e) {
		var f = e.target;
		if (!f || f.tagName !== 'FORM' || safeMatch(f, EXCLUDE)) { return; }
		// A [userflo_form name="…"] wrapper names any form manually; otherwise the form's own label is used.
		var wrap = f.closest('[data-userflo-form]');
		var info = { el: f, name: ((wrap && wrap.getAttribute('data-userflo-form')) || f.getAttribute('aria-label') || f.getAttribute('name') || f.id || 'form').slice(0, 80), target: selector(f), component: component(f), wrap: wrap };
		for (var i = 0; i < formHooks.length; i++) {
			try { if (formHooks[i](info) === false) { return; } } catch (err) { /* isolated */ }
		}
		if (ignored(info.target)) { return; }
		add('form', info.name, info.target, '', info.component);
	}
	function onMedia(e) {
		var m = e.target;
		if (!m || !m.tagName || !/^(VIDEO|AUDIO)$/.test(m.tagName) || safeMatch(m, EXCLUDE)) { return; }
		var src = m.currentSrc || m.src || '', path = '';
		try { path = src ? new URL(src, loc.href).pathname : ''; } catch (err) { path = ''; }
		var tg = selector(m);
		if (ignored(tg)) { return; }
		add('media', m.tagName.toLowerCase() + ' play', tg, path, component(m));
	}

	// ---- scroll / engagement ------------------------------------------
	var scrollQueued = false;
	function onScroll() {
		lastAct = Date.now();
		if (scrollQueued) { return; }
		scrollQueued = true;
		w.requestAnimationFrame(function () {
			scrollQueued = false;
			var de = d.documentElement, h = Math.max(de.scrollHeight, 1), pct = Math.min(100, Math.round(((w.pageYOffset || de.scrollTop) + (w.innerHeight || de.clientHeight)) / h * 100));
			if (pct > maxScroll) { maxScroll = pct; }
			(C.sc || []).forEach(function (th) { if (pct >= th && !fired['s' + th + pid]) { fired['s' + th + pid] = 1; add('scroll', th + '%', '', String(th)); } });
		});
	}
	// A page left open past the inactivity time keeps its session id in memory; coming back is a new visit, not the old session stretched.
	function idle() { return Date.now() - lastAct > (C.ttl || 1800) * 1000; }
	function resume() {
		if (C.sm) { tabIds(); } else { var s = getCookie('uf_sid'); sid = valid(s, 32) ? s : rid(16); }
		ckAt = 0; newPage();
	}
	function activity() { if (started && idle()) { resume(); } lastAct = Date.now(); }
	function tick() {
		if (d.visibilityState === 'visible' && Date.now() - lastAct < ACT_WINDOW) { eng += TICK / 1000; }
	}
	function startTick() { if (!tickTimer) { tickTimer = setInterval(tick, TICK); } }
	function stopTick() { clearInterval(tickTimer); tickTimer = 0; }

	// ---- page lifecycle -----------------------------------------------
	function newPage() {
		pid = rid(8); seq = 0; eng = 0; sentEng = 0; maxScroll = 0; sentScroll = 0; firstSent = false; sentDur = 0; fired = {};
		t0 = Date.now(); lastUrl = pagePath(); lastAct = t0;
		schedule(FIRST_MS);
	}
	function start() {
		if (started || !consented()) { return; }
		if (C.sm) { tabIds(); } else {
			vid = getCookie('uf_vid'); if (!valid(vid, 32)) { vid = rid(16); }
			sid = getCookie('uf_sid'); if (!valid(sid, 32)) { sid = rid(16); }
		}
		started = true; ckAt = 0; touchCookies();
		consentCbs.splice(0).forEach(function (cb) { try { cb(); } catch (e) { /* isolated */ } });
		newPage();
		if (C.ck) { d.addEventListener('click', onClick, true); d.addEventListener('submit', onSubmit, true); d.addEventListener('play', onMedia, true); }
		w.addEventListener('scroll', onScroll, { passive: true });
		['keydown', 'pointerdown', 'touchstart', 'mousemove'].forEach(function (t) { w.addEventListener(t, activity, { passive: true }); });
		d.addEventListener('visibilitychange', function () {
			if (d.visibilityState === 'hidden') { stopTick(); flush(true); } else { if (idle()) { resume(); } startTick(); lastAct = Date.now(); }
		});
		w.addEventListener('pagehide', function () { flush(true); });
		if (d.visibilityState === 'visible') { startTick(); }
		// Single-page-app navigation.
		['pushState', 'replaceState'].forEach(function (m) {
			var o = history[m];
			history[m] = function () { var r = o.apply(this, arguments); setTimeout(navigated, 0); return r; };
		});
		w.addEventListener('popstate', function () { setTimeout(navigated, 0); });
	}
	function navigated() {
		if (!started || pagePath() === lastUrl) { return; }
		flush(true);
		newPage();
	}

	// ---- public API ----------------------------------------------------
	w.UserFlo = {
		track: function (name, props) {
			var v = '';
			// Properties are only sent for names the site owner allow-listed (UserFlo Pro sets C.cp); plain names always work.
			if (props && typeof props === 'object' && C.cp && C.cp.length) {
				var o = {}, cnt = 0;
				Object.keys(props).forEach(function (k) {
					var x = props[k];
					if (cnt < 10 && C.cp.indexOf(k) > -1 && /^(string|number|boolean)$/.test(typeof x)) {
						if (typeof x === 'string') { x = x.slice(0, 100); if (/@|\d{7,}/.test(x.replace(/[\s().\-]/g, ''))) { return; } } // never an address or a phone-like number
						o[k] = x; cnt++;
					}
				});
				if (cnt) { v = JSON.stringify(o); }
			}
			add('custom', String(name || '').slice(0, 100), '', v);
		},
		onClick: function (fn) { if (typeof fn === 'function') { clickHooks.push(fn); } },
		onForm: function (fn) { if (typeof fn === 'function') { formHooks.push(fn); } },
		onEvent: function (fn) { if (typeof fn === 'function') { eventHooks.push(fn); } },
		onConsent: function (cb) { if (typeof cb !== 'function') { return; } if (started) { try { cb(); } catch (e) { /* isolated */ } } else { consentCbs.push(cb); } },
		adapter: function (fn) { if (typeof fn === 'function') { adapters.push(fn); } },
		track2: function (type, name, value, comp) { add(type, name, '', value, comp); },
		consent: function (granted) { w.userfloConsent = granted !== false; start(); },
		flush: function () { flush(false); }
	};
	d.addEventListener('userflo:consent', function () { start(); });
	// Complianz fires these when a visitor changes consent; start once statistics are allowed.
	['cmplz_status_change', 'cmplz_enable_category', 'cmplz_event_status', 'CookiebotOnAccept', 'cookieyes_consent_update', 'borlabs-cookie-consent-saved'].forEach(function (ev) { d.addEventListener(ev, function () { setTimeout(start, 50); }); });
	if (d.readyState === 'loading') { d.addEventListener('DOMContentLoaded', start); } else { start(); }
}(window, document));
