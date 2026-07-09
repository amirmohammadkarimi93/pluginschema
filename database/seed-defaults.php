<?php

defined('ABSPATH') || exit;

if (!function_exists('amk_schema_core_seed_table_exists')) {

    function amk_schema_core_seed_table_exists($table) {

        global $wpdb;

        if (!is_string($table) || $table === '') {
            return false;
        }

        $found = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table
            )
        );

        return $found === $table;
    }
}

if (!function_exists('amk_schema_core_seed_get_columns')) {

    function amk_schema_core_seed_get_columns($table) {

        global $wpdb;

        if (!amk_schema_core_seed_table_exists($table)) {
            return [];
        }

        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);

        if (empty($columns) || !is_array($columns)) {
            return [];
        }

        return array_map('strval', $columns);
    }
}

if (!function_exists('amk_schema_core_seed_filter_columns')) {

    function amk_schema_core_seed_filter_columns($row, $columns) {

        if (empty($row) || !is_array($row) || empty($columns) || !is_array($columns)) {
            return [];
        }

        $filtered = [];

        foreach ($row as $key => $value) {
            if (in_array($key, $columns, true)) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }
}

if (!function_exists('amk_schema_core_seed_formats_for_row')) {

    function amk_schema_core_seed_formats_for_row($row) {

        $formats = [];

        foreach ($row as $key => $value) {
            if (in_array($key, ['id', 'priority', 'override', 'template_id'], true)) {
                $formats[] = '%d';
                continue;
            }

            $formats[] = '%s';
        }

        return $formats;
    }
}

if (!function_exists('amk_schema_core_seed_normalize_type')) {

    function amk_schema_core_seed_normalize_type($type) {

        if (class_exists('AMK\\SchemaCore\\Schema\\SchemaTemplateContract')) {
            return AMK\SchemaCore\Schema\SchemaTemplateContract::normalize_type($type);
        }

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
}

if (!function_exists('amk_schema_core_seed_normalize_scope')) {

    function amk_schema_core_seed_normalize_scope($scope) {

        if (class_exists('AMK\\SchemaCore\\Schema\\SchemaTemplateContract')) {
            return AMK\SchemaCore\Schema\SchemaTemplateContract::normalize_scope($scope);
        }

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

if (!function_exists('amk_schema_core_seed_status')) {

    function amk_schema_core_seed_status($status) {

        $status = is_string($status) || is_numeric($status) ? sanitize_key((string) $status) : 'inactive';

        return in_array($status, ['active', 'inactive'], true) ? $status : 'inactive';
    }
}

if (!function_exists('amk_schema_core_seed_json')) {

    function amk_schema_core_seed_json($value) {

        if ($value === null) {
            return wp_json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_object($value)) {
            $value = json_decode(json_encode($value), true);
        }

        if (is_string($value)) {
            $value = trim(wp_unslash($value));

            if ($value === '') {
                return wp_json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_array($value) || is_bool($value) || is_numeric($value)) {
            return wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return wp_json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('amk_schema_core_seed_find_template')) {

    function amk_schema_core_seed_find_template($table, $name, $type, $scope) {

        global $wpdb;

        $name  = sanitize_text_field($name);
        $type  = amk_schema_core_seed_normalize_type($type);
        $scope = amk_schema_core_seed_normalize_scope($scope);

        if ($name === '') {
            return 0;
        }

        return absint(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table}
                     WHERE name = %s
                     AND type = %s
                     AND scope = %s
                     LIMIT 1",
                    $name,
                    $type,
                    $scope
                )
            )
        );
    }
}

if (!function_exists('amk_schema_core_seed_prepare_template_row')) {

    function amk_schema_core_seed_prepare_template_row($template, $include_name = true) {

        $template = is_array($template) ? $template : [];

        $row = [
            'type'        => amk_schema_core_seed_normalize_type($template['type'] ?? 'custom'),
            'scope'       => amk_schema_core_seed_normalize_scope($template['scope'] ?? 'global'),
            'status'      => amk_schema_core_seed_status($template['status'] ?? 'inactive'),
            'schema_json' => amk_schema_core_seed_json($template['schema_json'] ?? []),
            'bindings'    => amk_schema_core_seed_json($template['bindings'] ?? []),
            'conditions'  => amk_schema_core_seed_json($template['conditions'] ?? []),
            'priority'    => isset($template['priority']) ? intval($template['priority']) : 0,
            'override'    => !empty($template['override']) ? 1 : 0,
            'updated_at'  => current_time('mysql'),
        ];

        if ($include_name) {
            $row = array_merge(
                [
                    'name'       => sanitize_text_field($template['name'] ?? ''),
                    'created_at' => current_time('mysql'),
                ],
                $row
            );
        }

        return $row;
    }
}

if (!function_exists('amk_schema_core_seed_conditions')) {

    function amk_schema_core_seed_conditions($table, $template_id, $conditions) {

        global $wpdb;

        $template_id = absint($template_id);

        if (!$template_id || empty($conditions) || !is_array($conditions)) {
            return 0;
        }

        if (!amk_schema_core_seed_table_exists($table)) {
            return 0;
        }

        $columns  = amk_schema_core_seed_get_columns($table);
        $inserted = 0;

        foreach ($conditions as $condition) {
            if (empty($condition) || !is_array($condition)) {
                continue;
            }

            $field = $condition['field'] ?? ($condition['data_key'] ?? ($condition['key'] ?? ''));
            $field = sanitize_text_field($field);

            $operator = sanitize_key($condition['operator'] ?? 'empty');
            $action   = sanitize_key($condition['action'] ?? 'remove');
            $status   = sanitize_key($condition['status'] ?? 'active');

            if ($field === '' || $operator === '') {
                continue;
            }

            $row = [
                'template_id' => $template_id,
                'field'       => $field,
                'operator'    => $operator,
                'value'       => amk_schema_core_seed_json($condition['value'] ?? ($condition['expected'] ?? null)),
                'action'      => $action,
                'path'        => isset($condition['path']) ? sanitize_text_field($condition['path']) : null,
                'payload'     => amk_schema_core_seed_json($condition['payload'] ?? null),
                'priority'    => isset($condition['priority']) ? intval($condition['priority']) : 0,
                'status'      => $status ?: 'active',
                'created_at'  => current_time('mysql'),
                'updated_at'  => current_time('mysql'),
            ];

            $row = amk_schema_core_seed_filter_columns($row, $columns);

            if (empty($row)) {
                continue;
            }

            $result = $wpdb->insert(
                $table,
                $row,
                amk_schema_core_seed_formats_for_row($row)
            );

            if ($result !== false) {
                $inserted++;
            }
        }

        return $inserted;
    }
}

if (!function_exists('amk_schema_core_seed_defaults')) {

    function amk_schema_core_seed_defaults() {

        global $wpdb;

        $result = [
            'inserted'            => 0,
            'updated'             => 0,
            'skipped'             => 0,
            'conditions_inserted' => 0,
            'conditions_deleted'  => 0,
            'errors'              => [],
        ];

        if (!defined('AMK_SCHEMA_CORE_PATH')) {
            $result['errors'][] = __('The plugin base path is not defined.', 'amk-schema-core');
            return $result;
        }

        $table_templates  = $wpdb->prefix . 'amk_schema_templates';
        $table_conditions = $wpdb->prefix . 'amk_schema_conditions';
        $config_file      = AMK_SCHEMA_CORE_PATH . 'config/default-schemas.php';

        if (!file_exists($config_file)) {
            $result['errors'][] = __('The config/default-schemas.php file was not found.', 'amk-schema-core');
            return $result;
        }

        $defaults = require $config_file;

        if (empty($defaults) || !is_array($defaults)) {
            $result['errors'][] = __('The default schemas file is empty or invalid.', 'amk-schema-core');
            return $result;
        }

        if (!amk_schema_core_seed_table_exists($table_templates)) {
            $result['errors'][] = __('The amk_schema_templates table does not exist. Run the plugin migration first.', 'amk-schema-core');
            return $result;
        }

        $template_columns        = amk_schema_core_seed_get_columns($table_templates);
        $conditions_table_exists = amk_schema_core_seed_table_exists($table_conditions);

        foreach ($defaults as $template) {
            if (empty($template) || !is_array($template)) {
                continue;
            }

            $name  = sanitize_text_field($template['name'] ?? '');
            $type  = amk_schema_core_seed_normalize_type($template['type'] ?? 'custom');
            $scope = amk_schema_core_seed_normalize_scope($template['scope'] ?? 'global');

            if ($name === '') {
                continue;
            }

            $conditions = isset($template['conditions']) && is_array($template['conditions']) ? $template['conditions'] : [];

            $legacy_names = isset($template['legacy_names']) && is_array($template['legacy_names'])
                ? array_filter(array_map('sanitize_text_field', $template['legacy_names']))
                : [];

            /*
             * Migration safety:
             * Older installs used this default template name. The template was later
             * renamed because the entity can be Organization, Store, OnlineStore,
             * or a combination of them. Without this alias, a defaults hash change
             * would insert a second global organization template instead of updating
             * the existing one.
             */
            if ($name === 'Organization / Store Schema') {
                $legacy_names[] = 'Organization / OnlineStore Schema';
            }

            $legacy_names = array_values(array_unique(array_filter($legacy_names)));

            $existing_id     = amk_schema_core_seed_find_template($table_templates, $name, $type, $scope);
            $matched_legacy  = false;

            if (!$existing_id && !empty($legacy_names)) {
                foreach ($legacy_names as $legacy_name) {
                    $existing_id = amk_schema_core_seed_find_template($table_templates, $legacy_name, $type, $scope);

                    if ($existing_id) {
                        $matched_legacy = true;
                        break;
                    }
                }
            }

            if ($existing_id) {
                if (empty($template['replace_on_seed'])) {
                    $result['skipped']++;
                    continue;
                }

                $row = amk_schema_core_seed_prepare_template_row($template, false);

                if ($matched_legacy) {
                    $row['name'] = $name;
                }

                $row = amk_schema_core_seed_filter_columns($row, $template_columns);

                $updated = $wpdb->update(
                    $table_templates,
                    $row,
                    ['id' => $existing_id],
                    amk_schema_core_seed_formats_for_row($row),
                    ['%d']
                );

                if ($updated === false) {
                    $result['errors'][] = sprintf(__('Updating template %s failed.', 'amk-schema-core'), $name);
                    continue;
                }

                $result['updated']++;

                if ($conditions_table_exists) {
                    $deleted = $wpdb->delete(
                        $table_conditions,
                        ['template_id' => $existing_id],
                        ['%d']
                    );

                    if ($deleted !== false) {
                        $result['conditions_deleted'] += absint($deleted);
                    }

                    $result['conditions_inserted'] += amk_schema_core_seed_conditions($table_conditions, $existing_id, $conditions);
                }

                continue;
            }

            $row = amk_schema_core_seed_prepare_template_row($template, true);
            $row = amk_schema_core_seed_filter_columns($row, $template_columns);

            $inserted = $wpdb->insert(
                $table_templates,
                $row,
                amk_schema_core_seed_formats_for_row($row)
            );

            if (!$inserted) {
                $result['errors'][] = sprintf(__('Inserting template %s failed.', 'amk-schema-core'), $name);
                continue;
            }

            $template_id = absint($wpdb->insert_id);
            $result['inserted']++;

            if ($template_id && !empty($conditions) && $conditions_table_exists) {
                $result['conditions_inserted'] += amk_schema_core_seed_conditions($table_conditions, $template_id, $conditions);
            }
        }

        update_option('amk_schema_core_defaults_seeded', 1, false);
        update_option('amk_schema_core_defaults_seeded_at', current_time('mysql'), false);
        update_option('amk_schema_core_defaults_seeded_version', defined('AMK_SCHEMA_CORE_VERSION') ? AMK_SCHEMA_CORE_VERSION : '', false);
        update_option('amk_schema_core_defaults_seed_result', $result, false);

        return $result;
    }
}

if (!defined('AMK_SCHEMA_CORE_SKIP_AUTO_SEED') || !AMK_SCHEMA_CORE_SKIP_AUTO_SEED) {
    amk_schema_core_seed_defaults();
}