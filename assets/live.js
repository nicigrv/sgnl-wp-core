/* SG Neptun – Live-Wettkämpfe: aktualisiert die Karten alle 45 s (nur solange sichtbar) */
(function () {
	'use strict';
	var POLL_MS = 45000;
	var AFTER_MS = 48 * 3600 * 1000;
	var cfg = window.sgnlLive || { icons: {} };
	var ico = function (n) { return (cfg.icons && cfg.icons[n]) || ''; };

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function md(t) { return window.sgnlMarkdown ? window.sgnlMarkdown(t) : esc(t).replace(/\n/g, '<br>'); }

	var FD = new Intl.DateTimeFormat('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'Europe/Berlin' });
	var FT = new Intl.DateTimeFormat('de-DE', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Berlin' });
	var FN = new Intl.DateTimeFormat('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit', timeZone: 'Europe/Berlin' });
	var DAYKEY = new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Berlin' });

	function when(e) {
		var s = new Date(e.start * 1000), x = new Date(e.ende * 1000);
		if (DAYKEY.format(s) === DAYKEY.format(x)) {
			return FD.format(s) + (e.zeit ? ' · ' + FT.format(s) + '–' + FT.format(x) + ' Uhr' : '');
		}
		return FD.format(s) + ' – ' + FD.format(x);
	}
	function noteTime(sec, now) {
		var d = new Date(sec * 1000);
		var day = DAYKEY.format(d) === DAYKEY.format(new Date(now)) ? 'heute' : FN.format(d);
		return day + ', ' + FT.format(d) + ' Uhr';
	}

	function card(e, now) {
		var s = e.start * 1000, x = e.ende * 1000;
		var live = now >= s && now <= x, done = now > x;
		var badge = live ? '<span class="live-badge live-badge--now">Live jetzt</span>'
			: done ? '<span class="live-badge live-badge--done">Beendet</span>'
			: '<span class="live-badge live-badge--soon">Bevorstehend</span>';
		var notes = (e.nachrichten || []).filter(function (n) { return n && String(n.text || '').trim(); })
			.sort(function (a, b) { return (b.zeit || 0) - (a.zeit || 0); })
			.map(function (n) {
				return '<div class="live-note" role="note"><span class="live-note__label">' + ico('note') + 'Wichtige Info</span>' +
					'<div class="live-note__text">' + md(n.text) + '</div>' +
					'<div class="live-note__meta">' + (n.autor ? '<strong>' + esc(n.autor) + '</strong> · ' : '') + esc(noteTime(n.zeit, now)) + '</div></div>';
			}).join('');
		var links = [['meldeuebersicht', 'Meldeübersicht', 1], ['meldeergebnis', 'Meldeergebnis', 0], ['livetiming', 'Live-Timing', 1], ['protokoll', 'Protokoll', 0]]
			.filter(function (l) { return e[l[0]]; })
			.map(function (l) {
				return '<a class="live-link' + (l[2] ? ' live-link--ghost' : '') + '" href="' + esc(e[l[0]]) + '" target="_blank" rel="noopener noreferrer">' + ico('ext') + l[1] + '</a>';
			}).join('');
		return '<article class="live-card' + (live ? ' live-card--now' : '') + '">' + badge +
			'<h3>' + esc(e.name) + '</h3><div class="live-meta">' +
			(e.ort ? '<span>' + ico('pin') + esc(e.ort) + '</span>' : '') +
			'<span>' + ico('cal') + esc(when(e)) + '</span></div>' +
			(notes ? '<div class="live-notes">' + notes + '</div>' : '') +
			(links ? '<div class="live-links">' + links + '</div>' : '') + '</article>';
	}

	function render(box, data) {
		var now = Date.now();
		var events = (data && data.events || []).filter(function (e) {
			var has = e.meldeuebersicht || e.meldeergebnis || e.livetiming || e.protokoll || (e.nachrichten && e.nachrichten.length);
			var running = e.start * 1000 <= now && e.ende * 1000 >= now;
			return e.ende * 1000 >= now - AFTER_MS && (running || has);
		}).sort(function (a, b) { return a.start - b.start; });
		var list = box.querySelector('.live-list'), empty = box.querySelector('.live-empty');
		var any = events.some(function (e) { return now >= e.start * 1000 && now <= e.ende * 1000; });
		box.setAttribute('data-live-now', any ? '1' : '0');
		box.dispatchEvent(new CustomEvent('sgnl:live', { bubbles: true, detail: { live: any } }));
		list.innerHTML = events.map(function (e) { return card(e, now); }).join('');
		list.hidden = !events.length;
		empty.hidden = !!events.length;
	}

	function load(box) {
		var url = box.getAttribute('data-endpoint');
		if (!url) { return; }
		fetch(url + (url.indexOf('?') > -1 ? '&' : '?') + 't=' + Date.now(), { credentials: 'omit', cache: 'no-store' })
			.then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
			.then(function (d) { render(box, d); })
			.catch(function () { /* vorhandene Karten stehen lassen */ });
	}

	function visible(box) { return box.offsetParent !== null; }

	function init() {
		var boxes = Array.prototype.slice.call(document.querySelectorAll('.sgnl-live'));
		boxes.forEach(function (box) {
			box.querySelectorAll('[data-md]').forEach(function (el) { el.innerHTML = md(el.textContent); el.removeAttribute('data-md'); });
			box.dispatchEvent(new CustomEvent('sgnl:live', { bubbles: true, detail: { live: box.getAttribute('data-live-now') === '1' } }));
			box.sgnlRefresh = function () { load(box); };
		});
		setInterval(function () {
			if (document.hidden) { return; }
			boxes.forEach(function (box) { if (visible(box)) { load(box); } });
		}, POLL_MS);
		document.addEventListener('visibilitychange', function () {
			if (!document.hidden) { boxes.forEach(function (box) { if (visible(box)) { load(box); } }); }
		});
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
