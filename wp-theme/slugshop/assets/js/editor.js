/**
 * A téma saját blokkjai a szerkesztőben, ha az iu_custom_blocks mu-plugin nincs telepítve (vele az iucb_add_block
 * regisztrálja őket). Minden blokk dinamikus (a kimenetet a PHP adja): a szerkesztőben egy felcímkézett helyőrző
 * látszik, a beállítások az oldalsávban (Beállítások panel) szerkeszthetők.
 */
(function (blocks, element, blockEditor, components) {
	var h = element.createElement;

	function control(field, props) {
		var value = props.attributes[field.key];
		var set = function (v) {
			var attrs = {};
			var def = props.attributes[field.key];
			attrs[field.key] = typeof def === 'number' ? (parseInt(v, 10) || 0) : v;
			props.setAttributes(attrs);
		};
		var common = { key: field.key, label: field.label, __nextHasNoMarginBottom: true };
		if (field.type === 'toggle') {
			return h(components.ToggleControl, Object.assign(common, { checked: !!value, onChange: set }));
		}
		if (field.type === 'select') {
			return h(components.SelectControl, Object.assign(common, { value: value, options: field.options, onChange: set }));
		}
		if (field.type === 'textarea') {
			return h(components.TextareaControl, Object.assign(common, { value: value == null ? '' : String(value), onChange: set, rows: 6 }));
		}
		return h(components.TextControl, Object.assign(common, { value: value == null ? '' : String(value), onChange: set, __next40pxDefaultSize: true }));
	}

	(window.SLUGSHOP_BLOCKS || []).forEach(function (def) {
		if (blocks.getBlockType(def.name)) {
			return;
		}
		var attributes = {};
		Object.keys(def.attributes || {}).forEach(function (key) {
			attributes[key] = def.attributes[key];
		});
		attributes.className = { type: 'string', default: '' };
		blocks.registerBlockType(def.name, {
			apiVersion: 3,
			title: def.title,
			category: 'widgets',
			icon: 'screenoptions',
			attributes: attributes,
			supports: { html: false, customClassName: true },
			edit: function (props) {
				var blockProps = blockEditor.useBlockProps({ className: 'slug-editor-block' });
				var fields = def.fields || [];
				return h(element.Fragment, null,
					fields.length ? h(blockEditor.InspectorControls, null,
						h(components.PanelBody, { title: 'Beállítások', initialOpen: true }, fields.map(function (f) { return control(f, props); }))
					) : null,
					h('div', blockProps, def.title, h('small', null, fields.length ? 'A beállítások a jobb oldali panelen.' : 'A tartalmat a téma adja (előnézet: az oldal megtekintése).'))
				);
			},
			save: function () {
				return null;
			}
		});
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components);
