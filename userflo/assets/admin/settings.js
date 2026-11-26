/* UserFlo Settings: tabs, help guides in a modal, logs and system information. Vanilla JS (wp.apiFetch). */
(function () {
	'use strict';
	var api = window.wp && window.wp.apiFetch, i18n = window.wp && window.wp.i18n;
	var __ = function (s) { return i18n ? i18n.__(s, 'userflo') : s; };
	var $ = function (sel, root) { return (root || document).querySelector(sel); };
	var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
	function el(tag, cls, text) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (text != null) { e.textContent = text; } return e; }

	// ---- tabs ------------------------------------------------------------
	var buttons = $$('.uf-tabbtn'), panels = $$('.uf-tabpanel'), bar = $('.uf-savebar');
	if (!buttons.length) { return; }
	var ids = buttons.map(function (b) { return b.getAttribute('data-tab'); });
	function show(id, focus) {
		if (ids.indexOf(id) < 0) { id = ids[0]; }
		buttons.forEach(function (b) { var on = b.getAttribute('data-tab') === id; b.setAttribute('aria-selected', on ? 'true' : 'false'); b.tabIndex = on ? 0 : -1; b.classList.toggle('is-active', on); if (on && focus) { b.focus(); } });
		panels.forEach(function (p) { var pid = p.getAttribute('data-panel').replace(/-editor$/, ''); p.hidden = pid !== id; });
		if (bar) { bar.hidden = ['help', 'about', 'translations', 'feedback', 'support', 'compare'].indexOf(id) > -1; }
		try { window.history.replaceState(null, '', '#' + id); } catch (e) { /* ignore */ }
		var ref = $('input[name="_wp_http_referer"]');
		if (ref) { ref.value = ref.value.replace(/([?&])tab=[^&]*&?/, '$1').replace(/[?&]$/, '') + (ref.value.indexOf('?') > -1 ? '&' : '?') + 'tab=' + id; }
	}
	buttons.forEach(function (b) {
		b.addEventListener('click', function () { show(b.getAttribute('data-tab')); });
		b.addEventListener('keydown', function (e) {
			var i = ids.indexOf(b.getAttribute('data-tab')), n = null;
			if (e.key === 'ArrowDown' || e.key === 'ArrowRight') { n = ids[(i + 1) % ids.length]; }
			else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') { n = ids[(i + ids.length - 1) % ids.length]; }
			else if (e.key === 'Home') { n = ids[0]; } else if (e.key === 'End') { n = ids[ids.length - 1]; }
			if (n) { e.preventDefault(); show(n, true); }
		});
	});
	var q = new URLSearchParams(window.location.search).get('tab') || window.location.hash.replace(/^#(tab=)?/, '');
	show(q);
	document.documentElement.classList.add('uf-tabs-ready');
	if (!api) { return; }

	// ---- guide modal -------------------------------------------------------
	var modal = null, prevFocus = null;
	function closeModal() { if (modal) { modal.remove(); modal = null; document.body.classList.remove('uf-modal-open'); document.removeEventListener('keydown', onKey); if (prevFocus) { prevFocus.focus(); } } }
	function onKey(e) { if (e.key === 'Escape') { closeModal(); } }
	function openGuide(slug) {
		if (!modal) {
			prevFocus = document.activeElement;
			var bg = el('div', 'uf-modal-bg'), box = el('div', 'uf-modal uf-tier-silver uf-guide-modal');
			box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true'); box.setAttribute('aria-labelledby', 'uf-guide-title'); box.tabIndex = -1;
			var top = el('div', 'uf-modal-top'), t = el('h2', null, __('Loading…')); t.id = 'uf-guide-title';
			var x = el('button', 'button', '×'); x.type = 'button'; x.setAttribute('aria-label', __('Close')); x.addEventListener('click', closeModal);
			top.appendChild(t); top.appendChild(x); box.appendChild(top); box.appendChild(el('div', 'uf-guide-body')); bg.appendChild(box);
			bg.addEventListener('click', function (e) { if (e.target === bg) { closeModal(); } });
			box.addEventListener('click', function (e) { var a = e.target.closest ? e.target.closest('a[data-guide]') : null; if (a) { e.preventDefault(); openGuide(a.getAttribute('data-guide')); } });
			document.body.appendChild(bg); document.body.classList.add('uf-modal-open'); document.addEventListener('keydown', onKey); modal = bg; box.focus();
		}
		var title = $('#uf-guide-title', modal), body = $('.uf-guide-body', modal);
		title.textContent = __('Loading…'); body.textContent = '';
		api({ path: '/userflo/v1/help/guides/' + encodeURIComponent(slug) }).then(function (g) {
			title.textContent = g.title; body.innerHTML = g.html; body.scrollTop = 0; $('.uf-modal', modal).scrollTop = 0;
		}, function (e) { title.textContent = __('Guide not found.'); body.textContent = e && e.message ? e.message : ''; });
	}
	document.addEventListener('click', function (e) {
		var t = e.target.closest ? e.target.closest('[data-guide]') : null;
		if (t && !(modal && modal.contains(t))) { e.preventDefault(); openGuide(t.getAttribute('data-guide')); }
	});

	// ---- logs ----------------------------------------------------------------
	var logBox = $('#uf-log');
	function loadLog() {
		if (!logBox) { return; }
		api({ path: '/userflo/v1/help/log' }).then(function (r) {
			logBox.textContent = '';
			if (!r.items.length) { logBox.appendChild(el('p', 'uf-empty', __('Nothing logged yet.'))); return; }
			var t = el('table', 'widefat striped uf-table'), h = el('tr');
			[__('When'), __('Level'), __('Area'), __('Message')].forEach(function (x) { h.appendChild(el('th', null, x)); });
			t.appendChild(el('thead')).appendChild(h);
			var tb = el('tbody');
			r.items.forEach(function (i) {
				var tr = el('tr'); tr.appendChild(el('td', null, new Date(i.at).toLocaleString()));
				var lv = el('td'); lv.appendChild(el('span', 'uf-badge ' + (i.level === 'error' ? 'uf-badge-est' : i.level === 'warning' ? 'uf-badge-est' : 'uf-badge-live'), i.level)); tr.appendChild(lv);
				tr.appendChild(el('td', null, i.area)); tr.appendChild(el('td', null, i.message)); tb.appendChild(tr);
			});
			t.appendChild(tb); logBox.appendChild(t);
		}, function (e) { logBox.textContent = e.message; });
	}
	var lr = $('#uf-log-refresh'), lc = $('#uf-log-clear');
	if (lr) { lr.addEventListener('click', loadLog); }
	if (lc) { lc.addEventListener('click', function () { if (window.confirm(__('Clear the log?'))) { api({ path: '/userflo/v1/help/log', method: 'DELETE' }).then(loadLog); } }); }
	loadLog();

	// ---- system info -----------------------------------------------------------
	var sl = $('#uf-sys-load'), sc = $('#uf-sys-copy'), pre = $('#uf-sysinfo');
	if (sl) {
		sl.addEventListener('click', function () {
			api({ path: '/userflo/v1/help/system' }).then(function (r) {
				pre.textContent = Object.keys(r.rows).map(function (k) { return k + ': ' + r.rows[k]; }).join('\n'); pre.hidden = false; sc.hidden = false;
			});
		});
		sc.addEventListener('click', function () {
			var done = function () { sc.textContent = __('Copied'); setTimeout(function () { sc.textContent = __('Copy to clipboard'); }, 1500); };
			if (navigator.clipboard) { navigator.clipboard.writeText(pre.textContent).then(done); } else { var r = document.createRange(); r.selectNodeContents(pre); var s = window.getSelection(); s.removeAllRanges(); s.addRange(r); document.execCommand('copy'); done(); }
		});
	}
}());
