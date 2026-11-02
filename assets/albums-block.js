/* Block "Galerie-Übersicht" – Unterseiten als Kacheln (Galerien oder Mannschaften) */
(function (blocks, el, be, comp, SSR, data) {
	var h = el.createElement;
	blocks.registerBlockType('sgnl/albums', {
		variations: [
			{ name: 'gallery', title: 'Galerie-Übersicht', icon: 'images-alt2', isDefault: true, attributes: { mode: 'gallery' }, scope: ['inserter', 'transform'],
				description: 'Zeigt die Unterseiten als Kacheln mit Titelbild – z. B. alle Jahre oder alle Alben eines Jahres.',
				isActive: function (a) { return a.mode !== 'teams'; } },
			{ name: 'teams', title: 'Mannschaften-Übersicht', icon: 'groups', attributes: { mode: 'teams' }, scope: ['inserter', 'transform'],
				description: 'Zeigt alle Mannschaften (Unterseiten) als Kacheln mit dem Mannschaftsfoto der jeweiligen Seite.',
				keywords: ['mannschaft', 'team', 'trainingsgruppe'],
				isActive: function (a) { return a.mode === 'teams'; } }
		],
		edit: function (props) {
			var a = props.attributes;
			var teams = a.mode === 'teams';
			var postId = data.useSelect(function (select) { return select('core/editor') ? select('core/editor').getCurrentPostId() : 0; }, []);
			var blockProps = be.useBlockProps();
			return h('div', blockProps,
				h(be.InspectorControls, null,
					h(comp.PanelBody, { title: teams ? 'Mannschaften-Übersicht' : 'Galerie-Übersicht', initialOpen: true },
						h(comp.SelectControl, {
							label: 'Art', value: teams ? 'teams' : 'gallery', __nextHasNoMarginBottom: true,
							options: [{ label: 'Galerien (Titelbild + Fotoanzahl)', value: 'gallery' }, { label: 'Mannschaften (Mannschaftsfoto + Trainer)', value: 'teams' }],
							onChange: function (v) { props.setAttributes({ mode: v }); }
						}),
						h('p', { style: { marginTop: '12px' } }, 'Zeigt alle veröffentlichten Unterseiten dieser Seite als Kacheln.'),
						h('p', null, teams
							? 'Bild = erstes Foto auf der Mannschaftsseite (nicht die runden Trainer-Porträts), sonst das Beitragsbild. Darunter stehen die Trainer aus den Team-Karten.'
							: 'Titelbild = Beitragsbild der Unterseite, sonst das erste Foto der Galerie. Reihenfolge über „Reihenfolge“ der Seiten, sonst das neueste zuerst.'),
						h(comp.TextControl, {
							label: 'Andere Elternseite (ID, optional)', type: 'number', value: a.parent || '', __nextHasNoMarginBottom: true,
							help: 'Leer lassen = Unterseiten dieser Seite.',
							onChange: function (v) { props.setAttributes({ parent: parseInt(v, 10) || 0 }); }
						})
					)
				),
				h(SSR, { block: 'sgnl/albums', attributes: { parent: a.parent, mode: a.mode || 'gallery' }, urlQueryArgs: postId ? { post_id: postId } : {} })
			);
		},
		save: function () { return null; }
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.serverSideRender, window.wp.data);
