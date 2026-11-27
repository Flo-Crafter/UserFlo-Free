/* FloLens Tools page: export jobs with progress, retention and purge actions. Vanilla JS (wp.apiFetch). */
(function () {
	'use strict';
	var cfg = window.userfloTools || {};
	var api = window.wp && window.wp.apiFetch;
	var __ = window.wp && window.wp.i18n ? function (s) { return window.wp.i18n.__(s, 'flolens'); } : function (s) { return s; };
	if (!api) { return; }
	var $ = function (id) { return document.getElementById(id); };
	var form = $('uf-export-form'), statusEl = $('uf-export-status'), cancelBtn = $('uf-export-cancel'), startBtn = $('uf-export-start');
	var job = null, cancelled = false;

	function msg(el, text, cls) { el.textContent = ''; var p = document.createElement('p'); p.textContent = text; if (cls) { p.className = cls; } el.appendChild(p); }

	function render(j) {
		statusEl.textContent = '';
		var wrap = document.createElement('div'); wrap.className = 'uf-job';
		var prog = document.createElement('progress'); prog.className = 'uf-progress'; prog.max = 100; prog.value = j.percent;
		prog.setAttribute('aria-label', __('Export progress'));
		var t = document.createElement('p');
		t.textContent = j.status === 'done' ? __('Export finished.') + ' ' + j.rows + ' ' + __('rows') + ' (' + Math.round(j.bytes / 1024) + ' KB)'
			: j.status === 'failed' ? __('Export failed: ') + j.error
			: j.percent + '% — ' + j.rows + ' / ' + j.total + ' ' + __('rows');
		wrap.appendChild(prog); wrap.appendChild(t);
		if (j.status === 'done') {
			var a = document.createElement('a'); a.className = 'button button-primary';
			a.href = cfg.download + '?action=userflo_download&job=' + encodeURIComponent(j.id) + '&_wpnonce=' + encodeURIComponent(cfg.nonce);
			a.textContent = __('Download file'); wrap.appendChild(a);
		}
		statusEl.appendChild(wrap);
	}

	async function run(j) {
		while (j.status === 'running' && !cancelled) {
			render(j);
			try { j = await api({ path: '/userflo/v1/export/' + j.id + '/step', method: 'POST' }); }
			catch (e) { msg(statusEl, __('Export interrupted: ') + (e.message || '') + ' ' + __('You can start it again.'), 'uf-err'); break; }
			job = j;
		}
		if (!cancelled) { render(j); }
		cancelBtn.hidden = true; startBtn.disabled = false;
	}

	if (form) {
		form.addEventListener('submit', async function (e) {
			e.preventDefault();
			cancelled = false; startBtn.disabled = true;
			statusEl.textContent = ''; // never leave the previous download link visible
			var data = {};
			new FormData(form).forEach(function (v, k) { if (v !== '') { data[k] = k === 'visitor' ? parseInt(v, 10) : v; } });
			try {
				job = await api({ path: '/userflo/v1/export', method: 'POST', data: data });
				cancelBtn.hidden = false;
				run(job);
			} catch (err) { msg(statusEl, err.message || __('Could not start the export.'), 'uf-err'); startBtn.disabled = false; }
		});
		cancelBtn.addEventListener('click', async function () {
			cancelled = true;
			if (job) { try { await api({ path: '/userflo/v1/export/' + job.id, method: 'DELETE' }); } catch (e) { /* already gone */ } }
			msg(statusEl, __('Export cancelled.')); cancelBtn.hidden = true; startBtn.disabled = false;
		});
	}

	var cl = $('uf-cleanup-now');
	if (cl) {
		cl.addEventListener('click', async function () {
			cl.disabled = true;
			try { var r = await api({ path: '/userflo/v1/data/cleanup', method: 'POST' }); $('uf-cleanup-result').textContent = __('Deleted') + ': ' + r.events + ' ' + __('events') + ', ' + r.pageviews + ' ' + __('page views') + ', ' + r.sessions + ' ' + __('sessions') + ', ' + r.visitors + ' ' + __('visitors'); }
			catch (e) { $('uf-cleanup-result').textContent = e.message; }
			cl.disabled = false;
		});
	}
	var pc = $('uf-purge'), pi = $('uf-purge-confirm');
	if (pc && pi) {
		pi.addEventListener('input', function () { pc.disabled = pi.value !== 'DELETE ALL DATA'; });
		pc.addEventListener('click', async function () {
			pc.disabled = true;
			try { await api({ path: '/userflo/v1/data/purge', method: 'POST', data: { confirm: pi.value } }); $('uf-purge-result').textContent = __('All analytics data deleted.'); pi.value = ''; }
			catch (e) { $('uf-purge-result').textContent = e.message; }
		});
	}
}());
