/* UserFlo shared page: read-only statistics / live view, rendered without WordPress login. */
(function () {
	'use strict';
	var C = window.userfloShare, app = document.getElementById('us-app');
	if (!C || !app) { return; }
	var S = C.strings, days = [1, 7, 30, 90].indexOf(C.days) > -1 ? C.days : 30;
	var nf = new Intl.NumberFormat(C.locale);
	function el(t, c, x) { var e = document.createElement(t); if (c) { e.className = c; } if (x != null) { e.textContent = x; } return e; }
	function dur(s) { s = Math.round(s); return s < 60 ? s + 's' : s < 3600 ? Math.floor(s / 60) + 'm ' + (s % 60) + 's' : Math.floor(s / 3600) + 'h ' + Math.floor(s % 3600 / 60) + 'm'; }
	function get(what, q) {
		var u = C.api + what + (q ? (C.api.indexOf('?') > -1 ? '&' + q.slice(1) : q) : '');
		return fetch(u, { credentials: 'omit', cache: 'no-store' }).then(function (r) { if (!r.ok) { throw new Error('x'); } return r.json(); });
	}
	function fail() { app.textContent = ''; app.appendChild(el('p', 'us-msg', S.error)); }
	function stat(label, v) { var d = el('div', 'us-stat'); d.appendChild(el('b', null, v)); d.appendChild(el('span', null, label)); return d; }
	function rank(title, rows) {
		var c = el('section', 'us-card'); c.appendChild(el('h2', null, title));
		if (!rows.length) { c.appendChild(el('p', 'us-empty', S.none)); return c; }
		var max = Math.max.apply(null, rows.map(function (r) { return r.n; }).concat([1])), ol = el('ol', 'us-rank');
		rows.slice(0, 10).forEach(function (r) {
			var li = el('li'), t = el('div', 't'); t.appendChild(el('span', 'l', r.l)); t.appendChild(el('span', null, nf.format(r.n))); li.appendChild(t);
			var b = el('div', 'bar'), i = el('i'); i.style.width = Math.max(2, r.n / max * 100) + '%'; b.appendChild(i); li.appendChild(b); ol.appendChild(li);
		});
		c.appendChild(ol); return c;
	}
	function ins(rows) { return (rows || []).map(function (r) { return { l: r.key, n: r.count }; }); }

	function stats() {
		var bar = el('div', 'us-bar'), out = el('div');
		[7, 30, 90].forEach(function (d) {
			var b = el('button', null, S.last + ' ' + d + ' ' + S.days); b.type = 'button'; b.setAttribute('aria-pressed', String(d === days));
			b.addEventListener('click', function () { days = d; Array.prototype.forEach.call(bar.children, function (x) { x.setAttribute('aria-pressed', String(x === b)); }); load(); });
			bar.appendChild(b);
		});
		app.textContent = ''; app.appendChild(bar); app.appendChild(out);
		function load() {
			Promise.all([get('overview', '?days=' + days), get('insights', '?days=' + days)]).then(function (res) {
				var o = res[0], n = res[1], k = o.kpis; out.textContent = '';
				var st = el('div', 'us-stats');
				st.appendChild(stat(S.visitors, nf.format(k.visitors))); st.appendChild(stat(S.sessions, nf.format(k.sessions))); st.appendChild(stat(S.pageviews, nf.format(k.pageviews)));
				st.appendChild(stat(S.avg, dur(k.avg_session))); st.appendChild(stat(S.engaged, dur(k.engaged))); out.appendChild(st);
				var dc = el('section', 'us-card'); dc.appendChild(el('h2', null, S.daily)); var ch = el('div', 'us-chart'); ch.setAttribute('role', 'img'); ch.setAttribute('aria-label', S.daily);
				var max = Math.max.apply(null, o.daily.map(function (p) { return p.pageviews; }).concat([1]));
				o.daily.forEach(function (p) { var i = el('i'); i.style.height = Math.max(1, p.pageviews / max * 100) + '%'; i.title = p.date + ': ' + p.pageviews; var b = el('b'); b.style.height = Math.min(100, p.sessions / Math.max(1, p.pageviews) * 100) + '%'; i.appendChild(b); ch.appendChild(i); });
				dc.appendChild(ch); out.appendChild(dc);
				var cols = el('div', 'us-cols');
				cols.appendChild(rank(S.pages, n.pages.map(function (p) { return { l: p.url, n: p.views }; })));
				cols.appendChild(rank(S.sources, o.referrers.map(function (r) { return { l: r.source, n: r.sessions }; })));
				cols.appendChild(rank(S.buttons, ins(n.buttons))); cols.appendChild(rank(S.links, ins(n.links))); cols.appendChild(rank(S.outbound, ins(n.outbound)));
				cols.appendChild(rank(S.downloads, ins(n.downloads))); cols.appendChild(rank(S.devices, ins(n.devices))); cols.appendChild(rank(S.browsers, ins(n.browsers)));
				out.appendChild(cols);
				var jc = el('section', 'us-card'); jc.appendChild(el('h2', null, S.journeys));
				if (!n.journeys.top.length) { jc.appendChild(el('p', 'us-empty', S.none)); }
				n.journeys.top.slice(0, 8).forEach(function (j) { jc.appendChild(el('p', null, nf.format(j.count) + '×  ' + j.steps.join('  →  '))); });
				out.appendChild(jc);
			}).catch(fail);
		}
		load();
	}

	function live() {
		var cur = { pv: 0, ev: 0 }, items = [], seen = {}, wrap = el('div'), st = el('div', 'us-stats'), cols = el('div', 'us-cols'), act = el('section', 'us-card'), feed = el('section', 'us-card'), ul = el('ul', 'us-feed'), tb = el('div'), upd = el('p', 'us-empty');
		ul.tabIndex = 0; ul.setAttribute('aria-label', S.feed); act.appendChild(el('h2', null, S.live_active)); act.appendChild(tb); feed.appendChild(el('h2', null, S.feed)); feed.appendChild(ul);
		cols.appendChild(act); cols.appendChild(feed); wrap.appendChild(st); wrap.appendChild(cols); wrap.appendChild(upd); app.textContent = ''; app.appendChild(wrap);
		function tick() {
			if (document.visibilityState === 'hidden') { return; }
			get('live', '?pv=' + cur.pv + '&ev=' + cur.ev).then(function (d) {
				cur = d.cursor; st.textContent = '';
				var li = d.active.filter(function (s) { return s.logged_in; }).length;
				st.appendChild(stat(S.active, nf.format(d.active.length))); st.appendChild(stat(S.loggedin, nf.format(li))); st.appendChild(stat(S.anon, nf.format(d.active.length - li))); st.appendChild(stat(S.pageviews + ' (1h)', nf.format(d.last_hour)));
				tb.textContent = '';
				if (!d.active.length) { tb.appendChild(el('p', 'us-empty', S.none)); } else {
					var t = el('table', 'us-t'), h = el('tr'); [S.current, S.time_on_site, S.device].forEach(function (x) { h.appendChild(el('th', null, x)); }); t.appendChild(h);
					d.active.forEach(function (s) { var r = el('tr'); r.appendChild(el('td', null, s.exit)); r.appendChild(el('td', null, dur(s.elapsed))); r.appendChild(el('td', null, [s.device, s.browser].filter(Boolean).join(' · '))); t.appendChild(r); });
					tb.appendChild(t);
				}
				var add = d.items.filter(function (i) { return !seen[i.key]; }); add.forEach(function (i) { seen[i.key] = 1; i.isNew = items.length > 0; });
				items = add.concat(items).slice(0, 80); ul.textContent = '';
				items.forEach(function (i) {
					var l = el('li', i.isNew ? 'n' : ''), tm = el('time', null, new Date(i.at).toLocaleTimeString(C.locale)); l.appendChild(tm);
					l.appendChild(document.createTextNode((i.logged_in ? S.loggedin : S.anon) + ' ' + (i.kind === 'pageview' ? S.viewed + ' ' + (i.title || i.url) : i.type + (i.name ? ': ' + i.name : '') + (i.url ? ' — ' + i.url : ''))));
					ul.appendChild(l);
				});
				upd.textContent = S.updated + ': ' + new Date(d.server).toLocaleTimeString(C.locale);
			}).catch(fail);
		}
		tick(); setInterval(tick, 5000);
	}
	if (C.scope === 'live') { live(); } else { stats(); }
}());
