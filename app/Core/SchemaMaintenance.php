<?php

namespace AMK\SchemaCore\Core;

defined('ABSPATH') || exit;

/**
 * Maintenance utilities for default schema templates.
 *
 * This class intentionally does not reset all plugin data. It only inspects and
 * repairs plugin default templates against config/default-schemas.php.
 */
class SchemaMaintenance {

    /**
     * Inspect database templates against config/default-schemas.php.
     *
     * @return array
     */
    public static function inspect() {
        global $wpdb;

        $result = self::empty_result('inspect');

        $defaults = self::load_defaults($result);
        if (empty($defaults)) {
            self::store_last_report($result);
            return $result;
        }

        $table_templates  = $wpdb->prefix . 'amk_schema_templates';
        $table_conditions = $wpdb->prefix . 'amk_schema_conditions';

        if (!self::table_exists($table_templates)) {
            $result['errors'][] = __('The schema templates table does not exist. Run the plugin migration first.', 'amk-schema-core');
            self::store_last_report($result);
            return $result;
        }

        $result['tables']['templates']  = $table_templates;
        $result['tables']['conditions'] = $table_conditions;
        $result['counts']['defaults']   = count($defaults);

        foreach ($defaults as $template) {
            $template = is_array($template) ? $template : [];
            $name     = sanitize_text_field($template['name'] ?? '');
            $type     = self::normalize_type($template['type'] ?? 'custom');
            $scope    = self::normalize_scope($template['scope'] ?? 'global');

            if ($name === '') {
                continue;
            }

            $row = self::find_default_template_row($table_templates, $template);

            if (empty($row)) {
                $result['counts']['missing']++;
                $result['items'][] = [
                    'name'   => $name,
                    'type'   => $type,
                    'scope'  => $scope,
                    'status' => 'missing',
                    'notes'  => [__('Does not exist in the database.', 'amk-schema-core')],
                ];
                continue;
            }

            $result['counts']['existing']++;

            $notes = [];
            $status = 'ok';

            if (!empty($row['_matched_legacy'])) {
                $result['counts']['legacy_matched']++;
                $status = 'needs_update';
                $notes[] = __('Found with the old name and should be moved to the new name.', 'amk-schema-core');
            }

            $comparison = self::compare_template_with_default($row, $template);

            if (!empty($comparison)) {
                $status = 'needs_update';
                $result['counts']['outdated']++;
                $notes = array_merge($notes, $comparison);
            }

            $serialized_row = implode(' ', [
                (string) ($row['schema_json'] ?? ''),
                (string) ($row['bindings'] ?? ''),
                (string) ($row['conditions'] ?? ''),
            ]);

            if (strpos($serialized_row, '{{organization_type}}') !== false) {
                $status = 'needs_update';
                $result['counts']['old_placeholders']++;
                $notes[] = __('The old {{organization_type}} placeholder still exists in the database.', 'amk-schema-core');
            }

            $schema_json = self::decode_json($row['schema_json'] ?? '');

            if (self::array_has_key_recursive($schema_json, 'paymentAccepted')) {
                $status = 'needs_update';
                $result['counts']['deprecated_properties']++;
                $notes[] = __('The old paymentAccepted property exists in the database schema_json.', 'amk-schema-core');
            }

            if (self::array_has_key_recursive($schema_json, 'openingHours')) {
                $status = 'needs_update';
                $result['counts']['deprecated_properties']++;
                $notes[] = __('The raw openingHours property exists in the database schema_json.', 'amk-schema-core');
            }

            if (empty($notes)) {
                $notes[] = __('Matches the current default file.', 'amk-schema-core');
            }

            $result['items'][] = [
                'id'     => absint($row['id'] ?? 0),
                'name'   => $name,
                'type'   => $type,
                'scope'  => $scope,
                'status' => $status,
                'notes'  => array_values(array_unique($notes)),
            ];
        }

        $result['ok'] = empty($result['errors']);
        self::store_last_report($result);

        return $result;
    }

    /**
     * Backup current default templates, then run controlled install/seed flow.
     *
     * @return array
     */
    public static function sync_defaults() {
        $result = self::empty_result('sync');

        if (!current_user_can('manage_options')) {
            $result['errors'][] = __('You do not have permission to run maintenance actions.', 'amk-schema-core');
            self::store_last_report($result);
            return $result;
        }

        $before = self::inspect();
        $backup = self::backup_current_default_templates();

        $result['before'] = $before;
        $result['backup'] = $backup;

        if (!empty($backup['errors'])) {
            foreach ($backup['errors'] as $error) {
                $result['errors'][] = $error;
            }
            self::store_last_report($result);
            return $result;
        }

        if (class_exists(__NAMESPACE__ . '\\Activator')) {
            $upgrade_result = Activator::install_or_upgrade('manual_schema_maintenance');
            $result['seed'] = $upgrade_result;

            if (is_array($upgrade_result) && !empty($upgrade_result['errors'])) {
                foreach ($upgrade_result['errors'] as $error) {
                    $result['errors'][] = $error;
                }
            }
        } else {
            $result['errors'][] = __('The Activator class is not available and synchronization was not run.', 'amk-schema-core');
        }

        self::clear_possible_schema_cache();

        $after = self::inspect();
        $result['after']  = $after;
        $result['counts'] = $after['counts'] ?? $result['counts'];
        $result['items']  = $after['items'] ?? [];
        $result['ok']     = empty($result['errors']) && empty($after['errors']);

        update_option('amk_schema_core_last_manual_sync_at', current_time('mysql'), false);
        update_option('amk_schema_core_last_manual_sync_result', $result, false);

        self::store_last_report($result);

        return $result;
    }

    /**
     * Get the last maintenance report stored in wp_options.
     *
     * @return array
     */
    public static function last_report() {
        $report = get_option('amk_schema_core_maintenance_last_report', []);
        return is_array($report) ? $report : [];
    }

    /**
     * Build a concise admin notice message from a report.
     *
     * @param array $report
     * @return array
     */
    public static function notice_from_report($report) {
        $report = is_array($report) ? $report : [];
        $action = sanitize_key($report['action'] ?? 'inspect');
        $counts = isset($report['counts']) && is_array($report['counts']) ? $report['counts'] : [];
        $errors = isset($report['errors']) && is_array($report['errors']) ? $report['errors'] : [];

        $messages = [];

        if ($action === 'sync') {
            $messages[] = __('Default schema template synchronization was run.', 'amk-schema-core');
        } else {
            $messages[] = __('Default schema template diff check was run.', 'amk-schema-core');
        }

        $messages[] = sprintf(
            __('Defaults: %d | Existing: %d | Missing: %d | Needs update: %d | Old placeholder: %d | Old property: %d', 'amk-schema-core'),
            absint($counts['defaults'] ?? 0),
            absint($counts['existing'] ?? 0),
            absint($counts['missing'] ?? 0),
            absint($counts['outdated'] ?? 0),
            absint($counts['old_placeholders'] ?? 0),
            absint($counts['deprecated_properties'] ?? 0)
        );

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $messages[] = __('Error: ', 'amk-schema-core') . wp_strip_all_tags((string) $error);
            }
        }

        return [
            'type'     => empty($errors) ? 'success' : 'error',
            'messages' => $messages,
        ];
    }

    /**
     * Create an empty normalized result.
     *
     * @param string $action
     * @return array
     */
    private static function empty_result($action) {
        return [
            'ok'       => false,
            'action'   => sanitize_key($action),
            'time'     => current_time('mysql'),
            'counts'   => [
                'defaults'              => 0,
                'existing'              => 0,
                'missing'               => 0,
                'outdated'              => 0,
                'legacy_matched'        => 0,
                'old_placeholders'      => 0,
                'deprecated_properties' => 0,
            ],
            'tables'   => [],
            'items'    => [],
            'errors'   => [],
        ];
    }

    /**
     * Load default schema templates.
     *
     * @param array $result
     * @return array
     */
    private static function load_defaults(&$result) {
        if (!defined('AMK_SCHEMA_CORE_PATH')) {
            $result['errors'][] = __('AMK_SCHEMA_CORE_PATH is not defined.', 'amk-schema-core');
            return [];
        }

        $file = AMK_SCHEMA_CORE_PATH . 'config/default-schemas.php';

        if (!file_exists($file) || !is_readable($file)) {
            $result['errors'][] = __('The config/default-schemas.php file was not found or is not readable.', 'amk-schema-core');
            return [];
        }

        $defaults = require $file;

        if (empty($defaults) || !is_array($defaults)) {
            $result['errors'][] = __('The default-schemas.php file does not return valid output.', 'amk-schema-core');
            return [];
        }

        return $defaults;
    }

    /**
     * Backup current DB rows that match default templates.
     *
     * @return array
     */
    private static function backup_current_default_templates() {
        global $wpdb;

        $result = [
            'ok'       => false,
            'time'     => current_time('mysql'),
            'rows'     => 0,
            'option'   => 'amk_schema_core_last_default_template_backup',
            'errors'   => [],
        ];

        $load_result = self::empty_result('backup');
        $defaults = self::load_defaults($load_result);

        if (empty($defaults)) {
            $result['errors'] = $load_result['errors'];
            return $result;
        }

        $table_templates = $wpdb->prefix . 'amk_schema_templates';

        if (!self::table_exists($table_templates)) {
            $result['errors'][] = __('The schema templates table does not exist for backup.', 'amk-schema-core');
            return $result;
        }

        $rows = [];

        foreach ($defaults as $template) {
            $row = self::find_default_template_row($table_templates, $template);
            if (!empty($row)) {
                unset($row['_matched_legacy']);
                $rows[] = $row;
            }
        }

        $payload = [
            'created_at' => current_time('mysql'),
            'version'    => defined('AMK_SCHEMA_CORE_VERSION') ? AMK_SCHEMA_CORE_VERSION : '',
            'rows'       => $rows,
        ];

        update_option($result['option'], $payload, false);

        $result['ok']   = true;
        $result['rows'] = count($rows);

        return $result;
    }

    /**
     * Find default template row by current or legacy name.
     *
     * @param string $table
     * @param array  $template
     * @return array|null
     */
    private static function find_default_template_row($table, $template) {
        global $wpdb;

        $template = is_array($template) ? $template : [];
        $name     = sanitize_text_field($template['name'] ?? '');
        $type     = self::normalize_type($template['type'] ?? 'custom');
        $scope    = self::normalize_scope($template['scope'] ?? 'global');

        if ($name === '') {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE name = %s AND type = %s AND scope = %s LIMIT 1",
                $name,
                $type,
                $scope
            ),
            ARRAY_A
        );

        if (!empty($row)) {
            $row['_matched_legacy'] = false;
            return $row;
        }

        $legacy_names = isset($template['legacy_names']) && is_array($template['legacy_names'])
            ? array_filter(array_map('sanitize_text_field', $template['legacy_names']))
            : [];

        if ($name === 'Organization / Store Schema') {
            $legacy_names[] = 'Organization / OnlineStore Schema';
        }

        $legacy_names = array_values(array_unique(array_filter($legacy_names)));

        foreach ($legacy_names as $legacy_name) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE name = %s AND type = %s AND scope = %s LIMIT 1",
                    $legacy_name,
                    $type,
                    $scope
                ),
                ARRAY_A
            );

            if (!empty($row)) {
                $row['_matched_legacy'] = true;
                return $row;
            }
        }

        return null;
    }

    /**
     * Compare a DB template row with a default template definition.
     *
     * @param array $row
     * @param array $template
     * @return array
     */
    private static function compare_template_with_default($row, $template) {
        $notes = [];

        $default_schema     = $template['schema_json'] ?? [];
        $default_bindings   = $template['bindings'] ?? [];
        $default_conditions = $template['conditions'] ?? [];

        $row_schema     = self::decode_json($row['schema_json'] ?? '');
        $row_bindings   = self::decode_json($row['bindings'] ?? '');
        $row_conditions = self::decode_json($row['conditions'] ?? '');

        if (self::canonical_json($row_schema) !== self::canonical_json($default_schema)) {
            $notes[] = __('schema_json does not match config/default-schemas.php.', 'amk-schema-core');
        }

        if (self::canonical_json($row_bindings) !== self::canonical_json($default_bindings)) {
            $notes[] = __('bindings do not match the current default version.', 'amk-schema-core');
        }

        if (self::canonical_json($row_conditions) !== self::canonical_json($default_conditions)) {
            $notes[] = __('Saved conditions do not match the current default version.', 'amk-schema-core');
        }

        $default_status   = sanitize_key($template['status'] ?? 'inactive');
        $default_priority = isset($template['priority']) ? intval($template['priority']) : 0;
        $default_override = !empty($template['override']) ? 1 : 0;

        if (sanitize_key($row['status'] ?? '') !== $default_status) {
            $notes[] = __('status does not match the current default version.', 'amk-schema-core');
        }

        if (intval($row['priority'] ?? 0) !== $default_priority) {
            $notes[] = __('priority does not match the current default version.', 'amk-schema-core');
        }

        if (intval($row['override'] ?? 0) !== $default_override) {
            $notes[] = __('override does not match the current default version.', 'amk-schema-core');
        }

        return $notes;
    }

    /**
     * Delete likely plugin cache entries and expose a hook for future cache layers.
     *
     * @return void
     */
    private static function clear_possible_schema_cache() {
        delete_transient('amk_schema_core_schema_output');
        delete_transient('amk_schema_core_frontend_output');
        delete_transient('amk_schema_core_compiled_graph');

        do_action('amk_schema_core_maintenance_cache_cleared');
    }

    private static function table_exists($table) {
        global $wpdb;

        if (!is_string($table) || $table === '') {
            return false;
        }

        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return $found === $table;
    }

    private static function decode_json($value) {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : [];
    }

    private static function canonical_json($value) {
        $value = self::sort_recursive($value);
        return wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function sort_recursive($value) {
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::sort_recursive($item);
        }

        if (self::is_assoc($value)) {
            ksort($value);
        }

        return $value;
    }

    private static function is_assoc($array) {
        if (!is_array($array)) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    private static function array_has_key_recursive($array, $wanted_key) {
        if (!is_array($array)) {
            return false;
        }

        foreach ($array as $key => $value) {
            if ((string) $key === (string) $wanted_key) {
                return true;
            }

            if (is_array($value) && self::array_has_key_recursive($value, $wanted_key)) {
                return true;
            }
        }

        return false;
    }

    private static function store_last_report($result) {
        update_option('amk_schema_core_maintenance_last_report', is_array($result) ? $result : [], false);
    }

    private static function normalize_type($type) {
        $type = is_string($type) || is_numeric($type) ? sanitize_key((string) $type) : 'custom';

        $aliases = [
            'online_store'     => 'onlinestore',
            'online-store'     => 'onlinestore',
            'local_business'   => 'localbusiness',
            'local-business'   => 'localbusiness',
            'web_site'         => 'website',
            'web-site'         => 'website',
            'web_page'         => 'webpage',
            'web-page'         => 'webpage',
            'about_page'       => 'aboutpage',
            'about-page'       => 'aboutpage',
            'contact_page'     => 'contactpage',
            'contact-page'     => 'contactpage',
            'product_group'    => 'productgroup',
            'product-group'    => 'productgroup',
            'collection_page'  => 'collection',
            'collection-page'  => 'collection',
            'collectionpage'   => 'collection',
            'breadcrumb_list'  => 'breadcrumb',
            'breadcrumb-list'  => 'breadcrumb',
            'breadcrumblist'   => 'breadcrumb',
        ];

        return isset($aliases[$type]) ? $aliases[$type] : ($type ?: 'custom');
    }

    private static function normalize_scope($scope) {
        $scope = is_string($scope) || is_numeric($scope) ? sanitize_key((string) $scope) : 'global';

        $aliases = [
            'sitewide'         => 'global',
            'all'              => 'global',
            'front_page'       => 'home',
            'front-page'       => 'home',
            'homepage'         => 'home',
            'post'             => 'single_post',
            'single-post'      => 'single_post',
            'singlepost'       => 'single_post',
            'single_product'   => 'product',
            'single-product'   => 'product',
            'product-category' => 'product_category',
            'product_cat'      => 'product_category',
            'product-tag'      => 'product_tag',
            'shop'             => 'collection',
            'product_archive'  => 'collection',
            'product-archive'  => 'collection',
            'collection_page'  => 'collection',
            'collection-page'  => 'collection',
            'blog-archive'     => 'blog_archive',
            'blog'             => 'blog_archive',
            'category'         => 'category_archive',
            'category-archive' => 'category_archive',
            'tag'              => 'tag_archive',
            'tag-archive'      => 'tag_archive',
            'author'           => 'author_archive',
            'author-archive'   => 'author_archive',
            'not_found'        => '404',
            'not-found'        => '404',
            'notfound'         => '404',
            '404_page'         => '404',
            'fallback'         => 'default',
        ];

        return isset($aliases[$scope]) ? $aliases[$scope] : ($scope ?: 'global');
    }
}