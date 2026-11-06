/* SG Neptun – Trainingszeiten: Ansicht (Tage/Gruppen) und Gruppenfilter, Auswahl wird gemerkt */
(function () {
	'use strict';
	var KEY = 'sgnl-training';
	function load() { try { return JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) { return {}; } }
	function save(v) { try { localStorage.setItem(KEY, JSON.stringify(v)); } catch (e) {} }

	function setup(root) {
		var state = load();
		var views = root.querySelectorAll('.sgnl-training__views button');
		var chips = root.querySelectorAll('.sgnl-training__chip');
		function view(v) {
			if (!root.querySelector('.sgnl-training__views')) { return; }
			views.forEach(function (b) { b.setAttribute('aria-selected', b.getAttribute('data-view') === v ? 'true' : 'false'); });
			root.querySelector('.sgnl-training__days').hidden = v !== 'days';
			root.querySelector('.sgnl-training__groups').hidden = v !== 'groups';
			root.setAttribute('data-view', v);
		}
		function filter(g) {
			var found = false;
			chips.forEach(function (c) {
				var on = c.getAttribute('data-group') === g;
				if (on) { found = true; }
				c.classList.toggle('is-active', on);
				c.setAttribute('aria-pressed', on ? 'true' : 'false');
			});
			if (!found) { g = ''; chips[0] && chips[0].classList.add('is-active'); }
			root.querySelectorAll('[data-group]').forEach(function (el) {
				if (el.classList.contains('sgnl-training__chip')) { return; }
				if (g && el.getAttribute('data-group') === g) { el.setAttribute('data-group-match', ''); } else { el.removeAttribute('data-group-match'); }
			});
			if (g) { root.setAttribute('data-filter', g); } else { root.removeAttribute('data-filter'); }
		}
		views.forEach(function (b) {
			b.addEventListener('click', function () { view(b.getAttribute('data-view')); state.view = b.getAttribute('data-view'); save(state); });
		});
		chips.forEach(function (c) {
			c.addEventListener('click', function () { var g = c.getAttribute('data-group'); filter(g); state.group = g; save(state); });
		});
		var hash = decodeURIComponent((location.hash || '').replace('#', ''));
		var fromHash = hash.indexOf('gruppe=') === 0 ? hash.slice(7) : '';
		if (state.view === 'days' || state.view === 'groups') { view(state.view); }
		filter(fromHash || state.group || '');
	}
	function init() { document.querySelectorAll('.sgnl-training').forEach(setup); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
