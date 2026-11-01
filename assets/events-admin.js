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
