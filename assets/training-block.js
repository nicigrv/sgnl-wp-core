/* Block "Trainingszeiten" */
(function (blocks, el, be, comp, SSR) {
	var h = el.createElement;
	var cfg = window.sgnlTraining || { groups: [], admin: '' };
	blocks.registerBlockType('sgnl/training', {
		edit: function (props) {
			var a = props.attributes;
			return h('div', be.useBlockProps(),
				h(be.InspectorControls, null,
					h(comp.PanelBody, { title: 'Trainingszeiten', initialOpen: true },
						h(comp.SelectControl, {
							label: 'Ansicht zu Beginn', value: a.view, __nextHasNoMarginBottom: true,
							options: [{ label: 'Nach Tagen', value: 'days' }, { label: 'Nach Gruppen', value: 'groups' }],
							onChange: function (v) { props.setAttributes({ view: v }); }
						}),
						h('div', { style: { height: '12px' } }),
						h(comp.SelectControl, {
							label: 'Nur eine Gruppe zeigen', value: a.group, __nextHasNoMarginBottom: true,
							help: 'Z. B. auf der Seite der 1. Mannschaft nur deren Zeiten.',
							options: [{ label: 'Alle Gruppen', value: '' }].concat(cfg.groups.map(function (g) { return { label: g, value: g }; })),
							onChange: function (v) { props.setAttributes({ group: v }); }
						}),
						h('div', { style: { height: '12px' } }),
						h(comp.ToggleControl, {
							label: 'Filter und Umschalter anzeigen', checked: !!a.filter, __nextHasNoMarginBottom: true,
							onChange: function (v) { props.setAttributes({ filter: v }); }
						}),
						cfg.admin ? h('p', { style: { marginTop: '16px' } }, h('a', { href: cfg.admin, target: '_blank' }, 'Zeiten bearbeiten (Termine → Trainingszeiten) ↗')) : null
					)
				),
				h(SSR, { block: 'sgnl/training', attributes: { view: a.view, group: a.group, filter: a.filter, align: a.align } })
			);
		},
		save: function () { return null; }
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.serverSideRender);
