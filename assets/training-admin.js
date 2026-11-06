/* Trainingszeiten bearbeiten: Zeilen hinzufügen, duplizieren, löschen, verschieben */
(function () {
	'use strict';
	var body = document.getElementById('sgnl-ta-rows');
	var tpl = document.getElementById('sgnl-ta-template');
	if (!body || !tpl) { return; }
	var counter = body.children.length + 1000;

	function renumber() {
		Array.prototype.forEach.call(body.children, function (tr, i) {
			tr.querySelectorAll('[name^="sgnl_training[sessions]"]').forEach(function (el) {
				el.name = el.name.replace(/sgnl_training\[sessions\]\[[^\]]+\]/, 'sgnl_training[sessions][' + i + ']');
			});
			var prev = tr.previousElementSibling;
			var day = tr.querySelector('select').value;
			tr.classList.toggle('is-day-start', !!prev && prev.querySelector('select').value !== day);
		});
	}
	function newRow(after, copyFrom) {
		var html = tpl.innerHTML.replace(/__i__/g, String(counter++));
		var wrap = document.createElement('tbody');
		wrap.innerHTML = html;
		var tr = wrap.firstElementChild;
		if (copyFrom) {
			var src = copyFrom.querySelectorAll('input, select, textarea');
			var dst = tr.querySelectorAll('input, select, textarea');
			src.forEach(function (el, i) { if (dst[i]) { dst[i].value = el.value; } });
		} else if (after) {
			tr.querySelector('select').value = after.querySelector('select').value;
		}
		if (after) { after.after(tr); } else { body.appendChild(tr); }
		renumber();
		var g = tr.querySelector('[name$="[group]"]');
		if (g) { g.focus(); }
		return tr;
	}

	document.getElementById('sgnl-ta-add').addEventListener('click', function () {
		newRow(body.lastElementChild, null);
	});
	body.addEventListener('click', function (e) {
		var b = e.target.closest('[data-act]');
		if (!b) { return; }
		var tr = b.closest('tr');
		if (b.getAttribute('data-act') === 'copy') { newRow(tr, tr); }
		if (b.getAttribute('data-act') === 'del') {
			var g = tr.querySelector('[name$="[group]"]').value || 'diese Zeile';
			if (window.confirm('„' + g + '“ wirklich löschen?')) { tr.remove(); renumber(); }
		}
	});
	body.addEventListener('change', function (e) { if (e.target.tagName === 'SELECT') { renumber(); } });

	// Ziehen zum Verschieben
	var drag = null;
	body.addEventListener('mousedown', function (e) {
		var h = e.target.closest('.sgnl-ta__drag');
		if (h) { h.closest('tr').draggable = true; }
	});
	body.addEventListener('dragstart', function (e) { drag = e.target.closest('tr'); if (drag) { drag.classList.add('is-dragging'); e.dataTransfer.effectAllowed = 'move'; } });
	body.addEventListener('dragover', function (e) {
		if (!drag) { return; }
		e.preventDefault();
		var over = e.target.closest('tr');
		if (!over || over === drag) { return; }
		var r = over.getBoundingClientRect();
		if (e.clientY - r.top > r.height / 2) { over.after(drag); } else { over.before(drag); }
	});
	body.addEventListener('dragend', function () { if (drag) { drag.classList.remove('is-dragging'); drag.draggable = false; drag = null; renumber(); } });

	// Ungespeicherte Änderungen
	var dirty = false, form = document.getElementById('sgnl-ta-form');
	form.addEventListener('input', function () { dirty = true; });
	form.addEventListener('submit', function () { dirty = false; });
	window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
	renumber();
})();
