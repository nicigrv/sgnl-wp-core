/* SG Neptun – Termin bearbeiten: Datei aus der Mediathek wählen, "bis"-Datum prüfen */
jQuery(function ($) {
	$(document).on('click', '.sgnl-ev__pick', function (e) {
		e.preventDefault();
		var $input = $('#' + $(this).data('target'));
		var frame = wp.media({ title: $(this).data('title') + ' wählen', button: { text: 'Übernehmen' }, multiple: false });
		frame.on('select', function () {
			var file = frame.state().get('selection').first().toJSON();
			$input.val(file.url).trigger('change');
		});
		frame.open();
	});
	$(document).on('input change', '.sgnl-ev__filerow input', function () {
		var $open = $(this).closest('.sgnl-ev__filerow').find('.sgnl-ev__open');
		var v = $.trim($(this).val());
		$open.attr('href', v).prop('hidden', !v);
	});
	var $start = $('#sgnl-ev-start'), $end = $('#sgnl-ev-end');
	$start.on('change', function () {
		$end.attr('min', $start.val());
		if ($end.val() && $end.val() < $start.val()) { $end.val(''); }
	});
});

/* Nachrichten (Live) veröffentlichen / löschen */
jQuery(function ($) {
	var $box = $('.sgnl-msgs');
	if (!$box.length) { return; }
	function render() {
		if (window.sgnlMarkdown) {
			$box.find('[data-md]').each(function () { this.innerHTML = window.sgnlMarkdown(this.textContent); this.removeAttribute('data-md'); });
		}
	}
	function send(data, $status) {
		$status.text('…');
		return $.post(window.ajaxurl, $.extend({ action: 'sgnl_event_msg', post: $box.data('post'), nonce: $box.data('nonce') }, data))
			.done(function (r) {
				if (r && r.success) {
					$box.find('.sgnl-msgs__list').html(r.data.html);
					render();
					$status.text('Gespeichert ✓');
				} else {
					$status.text((r && r.data && r.data.message) || 'Fehler');
				}
			})
			.fail(function () { $status.text('Fehler beim Speichern'); });
	}
	$box.on('click', '.sgnl-msgs__add', function () {
		var $t = $('#sgnl-msg-text');
		if (!$.trim($t.val())) { $t.trigger('focus'); return; }
		send({ op: 'add', text: $t.val() }, $box.find('.sgnl-msgs__status')).done(function (r) {
			if (r && r.success) { $t.val(''); $t[0].dispatchEvent(new Event('input')); }
		});
	});
	$box.on('click', '.sgnl-msg__del', function () {
		if (!window.confirm('Nachricht löschen?')) { return; }
		send({ op: 'delete', id: $(this).data('id') }, $box.find('.sgnl-msgs__status'));
	});
	render();
});
