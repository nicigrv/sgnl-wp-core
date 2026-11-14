/* Block „Offizielle Ankündigungen“ – Vorschau im Editor, Inhalte unter Dashboard → Ankündigungen */
(function (blocks, el, be, comp, SSR) {
	var h = el.createElement;
	var cfg = window.sgnlAnn || {};
	blocks.registerBlockType('sgnl/announcements', {
		edit: function () {
			return h('div', be.useBlockProps(),
				h(be.InspectorControls, null,
					h(comp.PanelBody, { title: 'Ankündigungen', initialOpen: true },
						h('p', null, 'Die Inhalte pflegst du unter Dashboard → Ankündigungen. Jede Ankündigung lässt sich dort ein- und ausschalten.'),
						h('p', null, h(comp.Button, { variant: 'secondary', href: cfg.list, target: '_blank' }, 'Ankündigungen verwalten')),
						h('p', null, h(comp.Button, { variant: 'tertiary', href: cfg.settings, target: '_blank' }, 'Bereich ein-/ausschalten, Überschrift'))
					)
				),
				h(SSR, { block: 'sgnl/announcements', attributes: {} })
			);
		},
		save: function () { return null; }
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.serverSideRender);
