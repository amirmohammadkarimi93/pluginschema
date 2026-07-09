jQuery(document).ready(function($) {

    function t(key, fallback) {
        return (window.AMKSchemaCoreI18n && AMKSchemaCoreI18n[key]) ? AMKSchemaCoreI18n[key] : fallback;
    }

    const $builder = $('#amk-binding-builder');
    const $rows = $('#amk-binding-rows');
    const $textarea = $('#amk-bindings-json');

    if (!$builder.length || !$rows.length || !$textarea.length) {
        return;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function parseBindingsJson() {
        const raw = String($textarea.val() || '').trim();

        if (!raw) {
            return {};
        }

        try {
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
        } catch (e) {
            console.warn('AMK Binding JSON parse error:', e);
            return {};
        }
    }

    function sourceOptions(selected) {
        const options = {
            resolver: t('source_resolver', 'Resolver variable'),
            value: t('source_value', 'Static value'),
            post_meta: t('source_post_meta', 'Post Meta'),
            product_meta: t('source_product_meta', 'Product Meta'),
            product_attribute: t('source_product_attribute', 'WooCommerce product attribute'),
            taxonomy_terms: t('source_taxonomy_terms', 'Taxonomy terms'),
            term_meta: t('source_term_meta', 'Term Meta'),
            user_meta: t('source_user_meta', 'User Meta'),
            option: t('source_option', 'WordPress option'),
            theme_mod: t('source_theme_mod', 'Theme Mod'),
            global_setting: t('source_global_setting', 'Plugin global setting')
        };

        let html = '';

        Object.keys(options).forEach(function(value) {
            html += `<option value="${escapeHtml(value)}" ${selected === value ? 'selected' : ''}>${escapeHtml(options[value])}</option>`;
        });

        return html;
    }

    function transformOptions(selected) {
        const options = {
            '': t('transform_none', 'No transform'),
            string: t('transform_string', 'Convert to string'),
            strip_tags: t('transform_strip_tags', 'Strip HTML'),
            sanitize_text: t('transform_sanitize_text', 'Sanitize text'),
            url: t('transform_url', 'Sanitize URL'),
            absint: t('transform_absint', 'Positive integer'),
            float: t('transform_float', 'Float number'),
            bool: t('transform_bool', 'Boolean'),
            csv_array: t('transform_csv_array', 'CSV to array'),
            first: t('transform_first', 'First array value')
        };

        let html = '';

        Object.keys(options).forEach(function(value) {
            html += `<option value="${escapeHtml(value)}" ${selected === value ? 'selected' : ''}>${escapeHtml(options[value])}</option>`;
        });

        return html;
    }

    function normalizeBinding(key, binding) {
        const normalized = {
            variable: key || '',
            source: 'resolver',
            key: '',
            default: '',
            transform: ''
        };

        if (typeof binding === 'string') {
            normalized.source = 'resolver';
            normalized.key = binding;
            return normalized;
        }

        if (!binding || typeof binding !== 'object' || Array.isArray(binding)) {
            return normalized;
        }

        if (Object.prototype.hasOwnProperty.call(binding, 'value')) {
            normalized.source = 'value';
            normalized.key = valueToInput(binding.value);
        } else {
            normalized.source = binding.source || 'resolver';

            if (binding.data_key) {
                normalized.key = binding.data_key;
            } else if (binding.path) {
                normalized.key = binding.path;
            } else if (binding.key) {
                normalized.key = binding.key;
            } else if (binding.option) {
                normalized.key = binding.option;
            } else if (binding.taxonomy) {
                normalized.key = binding.taxonomy;
            } else if (binding.attribute) {
                normalized.key = binding.attribute;
            }
        }

        if (Object.prototype.hasOwnProperty.call(binding, 'default')) {
            normalized.default = valueToInput(binding.default);
        }

        if (binding.transform) {
            normalized.transform = Array.isArray(binding.transform) ? binding.transform[0] : binding.transform;
        }

        return normalized;
    }

    function valueToInput(value) {
        if (value === null || typeof value === 'undefined') {
            return '';
        }

        if (typeof value === 'object') {
            try {
                return JSON.stringify(value);
            } catch (e) {
                return '';
            }
        }

        return String(value);
    }

    function parseMaybeJson(value) {
        value = String(value || '').trim();

        if (value === '') {
            return '';
        }

        const first = value.charAt(0);
        const last = value.charAt(value.length - 1);

        const looksLikeJson =
            (first === '{' && last === '}') ||
            (first === '[' && last === ']') ||
            (first === '"' && last === '"') ||
            value === 'true' ||
            value === 'false' ||
            value === 'null';

        if (!looksLikeJson) {
            return value;
        }

        try {
            return JSON.parse(value);
        } catch (e) {
            return value;
        }
    }

    function rowTemplate(binding = {}) {
        const variable = escapeHtml(binding.variable || '');
        const source = binding.source || 'resolver';
        const key = escapeHtml(binding.key || '');
        const def = escapeHtml(binding.default || '');
        const transform = binding.transform || '';

        return `
            <div class="amk-binding-row">
                <div class="amk-binding-grid">

                    <label>
                        <strong>${escapeHtml(t('variable_name', 'Variable name'))}</strong>
                        <input type="text" class="amk-binding-variable" value="${variable}" placeholder="${escapeHtml(t('example_custom_gtin', 'For example custom_gtin'))}">
                    </label>

                    <label>
                        <strong>${escapeHtml(t('data_source', 'Data source'))}</strong>
                        <select class="amk-binding-source">
                            ${sourceOptions(source)}
                        </select>
                    </label>

                    <label>
                        <strong class="amk-binding-key-label">${escapeHtml(t('key_path_value', 'Key / path / value'))}</strong>
                        <input type="text" class="amk-binding-key" value="${key}" placeholder="${escapeHtml(t('example_binding_key', 'For example _gtin, pa_brand, or title'))}">
                    </label>

                    <label>
                        <strong>${escapeHtml(t('default_value', 'Default value'))}</strong>
                        <input type="text" class="amk-binding-default" value="${def}" placeholder="${escapeHtml(t('optional', 'Optional'))}">
                    </label>

                    <label>
                        <strong>${escapeHtml(t('value_transform', 'Value transform'))}</strong>
                        <select class="amk-binding-transform">
                            ${transformOptions(transform)}
                        </select>
                    </label>

                    <button type="button" class="button-link-delete amk-remove-binding">
                        ${escapeHtml(t('remove', 'Remove'))}
                    </button>

                </div>

                <p class="description amk-binding-help"></p>
            </div>
        `;
    }

    function updateRowHelp($row) {
        const source = $row.find('.amk-binding-source').val();
        const $help = $row.find('.amk-binding-help');
        const $keyLabel = $row.find('.amk-binding-key-label');

        const helps = {
            resolver: {
                label: t('resolver_key', 'Resolver key'),
                text: t('resolver_key_help', 'For example title, price, organization_name, or breadcrumb_items.')
            },
            value: {
                label: t('source_value', 'Static value'),
                text: t('static_value_help', 'The entered value is used exactly as the placeholder value.')
            },
            post_meta: {
                label: 'Meta Key',
                text: t('post_meta_help', 'For example _yoast_wpseo_title or a custom field for the post/page.')
            },
            product_meta: {
                label: 'Product Meta Key',
                text: t('product_meta_help', 'For example _gtin, _mpn, or any custom product meta.')
            },
            product_attribute: {
                label: 'Attribute / Taxonomy',
                text: t('attribute_help', 'For example pa_brand or pa_color.')
            },
            taxonomy_terms: {
                label: 'Taxonomy',
                text: t('taxonomy_help', 'For example product_cat, product_tag, pa_brand, or category.')
            },
            term_meta: {
                label: 'Term Meta Key',
                text: t('term_meta_help', 'Meta key for the current term.')
            },
            user_meta: {
                label: 'User Meta Key',
                text: t('user_meta_help', 'Meta key for the content author.')
            },
            option: {
                label: 'Option Name',
                text: t('option_help', 'For example blogname or a custom option.')
            },
            theme_mod: {
                label: 'Theme Mod Key',
                text: t('theme_mod_help', 'For example custom_logo or a custom theme mod.')
            },
            global_setting: {
                label: t('settings_path', 'Settings path'),
                text: t('settings_path_help', 'For example organization.name or commerce.return_policy.')
            }
        };

        const item = helps[source] || helps.resolver;

        $keyLabel.text(item.label);
        $help.text(item.text);
    }

    function renderExistingBindings() {
        const bindings = parseBindingsJson();
        $rows.empty();

        Object.keys(bindings).forEach(function(key) {
            const normalized = normalizeBinding(key, bindings[key]);
            const $row = $(rowTemplate(normalized));
            $rows.append($row);
            updateRowHelp($row);
        });

        if (!$rows.find('.amk-binding-row').length) {
            addEmptyRow();
        }
    }

    function addEmptyRow() {
        const $row = $(rowTemplate({}));
        $rows.append($row);
        updateRowHelp($row);
    }

    function collectBindings() {
        const bindings = {};
        let invalid = false;

        $rows.find('.amk-binding-row').each(function() {
            const $row = $(this);

            const variable = String($row.find('.amk-binding-variable').val() || '').trim();
            const source = String($row.find('.amk-binding-source').val() || 'resolver').trim();
            const key = String($row.find('.amk-binding-key').val() || '').trim();
            const def = String($row.find('.amk-binding-default').val() || '').trim();
            const transform = String($row.find('.amk-binding-transform').val() || '').trim();

            if (!variable && !key && !def) {
                return;
            }

            if (!variable || !source) {
                markInvalid($row);
                invalid = true;
                return;
            }

            if (source !== 'value' && !key) {
                markInvalid($row);
                invalid = true;
                return;
            }

            markValid($row);

            let binding = {};

            if (source === 'value') {
                binding.value = parseMaybeJson(key);
            } else if (source === 'resolver' || source === 'data' || source === 'data_key') {
                binding.source = 'resolver';
                binding.data_key = key;
            } else if (source === 'global_setting') {
                binding.source = source;
                binding.path = key;
            } else if (source === 'product_attribute') {
                binding.source = source;
                binding.key = key;
            } else if (source === 'taxonomy_terms') {
                binding.source = source;
                binding.taxonomy = key;
            } else {
                binding.source = source;
                binding.key = key;
            }

            if (def !== '') {
                binding.default = parseMaybeJson(def);
            }

            if (transform !== '') {
                binding.transform = transform;
            }

            bindings[variable] = binding;
        });

        if (invalid) {
            return null;
        }

        return bindings;
    }

    function syncTextarea() {
        const bindings = collectBindings();

        if (bindings === null) {
            return false;
        }

        $textarea.val(JSON.stringify(bindings, null, 2));

        return true;
    }

    function markInvalid($row) {
        $row.css({
            borderColor: '#f43f5e',
            backgroundColor: '#fff1f2'
        });
    }

    function markValid($row) {
        $row.css({
            borderColor: '#e5e7eb',
            backgroundColor: '#ffffff'
        });
    }

    $(document).on('click', '#amk-add-binding', function(e) {
        e.preventDefault();
        addEmptyRow();
        syncTextarea();
    });

    $(document).on('click', '.amk-remove-binding', function(e) {
        e.preventDefault();

        const $currentRows = $rows.find('.amk-binding-row');

        if ($currentRows.length <= 1) {
            const $row = $(this).closest('.amk-binding-row');
            $row.find('input').val('');
            $row.find('select').prop('selectedIndex', 0);
            updateRowHelp($row);
        } else {
            $(this).closest('.amk-binding-row').remove();
        }

        syncTextarea();
    });

    $(document).on('change keyup', '.amk-binding-row input, .amk-binding-row select', function() {
        const $row = $(this).closest('.amk-binding-row');

        if ($(this).hasClass('amk-binding-source')) {
            updateRowHelp($row);
        }

        syncTextarea();
    });

    $(document).on('click', '#amk-format-bindings-json', function(e) {
        e.preventDefault();

        try {
            const parsed = JSON.parse($textarea.val() || '{}');
            $textarea.val(JSON.stringify(parsed, null, 2));
            renderExistingBindings();
        } catch (error) {
            alert(t('binding_json_invalid', 'Binding JSON is invalid: ') + error.message);
        }
    });

    $(document).on('click', '#amk-sync-bindings-from-json', function(e) {
        e.preventDefault();
        renderExistingBindings();
    });

    renderExistingBindings();

});
