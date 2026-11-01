/* Block "Live-Wettkämpfe" */
(function (blocks, el, be, SSR) {
	var h = el.createElement;
	blocks.registerBlockType('sgnl/live', {
		edit: function () {
			return h('div', be.useBlockProps(),
				h(SSR, { block: 'sgnl/live', attributes: {} }),
				h('p', { style: { fontSize: 12, color: '#5B6B83', marginTop: 6 } }, 'Gepflegt unter Dashboard → Termine (Meldeergebnis, Live-Timing, Nachrichten). Aktualisiert sich auf der Website alle 45 Sekunden.')
			);
		},
		save: function () { return null; }
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.serverSideRender);
