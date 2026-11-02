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
			var pid = a.parent || postId;
			var images = a.images || {};
			var kids = data.useSelect(function (select) {
				if (!teams || !pid) { return null; }
				return select('core').getEntityRecords('postType', 'page', { parent: pid, per_page: 100, status: 'publish', orderby: 'menu_order', order: 'asc', _fields: 'id,title' });
			}, [teams, pid]);
			var media = data.useSelect(function (select) {
				var m = {};
				Object.keys(images).forEach(function (k) { if (images[k]) { m[k] = select('core').getMedia(images[k]); } });
				return m;
			}, [JSON.stringify(images)]);
			function setImage(pageId, id) {
				var next = Object.assign({}, images);
				if (id) { next[pageId] = id; } else { delete next[pageId]; }
				props.setAttributes({ images: next });
			}
			var teamPanel = !teams ? null : h(comp.PanelBody, { title: 'Bilder der Mannschaften', initialOpen: true },
				h(comp.SelectControl, {
					label: 'Automatisch verwenden', value: String(a.photo || 1), __nextHasNoMarginBottom: true,
					options: [1, 2, 3, 4, 5].map(function (n) { return { label: n + '. Foto auf der Mannschaftsseite', value: String(n) }; }),
					help: 'Runde Trainer-Porträts zählen nicht mit. Hat eine Seite weniger Fotos, wird das letzte genommen.',
					onChange: function (v) { props.setAttributes({ photo: parseInt(v, 10) || 1 }); }
				}),
				h('p', { style: { margin: '16px 0 8px', fontWeight: 600 } }, 'Bild selbst wählen (überschreibt die Automatik)'),
				!kids ? h(comp.Spinner) : (kids.length ? kids.map(function (k) {
					var id = images[k.id], m = media[k.id];
					var thumb = m && (m.media_details && m.media_details.sizes && (m.media_details.sizes.thumbnail || m.media_details.sizes.medium) || null);
					var src = thumb ? thumb.source_url : (m ? m.source_url : '');
					return h('div', { key: k.id, style: { display: 'flex', alignItems: 'center', gap: '10px', padding: '8px 0', borderTop: '1px solid #e0e0e0' } },
						h('div', { style: { width: '56px', height: '42px', flex: 'none', borderRadius: '6px', background: '#EEF5FA', overflow: 'hidden' } },
							id && src ? h('img', { src: src, alt: '', style: { width: '100%', height: '100%', objectFit: 'cover', display: 'block' } }) : null),
						h('div', { style: { flex: 1, minWidth: 0 } },
							h('div', { style: { fontWeight: 600, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' } }, (k.title && k.title.rendered || '').replace(/<[^>]+>/g, '').replace(/&#8211;/g, '–').replace(/&amp;/g, '&')),
							h('div', { style: { color: '#757575', fontSize: '12px' } }, id ? 'eigenes Bild' : 'automatisch')),
						h(be.MediaUploadCheck, null,
							h(be.MediaUpload, {
								allowedTypes: ['image'], value: id || undefined,
								onSelect: function (img) { setImage(k.id, img.id); },
								render: function (o) { return h(comp.Button, { variant: 'secondary', size: 'small', onClick: o.open }, id ? 'Ändern' : 'Wählen'); }
							})),
						id ? h(comp.Button, { variant: 'link', isDestructive: true, size: 'small', onClick: function () { setImage(k.id, 0); } }, 'Automatisch') : null
					);
				}) : h('p', null, 'Noch keine veröffentlichten Unterseiten.'))
			);
			return h('div', blockProps,
				h(be.InspectorControls, null,
					teamPanel,
					h(comp.PanelBody, { title: teams ? 'Mannschaften-Übersicht' : 'Galerie-Übersicht', initialOpen: !teams },
						h(comp.SelectControl, {
							label: 'Art', value: teams ? 'teams' : 'gallery', __nextHasNoMarginBottom: true,
							options: [{ label: 'Galerien (Titelbild + Fotoanzahl)', value: 'gallery' }, { label: 'Mannschaften (Mannschaftsfoto + Trainer)', value: 'teams' }],
							onChange: function (v) { props.setAttributes({ mode: v }); }
						}),
						h('p', { style: { marginTop: '12px' } }, 'Zeigt alle veröffentlichten Unterseiten dieser Seite als Kacheln.'),
						h('p', null, teams
							? 'Bild = Foto auf der Mannschaftsseite (Nummer und eigene Bilder unten unter „Bilder der Mannschaften“), sonst das Beitragsbild. Darunter stehen die Trainer aus den Team-Karten.'
							: 'Titelbild = Beitragsbild der Unterseite, sonst das erste Foto der Galerie. Reihenfolge über „Reihenfolge“ der Seiten, sonst das neueste zuerst.'),
						h(comp.TextControl, {
							label: 'Andere Elternseite (ID, optional)', type: 'number', value: a.parent || '', __nextHasNoMarginBottom: true,
							help: 'Leer lassen = Unterseiten dieser Seite.',
							onChange: function (v) { props.setAttributes({ parent: parseInt(v, 10) || 0 }); }
						})
					)
				),
				h(SSR, { block: 'sgnl/albums', attributes: { parent: a.parent, mode: a.mode || 'gallery', photo: a.photo || 1, images: images }, urlQueryArgs: postId ? { post_id: postId } : {} })
			);
		},
		save: function () { return null; }
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.serverSideRender, window.wp.data);
