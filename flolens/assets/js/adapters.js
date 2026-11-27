/*! UserFlo builder adapters — optional; only enrich event metadata. Each adapter feature-detects. */
(function (w, d) {
	'use strict';
	var U = w.UserFlo;
	if (!U || !U.adapter) { return; }

	function cls(el, re) {
		var list = (el.getAttribute('class') || '').split(/\s+/), i, m;
		for (i = 0; i < list.length; i++) { m = re.exec(list[i]); if (m) { return m; } }
		return null;
	}

	// Divi (4 and 5): module type from the numbered class Divi gives each element (et_pb_blurb_0 => blurb).
	U.adapter(function (el) {
		var node = el, m, fallback = '';
		while (node && node.nodeType === 1 && node !== d.body) {
			m = cls(node, /^et_pb_([a-z]+(?:_[a-z]+)*)_\d+$/);
			if (m) {
				if (!/^(section|row|column|row_inner|column_inner)$/.test(m[1])) { return 'divi:' + m[1].replace(/_/g, '-'); }
				fallback = fallback || 'divi:' + m[1];
			}
			node = node.parentElement;
		}
		if (fallback) { return fallback; }
		if (el.closest('#main-header, #et-top-navigation')) { return 'divi:header'; }
		if (el.closest('#main-footer')) { return 'divi:footer'; }
		return '';
	});

	// Elementor: widget type from data-widget_type ("button.default" => button).
	U.adapter(function (el) {
		var wdg = el.closest('.elementor-widget');
		if (wdg) { return 'elementor:' + String(wdg.getAttribute('data-widget_type') || 'widget').split('.')[0]; }
		if (el.closest('.elementor-popup-modal')) { return 'elementor:popup'; }
		return '';
	});

	// Bricks: brxe-{name} class (the id carries the generated element id, which we ignore).
	U.adapter(function (el) {
		var node = el.closest('[class*="brxe-"]'), m;
		while (node && node.nodeType === 1) {
			m = cls(node, /^brxe-([a-z]+(?:-[a-z]+)*)$/);
			if (m && node.id !== m[0]) { return 'bricks:' + m[1]; }
			node = node.parentElement;
		}
		return '';
	});

	// WooCommerce: page context + add-to-cart events.
	U.adapter(function (el) {
		if (!d.body || !/(^|\s)woocommerce/.test(d.body.className)) { return ''; }
		if (el.closest('.woocommerce-cart-form, .cart_totals')) { return 'woocommerce:cart'; }
		if (el.closest('form.checkout, #payment')) { return 'woocommerce:checkout'; }
		if (el.closest('.product')) { return 'woocommerce:product'; }
		return '';
	});
	d.addEventListener('click', function (e) {
		var b = e.target && e.target.closest ? e.target.closest('.add_to_cart_button, .single_add_to_cart_button') : null;
		if (!b || b.closest('[data-userflo-ignore]')) { return; }
		var f = b.closest('form.cart'), id = b.getAttribute('data-product_id') || b.getAttribute('data-product_sku') || b.value || (f && f.querySelector('[name="add-to-cart"]') ? f.querySelector('[name="add-to-cart"]').value : '');
		U.track2('commerce', 'add_to_cart', String(id || ''), 'woocommerce:product');
	}, true);
}(window, document));
