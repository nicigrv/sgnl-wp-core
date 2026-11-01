/* Block "Termine" – Terminplan oder Archiv eines Jahres */
(function (blocks, el, be, comp, SSR) {
	var h = el.createElement, cfg = window.sgnlEventsBlock || {};
	blocks.registerBlockType('sgnl/events', {
		edit: function (props) {
			var a = props.attributes, set = props.setAttributes;
			var archive = a.mode === 'archive';
			var years = (cfg.years || []).slice();
			if (years.indexOf(cfg.current) < 0) { years.unshift(cfg.current); }
			var yearOptions = years.map(function (y) { return { label: String(y), value: y }; });
			var blockProps = be.useBlockProps();
			return h('div', blockProps,
				h(be.InspectorControls, null,
					h(comp.PanelBody, { title: 'Termine', initialOpen: true },
						h(comp.SelectControl, {
							label: 'Anzeigen', value: a.mode, __nextHasNoMarginBottom: true,
							options: [{ label: 'Terminplan (kommende Termine)', value: 'upcoming' }, { label: 'Archiv eines Jahres', value: 'archive' }],
							onChange: function (v) { set({ mode: v, year: v === 'archive' ? (a.year || cfg.current) : 0 }); }
						}),
						archive ? h('div', { style: { marginTop: 16 } }, h(comp.SelectControl, {
							label: 'Jahr', value: a.year || cfg.current, options: yearOptions, __nextHasNoMarginBottom: true,
							onChange: function (v) { set({ year: parseInt(v, 10) || 0 }); }
						})) : null,
						h('div', { style: { marginTop: 16 } }, h(comp.SelectControl, {
							label: 'Ansicht', value: a.view, __nextHasNoMarginBottom: true,
							options: [{ label: 'Liste', value: 'list' }, { label: 'Kacheln', value: 'tiles' }],
							onChange: function (v) { set({ view: v }); }
						})),
						h('div', { style: { marginTop: 16 } }, h(comp.ToggleControl, {
							label: 'Besucher können zwischen Liste und Kacheln wechseln', checked: !!a.switcher, __nextHasNoMarginBottom: true,
							onChange: function (v) { set({ switcher: v }); }
						})),
						!archive ? h('div', { style: { marginTop: 16 } }, h(comp.ToggleControl, {
							label: 'Button „Terminplan herunterladen“ (PDF)', checked: !!a.pdf, __nextHasNoMarginBottom: true,
							onChange: function (v) { set({ pdf: v }); }
						})) : null,
						h('div', { style: { marginTop: 16 } },
							h('p', { style: { fontWeight: 600, margin: '0 0 6px' } }, 'Kategorien'),
							h('p', { style: { fontSize: 12, color: '#757575', margin: '0 0 8px' } }, 'Keine ausgewählt = alle anzeigen.'),
							(cfg.cats || []).map(function (c) {
								var sel = (a.cats || '').split(',').filter(Boolean);
								return h('div', { key: c.slug, style: { marginBottom: 6 } }, h(comp.CheckboxControl, {
									label: c.name, checked: sel.indexOf(c.slug) > -1, __nextHasNoMarginBottom: true,
									onChange: function (v) {
										var next = (cfg.cats || []).map(function (x) { return x.slug; }).filter(function (x) { return x === c.slug ? v : sel.indexOf(x) > -1; });
										set({ cats: next.join(',') });
									}
								}));
							})
						),
						h('div', { style: { marginTop: 12 } }, h(comp.ToggleControl, {
							label: 'Filter nach Kategorie anzeigen', checked: a.filter !== false, __nextHasNoMarginBottom: true,
							onChange: function (v) { set({ filter: v }); }
						})),
						h('p', { style: { marginTop: 16 } }, h('a', { href: cfg.listUrl, target: '_blank', rel: 'noopener' }, 'Termine verwalten ↗')),
						h('p', null, h('a', { href: cfg.newUrl, target: '_blank', rel: 'noopener' }, 'Neuen Termin anlegen ↗'))
					)
				),
				h(SSR, { block: 'sgnl/events', attributes: { mode: a.mode, year: a.year, view: a.view, switcher: a.switcher, pdf: a.pdf, cats: a.cats, filter: a.filter } }),
				h('p', { style: { fontSize: 12, color: '#5B6B83', marginTop: 6 } },
					archive ? 'Vergangene Termine erscheinen hier automatisch. ' : 'Vergangene Termine wandern automatisch ins Archiv. ',
					h('a', { href: cfg.listUrl, target: '_blank', rel: 'noopener' }, 'Termine verwalten ↗'))
			);
		},
		save: function () { return null; }
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.serverSideRender);
