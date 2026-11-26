/* Distraction-free view: the switch in the UserFlo header hides the WordPress menu and shows UserFlo's own horizontal menu. */
(function () {
	'use strict';
	var b = document.getElementById('uf-focus-btn'), root = document.documentElement;
	if (!b) { return; }
	function sync() {
		var on = root.classList.contains('uf-focus'), ic = b.querySelector('.dashicons'), sr = b.querySelector('.screen-reader-text'), t = b.getAttribute(on ? 'data-on' : 'data-off');
		b.setAttribute('aria-pressed', on ? 'true' : 'false'); b.title = t; if (sr) { sr.textContent = t; }
		if (ic) { ic.className = 'dashicons ' + (on ? 'dashicons-editor-contract' : 'dashicons-editor-expand'); }
	}
	b.addEventListener('click', function () {
		var on = root.classList.toggle('uf-focus');
		try { localStorage.setItem('userflo_focus', on ? '1' : '0'); } catch (e) { /* ignore */ }
		sync(); window.dispatchEvent(new Event('resize'));
	});
	sync();
}());
