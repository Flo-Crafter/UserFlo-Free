/* UserFlo Settings → Translations: language list and string editor. Stored on the server; English is the source of truth. */
(function () {
	'use strict';
	var api = window.wp && window.wp.apiFetch, root = document.getElementById('uf-translations'), cfg = window.userfloTr || {};
	if (!api || !root) { return; }
	var __ = function (s) { return window.wp.i18n ? window.wp.i18n.__(s, 'userflo') : s; };
	function el(tag, cls, text) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (text != null) { e.textContent = text; } return e; }
	function btn(label, cls, fn) { var b = el('button', 'button ' + (cls || ''), label); b.type = 'button'; if (fn) { b.addEventListener('click', fn); } return b; }
	function ph(s) { return (String(s).match(/%(?:\d+\$)?[sdf]/g) || []).sort().join(','); }
	function msg(text, err) { var m = el('div', 'notice inline ' + (err ? 'notice-error' : 'notice-success')); m.setAttribute('role', err ? 'alert' : 'status'); m.appendChild(el('p', null, text)); return m; }

	// ---- language list ------------------------------------------------------------
	function list(note) {
		root.textContent = '';
		var head = el('div', 'uf-tr-head'), left = el('div');
		left.appendChild(el('h3', null, __('Languages')));
		left.appendChild(el('p', 'uf-sub', __('Every piece of text UserFlo shows, in every language it has. English is the source of truth and anything untranslated falls back to it, so a half-finished translation is still worth saving. Changes here are stored on the server and survive an upgrade.')));
		head.appendChild(left); head.appendChild(btn('+ ' + __('New language'), 'button-primary', newForm));
		root.appendChild(head);
		if (note) { root.appendChild(note); }
		var holder = el('div'); root.appendChild(holder);
		api({ path: '/userflo/v1/translations' }).then(function (r) {
			var t = el('table', 'widefat striped uf-table'), hr = el('tr');
			[__('Language'), __('Code'), __('Translated'), __('Changed here'), ''].forEach(function (x) { hr.appendChild(el('th', null, x)); });
			t.appendChild(el('thead')).appendChild(hr);
			var tb = el('tbody');
			r.languages.forEach(function (l) {
				var tr = el('tr'), n = el('td'); n.appendChild(el('strong', null, l.name)); tr.appendChild(n);
				tr.appendChild(el('td', null, l.code)); tr.appendChild(el('td', null, l.percent + '%'));
				tr.appendChild(el('td', null, l.changed ? l.changed + ' ' + __('edited') : (l.shipped ? __('as shipped') : __('new'))));
				var act = el('td', 'uf-tr-actions');
				var ed = btn(__('Edit'), '', function () { edit(l.code); }); ed.setAttribute('aria-label', __('Edit') + ' ' + l.name); act.appendChild(ed);
				var dl = el('a', 'button', '↓'); dl.href = cfg.export + '?action=userflo_translations_export&code=' + encodeURIComponent(l.code) + '&_wpnonce=' + encodeURIComponent(cfg.nonce); dl.setAttribute('aria-label', __('Download .po file') + ': ' + l.name); dl.title = __('Download .po file'); act.appendChild(dl);
				tr.appendChild(act); tb.appendChild(tr);
			});
			t.appendChild(tb); holder.appendChild(t);
		}, function (e) { holder.appendChild(msg(e.message, true)); });
	}

	function newForm() {
		var code = el('input'); code.type = 'text'; code.placeholder = 'de_DE'; code.maxLength = 6; code.setAttribute('aria-label', __('Language code'));
		var name = el('input'); name.type = 'text'; name.placeholder = 'Deutsch'; name.setAttribute('aria-label', __('Name, in its own language'));
		var out = el('div');
		var box = el('div', 'uf-card uf-tr-new');
		box.appendChild(el('h4', null, __('New language')));
		box.appendChild(el('p', 'description', __('Use a locale code such as de_DE, it_IT or pt. Start empty: untranslated text shows in English.')));
		var row = el('p'); row.appendChild(code); row.appendChild(document.createTextNode(' ')); row.appendChild(name); row.appendChild(document.createTextNode(' '));
		row.appendChild(btn(__('Create'), 'button-primary', function () {
			api({ path: '/userflo/v1/translations', method: 'POST', data: { code: code.value.trim(), name: name.value.trim() } }).then(function () { edit(code.value.trim()); }, function (e) { out.textContent = ''; out.appendChild(msg(e.message, true)); });
		}));
		row.appendChild(btn(__('Cancel'), '', function () { list(); }));
		box.appendChild(row); box.appendChild(out);
		var old = root.querySelector('.uf-tr-new'); if (old) { old.remove(); }
		root.insertBefore(box, root.children[1] || null); code.focus();
	}

	// ---- editor -----------------------------------------------------------------------
	function edit(code) {
		root.textContent = ''; root.appendChild(el('p', 'uf-loading', __('Loading…')));
		api({ path: '/userflo/v1/translations/' + encodeURIComponent(code) }).then(function (d) { editor(d); }, function (e) { root.textContent = ''; root.appendChild(msg(e.message, true)); });
	}

	function editor(d) {
		var changes = {}, dirty = false, current = {}, shipped = {}, open = {};
		d.sections.forEach(function (s) { s.items.forEach(function (it) { current[it.k] = it.c !== '' ? it.c : it.s; shipped[it.k] = it.s; }); });
		root.textContent = '';
		var top = el('div', 'uf-tr-top'), title = el('h3'); title.appendChild(document.createTextNode(d.name + ' ')); title.appendChild(el('small', null, d.code));
		var count = el('span', 'uf-sub'); top.appendChild(title); top.appendChild(count);
		var acts = el('span', 'uf-tr-topacts'), saveB = btn(__('Save'), 'button-primary', save), closeB = btn(__('Close'), '', function () { if (!dirty || window.confirm(__('Discard unsaved changes?'))) { list(); } });
		acts.appendChild(closeB); acts.appendChild(saveB); top.appendChild(acts); root.appendChild(top);
		var status = el('div'); root.appendChild(status);

		var tools = el('div', 'uf-card uf-tr-tools');
		var nameIn = el('input'); nameIn.type = 'text'; nameIn.value = d.name; nameIn.maxLength = 60;
		var nameL = el('label', null, __('Name, in its own language')); nameL.appendChild(nameIn);
		var find = el('input'); find.type = 'search'; find.placeholder = __('Key, English text, or your translation'); var findL = el('label', null, __('Find a string')); findL.appendChild(find);
		var miss = el('input'); miss.type = 'checkbox'; var missL = el('label', 'uf-tr-miss'); missL.appendChild(miss); missL.appendChild(document.createTextNode(' ' + __('Only what is missing')));
		var notes = el('textarea'); notes.rows = 3; notes.value = d.notes; var det = el('details'); det.appendChild(el('summary', null, __('Notes for whoever translates this'))); det.appendChild(notes);
		var line = el('div', 'uf-tr-toolline'); line.appendChild(nameL); line.appendChild(findL); line.appendChild(missL); tools.appendChild(line); tools.appendChild(det); root.appendChild(tools);
		[find, nameIn].forEach(function (i) { i.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } }); });

		var bar = el('div', 'uf-tr-bar'), secCount = el('span', 'uf-sub', d.sections.length + ' ' + __('sections'));
		var ex = btn(__('Expand all'), 'button-link', function () { d.sections.forEach(function (s) { open[s.id] = true; }); renderSections(); });
		var co = btn(__('Collapse all'), 'button-link', function () { open = {}; renderSections(); });
		bar.appendChild(secCount); bar.appendChild(ex); bar.appendChild(co); root.appendChild(bar);
		var wrap = el('div', 'uf-tr-sections'); root.appendChild(wrap);
		var foot = el('p'); if (d.code !== 'en_US') { foot.appendChild(btn(Object.keys(shipped).some(function (k) { return shipped[k] !== ''; }) && d.sections.length ? __('Reset my edits') : __('Delete language'), 'button-link-delete', function () {
			if (window.confirm(__('Remove all edits made here for this language? Shipped translations stay.'))) { api({ path: '/userflo/v1/translations/' + encodeURIComponent(d.code), method: 'DELETE' }).then(function () { list(); }); }
		})); } else { foot.appendChild(btn(__('Reset my edits'), 'button-link-delete', function () { if (window.confirm(__('Remove all edits made here for this language? Shipped translations stay.'))) { api({ path: '/userflo/v1/translations/en_US', method: 'DELETE' }).then(function () { list(); }); } })); }
		root.appendChild(foot);

		function effective(k) { return Object.prototype.hasOwnProperty.call(changes, k) ? changes[k] : current[k]; }
		function stats() {
			var done = 0, total = 0; d.sections.forEach(function (s) { s.items.forEach(function (it) { total++; if (d.code === 'en_US' || effective(it.k)) { done++; } }); });
			count.textContent = done + ' ' + __('of') + ' ' + total + ' ' + __('translated'); saveB.disabled = !dirty;
		}
		function match(it) {
			var v = find.value.trim().toLowerCase();
			if (miss.checked && (d.code === 'en_US' || effective(it.k))) { return false; }
			return !v || it.k.toLowerCase().indexOf(v) > -1 || String(effective(it.k) || '').toLowerCase().indexOf(v) > -1;
		}
		function row(it) {
			var r = el('div', 'uf-tr-row'), en = el('div', 'uf-tr-en', it.k), ta = el('textarea'); ta.value = effective(it.k) || '';
			ta.rows = Math.min(8, Math.max(1, Math.ceil(Math.max(it.k.length, ta.value.length) / 60))); ta.setAttribute('aria-label', __('Translation of') + ': ' + it.k);
			if (d.code !== 'en_US' && !ta.value) { ta.placeholder = __('Untranslated: shows the English text'); }
			var warn = el('div', 'uf-err'); warn.setAttribute('role', 'alert');
			ta.addEventListener('input', function () {
				var v = ta.value;
				if (v === (current[it.k] || '')) { delete changes[it.k]; } else { changes[it.k] = v; }
				warn.textContent = v && ph(it.k) !== ph(v) ? __('Keep the same placeholders as the English text:') + ' ' + (ph(it.k) || '—') : '';
				r.classList.toggle('is-edited', Object.prototype.hasOwnProperty.call(changes, it.k));
				dirty = Object.keys(changes).length > 0; stats();
			});
			r.classList.toggle('is-edited', Object.prototype.hasOwnProperty.call(changes, it.k) || it.c !== '');
			r.appendChild(en); r.appendChild(ta); r.appendChild(warn); return r;
		}
		function renderSections() {
			wrap.textContent = '';
			var searching = find.value.trim() !== '' || miss.checked;
			d.sections.forEach(function (s) {
				var items = s.items.filter(match); if (searching && !items.length) { return; }
				var det = el('section', 'uf-tr-sec'), isOpen = open[s.id] || (searching && items.length > 0);
				var h = el('button', 'uf-tr-sechead'); h.type = 'button'; h.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
				var done = 0; s.items.forEach(function (it) { if (d.code === 'en_US' || effective(it.k)) { done++; } });
				h.appendChild(el('span', 'uf-tr-chev', isOpen ? '▾' : '▸')); h.appendChild(el('span', 'uf-tr-label', s.label));
				var prog = el('span', 'uf-tr-prog'), pb = el('span', 'uf-tr-progbar'), pi = el('i'); pi.style.width = (100 * done / s.items.length) + '%'; pb.appendChild(pi);
				prog.appendChild(el('span', null, done + ' / ' + s.items.length)); prog.appendChild(pb); h.appendChild(prog);
				h.addEventListener('click', function () { open[s.id] = !open[s.id]; renderSections(); });
				det.appendChild(h);
				if (isOpen) { var body = el('div', 'uf-tr-body'); items.slice(0, 300).forEach(function (it) { body.appendChild(row(it)); }); if (items.length > 300) { body.appendChild(el('p', 'uf-sub', __('Showing the first 300 matches; refine the search.'))); } det.appendChild(body); }
				wrap.appendChild(det);
			});
			if (!wrap.children.length) { wrap.appendChild(el('p', 'uf-empty', __('No strings match.'))); }
		}
		find.addEventListener('input', renderSections); miss.addEventListener('change', renderSections);
		[nameIn, notes].forEach(function (i) { i.addEventListener('input', function () { dirty = true; stats(); }); });
		function save() {
			saveB.disabled = true; status.textContent = '';
			api({ path: '/userflo/v1/translations/' + encodeURIComponent(d.code), method: 'POST', data: { name: nameIn.value, notes: notes.value, strings: changes } }).then(function (r) {
				var note = msg(__('Saved.') + ' ' + r.saved + ' ' + __('edited') + (r.rejected && r.rejected.length ? ' · ' + r.rejected.length + ' ' + __('rejected because their placeholders do not match the English text') : ''), r.rejected && r.rejected.length > 0);
				api({ path: '/userflo/v1/translations/' + encodeURIComponent(d.code) }).then(function (nd) { editor(nd); root.insertBefore(note, root.children[1]); });
			}, function (e) { status.appendChild(msg(e.message, true)); saveB.disabled = false; });
		}
		stats(); renderSections();
	}
	list();
}());
