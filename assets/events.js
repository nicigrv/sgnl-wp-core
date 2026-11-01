/* SG Neptun – Termine: Ansicht umschalten (Liste/Kacheln), Auswahl wird gemerkt */
(function () {
	var KEY = 'sgnl-events-view';
	function read() { try { return window.localStorage.getItem(KEY); } catch (e) { return null; } }
	function save(v) { try { window.localStorage.setItem(KEY, v); } catch (e) {} }
	function set(box, v) {
		box.setAttribute('data-view', v);
		box.querySelectorAll('.sgnl-events__switch button').forEach(function (b) {
			b.setAttribute('aria-pressed', b.getAttribute('data-view') === v ? 'true' : 'false');
		});
	}
	function init() {
		var saved = read();
		document.querySelectorAll('.sgnl-events').forEach(function (box) {
			if (!box.querySelector('.sgnl-events__switch')) { return; }
			if (saved === 'list' || saved === 'tiles') { set(box, saved); }
			box.addEventListener('click', function (ev) {
				var b = ev.target.closest('.sgnl-events__switch button');
				if (!b) { return; }
				var v = b.getAttribute('data-view');
				document.querySelectorAll('.sgnl-events').forEach(function (other) { set(other, v); });
				save(v);
			});
		});
	}
	/* Nach Kategorie filtern */
	function filter(box, cat) {
		box.querySelectorAll('.sgnl-events__filter button').forEach(function (b) {
			b.setAttribute('aria-pressed', b.getAttribute('data-cat') === cat ? 'true' : 'false');
		});
		box.querySelectorAll('tr[data-cat], .sgnl-event[data-cat]').forEach(function (el) {
			var cats = (el.getAttribute('data-cat') || '').split(' ');
			el.hidden = !!cat && cats.indexOf(cat) === -1;
		});
		// Monats-Zeilen ohne sichtbare Termine ausblenden
		box.querySelectorAll('tr.is-group-row').forEach(function (g) {
			var n = g.nextElementSibling, any = false;
			while (n && !n.classList.contains('is-group-row')) { if (!n.hidden) { any = true; } n = n.nextElementSibling; }
			g.hidden = !any;
		});
		var empty = box.querySelector('.sgnl-events__none');
		var visible = box.querySelectorAll('.sgnl-event[data-cat]:not([hidden])').length;
		if (empty) { empty.hidden = visible > 0; }
	}
	function initFilter() {
		document.querySelectorAll('.sgnl-events').forEach(function (box) {
			var bar = box.querySelector('.sgnl-events__filter');
			if (!bar) { return; }
			bar.addEventListener('click', function (ev) {
				var b = ev.target.closest('button');
				if (b) { filter(box, b.getAttribute('data-cat')); }
			});
		});
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initFilter); } else { initFilter(); }
})();
