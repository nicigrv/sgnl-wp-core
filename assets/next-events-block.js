/* Block "Nächste Termine" */
(function (blocks, el, be, comp, SSR) {
	var h = el.createElement;
	blocks.registerBlockType('sgnl/next-events', {
		edit: function (props) {
			var a = props.attributes, set = props.setAttributes;
			return h('div', be.useBlockProps(),
				h(be.InspectorControls, null,
					h(comp.PanelBody, { title: 'Nächste Termine', initialOpen: true },
						h(comp.RangeControl, { label: 'Anzahl', min: 1, max: 10, value: a.count, onChange: function (v) { set({ count: v || 3 }); }, __nextHasNoMarginBottom: true }),
						h('div', { style: { marginTop: 12 } }, h(comp.ToggleControl, { label: 'Link „Zum Terminplan“', checked: !!a.showLink, onChange: function (v) { set({ showLink: v }); }, __nextHasNoMarginBottom: true })),
						h('p', { style: { fontSize: 12, color: '#757575', marginTop: 12 } }, 'Termine werden unter Dashboard → Termine gepflegt.')
					)
				),
				h(SSR, { block: 'sgnl/next-events', attributes: { count: a.count, cats: a.cats, showLink: a.showLink } })
			);
		},
		save: function () { return null; }
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.serverSideRender);
