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
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
