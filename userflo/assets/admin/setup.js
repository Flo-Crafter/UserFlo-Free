/* UserFlo setup wizard: one form shown a step at a time, live style preview, logo picker and a review table. Vanilla JS. */
(function () {
	'use strict';
	var root = document.getElementById('uf-wizard');
	if (!root) { return; }
	var i18n = window.wp && window.wp.i18n;
	var __ = function (s) { return i18n ? i18n.__(s, 'userflo') : s; };
	var steps = Array.prototype.slice.call(root.querySelectorAll('.uf-wz-step'));
	var items = Array.prototype.slice.call(root.querySelectorAll('.uf-wz-progress li'));
	var back = document.getElementById('uf-wz-back'), next = document.getElementById('uf-wz-next'), finish = document.getElementById('uf-wz-finish');
	var form = document.getElementById('uf-wizard-form');
	var cur = 0;

	function summary() {
		var body = document.querySelector('#uf-wz-summary tbody');
		if (!body) { return; }
		body.innerHTML = '';
		var seen = {};
		Array.prototype.slice.call(form.querySelectorAll('[data-summary]')).forEach(function (f) {
			var label = f.getAttribute('data-summary'), val;
			if (f.type === 'radio') { if (!f.checked) { return; } val = f.getAttribute('data-vlabel') || f.value; }
			else if (f.type === 'checkbox') { val = f.checked ? __('Yes') : __('No'); if (f.disabled) { return; } }
			else if (f.tagName === 'SELECT') { val = f.options[f.selectedIndex] ? f.options[f.selectedIndex].text : ''; }
			else { val = f.value; }
			var key = label + '|' + (f.type === 'checkbox' ? f.name : '');
			if (seen[key]) { return; }
			seen[key] = 1;
			var tr = document.createElement('tr'), th = document.createElement('th'), td = document.createElement('td');
			th.scope = 'row'; th.textContent = label; td.textContent = val;
			tr.appendChild(th); tr.appendChild(td); body.appendChild(tr);
		});
		var logo = document.getElementById('uf-wz-logo-id');
		if (logo) {
			var tr2 = document.createElement('tr'), th2 = document.createElement('th'), td2 = document.createElement('td');
			th2.scope = 'row'; th2.textContent = __('Company logo'); td2.textContent = logo.value && logo.value !== '0' ? __('Selected') : __('None');
			tr2.appendChild(th2); tr2.appendChild(td2); body.insertBefore(tr2, body.firstChild);
		}
	}

	function show(n, focus) {
		cur = Math.max(0, Math.min(steps.length - 1, n));
		steps.forEach(function (s, i) { s.hidden = i !== cur; });
		items.forEach(function (li, i) { li.classList.toggle('is-done', i < cur); li.classList.toggle('is-current', i === cur); if (i === cur) { li.setAttribute('aria-current', 'step'); } else { li.removeAttribute('aria-current'); } });
		back.hidden = cur === 0;
		var last = cur === steps.length - 1;
		next.hidden = last; finish.hidden = !last;
		if (last) { summary(); }
		if (focus) { var h = steps[cur].querySelector('.uf-wz-h'); if (h) { h.focus(); } }
		try { window.scrollTo(0, 0); } catch (e) { /* ignore */ }
	}
	back.addEventListener('click', function () { show(cur - 1, true); });
	next.addEventListener('click', function () { show(cur + 1, true); });
	function fromHash() { var m = /step=(\d+)/.exec(window.location.hash); return m ? parseInt(m[1], 10) - 1 : 0; }
	show(fromHash(), false);
	window.addEventListener('hashchange', function () { show(fromHash(), false); });

	// Live style preview: only toggles the body class; nothing is saved until the end.
	var styles = ['dark', 'glass', 'elegant', 'fancy', 'mono', 'noir'];
	Array.prototype.slice.call(form.querySelectorAll('input[name="uf[ui_style]"]')).forEach(function (r) {
		r.addEventListener('change', function () {
			styles.forEach(function (s) { document.body.classList.remove('uf-style-' + s); });
			if (r.checked && styles.indexOf(r.value) > -1) { document.body.classList.add('uf-style-' + r.value); }
		});
	});

	// Company logo (WordPress media library).
	var pick = document.getElementById('uf-wz-logo-pick'), clr = document.getElementById('uf-wz-logo-clear'), id = document.getElementById('uf-wz-logo-id'), pv = document.getElementById('uf-wz-logo-preview');
	if (pick && window.wp && window.wp.media) {
		var frame;
		pick.addEventListener('click', function () {
			if (!frame) {
				frame = window.wp.media({ title: __('Choose logo'), library: { type: 'image' }, multiple: false });
				frame.on('select', function () {
					var a = frame.state().get('selection').first().toJSON();
					id.value = a.id; pv.innerHTML = '';
					var im = document.createElement('img'); im.src = a.url; im.alt = ''; im.style.maxHeight = '60px'; im.style.maxWidth = '240px'; pv.appendChild(im);
				});
			}
			frame.open();
		});
		clr.addEventListener('click', function () { id.value = '0'; pv.innerHTML = ''; });
	}

	// Enter must not submit early.
	form.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target && e.target.tagName !== 'BUTTON' && e.target.tagName !== 'TEXTAREA') { e.preventDefault(); if (cur < steps.length - 1) { show(cur + 1, true); } } });
})();
