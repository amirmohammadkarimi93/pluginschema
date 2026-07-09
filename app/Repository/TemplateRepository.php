<?php

namespace AMK\SchemaCore\Repository;

use AMK\SchemaCore\Schema\SchemaTemplateContract;

defined('ABSPATH') || exit;

class TemplateRepository {

    private $wpdb;
    private $table;
    private $conditions_table;

    public function __construct() {

        global $wpdb;

        $this->wpdb             = $wpdb;
        $this->table            = $wpdb->prefix . 'amk_schema_templates';
        $this->conditions_table = $wpdb->prefix . 'amk_schema_conditions';
    }

    public function table_exists() {

        $found = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $this->table
            )
        );

        return $found === $this->table;
    }

    public function conditions_table_exists() {

        $found = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $this->conditions_table
            )
        );

        return $found === $this->conditions_table;
    }

    public function all($args = []) {

        if (!$this->table_exists()) {
            return [];
        }

        $defaults = [
            'status'  => '',
            'scope'   => '',
            'type'    => '',
            'limit'   => 100,
            'orderby' => 'priority',
            'order'   => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $where  = [];
        $values = [];

        if (!empty($args['status'])) {
            $where[]  = 'status = %s';
            $values[] = $this->sanitize_status($args['status']);
        }

        if (!empty($args['scope'])) {
            $where[]  = 'scope = %s';
            $values[] = SchemaTemplateContract::normalize_scope($args['scope']);
        }

        if (!empty($args['type'])) {
            $where[]  = 'type = %s';
            $values[] = SchemaTemplateContract::normalize_type($args['type']);
        }

        $allowed_orderby = ['id', 'name', 'type', 'scope', 'status', 'priority', 'created_at', 'updated_at'];
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'priority';

        $order = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $limit = absint($args['limit']);
        $limit = $limit > 0 ? $limit : 100;

        $sql = "SELECT * FROM {$this->table}";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= " ORDER BY {$orderby} {$order}, id DESC LIMIT %d";
        $values[] = $limit;

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $values),
            ARRAY_A
        );

        if (empty($rows) || !is_array($rows)) {
            return [];
        }

        return array_map([$this, 'parse_row'], $rows);
    }

    public function find($id) {

        if (!$this->table_exists()) {
            return null;
        }

        $id = absint($id);

        if (!$id) {
            return null;
        }

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        return $row ? $this->parse_row($row) : null;
    }

    public function active_by_context($context) {

        if (!$this->table_exists()) {
            return [];
        }

        $context = SchemaTemplateContract::normalize_scope($context);
        $scopes  = $this->allowed_scopes_for_context($context);

        if (empty($scopes)) {
            return [];
        }

        $scopes = array_values(array_unique(array_map([SchemaTemplateContract::class, 'normalize_scope'], $scopes)));

        $placeholders = implode(',', array_fill(0, count($scopes), '%s'));
        $field_args   = implode(',', array_fill(0, count($scopes), '%s'));

        $values = array_merge(['active'], $scopes, $scopes);

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE status = %s
                 AND scope IN ({$placeholders})
                 ORDER BY FIELD(scope, {$field_args}) ASC, priority DESC, id DESC",
                $values
            ),
            ARRAY_A
        );

        if (empty($rows) || !is_array($rows)) {
            return [];
        }

        return array_map([$this, 'parse_row'], $rows);
    }

    public function find_by_scope($scope, $only_active = true) {

        if (!$this->table_exists()) {
            return [];
        }

        $scope = SchemaTemplateContract::normalize_scope($scope);

        if ($only_active) {
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table}
                     WHERE scope = %s AND status = %s
                     ORDER BY priority DESC, id DESC",
                    $scope,
                    'active'
                ),
                ARRAY_A
            );
        } else {
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table}
                     WHERE scope = %s
                     ORDER BY priority DESC, id DESC",
                    $scope
                ),
                ARRAY_A
            );
        }

        if (empty($rows) || !is_array($rows)) {
            return [];
        }

        return array_map([$this, 'parse_row'], $rows);
    }

    public function find_by_type($type, $only_active = true) {

        if (!$this->table_exists()) {
            return [];
        }

        $type = SchemaTemplateContract::normalize_type($type);

        if ($only_active) {
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table}
                     WHERE type = %s AND status = %s
                     ORDER BY priority DESC, id DESC",
                    $type,
                    'active'
                ),
                ARRAY_A
            );
        } else {
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table}
                     WHERE type = %s
                     ORDER BY priority DESC, id DESC",
                    $type
                ),
                ARRAY_A
            );
        }

        if (empty($rows) || !is_array($rows)) {
            return [];
        }

        return array_map([$this, 'parse_row'], $rows);
    }

    public function create($data) {

        if (!$this->table_exists()) {
            return 0;
        }

        $columns = $this->get_columns($this->table);
        $row     = $this->prepare_row($data);

        if (in_array('created_at', $columns, true)) {
            $row['created_at'] = current_time('mysql');
        }

        if (in_array('updated_at', $columns, true)) {
            $row['updated_at'] = current_time('mysql');
        }

        $row = $this->filter_row_by_columns($row, $columns);

        $inserted = $this->wpdb->insert(
            $this->table,
            $row,
            $this->formats_for_row($row)
        );

        if (!$inserted) {
            return 0;
        }

        $id = absint($this->wpdb->insert_id);

        if ($id && !empty($data['conditions'])) {
            $this->sync_conditions_column($id, $data['conditions']);
        }

        return $id;
    }

    public function update($id, $data) {

        if (!$this->table_exists()) {
            return false;
        }

        $id = absint($id);

        if (!$id) {
            return false;
        }

        $columns = $this->get_columns($this->table);
        $row     = $this->prepare_row($data);

        if (in_array('updated_at', $columns, true)) {
            $row['updated_at'] = current_time('mysql');
        }

        $row = $this->filter_row_by_columns($row, $columns);

        $updated = $this->wpdb->update(
            $this->table,
            $row,
            ['id' => $id],
            $this->formats_for_row($row),
            ['%d']
        );

        if ($updated === false) {
            return false;
        }

        if (array_key_exists('conditions', $data)) {
            $this->sync_conditions_column($id, $data['conditions']);
        }

        return true;
    }

    public function delete($id) {

        if (!$this->table_exists()) {
            return false;
        }

        $id = absint($id);

        if (!$id) {
            return false;
        }

        $deleted = $this->wpdb->delete(
            $this->table,
            ['id' => $id],
            ['%d']
        );

        if ($deleted !== false && $this->conditions_table_exists()) {
            $this->wpdb->delete(
                $this->conditions_table,
                ['template_id' => $id],
                ['%d']
            );
        }

        return $deleted !== false;
    }

    public function duplicate($id) {

        $template = $this->find($id);

        if (!$template) {
            return 0;
        }

        unset($template['id'], $template['created_at'], $template['updated_at']);

        $template['name']   = $template['name'] . __(' - Copy', 'amk-schema-core');
        $template['status'] = 'inactive';

        return $this->create($template);
    }

    public function count($args = []) {

        if (!$this->table_exists()) {
            return 0;
        }

        $defaults = [
            'status' => '',
            'scope'  => '',
            'type'   => '',
        ];

        $args = wp_parse_args($args, $defaults);

        $where  = [];
        $values = [];

        if (!empty($args['status'])) {
            $where[]  = 'status = %s';
            $values[] = $this->sanitize_status($args['status']);
        }

        if (!empty($args['scope'])) {
            $where[]  = 'scope = %s';
            $values[] = SchemaTemplateContract::normalize_scope($args['scope']);
        }

        if (!empty($args['type'])) {
            $where[]  = 'type = %s';
            $values[] = SchemaTemplateContract::normalize_type($args['type']);
        }

        $sql = "SELECT COUNT(*) FROM {$this->table}";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
            return absint($this->wpdb->get_var($this->wpdb->prepare($sql, $values)));
        }

        return absint($this->wpdb->get_var($sql));
    }

    public function normalize_existing_rows($limit = 500) {

        if (!$this->table_exists()) {
            return 0;
        }

        $limit = absint($limit);
        $limit = $limit > 0 ? $limit : 500;

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, type, scope FROM {$this->table} LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        if (empty($rows) || !is_array($rows)) {
            return 0;
        }

        $changed = 0;

        foreach ($rows as $row) {
            $id = isset($row['id']) ? absint($row['id']) : 0;

            if (!$id) {
                continue;
            }

            $type  = SchemaTemplateContract::normalize_type($row['type'] ?? 'custom');
            $scope = SchemaTemplateContract::normalize_scope($row['scope'] ?? 'global');

            if ($type === ($row['type'] ?? '') && $scope === ($row['scope'] ?? '')) {
                continue;
            }

            $data = [
                'type'  => $type,
                'scope' => $scope,
            ];

            $formats = ['%s', '%s'];

            $columns = $this->get_columns($this->table);

            if (in_array('updated_at', $columns, true)) {
                $data['updated_at'] = current_time('mysql');
                $formats[] = '%s';
            }

            $updated = $this->wpdb->update(
                $this->table,
                $data,
                ['id' => $id],
                $formats,
                ['%d']
            );

            if ($updated !== false) {
                $changed++;
            }
        }

        return $changed;
    }

    private function prepare_row($data) {

        $data = is_array($data) ? $data : [];

        return [
            'name'        => sanitize_text_field($data['name'] ?? ''),
            'type'        => SchemaTemplateContract::normalize_type($data['type'] ?? 'custom'),
            'scope'       => SchemaTemplateContract::normalize_scope($data['scope'] ?? 'global'),
            'status'      => $this->sanitize_status($data['status'] ?? 'active'),
            'schema_json' => $this->prepare_json($data['schema_json'] ?? []),
            'bindings'    => $this->prepare_json($data['bindings'] ?? []),
            'conditions'  => $this->prepare_json($data['conditions'] ?? []),
            'priority'    => intval($data['priority'] ?? 0),
            'override'    => !empty($data['override']) ? 1 : 0,
        ];
    }

    private function parse_row($row) {

        $template_id = isset($row['id']) ? absint($row['id']) : 0;
        $conditions  = $this->decode_json($row['conditions'] ?? '');

        if (empty($conditions) && $template_id) {
            $conditions = $this->load_conditions_from_conditions_table($template_id);
        }

        return [
            'id'          => $template_id,
            'name'        => isset($row['name']) ? sanitize_text_field($row['name']) : '',
            'type'        => SchemaTemplateContract::normalize_type($row['type'] ?? 'custom'),
            'scope'       => SchemaTemplateContract::normalize_scope($row['scope'] ?? 'global'),
            'status'      => $this->sanitize_status($row['status'] ?? 'active'),
            'schema_json' => $this->decode_json($row['schema_json'] ?? ''),
            'bindings'    => $this->decode_json($row['bindings'] ?? ''),
            'conditions'  => $conditions,
            'priority'    => isset($row['priority']) ? intval($row['priority']) : 0,
            'override'    => !empty($row['override']) ? 1 : 0,
            'created_at'  => isset($row['created_at']) ? $row['created_at'] : '',
            'updated_at'  => isset($row['updated_at']) ? $row['updated_at'] : '',
        ];
    }

    private function prepare_json($value) {

        if (is_string($value)) {
            $value = wp_unslash($value);
            $value = trim($value);

            if ($value === '') {
                return wp_json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return wp_json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_object($value)) {
            $value = json_decode(json_encode($value), true);
        }

        if (is_array($value)) {
            return wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return wp_json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function decode_json($value) {

        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            $value = json_decode(json_encode($value), true);
            return is_array($value) ? $value : [];
        }

        if (!is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        if (is_string($decoded)) {
            $decoded_twice = json_decode($decoded, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $decoded = $decoded_twice;
            }
        }

        return is_array($decoded) ? $decoded : [];
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

    private function get_columns($table) {

        $columns = $this->wpdb->get_col("DESC {$table}", 0);

        return is_array($columns) ? $columns : [];
    }

    private function filter_row_by_columns($row, $columns) {

        if (empty($columns)) {
            return $row;
        }

        return array_intersect_key($row, array_flip($columns));
    }

    private function formats_for_row($row) {

        $formats = [];

        foreach ($row as $key => $value) {
            if (in_array($key, ['id', 'priority', 'override'], true)) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }

    private function allowed_scopes_for_context($context) {

        $context = SchemaTemplateContract::normalize_scope($context);

        $scopes = [
            'global',
            $context,
        ];

        $fallbacks = [
            'home'             => ['page'],
            'product'          => ['page'],
            'product_category' => ['collection', 'archive'],
            'product_tag'      => ['collection', 'archive'],
            'collection'       => ['archive', 'page'],
            'single_post'      => ['page'],
            'blog_archive'     => ['archive', 'collection'],
            'category_archive' => ['blog_archive', 'archive', 'collection'],
            'tag_archive'      => ['blog_archive', 'archive', 'collection'],
            'author_archive'   => ['blog_archive', 'archive', 'collection'],
            'archive'          => ['blog_archive', 'collection'],
            'search'           => ['page'],
            '404'              => ['page'],
        ];

        if (isset($fallbacks[$context])) {
            $scopes = array_merge($scopes, $fallbacks[$context]);
        }

        $scopes[] = 'default';

        return array_values(array_unique(array_filter($scopes)));
    }

    private function load_conditions_from_conditions_table($template_id) {

        if (!$this->conditions_table_exists()) {
            return [];
        }

        $template_id = absint($template_id);

        if (!$template_id) {
            return [];
        }

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->conditions_table}
                 WHERE template_id = %d
                 AND status = %s
                 ORDER BY priority ASC, id ASC",
                $template_id,
                'active'
            ),
            ARRAY_A
        );

        if (empty($rows) || !is_array($rows)) {
            return [];
        }

        $conditions = [];

        foreach ($rows as $row) {
            $value = isset($row['value']) ? maybe_unserialize($row['value']) : '';

            $conditions[] = [
                'data_key' => isset($row['field']) ? sanitize_text_field($row['field']) : '',
                'field'    => isset($row['field']) ? sanitize_text_field($row['field']) : '',
                'operator' => isset($row['operator']) ? sanitize_key($row['operator']) : 'empty',
                'expected' => $value,
                'value'    => $value,
                'action'   => isset($row['action']) ? sanitize_key($row['action']) : 'remove',
                'path'     => isset($row['path']) ? sanitize_text_field($row['path']) : '',
                'priority' => isset($row['priority']) ? intval($row['priority']) : 0,
            ];
        }

        return $conditions;
    }

    private function sync_conditions_column($template_id, $conditions) {

        if (!$this->table_exists()) {
            return false;
        }

        $template_id = absint($template_id);

        if (!$template_id) {
            return false;
        }

        $columns = $this->get_columns($this->table);

        if (!in_array('conditions', $columns, true)) {
            return false;
        }

        $data = [
            'conditions' => $this->prepare_json($conditions),
        ];

        $formats = ['%s'];

        if (in_array('updated_at', $columns, true)) {
            $data['updated_at'] = current_time('mysql');
            $formats[] = '%s';
        }

        $updated = $this->wpdb->update(
            $this->table,
            $data,
            ['id' => $template_id],
            $formats,
            ['%d']
        );

        return $updated !== false;
    }
}