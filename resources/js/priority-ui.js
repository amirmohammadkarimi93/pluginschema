jQuery(document).ready(function($) {

    function t(key, fallback) {
        return (window.AMKSchemaCoreI18n && AMKSchemaCoreI18n[key]) ? AMKSchemaCoreI18n[key] : fallback;
    }

    const $table = $('.amk-schema-table, .amk-schema-wrap table.widefat').first();

    if (!$table.length) {
        return;
    }

    function getTemplateIdFromRow($row) {
        const dataId = parseInt($row.attr('data-template-id'), 10);

        if (dataId) {
            return dataId;
        }

        const idText = $.trim($row.find('td').eq(0).text());

        return parseInt(idText, 10) || 0;
    }

    function getPriorityFromRow($row) {
        const dataPriority = parseInt($row.attr('data-priority'), 10);

        if (!isNaN(dataPriority)) {
            return dataPriority;
        }

        const $priorityCell = $row.find('.amk-col-priority').first();

        if ($priorityCell.length) {
            return parseInt($.trim($priorityCell.text()), 10) || 0;
        }

        return 0;
    }

    function getOverrideFromRow($row) {
        const dataOverride = $row.attr('data-override');

        if (dataOverride === '1') {
            return true;
        }

        if (dataOverride === '0') {
            return false;
        }

        const $overrideCell = $row.find('.amk-col-override').first();

        if ($overrideCell.length) {
            return normalizeBooleanText($overrideCell.text());
        }

        return false;
    }

    function normalizeBooleanText(value) {
        value = $.trim(String(value || ''));

        return value === t('yes', 'Yes') ||
            value === t('active', 'Active') ||
            value === 'yes' ||
            value === 'true' ||
            value === '1';
    }

    function enhancePriorityTable() {

        if ($table.data('amk-priority-enhanced')) {
            return;
        }

        $table.data('amk-priority-enhanced', true);

        const $rows = $table.find('tbody tr');

        $rows.each(function() {
            const $row = $(this);
            const templateId = getTemplateIdFromRow($row);

            if (!templateId) {
                return;
            }

            const priority = getPriorityFromRow($row);
            const override = getOverrideFromRow($row);

            $row.attr('data-template-id', templateId);
            $row.attr('data-priority', priority);
            $row.attr('data-override', override ? '1' : '0');

            const $priorityCell = $row.find('.amk-col-priority').first();
            const $overrideCell = $row.find('.amk-col-override').first();

            if ($priorityCell.length) {
                $priorityCell.html(
                    '<input type="number" class="small-text amk-priority-input" name="priority[]" value="' + escapeHtml(priority) + '" step="1">'
                );
            }

            if ($overrideCell.length) {
                $overrideCell.html(
                    '<label class="amk-priority-toggle">' +
                        '<input type="checkbox" class="amk-override-input" name="override[]" value="1" ' + (override ? 'checked' : '') + '> ' +
                        '<span></span>' +
                        '<strong>' + escapeHtml(t('override', 'Override')) + '</strong>' +
                    '</label>'
                );
            }
        });
    }

    function collectPriorityData() {
        const items = [];

        $table.find('tbody tr').each(function() {
            const $row = $(this);
            const templateId = getTemplateIdFromRow($row);

            if (!templateId) {
                return;
            }

            const priority = parseInt($row.find('.amk-priority-input').val(), 10) || 0;
            const override = $row.find('.amk-override-input').is(':checked') ? 1 : 0;

            items.push({
                id: templateId,
                priority: priority,
                override: override
            });
        });

        return items;
    }

    function setLoading(isLoading) {
        const $button = $('#save-priority');

        if (!$button.length) {
            return;
        }

        if (isLoading) {
            $button.prop('disabled', true).text(t('saving', 'Saving...'));
        } else {
            $button.prop('disabled', false).text(t('save_priorities', 'Save priorities'));
        }
    }

    function showNotice(type, message) {
        $('.amk-priority-notice').remove();

        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';

        const html =
            '<div class="notice ' + noticeClass + ' is-dismissible amk-priority-notice">' +
                '<p>' + escapeHtml(message) + '</p>' +
            '</div>';

        const $target = $('.amk-schema-list-card').first();

        if ($target.length) {
            $target.before(html);
        } else {
            $('.wrap').first().prepend(html);
        }
    }

    function escapeHtml(value) {
        return String(value || '')
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

    function savePriorities() {
        const items = collectPriorityData();

        if (!items.length) {
            showNotice('error', t('no_priorities', 'No templates were found for saving priorities.'));
            return;
        }

        if (!hasRestSettings()) {
            showNotice('error', t('rest_settings_missing', 'REST settings are not loaded on this page. Check Assets.php.'));
            return;
        }

        setLoading(true);

        $.ajax({
            url: wpApiSettings.root + 'amk-schema/priority',
            method: 'POST',
            data: JSON.stringify({
                items: items
            }),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
            },
            success: function(response) {
                if (response && response.status === 'success') {
                    showNotice('success', response.message || t('priorities_saved', 'Priorities saved successfully.'));

                    items.forEach(function(item) {
                        const $row = $table.find('tr[data-template-id="' + item.id + '"]');

                        $row.attr('data-priority', item.priority);
                        $row.attr('data-override', item.override ? '1' : '0');
                    });

                    return;
                }

                showNotice('error', (response && response.message) ? response.message : t('priorities_save_error', 'Error saving priorities.'));
            },
            error: function(xhr, status, error) {
                console.error('AMK Priority Save Error:', status, error, xhr.responseText);
                showNotice('error', t('priorities_save_error_console', 'Error saving priorities. Check the browser console for details.'));
            },
            complete: function() {
                setLoading(false);
            }
        });
    }

    $(document).on('click', '#save-priority', function(e) {
        e.preventDefault();
        savePriorities();
    });

    enhancePriorityTable();

});