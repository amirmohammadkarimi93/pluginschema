<?php

namespace AMK\SchemaCore\REST;

defined('ABSPATH') || exit;

class ConditionController {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('amk-schema', '/conditions', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_conditions'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'save_conditions'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);

        register_rest_route('amk-schema', '/conditions/(?P<template_id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_conditions'],
                'permission_callback' => [$this, 'permissions_check'],
                'args'                => [
                    'template_id' => [
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'delete_conditions'],
                'permission_callback' => [$this, 'permissions_check'],
                'args'                => [
                    'template_id' => [
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);
    }

    public function permissions_check() {
        return current_user_can('manage_options');
    }

    public function get_conditions($request) {
        global $wpdb;

        $template_id = absint($request->get_param('template_id'));

        if (!$template_id) {
            return rest_ensure_response([
                'status'     => 'error',
                'message'    => __('Template ID was not sent.', 'amk-schema-core'),
                'conditions' => [],
            ]);
        }

        if (!$this->template_exists($template_id)) {
            return rest_ensure_response([
                'status'     => 'error',
                'message'    => __('The requested template was not found.', 'amk-schema-core'),
                'conditions' => [],
            ]);
        }

        $table = $this->conditions_table();

        if (!$this->table_exists($table)) {
            return rest_ensure_response([
                'status'     => 'success',
                'message'    => __('The conditions table does not exist; conditions are read from the template column.', 'amk-schema-core'),
                'conditions' => $this->get_conditions_from_template_column($template_id),
            ]);
        }

        $columns = $this->get_columns($table);
        $select  = $this->build_select_columns($columns);
        $order   = in_array('priority', $columns, true) ? 'priority ASC, id ASC' : 'id ASC';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$select} FROM {$table} WHERE template_id = %d ORDER BY {$order}",
                $template_id
            ),
            ARRAY_A
        );

        $conditions = [];

        if (is_array($rows) && !empty($rows)) {
            foreach ($rows as $row) {
                $condition = $this->condition_from_db_row($row);

                if (!empty($condition)) {
                    $conditions[] = $condition;
                }
            }
        }

        if (empty($conditions)) {
            $conditions = $this->get_conditions_from_template_column($template_id);
        }

        return rest_ensure_response([
            'status'     => 'success',
            'conditions' => $conditions,
        ]);
    }

    public function save_conditions($request) {
        global $wpdb;

        $params = $request->get_json_params();

        if (!is_array($params)) {
            $params = $request->get_body_params();
        }

        if (!is_array($params)) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('The submitted data is invalid.', 'amk-schema-core'),
            ]);
        }

        $template_id = isset($params['template_id']) ? absint($params['template_id']) : 0;

        if (!$template_id) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('Template ID is required.', 'amk-schema-core'),
            ]);
        }

        if (!$this->template_exists($template_id)) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('The requested template was not found.', 'amk-schema-core'),
            ]);
        }

        $conditions = isset($params['conditions']) ? $params['conditions'] : [];
        $conditions = $this->normalize_conditions($conditions);

        $table = $this->conditions_table();

        if ($this->table_exists($table)) {
            $wpdb->delete(
                $table,
                ['template_id' => $template_id],
                ['%d']
            );

            $this->insert_conditions_rows($template_id, $conditions);
        }

        $this->sync_template_conditions($template_id, $conditions);

        return rest_ensure_response([
            'status'      => 'success',
            'message'     => __('Conditions saved successfully.', 'amk-schema-core'),
            'template_id' => $template_id,
            'conditions'  => $conditions,
            'count'       => count($conditions),
        ]);
    }

    public function delete_conditions($request) {
        global $wpdb;

        $template_id = absint($request->get_param('template_id'));

        if (!$template_id) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('Template ID is required.', 'amk-schema-core'),
            ]);
        }

        if (!$this->template_exists($template_id)) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('The requested template was not found.', 'amk-schema-core'),
            ]);
        }

        $table = $this->conditions_table();

        if ($this->table_exists($table)) {
            $wpdb->delete(
                $table,
                ['template_id' => $template_id],
                ['%d']
            );
        }

        $this->sync_template_conditions($template_id, []);

        return rest_ensure_response([
            'status'      => 'success',
            'message'     => __('Template conditions were deleted.', 'amk-schema-core'),
            'template_id' => $template_id,
            'conditions'  => [],
            'count'       => 0,
        ]);
    }

    private function insert_conditions_rows($template_id, $conditions) {
        global $wpdb;

        $table = $this->conditions_table();

        if (!$this->table_exists($table)) {
            return;
        }

        $columns = $this->get_columns($table);
        $order   = 0;

        foreach ($conditions as $condition) {
            $row = [
                'template_id' => absint($template_id),
                'field'       => $condition['data_key'],
                'operator'    => $condition['operator'],
                'value'       => $this->encode_condition_value($condition['expected']),
                'action'      => $condition['action'],
                'path'        => $condition['path'],
                'payload'     => !empty($condition['payload']) ? wp_json_encode($condition['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'priority'    => isset($condition['priority']) ? intval($condition['priority']) : $order,
                'status'      => $condition['status'],
                'created_at'  => current_time('mysql'),
                'updated_at'  => current_time('mysql'),
            ];

            $row = array_intersect_key($row, array_flip($columns));

            if (empty($row)) {
                continue;
            }

            $wpdb->insert(
                $table,
                $row,
                $this->formats_for_row($row)
            );

            $order++;
        }
    }

    private function sync_template_conditions($template_id, $conditions) {
        global $wpdb;

        $table = $this->templates_table();

        if (!$this->table_exists($table)) {
            return false;
        }

        $columns = $this->get_columns($table);

        if (!in_array('conditions', $columns, true)) {
            return false;
        }

        $data = [
            'conditions' => wp_json_encode(array_values($conditions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        $formats = ['%s'];

        if (in_array('updated_at', $columns, true)) {
            $data['updated_at'] = current_time('mysql');
            $formats[] = '%s';
        }

        $updated = $wpdb->update(
            $table,
            $data,
            ['id' => absint($template_id)],
            $formats,
            ['%d']
        );

        return $updated !== false;
    }

    private function get_conditions_from_template_column($template_id) {
        global $wpdb;

        $table = $this->templates_table();

        if (!$this->table_exists($table)) {
            return [];
        }

        $columns = $this->get_columns($table);

        if (!in_array('conditions', $columns, true)) {
            return [];
        }

        $raw = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT conditions FROM {$table} WHERE id = %d LIMIT 1",
                absint($template_id)
            )
        );

        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [];
        }

        return $this->normalize_conditions($decoded);
    }

    private function normalize_conditions($conditions) {
        if (is_string($conditions)) {
            $decoded = json_decode(wp_unslash($conditions), true);
            $conditions = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        if (!is_array($conditions)) {
            return [];
        }

        $normalized = [];
        $order      = 0;

        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }

            $data_key = $this->array_get_first($condition, ['data_key', 'field', 'key', 'source', 'source_key'], '');
            $operator = $this->array_get_first($condition, ['operator', 'compare'], 'empty');
            $expected = $this->array_get_first($condition, ['expected', 'value', 'compare_value'], null);
            $action   = $this->array_get_first($condition, ['action'], 'remove');
            $path     = $this->array_get_first($condition, ['path', 'target_path', 'schema_path'], '');
            $payload  = $this->array_get_first($condition, ['payload'], []);
            $priority = $this->array_get_first($condition, ['priority', 'sort_order'], $order);
            $status   = $this->array_get_first($condition, ['status'], 'active');

            $data_key = sanitize_text_field(wp_unslash($data_key));
            $operator = $this->sanitize_operator($operator);
            $action   = $this->sanitize_action($action);
            $path     = sanitize_text_field(wp_unslash($path));
            $status   = $this->sanitize_status($status);
            $priority = intval($priority);

            if ($data_key === '' || $operator === '') {
                continue;
            }

            if (($action === 'remove' || $action === 'remove_path') && $path === '') {
                continue;
            }

            if (!is_array($payload)) {
                $payload = $this->decode_maybe_json($payload);
                $payload = is_array($payload) ? $payload : [];
            }

            $normalized[] = [
                'data_key' => $data_key,
                'field'    => $data_key,
                'operator' => $operator,
                'expected' => $expected,
                'value'    => $expected,
                'action'   => $action,
                'path'     => $path,
                'payload'  => $payload,
                'priority' => $priority,
                'status'   => $status,
            ];

            $order++;
        }

        usort($normalized, function ($a, $b) {
            $priority_a = isset($a['priority']) ? intval($a['priority']) : 0;
            $priority_b = isset($b['priority']) ? intval($b['priority']) : 0;

            if ($priority_a === $priority_b) {
                return 0;
            }

            return $priority_a <=> $priority_b;
        });

        return array_values($normalized);
    }

    private function condition_from_db_row($row) {
        if (!is_array($row)) {
            return [];
        }

        $field    = isset($row['field']) ? sanitize_text_field($row['field']) : '';
        $operator = isset($row['operator']) ? $this->sanitize_operator($row['operator']) : 'empty';
        $expected = array_key_exists('value', $row) ? $this->decode_condition_value($row['value']) : null;
        $action   = isset($row['action']) ? $this->sanitize_action($row['action']) : 'remove';
        $path     = isset($row['path']) ? sanitize_text_field($row['path']) : '';
        $payload  = isset($row['payload']) ? $this->decode_condition_value($row['payload']) : [];
        $priority = isset($row['priority']) ? intval($row['priority']) : 0;
        $status   = isset($row['status']) ? $this->sanitize_status($row['status']) : 'active';

        if ($field === '' || $operator === '') {
            return [];
        }

        if (!is_array($payload)) {
            $payload = [];
        }

        return [
            'id'       => isset($row['id']) ? absint($row['id']) : 0,
            'data_key' => $field,
            'field'    => $field,
            'operator' => $operator,
            'expected' => $expected,
            'value'    => $expected,
            'action'   => $action,
            'path'     => $path,
            'payload'  => $payload,
            'priority' => $priority,
            'status'   => $status,
        ];
    }

    private function build_select_columns($columns) {
        $wanted = [
            'id',
            'template_id',
            'field',
            'operator',
            'value',
            'action',
            'path',
            'payload',
            'priority',
            'status',
        ];

        $select = [];

        foreach ($wanted as $column) {
            if (in_array($column, $columns, true)) {
                $select[] = $column;
            }
        }

        if (empty($select)) {
            return '*';
        }

        return implode(', ', $select);
    }

    private function encode_condition_value($value) {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    private function decode_condition_value($value) {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return json_decode(json_encode($value), true);
        }

        if (!is_string($value)) {
            return $value;
        }

        $decoded = $this->decode_maybe_json($value);

        return $decoded;
    }

    private function decode_maybe_json($value) {
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $first = substr($value, 0, 1);
        $last  = substr($value, -1);

        $looks_like_json = (
            ($first === '{' && $last === '}') ||
            ($first === '[' && $last === ']') ||
            ($first === '"' && $last === '"') ||
            $value === 'true' ||
            $value === 'false' ||
            $value === 'null'
        );

        if (!$looks_like_json) {
            return $value;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $value;
        }

        return $decoded;
    }

    private function sanitize_operator($operator) {
        $operator = sanitize_key($operator);

        $allowed = [
            'empty',
            'is_empty',
            'not_empty',
            'is_not_empty',
            'exists',
            'not_exists',
            'equals',
            'not_equals',
            'contains',
            'not_contains',
            'greater_than',
            'less_than',
            'greater_or_equal',
            'less_or_equal',
            'in',
            'not_in',
        ];

        return in_array($operator, $allowed, true) ? $operator : 'empty';
    }

    private function sanitize_action($action) {
        $action = sanitize_key($action);

        $allowed = [
            'remove',
            'remove_path',
        ];

        return in_array($action, $allowed, true) ? $action : 'remove';
    }

    private function sanitize_status($status) {
        $status = sanitize_key($status);

        $allowed = [
            'active',
            'inactive',
            'draft',
        ];

        return in_array($status, $allowed, true) ? $status : 'active';
    }

    private function template_exists($template_id) {
        global $wpdb;

        $table = $this->templates_table();

        if (!$this->table_exists($table)) {
            return false;
        }

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d LIMIT 1",
                absint($template_id)
            )
        );

        return !empty($exists);
    }

    private function templates_table() {
        global $wpdb;

        return $wpdb->prefix . 'amk_schema_templates';
    }

    private function conditions_table() {
        global $wpdb;

        return $wpdb->prefix . 'amk_schema_conditions';
    }

    private function table_exists($table) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private function get_columns($table) {
        global $wpdb;

        $columns = $wpdb->get_col("DESC {$table}", 0);

        return is_array($columns) ? $columns : [];
    }

    private function formats_for_row($row) {
        $formats = [];

        foreach ($row as $key => $value) {
            if (in_array($key, ['id', 'template_id', 'priority'], true)) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }

    private function array_get_first($array, $keys, $default = '') {
        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                return $array[$key];
            }
        }

        return $default;
    }
}