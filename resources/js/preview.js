jQuery(document).ready(function($) {

    function t(key, fallback) {
        return (window.AMKSchemaCoreI18n && AMKSchemaCoreI18n[key]) ? AMKSchemaCoreI18n[key] : fallback;
    }

    ensurePreviewButton();

    function getTemplateId() {
        const urlParams = new URLSearchParams(window.location.search);
        const templateId = urlParams.get('template_id') || urlParams.get('id');

        if (templateId) {
            return parseInt(templateId, 10) || 0;
        }

        const inputValue = $('input[name="template_id"], input[name="id"]').first().val();

        return inputValue ? parseInt(inputValue, 10) || 0 : 0;
    }

    function getFieldValue(selectors, defaultValue = '') {
        for (let i = 0; i < selectors.length; i++) {
            const $field = $(selectors[i]).first();

            if ($field.length) {
                return $field.val();
            }
        }

        return defaultValue;
    }

    function parseJsonField(value, fieldName, allowEmpty = true, emptyValue = []) {
        if (typeof value !== 'string') {
            return value;
        }

        value = value.trim();

        if (value === '') {
            return allowEmpty ? emptyValue : null;
        }

        try {
            return JSON.parse(value);
        } catch (e) {
            alert(fieldName + t('json_field_invalid', ' is invalid. Check the JSON structure.') + '\n\n' + e.message);
            return null;
        }
    }

    function collectSchemaJson() {
        const raw = getFieldValue([
            'textarea[name="schema_json"]',
            '#amk-schema-json',
            '#schema_json',
            '#amk_schema_json'
        ]);

        return parseJsonField(raw, 'JSON-LD', false);
    }

    function collectBindings() {
        const raw = getFieldValue([
            'textarea[name="bindings"]',
            '#amk-bindings-json',
            '#bindings',
            '#amk_schema_bindings'
        ], '{}');

        return parseJsonField(raw, 'Bindings', true, {});
    }

    function collectConditions() {
        const fromRows = collectConditionsFromRows();

        if (fromRows.length) {
            return fromRows;
        }

        const raw = getFieldValue([
            'textarea[name="conditions"]',
            '#amk-schema-conditions',
            '#conditions',
            '#amk_schema_conditions'
        ], '[]');

        return parseJsonField(raw, 'Conditions', true, []);
    }

    function collectConditionsFromRows() {
        const rows = [];

        $('#amk-conditions-list .amk-condition-row, #conditions-container .condition-row').each(function(index) {
            const $row = $(this);

            if ($row.hasClass('amk-condition-row-template')) {
                return;
            }

            const dataKey = getRowFieldValue($row, [
                '[name$="[data_key]"]',
                '[name="field[]"]',
                '[name$="[field]"]',
                '[name$="[key]"]'
            ]);

            const operator = getRowFieldValue($row, [
                '[name$="[operator]"]',
                '[name="operator[]"]'
            ], 'empty');

            const expectedRaw = getRowFieldValue($row, [
                '[name$="[expected]"]',
                '[name="value[]"]',
                '[name$="[value]"]'
            ], '');

            const action = getRowFieldValue($row, [
                '[name$="[action]"]',
                '[name="action[]"]'
            ], 'remove');

            const path = getRowFieldValue($row, [
                '[name$="[path]"]',
                '[name="path[]"]',
                '[name$="[target_path]"]',
                '[name$="[schema_path]"]'
            ], '');

            if (!dataKey && !operator && !expectedRaw && !path) {
                return;
            }

            if (!dataKey) {
                return;
            }

            rows.push({
                data_key: dataKey,
                field: dataKey,
                operator: operator || 'empty',
                expected: parseMaybeJson(expectedRaw),
                value: parseMaybeJson(expectedRaw),
                action: normalizeAction(action),
                path: path,
                payload: {},
                priority: index,
                status: 'active'
            });
        });

        return rows;
    }

    function getRowFieldValue($row, selectors, defaultValue = '') {
        for (let i = 0; i < selectors.length; i++) {
            const $field = $row.find(selectors[i]).first();

            if ($field.length) {
                return String($field.val() || '').trim();
            }
        }

        return defaultValue;
    }

    function parseMaybeJson(value) {
        if (typeof value !== 'string') {
            return value;
        }

        value = value.trim();

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

    function normalizeAction(action) {
        action = String(action || 'remove').trim();

        if (action === 'remove_path') {
            return 'remove_path';
        }

        return 'remove';
    }

    function collectPreviewData() {
        const schemaJson = collectSchemaJson();

        if (schemaJson === null) {
            return null;
        }

        const bindings = collectBindings();

        if (bindings === null) {
            return null;
        }

        const conditions = collectConditions();

        if (conditions === null) {
            return null;
        }

        const scope = getFieldValue(['select[name="scope"]', 'input[name="scope"]'], 'default');

        return {
            template_id: getTemplateId(),
            name: getFieldValue(['input[name="name"]'], 'Preview Template'),
            type: getFieldValue(['select[name="type"]', 'input[name="type"]'], 'custom'),
            scope: scope,
            context: scope,
            status: getFieldValue(['select[name="status"]', 'input[name="status"]'], 'active'),
            schema_json: schemaJson,
            bindings: bindings,
            conditions: conditions,
            priority: parseInt(getFieldValue(['input[name="priority"]'], '0'), 10) || 0,
            override: $('input[name="override"]').is(':checked') ? 1 : 0
        };
    }

    function ensurePreviewButton() {
        if ($('#preview-schema, .amk-preview-schema, [data-amk-action="preview"]').length) {
            return;
        }

        const $actions = $('.amk-editor-actions').first();

        if (!$actions.length) {
            return;
        }

        $actions.append(
            '<button type="button" id="preview-schema" class="button button-secondary amk-preview-schema">' + escapeHtml(t('preview_button', 'Preview dynamic output')) + '</button>'
        );
    }

    function ensurePreviewModal() {
        if ($('#amk-schema-preview-modal').length) {
            return;
        }

        $('body').append(`
            <div id="amk-schema-preview-modal" style="display:none;">
                <div class="amk-schema-preview-backdrop"></div>

                <div class="amk-schema-preview-box">
                    <div class="amk-schema-preview-header">
                        <strong>${escapeHtml(t('preview_title', 'JSON-LD Preview'))}</strong>

                        <div>
                            <button type="button" class="button amk-schema-preview-copy">${escapeHtml(t('copy_json', 'Copy JSON'))}</button>
                            <button type="button" class="button amk-schema-preview-close">${escapeHtml(t('close', 'Close'))}</button>
                        </div>
                    </div>

                    <div id="amk-schema-preview-validation"></div>

                    <div class="amk-schema-preview-tabs">
                        <button type="button" class="button amk-preview-tab active" data-target="json">${escapeHtml(t('json_output', 'JSON-LD output'))}</button>
                        <button type="button" class="button amk-preview-tab" data-target="resolver">${escapeHtml(t('resolver_data', 'Resolver data'))}</button>
                    </div>

                    <pre id="amk-schema-preview-output"></pre>
                    <pre id="amk-schema-preview-resolver" style="display:none;"></pre>
                </div>
            </div>
        `);

        if (!$('#amk-schema-preview-style').length) {
            $('head').append(`
                <style id="amk-schema-preview-style">
                    #amk-schema-preview-modal {
                        position: fixed;
                        z-index: 999999;
                        inset: 0;
                    }

                    .amk-schema-preview-backdrop {
                        position: absolute;
                        inset: 0;
                        background: rgba(15, 23, 42, 0.56);
                    }

                    .amk-schema-preview-box {
                        position: relative;
                        max-width: 980px;
                        max-height: 84vh;
                        overflow: auto;
                        margin: 6vh auto;
                        background: #fff;
                        padding: 20px;
                        border-radius: 14px;
                        box-shadow: 0 18px 60px rgba(0, 0, 0, 0.32);
                        direction: rtl;
                    }

                    .amk-schema-preview-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        gap: 12px;
                        margin-bottom: 15px;
                        padding-bottom: 12px;
                        border-bottom: 1px solid #e5e7eb;
                    }

                    .amk-schema-preview-tabs {
                        display: flex;
                        gap: 8px;
                        margin: 12px 0;
                    }

                    .amk-preview-tab.active {
                        background: #1d2327;
                        color: #fff;
                        border-color: #1d2327;
                    }

                    #amk-schema-preview-output,
                    #amk-schema-preview-resolver {
                        direction: ltr;
                        text-align: left;
                        background: #0f172a;
                        color: #e5e7eb;
                        padding: 16px;
                        overflow: auto;
                        white-space: pre-wrap;
                        border-radius: 10px;
                        max-height: 55vh;
                        line-height: 1.6;
                    }

                    #amk-schema-preview-validation {
                        margin-bottom: 12px;
                    }

                    #amk-schema-preview-validation .notice {
                        margin: 5px 0;
                        padding: 8px 10px;
                        border-radius: 8px;
                    }

                    #amk-schema-preview-validation ul {
                        margin: 8px 20px 0 0;
                    }
                </style>
            `);
        }
    }

    function renderValidation(validation) {
        if (!validation) {
            $('#amk-schema-preview-validation').html('');
            return;
        }

        let html = '';

        if (validation.valid === true && !hasItems(validation.errors) && !hasItems(validation.warnings)) {
            html += '<div class="notice notice-success"><p>' + escapeHtml(t('validation_passed', 'Output passed validation.')) + '</p></div>';
        }

        if (hasItems(validation.errors)) {
            html += '<div class="notice notice-error"><strong>' + escapeHtml(t('errors', 'Errors:')) + '</strong><ul>';

            validation.errors.forEach(function(error) {
                html += '<li>' + escapeHtml(error) + '</li>';
            });

            html += '</ul></div>';
        }

        if (hasItems(validation.warnings)) {
            html += '<div class="notice notice-warning"><strong>' + escapeHtml(t('warnings', 'Warnings:')) + '</strong><ul>';

            validation.warnings.forEach(function(warning) {
                html += '<li>' + escapeHtml(warning) + '</li>';
            });

            html += '</ul></div>';
        }

        $('#amk-schema-preview-validation').html(html);
    }

    function hasItems(value) {
        return Array.isArray(value) && value.length > 0;
    }

    function renderPreview(response) {
        ensurePreviewModal();

        const jsonLd = response.json_ld || response.jsonld || response.schema || null;

        if (!jsonLd) {
            alert(response.message || t('preview_missing', 'Preview output was not received.'));
            return;
        }

        $('#amk-schema-preview-output').text(
            JSON.stringify(jsonLd, null, 2)
        );

        $('#amk-schema-preview-resolver').text(
            JSON.stringify(response.resolver || {}, null, 2)
        );

        renderValidation(response.validation || null);

        showPreviewTab('json');

        $('#amk-schema-preview-modal').show();
    }

    function showPreviewTab(target) {
        $('.amk-preview-tab').removeClass('active');
        $('.amk-preview-tab[data-target="' + target + '"]').addClass('active');

        if (target === 'resolver') {
            $('#amk-schema-preview-output').hide();
            $('#amk-schema-preview-resolver').show();
            return;
        }

        $('#amk-schema-preview-resolver').hide();
        $('#amk-schema-preview-output').show();
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function hasRestSettings() {
        return typeof wpApiSettings !== 'undefined' &&
            wpApiSettings.root &&
            wpApiSettings.nonce;
    }

    function setPreviewLoading(isLoading) {
        const $button = $('#preview-schema, .amk-preview-schema, [data-amk-action="preview"]').first();

        if (!$button.length) {
            return;
        }

        if (isLoading) {
            $button.prop('disabled', true).data('old-text', $button.text()).text(t('preview_loading', 'Building preview...'));
        } else {
            $button.prop('disabled', false).text($button.data('old-text') || t('preview_button', 'Preview dynamic output'));
        }
    }

    function sendPreviewRequest(data) {
        if (!hasRestSettings()) {
            alert(t('rest_settings_missing', 'REST settings are not loaded on this page. Check Assets.php.'));
            return;
        }

        setPreviewLoading(true);

        $.ajax({
            url: wpApiSettings.root + 'amk-schema/preview',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
            },
            success: function(response) {
                if (!response || response.status !== 'success') {
                    alert((response && response.message) ? response.message : t('preview_error', 'Error building preview.'));
                    return;
                }

                renderPreview(response);
            },
            error: function(xhr, status, error) {
                console.error('AMK Schema Preview Error:', status, error, xhr.responseText);
                alert(t('preview_fetch_error', 'Error fetching preview. Check the browser console for details.'));
            },
            complete: function() {
                setPreviewLoading(false);
            }
        });
    }

    $(document).on('click', '#preview-schema, .amk-preview-schema, [data-amk-action="preview"]', function(e) {
        e.preventDefault();

        const data = collectPreviewData();

        if (!data) {
            return;
        }

        sendPreviewRequest(data);
    });

    $(document).on('click', '.amk-schema-preview-close, .amk-schema-preview-backdrop', function(e) {
        e.preventDefault();

        $('#amk-schema-preview-modal').hide();
    });

    $(document).on('click', '.amk-preview-tab', function(e) {
        e.preventDefault();

        showPreviewTab($(this).data('target') || 'json');
    });

    $(document).on('click', '.amk-schema-preview-copy', function(e) {
        e.preventDefault();

        const text = $('#amk-schema-preview-output').text();

        if (!text) {
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                alert(t('json_copied', 'JSON copied.'));
            });
            return;
        }

        const $tmp = $('<textarea>');
        $('body').append($tmp);
        $tmp.val(text).select();
        document.execCommand('copy');
        $tmp.remove();

        alert(t('json_copied', 'JSON copied.'));
    });

});