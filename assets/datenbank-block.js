/* Block "Datenbank" – Reiter auswählen */
(function (blocks, el, be, comp) {
	var h = el.createElement, cfg = window.sgnlDbBlock || { tabs: [] };
	var kinds = { live: 'aus den Terminen', events: 'aus den Terminen', iframe: 'Vereinsdatenbank (webclub)' };
	blocks.registerBlockType('sgnl/datenbank', {
		edit: function (props) {
			var sel = (props.attributes.tabs || '').split(',').filter(Boolean);
			function toggle(id, on) {
				var next = cfg.tabs.map(function (t) { return t.id; }).filter(function (x) { return x === id ? on : sel.indexOf(x) > -1; });
				props.setAttributes({ tabs: next.join(',') });
			}
			var chosen = cfg.tabs.filter(function (t) { return sel.indexOf(t.id) > -1; });
			return h('div', be.useBlockProps(),
				h(be.InspectorControls, null,
					h(comp.PanelBody, { title: 'Reiter', initialOpen: true },
						cfg.tabs.map(function (t) {
							return h('div', { key: t.id, style: { marginBottom: 10 } }, h(comp.CheckboxControl, {
								label: t.label, help: kinds[t.kind], checked: sel.indexOf(t.id) > -1, __nextHasNoMarginBottom: true,
								onChange: function (v) { toggle(t.id, v); }
							}));
						})
					)
				),
				h('div', { style: { borderRadius: 14, overflow: 'hidden', border: '1px solid #DCE6F0' } },
					h('div', { style: { background: '#0B2A5B', padding: '10px 12px', display: 'flex', gap: 6, flexWrap: 'wrap' } },
						chosen.map(function (t, i) {
							return h('span', { key: t.id, style: { color: i ? '#B9D4EA' : '#fff', background: i ? 'transparent' : 'rgba(255,255,255,.12)', padding: '8px 14px', borderRadius: 8, fontWeight: 600 } }, t.label);
						})
					),
					h('div', { style: { padding: 20, color: '#5B6B83', background: '#fff' } },
						chosen.length ? 'Datenbank mit ' + chosen.length + ' Reitern. Reiter rechts in den Block-Einstellungen auswählen. Live und Termine kommen aus Dashboard → Termine.' : 'Bitte rechts mindestens einen Reiter auswählen.')
				)
			);
		},
		save: function () { return null; }
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components);
