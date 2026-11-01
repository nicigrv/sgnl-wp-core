/* SG Neptun – Datenbank: Reiter, Teilen-Link, Neu laden, Vollbild, iFrame-Höhe, Freigabe externer Inhalte */
(function () {
	'use strict';
	var cfg = window.sgnlDb || {};
	var ORIGIN = cfg.origin || 'https://sgnl.webclub.app';
	var TIMEOUT = 20000;

	function hasConsent() {
		if (!cfg.gate) { return true; }
		var m = document.cookie.match(/(?:^|;\s*)sgnl_consent=([^;]*)/);
		return !!m && decodeURIComponent(m[1]).split('.').indexOf('ext') !== -1;
	}

	function setup(db) {
		var tabs = Array.prototype.slice.call(db.querySelectorAll('.sgnl-db__tab'));
		var panels = Array.prototype.slice.call(db.querySelectorAll('.sgnl-db__panel'));
		var label = db.querySelector('.sgnl-db__label');
		var rope = db.querySelector('.sgnl-db__rope');
		var openBtn = db.querySelector('[data-act="open"]');
		var frame = db.querySelector('.sgnl-db__frame');
		var current = null;
		var allowedOnce = {};

		function panelOf(id) { return panels.filter(function (p) { return p.getAttribute('data-tab') === id; })[0]; }

		function moveRope(tab) {
			if (!rope || !tab) { return; }
			rope.style.width = tab.offsetWidth + 'px';
			rope.style.transform = 'translateX(' + tab.offsetLeft + 'px)';
		}

		function show(state, panel, role) {
			panel.querySelectorAll('.sgnl-db__state').forEach(function (s) { s.hidden = s.getAttribute('data-role') !== role; });
		}

		function loadIframe(panel, force) {
			var body = panel.querySelector('.sgnl-db__body');
			var old = body.querySelector('iframe');
			if (old && !force) { return; }
			if (!hasConsent() && !allowedOnce[panel.getAttribute('data-tab')]) { show(null, panel, 'gate'); return; }
			if (old) { old.remove(); }
			body.removeAttribute('data-auto');
			body.style.height = '';
			show(null, panel, 'loading');
			var src = panel.getAttribute('data-src');
			var f = document.createElement('iframe');
			f.title = panel.getAttribute('data-label') + ' – SG Neptun Lampertheim';
			f.loading = 'lazy';
			f.referrerPolicy = 'strict-origin-when-cross-origin';
			f.src = src + (force ? (src.indexOf('?') > -1 ? '&' : '?') + 'r=' + Date.now() : '');
			var done = false;
			f.addEventListener('load', function () { done = true; show(null, panel, 'none'); });
			setTimeout(function () { if (!done) { show(null, panel, 'error'); } }, TIMEOUT);
			body.appendChild(f);
		}

		function select(id, opts) {
			opts = opts || {};
			var tab = tabs.filter(function (t) { return t.getAttribute('data-tab') === id; })[0] || tabs[0];
			id = tab.getAttribute('data-tab');
			current = id;
			tabs.forEach(function (t) {
				var on = t === tab;
				t.setAttribute('aria-selected', on ? 'true' : 'false');
				t.tabIndex = on ? 0 : -1;
			});
			panels.forEach(function (p) { p.hidden = p.getAttribute('data-tab') !== id; });
			var panel = panelOf(id);
			label.textContent = panel.getAttribute('data-label');
			var isFrame = panel.hasAttribute('data-src');
			openBtn.hidden = !isFrame;
			if (isFrame) { openBtn.href = panel.getAttribute('data-src'); loadIframe(panel, false); }
			if (!isFrame) {
				var live = panel.querySelector('.sgnl-live');
				if (live && live.sgnlRefresh && opts.user) { live.sgnlRefresh(); }
			}
			moveRope(tab);
			if (opts.user && history.replaceState) { history.replaceState(null, '', '#' + id); }
			if (opts.focus) { tab.focus(); }
		}

		tabs.forEach(function (t, i) {
			t.addEventListener('click', function () { select(t.getAttribute('data-tab'), { user: true }); });
			t.addEventListener('keydown', function (e) {
				var k = e.key, n = null;
				if (k === 'ArrowRight') { n = tabs[(i + 1) % tabs.length]; }
				if (k === 'ArrowLeft') { n = tabs[(i - 1 + tabs.length) % tabs.length]; }
				if (k === 'Home') { n = tabs[0]; }
				if (k === 'End') { n = tabs[tabs.length - 1]; }
				if (n) { e.preventDefault(); select(n.getAttribute('data-tab'), { user: true, focus: true }); }
			});
		});

		db.addEventListener('click', function (e) {
			var b = e.target.closest('[data-act]');
			if (!b) { return; }
			var act = b.getAttribute('data-act');
			var panel = panelOf(current);
			if (act === 'reload') {
				if (panel.hasAttribute('data-src')) { loadIframe(panel, true); }
				var live = panel.querySelector('.sgnl-live');
				if (live && live.sgnlRefresh) { live.sgnlRefresh(); }
			} else if (act === 'retry') {
				loadIframe(panel, true);
			} else if (act === 'load-once') {
				allowedOnce[current] = true;
				loadIframe(panel, true);
			} else if (act === 'share') {
				var url = location.href.split('#')[0] + '#' + current;
				var span = b.querySelector('span');
				var reset = function () { setTimeout(function () { span.textContent = 'Link teilen'; }, 2000); };
				if (navigator.share && /Mobi|Android|iPhone|iPad/.test(navigator.userAgent)) {
					navigator.share({ title: document.title, url: url }).catch(function () {});
				} else if (navigator.clipboard) {
					navigator.clipboard.writeText(url).then(function () { span.textContent = 'Link kopiert ✓'; reset(); });
				} else {
					window.prompt('Link kopieren:', url);
				}
			} else if (act === 'full') {
				if (document.fullscreenElement) { document.exitFullscreen(); }
				else if (frame.requestFullscreen) { frame.requestFullscreen().catch(function () {}); }
			}
		});

		// Höhenmeldung der eingebetteten Seiten (siehe iframe-hoehe.html)
		window.addEventListener('message', function (e) {
			if (e.origin !== ORIGIN || !e.data || e.data.type !== 'sgnl:height') { return; }
			panels.forEach(function (p) {
				var f = p.querySelector('iframe');
				if (f && f.contentWindow === e.source) {
					var body = p.querySelector('.sgnl-db__body');
					body.setAttribute('data-auto', '');
					body.style.height = Math.max(200, Math.ceil(+e.data.height || 0)) + 'px';
				}
			});
		});

		// Zustimmung über das Cookie-Banner → aktuellen Reiter laden
		document.addEventListener('sgnl:consent', function () {
			var p = panelOf(current);
			if (p && p.hasAttribute('data-src') && hasConsent()) { loadIframe(p, true); }
		});

		// Roter Punkt am Reiter "Live", wenn gerade ein Wettkampf läuft
		db.addEventListener('sgnl:live', function (e) {
			var t = tabs.filter(function (x) { return x.getAttribute('data-tab') === 'live'; })[0];
			if (t) { t.classList.toggle('is-live-now', !!(e.detail && e.detail.live)); }
		});
		var liveBox = db.querySelector('.sgnl-live');
		if (liveBox && liveBox.getAttribute('data-live-now') === '1') {
			var lt = tabs.filter(function (x) { return x.getAttribute('data-tab') === 'live'; })[0];
			if (lt) { lt.classList.add('is-live-now'); }
		}

		window.addEventListener('resize', function () { moveRope(tabs.filter(function (t) { return t.getAttribute('aria-selected') === 'true'; })[0]); });
		window.addEventListener('hashchange', function () {
			var id = location.hash.replace('#', '');
			if (panelOf(id)) { select(id); }
		});

		var start = location.hash.replace('#', '');
		select(panelOf(start) ? start : tabs[0].getAttribute('data-tab'));
		if (panelOf(start)) { setTimeout(function () { db.scrollIntoView({ block: 'start' }); }, 50); }
	}

	function init() { document.querySelectorAll('.sgnl-db').forEach(setup); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
