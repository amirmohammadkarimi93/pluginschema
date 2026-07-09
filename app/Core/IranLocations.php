<?php

namespace AMK\SchemaCore\Core;

defined('ABSPATH') || exit;

class IranLocations {

    const DATA_FILE = 'resources/data/world-locations.json';

    private static $data = null;

    public static function country_options() {

        $options = [];

        foreach (self::countries() as $country) {
            if (empty($country['code'])) {
                continue;
            }

            $options[$country['code']] = self::location_label($country, $country['code']);
        }

        if (empty($options)) {
            $options = self::fallback_country_options();
        }

        return $options;
    }

    public static function provinces($country = 'IR') {

        $country = self::normalize_country($country);
        $states = self::data()['statesByCountry'][$country] ?? [];
        $options = [];

        foreach ($states as $state) {
            if (empty($state['name'])) {
                continue;
            }

            $options[$state['name']] = self::location_label($state, $state['name']);
        }

        return $options;
    }

    public static function cities() {

        return self::data()['citiesByCountryState'] ?? [];
    }

    public static function city_options($country_or_province, $province = null) {

        if ($province === null) {
            $country = 'IR';
            $province = $country_or_province;
        } else {
            $country = self::normalize_country($country_or_province);
        }

        $province = is_string($province) ? trim($province) : '';

        if ($province === '') {
            return [];
        }

        $key = $country . '|' . $province;
        $cities = self::data()['citiesByCountryState'][$key] ?? [];
        $options = [];

        foreach ($cities as $city) {
            if (empty($city['name'])) {
                continue;
            }

            $options[$city['name']] = self::location_label($city, $city['name']);
        }

        return $options;
    }

    public static function countries() {

        return self::data()['countries'] ?? [];
    }

    public static function is_persian_locale() {

        $locale = '';

        if (function_exists('determine_locale')) {
            $locale = (string) determine_locale();
        } elseif (function_exists('get_locale')) {
            $locale = (string) get_locale();
        }

        $locale = strtolower(str_replace('_', '-', $locale));

        return strpos($locale, 'fa') === 0;
    }

    private static function location_label($location, $fallback = '') {

        $location = is_array($location) ? $location : [];
        $fallback = is_string($fallback) ? $fallback : '';

        if (self::is_persian_locale()) {
            $label = self::persian_only_label($location['label'] ?? '');

            if ($label !== '') {
                return $label;
            }
        }

        $label = $location['name'] ?? ($location['label'] ?? $fallback);

        return is_string($label) && $label !== '' ? $label : $fallback;
    }

    private static function persian_only_label($label) {

        $label = is_string($label) ? trim($label) : '';

        if ($label === '' || !preg_match('/[\x{0600}-\x{06FF}]/u', $label)) {
            return '';
        }

        $label = preg_replace('/\s*\([^)]*\)\s*$/u', '', $label);

        return is_string($label) ? trim($label) : '';
    }

    public static function locations_data_url() {

        if (defined('AMK_SCHEMA_CORE_URL')) {
            return AMK_SCHEMA_CORE_URL . self::DATA_FILE;
        }

        return '';
    }

    private static function data() {

        if (self::$data !== null) {
            return self::$data;
        }

        $path = defined('AMK_SCHEMA_CORE_PATH')
            ? AMK_SCHEMA_CORE_PATH . self::DATA_FILE
            : '';

        if ($path === '' || !file_exists($path)) {
            self::$data = [
                'countries' => [
                    [
                        'code'  => 'IR',
                        'name'  => 'Iran',
                        'label' => __('Iran', 'amk-schema-core'),
                    ],
                ],
                'statesByCountry' => [
                    'IR' => [],
                ],
                'citiesByCountryState' => [],
            ];

            return self::$data;
        }

        $raw = file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        self::$data = is_array($decoded) ? $decoded : [
            'countries' => [],
            'statesByCountry' => [],
            'citiesByCountryState' => [],
        ];

        if (empty(self::$data['countries'])) {
            self::$data['countries'] = self::fallback_countries();
        }

        return self::$data;
    }

    private static function normalize_country($country) {

        $country = is_string($country) ? strtoupper(trim($country)) : 'IR';

        return $country !== '' ? $country : 'IR';
    }

    private static function fallback_country_options() {

        $options = [];

        foreach (self::fallback_countries() as $country) {
            $options[$country['code']] = $country['label'];
        }

        return $options;
    }

    private static function fallback_countries() {

        return [
            ['code' => 'IR', 'name' => 'Iran', 'label' => 'Iran (IR)'],
            ['code' => 'US', 'name' => 'United States', 'label' => 'United States (US)'],
            ['code' => 'CA', 'name' => 'Canada', 'label' => 'Canada (CA)'],
            ['code' => 'GB', 'name' => 'United Kingdom', 'label' => 'United Kingdom (GB)'],
            ['code' => 'DE', 'name' => 'Germany', 'label' => 'Germany (DE)'],
            ['code' => 'FR', 'name' => 'France', 'label' => 'France (FR)'],
            ['code' => 'IT', 'name' => 'Italy', 'label' => 'Italy (IT)'],
            ['code' => 'ES', 'name' => 'Spain', 'label' => 'Spain (ES)'],
            ['code' => 'TR', 'name' => 'Turkey', 'label' => 'Turkey (TR)'],
            ['code' => 'AE', 'name' => 'United Arab Emirates', 'label' => 'United Arab Emirates (AE)'],
            ['code' => 'SA', 'name' => 'Saudi Arabia', 'label' => 'Saudi Arabia (SA)'],
            ['code' => 'CN', 'name' => 'China', 'label' => 'China (CN)'],
            ['code' => 'IN', 'name' => 'India', 'label' => 'India (IN)'],
            ['code' => 'JP', 'name' => 'Japan', 'label' => 'Japan (JP)'],
            ['code' => 'AU', 'name' => 'Australia', 'label' => 'Australia (AU)'],
        ];
    }
}
