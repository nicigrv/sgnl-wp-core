/* Editor für den Block "Eingebettete Seite (iFrame)" – ohne Build-Schritt */
(function (blocks, el, be, comp, i18n) {
	var h = el.createElement, __ = i18n.__;
	blocks.registerBlockType('sgnl/iframe', {
		edit: function (props) {
			var a = props.attributes, set = props.setAttributes;
			var blockProps = be.useBlockProps({ className: 'sgnl-iframe-editor' });
			return h('div', blockProps,
				h(be.InspectorControls, null,
					h(comp.PanelBody, { title: 'Einstellungen', initialOpen: true },
						h(comp.TextControl, { label: 'Adresse (URL)', value: a.src, onChange: function (v) { set({ src: v.trim() }); }, type: 'url', __nextHasNoMarginBottom: true }),
						h(comp.RangeControl, { label: 'Höhe in Pixeln', value: a.height, min: 200, max: 3000, step: 50, onChange: function (v) { set({ height: v }); } }),
						h(comp.TextControl, { label: 'Beschreibung (für Screenreader)', value: a.title, onChange: function (v) { set({ title: v }); }, __nextHasNoMarginBottom: true })
					)
				),
				a.src
					? h('div', { style: { border: '1px dashed #8FC9EC', borderRadius: 12, padding: 16, background: '#EEF5FA' } },
						h('strong', null, 'Eingebettete Seite'),
						h('div', { style: { wordBreak: 'break-all', margin: '6px 0' } }, a.src),
						h('div', { style: { color: '#5B6B83', fontSize: 13 } }, 'Höhe: ' + a.height + ' px – Vorschau auf der Seite.')
					)
					: h(comp.Placeholder, { icon: 'welcome-view-site', label: 'Eingebettete Seite (iFrame)', instructions: 'Adresse der Seite einfügen, die hier angezeigt werden soll.' },
						h(comp.TextControl, { value: a.src, placeholder: 'https://…', onChange: function (v) { set({ src: v.trim() }); }, type: 'url', __nextHasNoMarginBottom: true })
					)
			);
		},
		save: function () { return null; }
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n);
