<?php

namespace AMK\SchemaCore\Core;

defined('ABSPATH') || exit;

class Activator {

    /**
     * Run on plugin activation.
     *
     * @return void
     */
    public static function activate() {
        self::install_or_upgrade('activation');
    }

    /**
     * Run migrations and seed defaults when needed.
     *
     * This method is intentionally wrapped in try/catch so a bad migration,
     * malformed default schema, or unexpected database state does not white-screen
     * the whole site during activation or file replacement.
     *
     * @param string $reason
     * @return array
     */
    public static function install_or_upgrade($reason = 'manual') {
        $reason = self::sanitize_reason($reason);

        try {
            return self::perform_install_or_upgrade($reason);
        } catch (\Throwable $e) {
            return self::store_upgrade_failure($reason, $e);
        }
    }

    /**
     * Check whether plugin needs an upgrade run.
     *
     * @return bool
     */
    public static function maybe_upgrade() {
        try {
            $current_version = self::plugin_version();
            $stored_version  = (string) get_option('amk_schema_core_version', '');

            if ($stored_version !== $current_version) {
                self::install_or_upgrade('version_change');
                return true;
            }

            if (self::defaults_hash_changed()) {
                self::install_or_upgrade('defaults_changed');
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            self::store_upgrade_failure('maybe_upgrade', $e);
            return false;
        }
    }

    /**
     * Real installer/upgrader logic.
     *
     * @param string $reason
     * @return array
     */
    private static function perform_install_or_upgrade($reason) {
        $result = [
            'reason'           => self::sanitize_reason($reason),
            'migrations'       => false,
            'seed'             => null,
            'platform_prefill' => null,
            'errors'           => [],
        ];

        self::prevent_seed_auto_run();

        $migration_result = self::run_migrations();

        if ($migration_result === true) {
            $result['migrations'] = true;
        } else {
            $result['errors'][] = $migration_result;
        }

        $seed_result = self::run_seed_defaults();
        $result['seed'] = $seed_result;

        if (is_array($seed_result) && !empty($seed_result['errors'])) {
            foreach ($seed_result['errors'] as $error) {
                $result['errors'][] = $error;
            }
        }

        self::migrate_commerce_country_settings();

        $prefill_result = self::prefill_platform_settings($result['reason']);
        $result['platform_prefill'] = $prefill_result;

        if (is_array($prefill_result) && !empty($prefill_result['errors'])) {
            foreach ($prefill_result['errors'] as $error) {
                $result['errors'][] = $error;
            }
        }

        update_option('amk_schema_core_version', self::plugin_version(), false);
        update_option('amk_schema_core_installed_at', self::installed_at(), false);
        update_option('amk_schema_core_last_upgrade_at', current_time('mysql'), false);
        update_option('amk_schema_core_last_upgrade_reason', $result['reason'], false);
        update_option('amk_schema_core_last_upgrade_result', $result, false);

        self::update_defaults_hash();

        return $result;
    }

    /**
     * Run database migrations.
     *
     * @return true|string
     */
    private static function run_migrations() {
        if (!defined('AMK_SCHEMA_CORE_PATH')) {
            return __('AMK_SCHEMA_CORE_PATH is not defined.', 'amk-schema-core');
        }

        $migration_file = AMK_SCHEMA_CORE_PATH . 'database/migrations.php';

        if (!file_exists($migration_file)) {
            return __('The database/migrations.php file was not found.', 'amk-schema-core');
        }

        require_once $migration_file;

        update_option('amk_schema_core_db_migrated_at', current_time('mysql'), false);

        return true;
    }

    /**
     * Run default schema seeder.
     *
     * @return array
     */
    private static function run_seed_defaults() {
        if (!defined('AMK_SCHEMA_CORE_PATH')) {
            return [
                'inserted' => 0,
                'updated'  => 0,
                'skipped'  => 0,
                'errors'   => [__('AMK_SCHEMA_CORE_PATH is not defined.', 'amk-schema-core')],
            ];
        }

        $seed_file = AMK_SCHEMA_CORE_PATH . 'database/seed-defaults.php';

        if (!file_exists($seed_file)) {
            return [
                'inserted' => 0,
                'updated'  => 0,
                'skipped'  => 0,
                'errors'   => [__('The database/seed-defaults.php file was not found.', 'amk-schema-core')],
            ];
        }

        self::prevent_seed_auto_run();

        require_once $seed_file;

        if (!function_exists('amk_schema_core_seed_defaults')) {
            return [
                'inserted' => 0,
                'updated'  => 0,
                'skipped'  => 0,
                'errors'   => [__('The amk_schema_core_seed_defaults function is not available.', 'amk-schema-core')],
            ];
        }

        $seed_result = amk_schema_core_seed_defaults();

        if (!is_array($seed_result)) {
            $seed_result = [
                'inserted' => 0,
                'updated'  => 0,
                'skipped'  => 0,
                'errors'   => [__('The seeder output is invalid.', 'amk-schema-core')],
            ];
        }

        return $seed_result;
    }


    /**
     * Migrate legacy commerce country settings to the newer mode + list structure.
     *
     * Supports old single-country values, legacy comma/pipe-separated values,
     * and previously-saved arrays while avoiding destructive guesses for empty data.
     *
     * @return void
     */
    private static function migrate_commerce_country_settings() {

        $option_key = GlobalSettings::OPTION_KEY;
        $settings = get_option($option_key, []);

        if (!is_array($settings) || empty($settings['commerce']) || !is_array($settings['commerce'])) {
            return;
        }

        $changed = false;
        $commerce = $settings['commerce'];

        foreach ([
            'shipping_country'      => 'shipping',
            'return_policy_country' => 'return_policy',
        ] as $legacy_field => $prefix) {

            $mode_key      = $prefix . '_mode';
            $countries_key = $prefix . '_countries';

            $current_mode      = self::normalize_commerce_country_mode($commerce[$mode_key] ?? '');
            $current_countries = self::normalize_commerce_country_values($commerce[$countries_key] ?? []);
            $legacy_value      = $commerce[$legacy_field] ?? null;
            $legacy_countries  = self::normalize_commerce_country_values($legacy_value);
            $legacy_worldwide  = self::is_worldwide_country_value($legacy_value);

            if ($current_mode === 'worldwide') {
                if (($commerce[$countries_key] ?? null) !== ['WORLDWIDE']) {
                    $commerce[$countries_key] = ['WORLDWIDE'];
                    $changed = true;
                }

                continue;
            }

            if ($current_mode === 'specific_countries' && !empty($current_countries)) {
                if (($commerce[$countries_key] ?? null) !== $current_countries) {
                    $commerce[$countries_key] = $current_countries;
                    $changed = true;
                }

                continue;
            }

            if ($legacy_worldwide) {
                $commerce[$mode_key]      = 'worldwide';
                $commerce[$countries_key] = ['WORLDWIDE'];
                $changed = true;
                continue;
            }

            if (!empty($legacy_countries)) {
                $commerce[$mode_key]      = 'specific_countries';
                $commerce[$countries_key] = $legacy_countries;
                $changed = true;
                continue;
            }

            if (!empty($current_countries)) {
                $commerce[$mode_key]      = 'specific_countries';
                $commerce[$countries_key] = $current_countries;
                $changed = true;
            }
        }

        if ($changed) {
            $settings['commerce'] = $commerce;
            update_option($option_key, $settings, false);
        }
    }

    /**
     * Normalize the stored commerce country mode.
     *
     * @param mixed $mode
     * @return string
     */
    private static function normalize_commerce_country_mode($mode) {
        $mode = is_string($mode) ? strtolower(trim($mode)) : '';

        return in_array($mode, ['worldwide', 'specific_countries'], true) ? $mode : '';
    }

    /**
     * Normalize legacy or current country values to a clean list.
     *
     * @param mixed $value
     * @return array
     */
    private static function normalize_commerce_country_values($value) {
        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value) || is_numeric($value)) {
            $items = preg_split('/[|,]/', (string) $value);
        } else {
            $items = [];
        }

        $normalized = [];

        foreach ((array) $items as $item) {
            $item = strtoupper(trim((string) $item));

            if ($item === '') {
                continue;
            }

            if ($item === 'WORLDWIDE' || $item === 'WORLD WIDE' || $item === 'ALL COUNTRIES') {
                return ['WORLDWIDE'];
            }

            if (!preg_match('/^[A-Z]{2}$/', $item)) {
                continue;
            }

            $normalized[] = $item;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Determine whether the given value explicitly means worldwide coverage.
     *
     * @param mixed $value
     * @return bool
     */
    private static function is_worldwide_country_value($value) {
        $countries = self::normalize_commerce_country_values($value);

        return count($countries) === 1 && $countries[0] === 'WORLDWIDE';
    }

    /**
     * Import empty plugin settings from WordPress/WooCommerce defaults.
     *
     * This must run after migrations/default seed so the plugin has a stable
     * settings option to update. It never overwrites user-entered plugin values.
     *
     * @param string $reason
     * @return array
     */
    private static function prefill_platform_settings($reason) {
        try {
            if (!class_exists(GlobalSettings::class)) {
                return [
                    'updated' => false,
                    'errors'  => [__('The GlobalSettings class is not available.', 'amk-schema-core')],
                ];
            }

            return GlobalSettings::prefill_from_platform_defaults($reason);
        } catch (\Throwable $e) {
            return [
                'updated' => false,
                'errors'  => [$e->getMessage()],
                'exception' => [
                    'class' => get_class($e),
                    'file'  => $e->getFile(),
                    'line'  => $e->getLine(),
                ],
            ];
        }
    }

    /**
     * Prevent seed-defaults.php from running automatically when included by migrations.
     *
     * @return void
     */
    private static function prevent_seed_auto_run() {
        if (!defined('AMK_SCHEMA_CORE_SKIP_AUTO_SEED')) {
            define('AMK_SCHEMA_CORE_SKIP_AUTO_SEED', true);
        }
    }

    /**
     * Check if default-schemas.php hash changed.
     *
     * @return bool
     */
    private static function defaults_hash_changed() {
        $current_hash = self::defaults_hash();

        if ($current_hash === '') {
            return false;
        }

        $stored_hash = (string) get_option('amk_schema_core_defaults_hash', '');

        return $stored_hash !== $current_hash;
    }

    /**
     * Store default-schemas.php hash.
     *
     * @return void
     */
    private static function update_defaults_hash() {
        $hash = self::defaults_hash();

        if ($hash === '') {
            return;
        }

        update_option('amk_schema_core_defaults_hash', $hash, false);
    }

    /**
     * Get hash of config/default-schemas.php.
     *
     * @return string
     */
    private static function defaults_hash() {
        if (!defined('AMK_SCHEMA_CORE_PATH')) {
            return '';
        }

        $file = AMK_SCHEMA_CORE_PATH . 'config/default-schemas.php';

        if (!file_exists($file) || !is_readable($file)) {
            return '';
        }

        $hash = hash_file('sha256', $file);

        return is_string($hash) ? $hash : '';
    }

    /**
     * Get plugin version safely.
     *
     * @return string
     */
    private static function plugin_version() {
        return defined('AMK_SCHEMA_CORE_VERSION') ? (string) AMK_SCHEMA_CORE_VERSION : '1.0.0';
    }

    /**
     * Preserve original installation date.
     *
     * @return string
     */
    private static function installed_at() {
        $installed_at = (string) get_option('amk_schema_core_installed_at', '');

        if ($installed_at !== '') {
            return $installed_at;
        }

        return current_time('mysql');
    }

    /**
     * Store fatal upgrade/activation failure without killing the whole site.
     *
     * @param string     $reason
     * @param \Throwable $e
     * @return array
     */
    private static function store_upgrade_failure($reason, \Throwable $e) {
        $result = [
            'reason'     => self::sanitize_reason($reason),
            'migrations' => false,
            'seed'       => null,
            'errors'     => [
                $e->getMessage(),
            ],
            'exception'  => [
                'class' => get_class($e),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ],
        ];

        if (function_exists('update_option')) {
            update_option('amk_schema_core_last_upgrade_at', current_time('mysql'), false);
            update_option('amk_schema_core_last_upgrade_reason', $result['reason'], false);
            update_option('amk_schema_core_last_upgrade_result', $result, false);
            update_option('amk_schema_core_last_upgrade_error', $result, false);
        }

        if (function_exists('error_log')) {
            error_log(
                '[AMK Schema Core] Upgrade failure: ' .
                $result['exception']['class'] . ' - ' .
                $e->getMessage() . ' in ' .
                $e->getFile() . ':' .
                $e->getLine()
            );
        }

        return $result;
    }

    /**
     * Sanitize upgrade reason safely.
     *
     * @param mixed $reason
     * @return string
     */
    private static function sanitize_reason($reason) {
        $reason = is_string($reason) || is_numeric($reason) ? (string) $reason : 'manual';

        if (function_exists('sanitize_key')) {
            $reason = sanitize_key($reason);
        } else {
            $reason = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', $reason));
        }

        return $reason !== '' ? $reason : 'manual';
    }
}