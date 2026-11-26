/* "Saved" cue: when a save succeeds, a short message floats up in the middle of the screen and fades out. The normal WordPress notice stays. */
(function () {
	'use strict';
	var SAVED = /\b(saved|updated)\b/i, timer = null;

	function show(text) {
		var old = document.getElementById('uf-toast'), el;
		if (old && old.parentNode) { old.parentNode.removeChild(old); }
		clearTimeout(timer);
		el = document.createElement('div');
		el.id = 'uf-toast';
		el.className = 'uf-toast';
		el.setAttribute('role', 'status');
		el.setAttribute('aria-live', 'polite');
		el.innerHTML = '<span class="uf-toast-tick" aria-hidden="true">✓</span><span class="uf-toast-text"></span>';
		el.lastChild.textContent = text || 'Saved';
		document.body.appendChild(el);
		timer = setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 2400);
	}
	window.userfloToast = show;

	function isSaveNotice(n) {
		return n.nodeType === 1 && n.matches && n.matches('.notice-success, .updated') && SAVED.test(n.textContent || '');
	}
	function scan(root) {
		var list = root.querySelectorAll ? root.querySelectorAll('.notice-success, .updated') : [], i;
		for (i = 0; i < list.length; i++) {
			if (isSaveNotice(list[i])) { return true; }
		}
		return false;
	}

	/* Settings forms post to options.php and come back to the same screen without a message of their own: remember the save across the redirect. */
	var KEY = 'userfloSaved';
	document.addEventListener('submit', function (e) {
		var f = e.target, a = f && f.getAttribute ? (f.getAttribute('action') || '') : '';
		if (a.indexOf('options.php') !== -1) {
			try { sessionStorage.setItem(KEY, String(Date.now())); } catch (x) { /* ignore */ }
		}
	}, true);
	function flagged() {
		var t = 0;
		try { t = parseInt(sessionStorage.getItem(KEY) || '0', 10); sessionStorage.removeItem(KEY); } catch (x) { /* ignore */ }
		return t > 0 && Date.now() - t < 30000;
	}
	function ready() {
		var wrap = document.getElementById('wpbody-content') || document.body;
		var viaUrl = /[?&](settings-updated=true|saved=1)\b/.test(location.search);
		var failed = !!wrap.querySelector('.notice-error, .error');
		if (flagged() && !failed || viaUrl || scan(wrap)) { show(window.userfloToastText || 'Saved'); }
		if (window.MutationObserver) {
			new MutationObserver(function (muts) {
				var i, j, n;
				for (i = 0; i < muts.length; i++) {
					for (j = 0; j < muts[i].addedNodes.length; j++) {
						n = muts[i].addedNodes[j];
						if (isSaveNotice(n) || (n.nodeType === 1 && scan(n))) { show(window.userfloToastText || 'Saved'); return; }
					}
				}
			}).observe(wrap, { childList: true, subtree: true });
		}
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', ready); } else { ready(); }
}());
