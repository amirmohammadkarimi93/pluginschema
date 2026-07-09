(function ($) {
    'use strict';

    function t(key, fallback) {
        return (window.AMKSchemaCoreI18n && AMKSchemaCoreI18n[key]) ? AMKSchemaCoreI18n[key] : fallback;
    }

    function isPersianLocale() {
        const lang = String(document.documentElement.lang || '').toLowerCase().replace('_', '-');

        return lang.indexOf('fa') === 0;
    }

    function hasPersianText(value) {
        return /[\u0600-\u06FF]/.test(String(value || ''));
    }

    function persianOnlyLabel(value) {
        value = String(value || '').trim();

        if (!hasPersianText(value)) {
            return '';
        }

        return value.replace(/\s*\([^)]*\)\s*$/u, '').trim();
    }

    function locationLabel(item) {
        item = item || {};

        if (isPersianLocale()) {
            return persianOnlyLabel(item.label) || item.name || item.label || '';
        }

        return item.name || item.label || '';
    }

    function initCountrySelector() {

        $('.amk-country-select').each(function () {

            const $select = $(this);
            const $wrapper = $select.closest('[data-country-selector]');
            const jsonUrl = $wrapper.data('json-url');


            if ($.fn.select2) {

                $select.select2({
                    width: '100%',
                    dir: isPersianLocale() ? 'rtl' : 'ltr',
                    placeholder: t('country_placeholder', 'Select countries'),
                    allowClear: true,
                    closeOnSelect: false
                });

            }


            loadCountries($select, jsonUrl);
            handleConditionalVisibility($select.closest('.amk-country-selector-field'));


            const requires = $select.closest('.amk-country-selector-field').data('requires-checkbox');

            if (requires) {

                const $checkbox = $('#' + requires);

                $checkbox.on('change', function () {

                    handleConditionalVisibility(
                        $select.closest('.amk-country-selector-field')
                    );

                });

            }


            $select.on('change', function () {

                let values = $(this).val() || [];

                if (values.includes('WORLDWIDE')) {

                    $(this)
                        .val(['WORLDWIDE'])
                        .trigger('change.select2');

                    return;
                }

                values = values.filter(function (value) {
                    return /^[A-Z]{2}$/.test(value);
                });

                $(this)
                    .val(values)
                    .trigger('change.select2');

            });

        });

    }


    function handleConditionalVisibility($field) {

        const checkboxId = $field.data('requires-checkbox');

        if (!checkboxId) {
            return;
        }

        const $checkbox = $('#' + checkboxId);

        if (!$checkbox.length) {
            return;
        }

        if ($checkbox.is(':checked')) {
            $field.show();
        } else {
            $field.hide();
        }

    }


    function loadCountries($select, url) {

        if (!url) {
            return;
        }

        addWorldwideOption($select);

        $.getJSON(url)
            .done(function (response) {

                if (!response || !Array.isArray(response.countries)) {
                    return;
                }

                response.countries.forEach(function (country) {

                    if (!country.code) {
                        return;
                    }

                    if ($select.find('option[value="' + country.code + '"]').length) {
                        return;
                    }

                    $select.append(
                        $('<option>', {
                            value: country.code,
                            text: locationLabel(country)
                        })
                    );

                });

                $select.trigger('change.select2');

            });

    }


    function addWorldwideOption($select) {

        if (!$select.find('option[value="WORLDWIDE"]').length) {

            $select.prepend(
                $('<option>', {
                    value: 'WORLDWIDE',
                    text: t('all_countries', 'All countries')
                })
            );

        }

    }


    $(document).ready(function () {
        initCountrySelector();
    });


})(jQuery);
