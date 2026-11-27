/* FloLens History menu in the WordPress toolbar. Vanilla JS; loads nothing until the menu is opened. */
(function () {
	'use strict';
	var cfg = window.userfloHistory;
	var panel = document.getElementById('uf-hb-panel');
	if (!cfg || !panel) { return; }
	var T = cfg.i18n, state = { scope: 'all', loaded: {}, seq: 0 };
	var root = document.getElementById('wp-admin-bar-userflo-history');

	function el(tag, cls, text) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (text != null) { e.textContent = text; } return e; }
	function fmt(s, a) { var i = 0; return s.replace(/%(?:(\d)\$)?s/g, function (m, n) { var v = a[n ? n - 1 : i++]; return v == null ? '' : v; }); }
	function ago(iso) {
		var t = Date.parse(iso), d = (t - Date.now()) / 1000;
		if (isNaN(t)) { return ''; }
		if (Math.abs(d) < 45) { return T.justNow; }
		try {
			var rf = new Intl.RelativeTimeFormat(cfg.lang, { numeric: 'auto' }), units = [['day', 86400], ['hour', 3600], ['minute', 60]];
			for (var i = 0; i < units.length; i++) { if (Math.abs(d) >= units[i][1] || i === units.length - 1) { return rf.format(Math.round(d / units[i][1]), units[i][0]); } }
		} catch (e) { /* older browser: fall through */ }
		return new Date(t).toLocaleString();
	}
	function short(s, n) { s = String(s || ''); return s.length > n ? s.slice(0, n - 1) + '…' : s; }
	function quote(s) { return '“' + short(s, 60) + '”'; }

	function what(it) {
		if (it.kind === 'pageview') { return fmt(T.viewed, [it.title ? short(it.title, 60) : it.url]); }
		var n = it.name || it.target || it.type;
		switch (it.type) {
			case 'click': return fmt(T.clicked, [quote(n)]);
			case 'outbound': return fmt(T.outbound, [quote(n)]);
			case 'download': return fmt(T.download, [quote(n)]);
			case 'form': return fmt(T.form, [quote(n)]);
			case 'custom': return fmt(T.custom, [quote(n)]);
			default: return fmt(T.other, [it.type]);
		}
	}
	function link(href, text, cls) { var a = el('a', cls || '', text); a.href = href; a.target = '_blank'; a.rel = 'noopener'; return a; }

	function row(it) {
		var li = el('li', 'uf-hb-item'), who = el('div', 'uf-hb-who');
		who.appendChild(link(cfg.urls.visitors + '#/visitor/' + it.visitor_id + '/session/' + it.session_id, it.label, 'uf-hb-name'));
		who.appendChild(el('span', 'uf-hb-time', ' · ' + ago(it.at)));
		li.appendChild(who);
		var d = el('div', 'uf-hb-what'), text = what(it);
		if (it.url && cfg.urls.pages) { d.appendChild(link(cfg.urls.pages + '#/p/' + encodeURIComponent(it.url), text)); }
		else { d.textContent = text; }
		if (state.scope === 'all' && it.url) { d.appendChild(el('span', 'uf-hb-url', ' ' + short(it.url, 40))); }
		li.appendChild(d);
		return li;
	}

	function render(data) {
		panel.textContent = '';
		var body = el('div', 'uf-hb-body');
		var tabs = el('div', 'uf-hb-tabs'); tabs.setAttribute('role', 'tablist');
		[['all', T.all], ['page', T.thisPage]].forEach(function (t) {
			var b = el('button', 'uf-hb-tab' + (state.scope === t[0] ? ' is-on' : ''), t[1]); b.type = 'button'; b.setAttribute('role', 'tab'); b.setAttribute('aria-selected', state.scope === t[0] ? 'true' : 'false');
			b.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); if (state.scope !== t[0]) { state.scope = t[0]; load(true); } });
			tabs.appendChild(b);
		});
		body.appendChild(tabs);
		body.appendChild(el('p', 'uf-hb-intro', T.intro));
		if (data && data.totals) { body.appendChild(el('p', 'uf-hb-totals', fmt(T.totals, [data.totals.pageviews, data.totals.interactions, data.totals.visitors]))); }
		if (data && data.totals && data.totals.bots) { var b = data.totals.bots; body.appendChild(el('p', 'uf-hb-totals', fmt(T.bots, [b.ai, b.search, b.other]))); }
		if (!data) { body.appendChild(el('p', 'uf-hb-msg', T.loading)); }
		else if (data.error) { body.appendChild(el('p', 'uf-hb-msg', data.error)); }
		else if (!data.items.length) { body.appendChild(el('p', 'uf-hb-msg', T.empty)); }
		else { var ul = el('ul', 'uf-hb-list'); data.items.forEach(function (it) { ul.appendChild(row(it)); }); body.appendChild(ul); }
		panel.appendChild(body);
		var foot = el('div', 'uf-hb-foot'), left = el('span', 'uf-hb-links');
		(cfg.links || []).forEach(function (l) { // The owner can switch the quick links off in Settings → Privacy: the list is then empty.
			var a = el('a', 'uf-hb-link', l.label); a.href = l.url;
			if (l.dot) { var d = el('span', 'uf-hb-dot uf-hb-dot-' + l.dot); d.title = l.dot_text; a.appendChild(d); }
			left.appendChild(a);
		});
		var right = el('span', 'uf-hb-acts'), rl = el('button', 'uf-hb-btn', T.reload); rl.type = 'button';
		rl.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); load(true); });
		right.appendChild(rl);
		foot.appendChild(left); foot.appendChild(right); panel.appendChild(foot);
	}

	function load(force) {
		var scope = state.scope, my = ++state.seq;
		if (scope === 'page' && !cfg.page) { render({ error: T.noPage, items: [] }); return; }
		if (!force && state.loaded[scope]) { render(state.loaded[scope]); return; }
		render(null);
		var q = (cfg.rest.indexOf('?') > -1 ? '&' : '?') + 'scope=' + scope + (scope === 'page' ? '&url=' + encodeURIComponent(cfg.page) : '') + '&limit=30';
		// A request that never answers (a firewall or a stalled server) must end in a message, not an endless "Loading…".
		var ctl = window.AbortController ? new AbortController() : null, tm = ctl ? setTimeout(function () { ctl.abort(); }, 20000) : 0;
		fetch(cfg.rest + q, { credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce }, signal: ctl ? ctl.signal : undefined }).then(function (r) { clearTimeout(tm); return r; }, function (e) { clearTimeout(tm); throw e; }).then(function (r) { return r.ok ? r.json() : Promise.reject(r); }).then(function (d) {
			if (my !== state.seq) { return; }
			state.loaded[scope] = d; render(d);
		}).catch(function () { if (my === state.seq) { render({ error: T.failed, items: [] }); } });
	}

	var opened = false;
	function first() { if (!opened) { opened = true; load(false); } }
	if (root) {
		root.setAttribute('data-userflo-ignore', ''); // opening the menu is not a visitor interaction: the tracker leaves it out
		['mouseenter', 'focusin', 'click', 'touchstart'].forEach(function (ev) { root.addEventListener(ev, first, { passive: true }); });
		// The panel is a form of its own: clicks inside must not follow the menu's own link.
		panel.addEventListener('click', function (e) { if (!e.target.closest('a')) { e.stopPropagation(); } });
	}
}());
