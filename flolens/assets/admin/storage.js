/* FloLens Tools → Database & Storage. Vanilla JS: draws the stored figures and runs the maintenance actions through the REST routes
 * (Rest\StorageRoutes). It measures nothing by itself and sends no SQL; every request carries only numbers, dates or a fixed table name. */
(function () {
	'use strict';
	var cfg = window.userfloStorage || {};
	var api = window.wp && window.wp.apiFetch;
	var i18n = window.wp && window.wp.i18n;
	var root = document.getElementById('uf-storage');
	if (!api || !i18n || !root || !cfg.data) { return; }
	var __ = function (s) { return i18n.__(s, 'flolens'); };
	var sprintf = i18n.sprintf;
	var S = cfg.data;
	var can = !!cfg.canManage;
	var busy = false, driving = false;
	var draft = null; // What the person has picked but not saved yet, so a re-draw never loses it.
	var live, body;

	// ---- small helpers ----------------------------------------------------------------------------------------------------------------
	function h(tag, props) {
		var el = document.createElement(tag), i, k;
		props = props || {};
		for (k in props) {
			if (!Object.prototype.hasOwnProperty.call(props, k) || props[k] === null || props[k] === undefined || props[k] === false) { continue; }
			if (k === 'class') { el.className = props[k]; }
			else if (k === 'text') { el.textContent = props[k]; }
			else if (k.slice(0, 2) === 'on') { el.addEventListener(k.slice(2), props[k]); }
			else if (props[k] === true) { el.setAttribute(k, ''); }
			else { el.setAttribute(k, props[k]); }
		}
		for (i = 2; i < arguments.length; i++) { append(el, arguments[i]); }
		return el;
	}
	function append(el, c) {
		if (c === null || c === undefined || c === false) { return; }
		if (Array.isArray(c)) { c.forEach(function (x) { append(el, x); }); return; }
		el.appendChild(typeof c === 'string' || typeof c === 'number' ? document.createTextNode(String(c)) : c);
	}
	function num(n) { return Number(n).toLocaleString(cfg.locale || undefined); }
	function bytes(n) {
		if (n === null || n === undefined) { return __('Unavailable'); }
		var u = ['B', 'KB', 'MB', 'GB', 'TB'], i = 0, v = Number(n);
		while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
		return (i === 0 ? v : v.toFixed(v >= 100 ? 0 : 1)) + ' ' + u[i];
	}
	function when(ts) { var d = ts ? new Date(Number(ts) * 1000) : null; return d && !isNaN(d.getTime()) ? d.toLocaleString(cfg.locale || undefined) : '—'; }
	function announce(msg) { if (live) { live.textContent = ''; window.setTimeout(function () { live.textContent = msg; }, 30); } }
	function errText(e) { return (e && e.message) ? e.message : __('Something went wrong. Nothing was changed.'); }
	function sleep(ms) { return new Promise(function (r) { window.setTimeout(r, ms); }); }

	/* A modal confirmation. Uses the native <dialog> (focus is trapped and Escape closes it); where that is missing, the browser's own confirm(). */
	function confirmBox(o) {
		return new Promise(function (resolve) {
			var text = o.paragraphs.join('\n\n');
			if (typeof HTMLDialogElement !== 'function') { resolve(window.confirm(o.title + '\n\n' + text)); return; }
			var opener = document.activeElement;
			var id = 'uf-dlg-' + Date.now();
			var dlg = h('dialog', { class: 'uf-dialog', 'aria-labelledby': id + '-t', 'aria-describedby': id + '-d' },
				h('h2', { id: id + '-t', text: o.title }),
				h('div', { id: id + '-d' }, o.paragraphs.map(function (p) { return h('p', { text: p }); }), o.extra || null),
				h('div', { class: 'uf-dialog-actions' },
					h('button', { type: 'button', class: 'button', id: id + '-no', text: __('Cancel') }),
					h('button', { type: 'button', class: 'button ' + (o.danger ? 'button-link-delete uf-danger' : 'button-primary'), id: id + '-yes', text: o.confirm })));
			document.body.appendChild(dlg);
			function done(v) { dlg.close(); dlg.remove(); if (opener && opener.focus) { opener.focus(); } resolve(v); }
			dlg.querySelector('#' + id + '-no').addEventListener('click', function () { done(false); });
			dlg.querySelector('#' + id + '-yes').addEventListener('click', function () { done(true); });
			dlg.addEventListener('cancel', function (e) { e.preventDefault(); done(false); });
			dlg.showModal();
			dlg.querySelector('#' + id + '-no').focus(); // The safe choice has the focus.
		});
	}

	// ---- requests -------------------------------------------------------------------------------------------------------------------
	function call(path, method, data) {
		var o = { path: '/userflo/v1/storage' + path, method: method || 'GET' };
		if (data) { o.data = data; }
		return api(o);
	}
	function take(d) { S = d; render(); }
	function active() { return !!((S.retention && S.retention.job) || (S.summary && S.summary.rebuild && !S.summary.rebuild.finished)); }

	/* Keeps going while a purge or a rebuild is open, so it also resumes after the person left the page and came back. */
	async function drive() {
		if (driving) { return; }
		driving = true;
		try {
			while (active()) {
				var d = await call('/step', 'POST');
				var failed = d.result && d.result.errors && d.result.errors.length;
				take(d);
				if (failed) { announce(__('The cleanup stopped because of an error. It will try again at the next run.')); break; }
				await sleep(d.result && d.result.locked ? 3000 : 400);
			}
			if (!active()) { announce(__('Finished.')); }
		} catch (e) { announce(errText(e)); }
		driving = false;
		render();
	}

	async function act(label, fn) {
		if (busy) { return; }
		busy = true; render(); announce(label);
		try { var d = await fn(); take(d); busy = false; render(); if (active()) { drive(); } else { announce(__('Done.')); } }
		catch (e) { busy = false; render(); announce(errText(e)); }
	}

	// ---- sections -------------------------------------------------------------------------------------------------------------------
	function kindLabel(k) { return k === 'detail' ? __('Detailed data') : (k === 'summary' ? __('Summary / counters') : __('Settings & logs')); }

	function overview() {
		var t = S.totals, c = h('section', { class: 'uf-card', 'aria-labelledby': 'uf-st-h1' }, h('h2', { id: 'uf-st-h1', text: __('Storage used by FloLens') }));
		if (!S.measured_at || !t) {
			append(c, h('p', { text: __('FloLens has not measured its tables yet. Measuring reads the database’s own table statistics; it does not scan your data.') }));
		} else {
			var total = t.data + t.index;
			append(c, h('div', { class: 'uf-stats' },
				stat(bytes(total), __('Total used by FloLens tables'), __('Table data plus indexes. An index is the lookup structure that keeps reports fast.')),
				stat(bytes(t.detail), __('Detailed data'), __('Visits, page views, interactions and similar rows. Deleted when they pass the retention period.')),
				stat(bytes(t.summary), __('Summaries and counters'), __('Daily counts that stay after the detail is gone.')),
				stat(bytes(t.free), __('Free space inside tables'), __('Left behind by deleted rows. The database reuses it for new rows; the files only shrink if a table is rebuilt.'))));
			var src = S.source === 'information_schema' ? __('Source: the database’s table statistics.') : (S.source === 'show_table_status' ? __('Source: the database’s table status (your host does not expose the usual statistics view).') : '');
			append(c, h('p', { class: 'description' }, sprintf(__('Measured %s.'), when(S.measured_at)) + ' ' + src + ' ' +
				__('Sizes are measurements of space the database has allocated, taken at that moment. Row counts are estimates: the database does not count every row.')));
			if (S.source === 'none') { append(c, h('p', { class: 'uf-err', role: 'alert', text: __('Your host does not let FloLens read table sizes, so nothing is shown here. Nothing is guessed.') })); }
		}
		append(c, h('p', null, h('button', { type: 'button', class: 'button', disabled: busy, onclick: function () { act(__('Measuring…'), function () { return call('/refresh', 'POST'); }); }, text: S.measured_at ? __('Refresh measurement') : __('Measure now') })));
		return c;
	}
	function stat(v, l, hint) { return h('div', { class: 'uf-stat' }, h('div', { class: 'uf-stat-v', text: v }), h('div', { class: 'uf-stat-l', text: l }), h('div', { class: 'description', text: hint })); }

	function inventory() {
		var rows = S.tables.map(function (t) {
			var tot = t.available ? t.data + t.index : null;
			return h('tr', null,
				h('th', { scope: 'row' }, h('strong', { text: t.label }), h('br'), h('span', { class: 'description', text: t.purpose })),
				h('td', null, h('span', { class: 'uf-badge uf-kind-' + t.kind, text: kindLabel(t.kind) })),
				h('td', { class: 'uf-num', text: t.available && t.rows !== null ? (t.exact_rows ? '' : '≈ ') + num(t.rows) : __('Unavailable') }),
				h('td', { class: 'uf-num', text: bytes(t.data) }),
				h('td', { class: 'uf-num', text: bytes(t.index) }),
				h('td', { class: 'uf-num', text: bytes(tot) }),
				h('td', { class: 'uf-num', text: bytes(t.free) }),
				can ? h('td', null, t.can_rebuild && t.free > 1048576 ? h('button', { type: 'button', class: 'button button-small', disabled: busy || active(), 'aria-label': sprintf(__('Rebuild %s to release free space'), t.label), onclick: function () { rebuildTable(t); }, text: __('Rebuild') }) : '') : null);
		});
		return h('section', { class: 'uf-card', 'aria-labelledby': 'uf-st-h2' },
			h('h2', { id: 'uf-st-h2', text: __('Tables') }),
			S.measured_at ? null : h('p', { text: __('Measure the tables to see sizes.') }),
			h('div', { class: 'uf-scroll' }, h('table', { class: 'widefat striped uf-inv' },
				h('caption', { class: 'screen-reader-text', text: __('FloLens database tables with their purpose, estimated rows and sizes') }),
				h('thead', null, h('tr', null,
					h('th', { scope: 'col', text: __('Table') }), h('th', { scope: 'col', text: __('Type') }), h('th', { scope: 'col', class: 'uf-num', text: __('Rows (estimate)') }),
					h('th', { scope: 'col', class: 'uf-num', text: __('Data') }), h('th', { scope: 'col', class: 'uf-num', text: __('Indexes') }), h('th', { scope: 'col', class: 'uf-num', text: __('Total') }),
					h('th', { scope: 'col', class: 'uf-num', text: __('Free inside') }), can ? h('th', { scope: 'col', text: __('Action') }) : null)),
				h('tbody', null, rows))),
			h('p', { class: 'description', text: __('“Detailed data” grows with traffic and is removed when it expires. “Summary / counters” stay small. A table the database does not report is shown as unavailable, never as zero.') }));
	}

	function retention() {
		var st = S.settings, days = st.retention_days;
		var c = h('section', { class: 'uf-card', 'aria-labelledby': 'uf-st-h3' }, h('h2', { id: 'uf-st-h3', text: __('Detailed data retention') }));
		append(c, h('p', { text: days > 0 ? sprintf(__('Detailed data is deleted after %d days. This is the saved setting.'), days) : __('Detailed data is kept forever. This is the saved setting.') }));
		append(c, h('details', { class: 'uf-explain' }, h('summary', { text: __('What is deleted, and what stays?') }),
			h('ul', null,
				h('li', { text: __('Deleted when older than the period: visitors, visits (sessions), page views, interactions (clicks, scrolls, downloads, forms, custom events), goal completions and the sales mirror.') }),
				h('li', { text: __('Stays: the daily summaries (totals, top pages, sources, countries, devices, channels, goal completions, sales per currency, interaction counts and custom event counts per day) for the number of years set in Settings → Privacy, heatmap counters and bot counters (each with its own period).') }),
				h('li', { text: __('Effect on screens: anything that shows individual visits, journeys, visitor profiles or per-element detail only reaches back as far as the period. Year-on-year comparisons keep working from the summaries.') }),
				h('li', { text: __('Changing the period does not delete anything by itself. Data older than the new period is removed at the next cleanup run (daily, or when you run it below). Deleted data cannot be recovered; a backup is the only way back.') }))));
		if (!can) { append(c, h('p', { class: 'description', text: __('Only administrators can change these settings.') })); return c; }
		var chosen = draft ? draft.profile : st.profile;
		var custom = h('input', { type: 'number', min: 1, max: 3650, id: 'uf-st-custom', class: 'small-text', value: draft ? draft.custom : (days > 0 ? days : 180), 'aria-label': __('Custom number of days'),
			oninput: function () { keep(); } });
		var short = h('input', { type: 'number', min: 0, max: 3650, id: 'uf-st-short', class: 'small-text', value: draft ? draft.short : st.short_event_days, oninput: function () { keep(); } });
		var opts = [['minimal', __('Minimal'), sprintf(__('%d days'), st.presets.minimal)], ['standard', __('Standard'), sprintf(__('%d days'), st.presets.standard)], ['long', __('Long'), sprintf(__('%d days'), st.presets.long)], ['custom', __('Custom'), null], ['forever', __('Keep forever'), null]];
		var radios = opts.map(function (o) {
			var id = 'uf-st-p-' + o[0];
			return h('p', null, h('label', { for: id }, h('input', { type: 'radio', name: 'uf-st-profile', id: id, value: o[0], checked: chosen === o[0], onchange: function () { keep(); } }), ' ', h('strong', { text: o[1] }), o[2] ? ' — ' + o[2] : '', o[0] === 'custom' ? [' ', custom, ' ', __('days')] : ''));
		});
		append(c, h('fieldset', { class: 'uf-fieldset' }, h('legend', { text: __('How long to keep detailed data') }), radios,
			h('p', { class: 'description', text: __('Your current setting is selected. Choosing a profile changes nothing until you press Save.') })));
		append(c, h('p', null, h('label', { for: 'uf-st-short' }, __('Keep clicks and scroll events for only'), ' ', short, ' ', __('days (0 = as long as everything else)'))));
		append(c, h('p', { class: 'description', text: __('Clicks and scrolls are the most numerous rows. Their daily totals stay in the summaries. A shorter period means the per-element click lists reach back less far.') }));
		append(c, h('p', null, h('a', { href: cfg.settings, text: __('Daily summaries are kept for the number of years set in Settings → Privacy.') }), ' ', sprintf(__('Now: %d years.'), st.summary_years)));
		append(c, h('p', null, h('button', { type: 'button', class: 'button button-primary', disabled: busy, onclick: function () { saveRetention(custom, short); }, text: __('Save retention setting') })));
		return c;
	}

	function keep() {
		var sel = document.querySelector('input[name="uf-st-profile"]:checked'), c = document.getElementById('uf-st-custom'), sh = document.getElementById('uf-st-short');
		draft = { profile: sel ? sel.value : 'custom', custom: c ? c.value : '', short: sh ? sh.value : '0' };
	}

	async function saveRetention(custom, short) {
		var sel = document.querySelector('input[name="uf-st-profile"]:checked');
		var pick = sel ? sel.value : 'custom', p = S.settings.presets, days;
		days = pick === 'forever' ? 0 : (p[pick] || parseInt(custom.value, 10));
		if (!(days >= 0 && days <= 3650) || (pick === 'custom' && !(days >= 1))) { announce(__('Enter a number of days between 1 and 3650.')); custom.focus(); return; }
		var sh = parseInt(short.value, 10) || 0;
		if (sh < 0 || sh > 3650) { announce(__('Enter a number of days between 0 and 3650.')); short.focus(); return; }
		var payload = { days: days, short_event_days: sh };
		busy = true; render(); announce(__('Saving…'));
		try {
			var d;
			try { d = await call('/retention', 'POST', payload); }
			catch (e) {
				if (e && e.code === 'userflo_confirm') {
					busy = false; render();
					var ok = await confirmBox({ title: __('Make data eligible for deletion?'), danger: true, confirm: __('Save and allow deletion'), paragraphs: [e.message, impactText(e.data && e.data.impact), __('Nothing is deleted when you save. The deletion happens at the next cleanup run, and it cannot be undone.')] });
					if (!ok) { announce(__('Not saved.')); return; }
					busy = true; render();
					d = await call('/retention', 'POST', Object.assign({ confirm: true }, payload));
				} else { throw e; }
			}
			draft = null;
			take(d); busy = false; render();
			announce(__('Saved. The new period applies from the next cleanup run.'));
		} catch (e2) { busy = false; render(); announce(errText(e2)); }
	}
	function impactText(imp) {
		if (!imp) { return ''; }
		var out = [];
		['main', 'short'].forEach(function (k) {
			var i = imp[k]; if (!i) { return; }
			var cap = i.capped ? __('more than ') : '';
			out.push((k === 'short' ? __('Clicks and scrolls period: ') : __('Main period: ')) + sprintf(__('%1$s interactions, %2$s page views, %3$s visits are older than the new period.'), cap + num(i.events), cap + num(i.pageviews), cap + num(i.sessions)));
		});
		return out.join(' ');
	}

	function actions() {
		var c = h('section', { class: 'uf-card', 'aria-labelledby': 'uf-st-h4' }, h('h2', { id: 'uf-st-h4', text: __('Cleanup and maintenance') }));
		if (!can) { append(c, h('p', { text: __('Only administrators can run these actions.') })); return c; }
		var job = S.retention.job, rb = S.summary.rebuild, blocked = busy || active();
		append(c, h('h3', { text: __('Run the retention cleanup now') }));
		append(c, h('p', { text: __('Does what the daily cleanup does: writes any missing daily summary first, then deletes detailed data older than the retention period, in small batches. Safe to repeat.') }));
		append(c, h('p', null, h('button', { type: 'button', class: 'button', disabled: blocked, onclick: function () { act(__('Running the cleanup…'), function () { return call('/run', 'POST'); }); }, text: __('Run cleanup now') })));

		append(c, h('h3', { text: __('Delete detailed data older than a date') }));
		append(c, h('p', { text: sprintf(__('Removes visitors, visits, page views, interactions and goal completions from before the date you choose. Daily summaries stay. The last %d days cannot be chosen.'), S.retention.min_days) }));
		var max = new Date(Date.now() - S.retention.min_days * 86400000).toISOString().slice(0, 10);
		var date = h('input', { type: 'date', id: 'uf-st-date', max: max });
		append(c, h('p', null, h('label', { for: 'uf-st-date', text: __('Delete data from before') }), ' ', date, ' ', h('button', { type: 'button', class: 'button uf-danger', disabled: blocked, onclick: function () { purge(date); }, text: __('Review and delete…') })));

		append(c, h('h3', { text: __('Rebuild daily summaries') }));
		append(c, h('p', { text: __('Writes the summary of each chosen day again from the detailed data that is still stored. Days whose detailed data is already gone are skipped and their summaries are never changed. Safe to repeat. The rebuild uses your exclusion rules as they are today.') }));
		var from = h('input', { type: 'date', id: 'uf-st-from', value: S.summary.floor }), to = h('input', { type: 'date', id: 'uf-st-to', max: cfg.today, value: new Date(new Date(cfg.today + 'T12:00:00Z').getTime() - 86400000).toISOString().slice(0, 10) });
		append(c, h('p', null, h('label', { for: 'uf-st-from', text: __('From') }), ' ', from, ' ', h('label', { for: 'uf-st-to', text: __('To') }), ' ', to, ' ', h('button', { type: 'button', class: 'button', disabled: blocked || !S.summary.enabled, onclick: function () { rebuild(from, to); }, text: __('Rebuild summaries…') })));
		if (!S.summary.enabled) { append(c, h('p', { class: 'description', text: __('Daily summaries are switched off in Settings → Privacy.') })); }

		if (job) {
			var d = job.deleted || {};
			append(c, h('div', { class: 'uf-job', role: 'group', 'aria-label': __('Cleanup in progress') },
				h('p', null, h('strong', { text: sprintf(__('Deleting data from before %s…'), job.date) })),
				h('p', { text: sprintf(__('So far: %1$s interactions, %2$s page views, %3$s visits, %4$s visitors. The total is not known in advance, so no percentage is shown. You can leave this page; the cleanup continues in the background.'), num(d.events || 0), num(d.pageviews || 0), num(d.sessions || 0), num(d.visitors || 0)) })));
		}
		if (rb && !rb.finished) {
			append(c, h('div', { class: 'uf-job', role: 'group', 'aria-label': __('Rebuild in progress') },
				h('p', null, h('strong', { text: sprintf(__('Rebuilding summaries from %1$s to %2$s…'), rb.from, rb.to) })),
				h('p', { text: sprintf(__('Next day: %1$s. Written: %2$d, skipped: %3$d, failed: %4$d.'), rb.next, rb.done, rb.skipped, rb.failed) })));
		}
		return c;
	}

	async function purge(date) {
		if (!date.value) { announce(__('Choose a date first.')); date.focus(); return; }
		var imp;
		try { imp = await call('/impact?date=' + encodeURIComponent(date.value), 'GET'); } catch (e) { announce(errText(e)); return; }
		var i = imp.impact, cap = i.capped ? __('more than ') : '';
		var ok = await confirmBox({
			title: __('Delete detailed data?'), danger: true, confirm: __('Delete permanently'),
			paragraphs: [
				sprintf(__('Everything older than %s will be deleted.'), imp.cutoff_local),
				sprintf(__('About %1$s interactions, %2$s page views, %3$s visits and %4$s goal completions are affected.'), cap + num(i.events), cap + num(i.pageviews), cap + num(i.sessions), cap + num(i.goal_hits)),
				__('The daily summaries of those days stay, so totals, top pages, sources, goals and sales per day remain available. Visits, journeys, visitor profiles and per-click detail for those days will be gone. This cannot be undone.')]
		});
		if (!ok) { announce(__('Cancelled. Nothing was deleted.')); return; }
		act(__('Deleting in batches…'), function () { return call('/purge', 'POST', { date: date.value, confirm: true }); });
	}

	async function rebuild(from, to) {
		if (!from.value || !to.value || from.value > to.value) { announce(__('Choose a valid range of dates.')); from.focus(); return; }
		var ok = await confirmBox({
			title: __('Rebuild daily summaries?'), confirm: __('Rebuild'),
			paragraphs: [
				sprintf(__('Summaries for %1$s to %2$s are written again from the detailed data that is still stored.'), from.value, to.value),
				sprintf(__('Days before %s no longer have all their detailed data. They are skipped and their existing summaries are kept.'), S.summary.floor),
				__('Large ranges are processed in steps in the background. The dashboards keep working while this runs.')]
		});
		if (!ok) { return; }
		act(__('Rebuilding…'), function () { return call('/rebuild', 'POST', { from: from.value, to: to.value, confirm: true }); });
	}

	async function rebuildTable(t) {
		var ok = await confirmBox({
			title: sprintf(__('Rebuild “%s”?'), t.label), confirm: __('Rebuild table'),
			paragraphs: [
				__('This copies the table to give back the free space deleted rows left behind. It can take a while on a large table and slows reports while it runs. Tracking keeps working.'),
				__('The files only shrink if your database setting allows it; the free space may be reused instead. Run it at a quiet time.')]
		});
		if (!ok) { return; }
		act(__('Rebuilding the table…'), function () { return call('/optimize', 'POST', { table: t.key, confirm: true }); });
	}

	function status() {
		var r = S.retention, sm = S.summary, rows = [];
		rows.push([__('Last successful cleanup'), r.last_ok ? when(r.last_ok) + (r.last_counts ? ' — ' + sprintf(__('%1$s interactions, %2$s page views, %3$s visits deleted in that run'), num(r.last_counts.events), num(r.last_counts.pageviews), num(r.last_counts.sessions)) : '') : __('Not yet')]);
		rows.push([__('Last cleanup attempt'), when(r.last_run)]);
		rows.push([__('Next scheduled cleanup'), r.next ? when(r.next) : __('Not scheduled yet')]);
		rows.push([__('Last successful aggregation'), sm.last_ok ? when(sm.last_ok) + (sm.last_ok_day ? ' — ' + sprintf(__('day %s'), sm.last_ok_day) : '') : __('Not yet')]);
		rows.push([__('Oldest daily summary'), sm.first_day || '—']);
		if (S.maintenance && S.maintenance.at) { rows.push([__('Last table rebuild'), when(S.maintenance.at) + ' — ' + (S.maintenance.ok ? sprintf(__('%1$s to %2$s'), bytes(S.maintenance.before), bytes(S.maintenance.after)) : __('failed'))]); }
		if (r.last_job) { rows.push([__('Last manual deletion'), when(r.last_job.finished) + ' — ' + sprintf(__('data from before %s'), r.last_job.date)]); }
		var c = h('section', { class: 'uf-card', 'aria-labelledby': 'uf-st-h5' }, h('h2', { id: 'uf-st-h5', text: __('Latest results') }),
			h('table', { class: 'widefat striped' }, h('tbody', null, rows.map(function (x) { return h('tr', null, h('th', { scope: 'row', text: x[0] }), h('td', { text: x[1] })); }))));
		var errs = [];
		if (r.last_error) { errs.push(sprintf(__('The last cleanup had a problem (%1$s) at %2$s. The data of that step is kept and the next run tries again.'), r.last_error.what, when(r.last_error.at))); }
		if (sm.error) { errs.push(sprintf(__('The summary for %1$s could not be written (%2$s). Its detailed data is kept until it can be.'), sm.error.day, sm.error.why)); }
		if (sm.upgrade) { errs.push(sprintf(__('After the update, daily summaries are being refreshed from %s. Detailed data from that day on is kept until this finishes.'), sm.upgrade.next)); }
		if (errs.length) { append(c, h('div', { class: 'notice notice-warning inline', role: 'alert' }, errs.map(function (e) { return h('p', { text: e }); }))); }
		else { append(c, h('p', { class: 'description', text: __('No outstanding maintenance or aggregation problems.') })); }
		return c;
	}

	function growth() {
		var g = S.growth, c = h('section', { class: 'uf-card', 'aria-labelledby': 'uf-st-h6' }, h('h2', { id: 'uf-st-h6', text: __('Growth') }));
		if (!g.enough) {
			append(c, h('p', { text: __('A growth estimate needs at least five daily measurements spread over a week or more. FloLens measures once a day, so it will appear by itself.') + ' ' + sprintf(__('Measurements so far: %d.'), g.points) }));
			return c;
		}
		append(c, h('p', { text: sprintf(__('Over the last %s days the detailed tables changed by about %s per day (roughly %s per week, %s per 30 days).'), g.days, bytes(g.per_day), bytes(g.per_week), bytes(g.per_month)) }));
		append(c, h('p', { class: 'description', text: __('This is an estimate from your own past measurements, not a prediction. Traffic changes, and once data starts to expire the tables stop growing and level off. A negative number means they shrank.') }));
		return c;
	}

	function notes() {
		return h('section', { class: 'uf-card' }, h('h2', { text: __('About disk space') }),
			h('p', { text: __('Deleting rows frees space inside a table. The database reuses that space for new rows, so the table files often stay the same size. A rebuild gives the space back to the server, but it is slow on large tables, so FloLens never does it automatically.') }),
			h('p', { text: __('Sizes shown here are what the database reports for FloLens’s own tables. They do not include WordPress’s other tables or the size of backups.') }));
	}

	function render() {
		var focus = document.activeElement && document.activeElement.id;
		if (!live) {
			live = h('div', { id: 'uf-st-live', class: 'screen-reader-text', role: 'status', 'aria-live': 'polite', 'aria-atomic': 'true' });
			body = h('div', { id: 'uf-st-body' });
			root.appendChild(h('p', { class: 'uf-intro', text: __('See how much space FloLens uses, how long detailed data is kept, and what remains afterwards. Nothing on this screen deletes anything unless you confirm it.') }));
			root.appendChild(live); root.appendChild(body);
		}
		body.textContent = '';
		[overview(), inventory(), retention(), actions(), status(), growth(), notes()].forEach(function (s) { body.appendChild(s); });
		if (busy) { body.setAttribute('aria-busy', 'true'); } else { body.removeAttribute('aria-busy'); }
		if (focus) { var f = document.getElementById(focus); if (f && f.focus) { f.focus(); } }
	}

	render();
	if (can && active()) { drive(); } // A job was left running: pick it up.
}());
