/* SG Neptun – eingebettete Seiten von der eigenen Website: Höhe automatisch an den Inhalt anpassen
   (kein Scrollen im Rahmen). Funktioniert nur für Seiten derselben Herkunft (z. B. /vereinsdaten/…). */
(function () {
	'use strict';
	function measure(doc) {
		var b = doc.body;
		if (!b) { return 0; }
		var cs = doc.defaultView.getComputedStyle(b);
		return Math.ceil(Math.max(b.scrollHeight, b.offsetHeight) + parseFloat(cs.marginTop || 0) + parseFloat(cs.marginBottom || 0));
	}
	/**
	 * @param {HTMLIFrameElement} f
	 * @param {function(number)} apply  setzt die Höhe
	 */
	function fit(f, apply) {
		var first = true;
		function attach() {
			var doc;
			try { doc = f.contentDocument; } catch (e) { return; }
			if (!doc || !doc.body) { return; }
			doc.documentElement.style.height = 'auto';
			doc.documentElement.style.overflow = 'hidden';
			doc.body.style.height = 'auto';
			doc.body.style.overflow = 'hidden';
			var last = 0;
			var set = function () {
				var h = measure(doc);
				if (h > 50 && Math.abs(h - last) > 2) { last = h; apply(h); }
			};
			set();
			if (window.ResizeObserver) { new ResizeObserver(set).observe(doc.body); }
			new MutationObserver(set).observe(doc.body, { childList: true, subtree: true, attributes: true });
			var n = 0, iv = setInterval(function () { set(); if (++n > 20) { clearInterval(iv); } }, 300);
			// Bei Navigation innerhalb des Rahmens zum Anfang des Rahmens scrollen
			if (!first) {
				var r = f.getBoundingClientRect();
				if (r.top < 0) { window.scrollBy({ top: r.top - 90, behavior: 'smooth' }); }
			}
			first = false;
		}
		f.addEventListener('load', attach);
		try { if (f.contentDocument && f.contentDocument.readyState === 'complete' && f.contentDocument.body && f.contentDocument.body.childElementCount) { attach(); } } catch (e) {}
	}
	window.sgnlFitFrame = fit;

	function init() {
		document.querySelectorAll('iframe[data-sgnl-fit]').forEach(function (f) {
			fit(f, function (h) { f.style.height = h + 'px'; });
		});
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
