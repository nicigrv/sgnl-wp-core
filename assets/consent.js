/* SG Neptun – Cookie-Banner & Freigabe externer Inhalte */
(function () {
	'use strict';
	var d = document, root = d.documentElement;
	var NAME = 'sgnl_consent';

	function read() {
		var m = d.cookie.match(/(?:^|;\s*)sgnl_consent=([^;]*)/);
		return m ? decodeURIComponent(m[1]).split('.') : null;
	}
	function write(cats) {
		var v = ['v1'].concat(cats).join('.');
		d.cookie = NAME + '=' + encodeURIComponent(v) + '; Max-Age=31536000; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
	}
	function has(cat) { var c = read(); return !!c && c.indexOf(cat) !== -1; }

	function activateGate(gate) {
		var tpl = gate.querySelector('template');
		if (!tpl) { return; }
		var frag = tpl.content.cloneNode(true);
		gate.replaceWith(frag);
	}
	function activateScripts(cat) {
		d.querySelectorAll('script[type="text/plain"][data-sgnl-consent="' + cat + '"]').forEach(function (old) {
			var s = d.createElement('script');
			for (var i = 0; i < old.attributes.length; i++) {
				var a = old.attributes[i];
				if (a.name !== 'type' && a.name !== 'data-sgnl-consent') { s.setAttribute(a.name, a.value); }
			}
			s.text = old.text;
			old.replaceWith(s);
		});
	}
	function apply() {
		if (has('ext')) {
			d.querySelectorAll('.sgnl-gate[data-sgnl-consent="ext"]').forEach(activateGate);
			activateScripts('ext');
		}
	}

	var banner = d.getElementById('sgnl-consent');
	function showBanner() {
		if (!banner) { return; }
		var c = read();
		var box = banner.querySelector('input[name="sgnl-ext"]');
		if (box) { box.checked = !!c && c.indexOf('ext') !== -1; }
		banner.hidden = false;
		var first = banner.querySelector('[data-sgnl-choice="all"]');
		first && first.focus({ preventScroll: true });
	}
	function decide(cats) {
		write(cats);
		root.classList.remove('sgnl-consent-pending');
		if (banner) { banner.hidden = true; }
		apply();
		d.dispatchEvent(new CustomEvent('sgnl:consent', { detail: { categories: cats } }));
	}

	if (banner) {
		banner.addEventListener('click', function (e) {
			var b = e.target.closest('button');
			if (!b) { return; }
			if (b.hasAttribute('data-sgnl-details')) {
				var det = banner.querySelector('.sgnl-consent__details');
				det.hidden = !det.hidden;
				banner.querySelector('[data-sgnl-choice="save"]').hidden = det.hidden;
				return;
			}
			var choice = b.getAttribute('data-sgnl-choice');
			if (choice === 'all') { decide(['ext']); }
			if (choice === 'none') { decide([]); }
			if (choice === 'save') {
				var box = banner.querySelector('input[name="sgnl-ext"]');
				decide(box && box.checked ? ['ext'] : []);
			}
		});
	}

	d.addEventListener('click', function (e) {
		var t = e.target.closest('[data-sgnl-open], [data-sgnl-load], [data-sgnl-allow]');
		if (!t) { return; }
		if (t.hasAttribute('data-sgnl-open')) { e.preventDefault(); showBanner(); return; }
		var gate = t.closest('.sgnl-gate');
		if (t.hasAttribute('data-sgnl-load') && gate) { activateGate(gate); return; }
		if (t.hasAttribute('data-sgnl-allow')) {
			var c = (read() || []).filter(function (x) { return x !== 'v1'; });
			if (c.indexOf('ext') === -1) { c.push('ext'); }
			decide(c);
		}
	});

	apply();
	if (!read()) { showBanner(); }
})();
