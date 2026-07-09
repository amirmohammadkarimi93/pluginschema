jQuery(document).ready(function($) {

    function t(key, fallback) {
        return (window.AMKSchemaCoreI18n && AMKSchemaCoreI18n[key]) ? AMKSchemaCoreI18n[key] : fallback;
    }

    const $container = $('#conditions-container');
    const $textarea = $('textarea[name="conditions"], #amk-schema-conditions').first();

    if (!$container.length) {
        return;
    }

    function getTemplateId() {
        const urlParams = new URLSearchParams(window.location.search);
        const templateIdFromUrl = urlParams.get('template_id') || urlParams.get('id');

        if (templateIdFromUrl) {
            return parseInt(templateIdFromUrl, 10) || 0;
        }

        const templateIdFromInput = $('input[name="template_id"], input[name="id"]').first().val();

        return templateIdFromInput ? parseInt(templateIdFromInput, 10) || 0 : 0;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
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

        if (looksLikeJson) {
            try {
                return JSON.parse(value);
            } catch (e) {
                return value;
            }
        }

        if (!isNaN(value) && value !== '') {
            return value;
        }

        return value;
    }

    function normalizeCondition(condition, index) {
        condition = condition || {};

        const dataKey = condition.data_key || condition.field || condition.key || '';
        const operator = condition.operator || 'empty';
        const expected = typeof condition.expected !== 'undefined'
            ? condition.expected
            : (typeof condition.value !== 'undefined' ? condition.value : '');
        const action = condition.action || 'remove';
        const path = condition.path || condition.target_path || condition.schema_path || '';

        return {
            data_key: String(dataKey || '').trim(),
            field: String(dataKey || '').trim(),
            operator: String(operator || 'empty').trim(),
            expected: expected,
            value: expected,
            action: normalizeAction(action),
            path: String(path || '').trim(),
            payload: {},
            priority: Number.isInteger(index) ? index : 0,
            status: condition.status || 'active'
        };
    }

    function normalizeAction(action) {
        action = String(action || 'remove').trim();

        if (action === 'remove_path') {
            return 'remove_path';
        }

        return 'remove';
    }

    function getConditionsFromTextarea() {
        if (!$textarea.length) {
            return [];
        }

        const raw = ($textarea.val() || '').trim();

        if (raw === '') {
            return [];
        }

        try {
            const parsed = JSON.parse(raw);

            if (!Array.isArray(parsed)) {
                return [];
            }

            return parsed.map(function(condition, index) {
                return normalizeCondition(condition, index);
            }).filter(function(condition) {
                return condition.data_key !== '';
            });

        } catch (e) {
            console.warn('AMK Conditions JSON parse error:', e);
            return [];
        }
    }

    function syncTextarea(conditions) {
        if (!$textarea.length) {
            return;
        }

        $textarea.val(JSON.stringify(conditions || [], null, 2));
    }

    function operatorOptions(selected) {
        const options = {
            empty: t('condition_empty', 'Is empty'),
            not_empty: t('condition_not_empty', 'Is not empty'),
            exists: t('condition_exists', 'Exists'),
            not_exists: t('condition_not_exists', 'Does not exist'),
            equals: t('condition_equals', 'Equals'),
            not_equals: t('condition_not_equals', 'Does not equal'),
            contains: t('condition_contains', 'Contains'),
            not_contains: t('condition_not_contains', 'Does not contain'),
            greater_than: t('condition_greater_than', 'Greater than'),
            less_than: t('condition_less_than', 'Less than'),
            greater_or_equal: t('condition_greater_or_equal', 'Greater than or equal to'),
            less_or_equal: t('condition_less_or_equal', 'Less than or equal to'),
            in: t('condition_in', 'Is in list'),
            not_in: t('condition_not_in', 'Is not in list')
        };

        let html = '';

        Object.keys(options).forEach(function(value) {
            html += `<option value="${escapeHtml(value)}" ${selected === value ? 'selected' : ''}>${escapeHtml(options[value])}</option>`;
        });

        return html;
    }

    function actionOptions(selected) {
        selected = normalizeAction(selected);

        return `
            <option value="remove" ${selected === 'remove' ? 'selected' : ''}>${escapeHtml(t('remove_path', 'Remove path'))}</option>
            <option value="remove_path" ${selected === 'remove_path' ? 'selected' : ''}>${escapeHtml(t('remove_path', 'Remove path'))}</option>
        `;
    }

    function buildConditionRow(condition = {}, index = 0) {
        condition = normalizeCondition(condition, index);

        const dataKey = escapeHtml(condition.data_key);
        const operator = condition.operator || 'empty';
        const expected = escapeHtml(valueToInput(condition.expected));
        const action = normalizeAction(condition.action);
        const path = escapeHtml(condition.path);

        return `
            <div class="condition-row" style="margin-bottom: 12px; padding: 14px; background: #fff; border: 1px solid #ccd0d4; border-radius: 8px;">
                <div style="display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)) auto; gap: 10px; align-items: end;">

                    <label>
                        <strong>${escapeHtml(t('data_key', 'Data key'))}</strong>
                        <input type="text" name="field[]" value="${dataKey}" placeholder="${escapeHtml(t('example_condition_key', 'For example rating_value or price'))}" style="width: 100%;">
                    </label>

                    <label>
                        <strong>${escapeHtml(t('operator', 'Operator'))}</strong>
                        <select name="operator[]" style="width: 100%;">
                            ${operatorOptions(operator)}
                        </select>
                    </label>

                    <label>
                        <strong>${escapeHtml(t('value', 'Value'))}</strong>
                        <input type="text" name="value[]" value="${expected}" placeholder="${escapeHtml(t('optional', 'Optional'))}" style="width: 100%;">
                    </label>

                    <label>
                        <strong>${escapeHtml(t('action', 'Action'))}</strong>
                        <select name="action[]" style="width: 100%;">
                            ${actionOptions(action)}
                        </select>
                    </label>

                    <label>
                        <strong>${escapeHtml(t('path_to_remove', 'Path to remove'))}</strong>
                        <input type="text" name="path[]" value="${path}" placeholder="${escapeHtml(t('example_condition_path', 'For example aggregateRating or offers.price'))}" style="width: 100%;">
                    </label>

                    <button type="button" class="button remove-condition">${escapeHtml(t('remove', 'Remove'))}</button>
                </div>

                <p class="description" style="margin: 8px 0 0;">
                    ${escapeHtml(t('condition_help', 'When the condition matches, the specified path is removed from the final Schema output.'))}
                </p>
            </div>
        `;
    }

    function initRowsFromTextarea() {
        if ($container.find('.condition-row').length) {
            return;
        }

        const conditions = getConditionsFromTextarea();

        if (!conditions.length) {
            return;
        }

        conditions.forEach(function(condition, index) {
            $container.append(buildConditionRow(condition, index));
        });
    }

    function collectConditions() {
        const conditions = [];
        let invalid = false;

        $container.find('.condition-row').each(function(index) {
            const $row = $(this);

            const dataKey = ($row.find('input[name="field[]"]').val() || '').trim();
            const operator = ($row.find('select[name="operator[]"]').val() || 'empty').trim();
            const expectedRaw = ($row.find('input[name="value[]"]').val() || '').trim();
            const action = normalizeAction($row.find('select[name="action[]"]').val() || 'remove');
            const path = ($row.find('input[name="path[]"]').val() || '').trim();

            if (!dataKey && !operator && !path && !expectedRaw) {
                return;
            }

            if (!dataKey || !operator || !action) {
                invalid = true;
                markInvalid($row);
                return;
            }

            if ((action === 'remove' || action === 'remove_path') && !path) {
                invalid = true;
                markInvalid($row);
                return;
            }

            markValid($row);

            const expected = parseMaybeJson(expectedRaw);

            conditions.push({
                data_key: dataKey,
                field: dataKey,
                operator: operator,
                expected: expected,
                value: expected,
                action: action,
                path: path,
                payload: {},
                priority: index,
                status: 'active'
            });
        });

        if (invalid) {
            return null;
        }

        return conditions;
    }

    function markInvalid($row) {
        $row.css({
            backgroundColor: '#fff1f2',
            borderColor: '#f43f5e'
        });
    }

    function markValid($row) {
        $row.css({
            backgroundColor: '#fff',
            borderColor: '#ccd0d4'
        });
    }

    function saveConditions() {
        const templateId = getTemplateId();
        const conditions = collectConditions();

        if (conditions === null) {
            alert(t('conditions_incomplete', 'Some conditions are incomplete. Complete the data key, operator, action, and path to remove.'));
            return;
        }

        syncTextarea(conditions);

        if (!templateId) {
            alert(t('conditions_textarea_updated', 'Conditions were updated in the textarea. To save via REST, save the template first so template_id is created.'));
            return;
        }

        if (typeof wpApiSettings === 'undefined' || !wpApiSettings.root || !wpApiSettings.nonce) {
            alert(t('conditions_rest_missing', 'wpApiSettings is not available on this page. Conditions were only updated in the textarea.'));
            return;
        }

        $.ajax({
            url: wpApiSettings.root + 'amk-schema/conditions',
            method: 'POST',
            data: JSON.stringify({
                template_id: templateId,
                conditions: conditions
            }),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
            },
            success: function(response) {
                if (response && response.status === 'success') {
                    alert(response.message || t('conditions_saved', 'Conditions saved successfully.'));
                    return;
                }

                alert((response && response.message) ? response.message : t('conditions_save_error', 'Error saving conditions.'));
            },
            error: function(xhr, status, error) {
                console.error('AMK Condition Save Error:', status, error, xhr.responseText);
                alert(t('conditions_save_error_console', 'Error saving conditions. Check the browser console for details.'));
            }
        });
    }

    function fetchConditionsFromRest() {
        const templateId = getTemplateId();

        if (!templateId) {
            return;
        }

        if (typeof wpApiSettings === 'undefined' || !wpApiSettings.root || !wpApiSettings.nonce) {
            return;
        }

        if ($container.find('.condition-row').length) {
            return;
        }

        $.ajax({
            url: wpApiSettings.root + 'amk-schema/conditions/' + templateId,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
            },
            success: function(response) {
                if (!response || response.status !== 'success' || !Array.isArray(response.conditions)) {
                    return;
                }

                if (!response.conditions.length) {
                    return;
                }

                response.conditions.forEach(function(condition, index) {
                    $container.append(buildConditionRow(condition, index));
                });

                syncTextarea(collectConditions() || []);
            },
            error: function(xhr, status, error) {
                console.warn('AMK Condition Fetch Warning:', status, error);
            }
        });
    }

    $('#add-condition').on('click', function(e) {
        e.preventDefault();

        const index = $container.find('.condition-row').length;
        $container.append(buildConditionRow({}, index));

        const conditions = collectConditions();

        if (conditions !== null) {
            syncTextarea(conditions);
        }
    });

    $(document).on('click', '.remove-condition', function(e) {
        e.preventDefault();

        const $rows = $container.find('.condition-row');

        if ($rows.length <= 1) {
            const $row = $(this).closest('.condition-row');
            $row.find('input[type="text"]').val('');
            $row.find('select[name="operator[]"]').val('empty');
            $row.find('select[name="action[]"]').val('remove');
        } else {
            $(this).closest('.condition-row').remove();
        }

        const conditions = collectConditions();

        if (conditions !== null) {
            syncTextarea(conditions);
        }
    });

    $(document).on('change keyup', '.condition-row input, .condition-row select', function() {
        const conditions = collectConditions();

        if (conditions !== null) {
            syncTextarea(conditions);
        }
    });

    $('#save-conditions').on('click', function(e) {
        e.preventDefault();

        saveConditions();
    });

    initRowsFromTextarea();
    fetchConditionsFromRest();

});