/* Block "Galerie-Übersicht" – Unterseiten als Kacheln */
(function (blocks, el, be, comp, SSR, data) {
	var h = el.createElement;
	blocks.registerBlockType('sgnl/albums', {
		edit: function (props) {
			var a = props.attributes;
			var postId = data.useSelect(function (select) { return select('core/editor') ? select('core/editor').getCurrentPostId() : 0; }, []);
			var blockProps = be.useBlockProps();
			return h('div', blockProps,
				h(be.InspectorControls, null,
					h(comp.PanelBody, { title: 'Galerie-Übersicht', initialOpen: true },
						h('p', null, 'Zeigt alle veröffentlichten Unterseiten dieser Seite als Kacheln.'),
						h('p', null, 'Titelbild = Beitragsbild der Unterseite, sonst das erste Foto der Galerie. Reihenfolge über „Reihenfolge“ der Seiten, sonst das neueste zuerst.'),
						h(comp.TextControl, {
							label: 'Andere Elternseite (ID, optional)', type: 'number', value: a.parent || '', __nextHasNoMarginBottom: true,
							help: 'Leer lassen = Unterseiten dieser Seite.',
							onChange: function (v) { props.setAttributes({ parent: parseInt(v, 10) || 0 }); }
						})
					)
				),
				h(SSR, { block: 'sgnl/albums', attributes: { parent: a.parent }, urlQueryArgs: postId ? { post_id: postId } : {} })
			);
		},
		save: function () { return null; }
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.serverSideRender, window.wp.data);
