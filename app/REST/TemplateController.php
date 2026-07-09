<?php

namespace AMK\SchemaCore\REST;

use AMK\SchemaCore\Repository\TemplateRepository;
use AMK\SchemaCore\Schema\SchemaTemplateContract;
use AMK\SchemaCore\Schema\SchemaValidator;

defined('ABSPATH') || exit;

class TemplateController {

    /**
     * @var TemplateRepository
     */
    private $repository;

    public function __construct() {
        $this->repository = new TemplateRepository();

        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route('amk-schema', '/templates', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_templates'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'save_template'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);

        register_rest_route('amk-schema', '/templates/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_template'],
                'permission_callback' => [$this, 'permissions_check'],
                'args'                => [
                    'id' => [
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'delete_template'],
                'permission_callback' => [$this, 'permissions_check'],
                'args'                => [
                    'id' => [
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return bool
     */
    public function permissions_check() {
        return current_user_can('manage_options');
    }

    /**
     * Get templates list.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_templates($request) {
        $args = [
            'status'  => $this->get_request_param($request, 'status', ''),
            'type'    => $this->get_request_param($request, 'type', ''),
            'scope'   => $this->get_request_param($request, 'scope', ''),
            'limit'   => absint($this->get_request_param($request, 'limit', 200)),
            'orderby' => sanitize_key($this->get_request_param($request, 'orderby', 'priority')),
            'order'   => strtoupper((string) $this->get_request_param($request, 'order', 'DESC')),
        ];

        if (!empty($args['type'])) {
            $args['type'] = SchemaTemplateContract::normalize_type($args['type']);
        }

        if (!empty($args['scope'])) {
            $args['scope'] = SchemaTemplateContract::normalize_scope($args['scope']);
        }

        if ($args['limit'] <= 0) {
            $args['limit'] = 200;
        }

        $templates = $this->repository->all($args);

        return rest_ensure_response([
            'status'    => 'success',
            'templates' => $this->prepare_templates_for_response($templates),
            'contract'  => $this->contract_payload(),
        ]);
    }

    /**
     * Get one template.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_template($request) {
        $id = absint($request->get_param('id'));

        if (!$id) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('The template ID is invalid.', 'amk-schema-core'),
            ]);
        }

        $template = $this->repository->find($id);

        if (!$template) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('The requested template was not found.', 'amk-schema-core'),
            ]);
        }

        return rest_ensure_response([
            'status'   => 'success',
            'template' => $this->prepare_template_for_response($template),
            'contract' => $this->contract_payload(),
        ]);
    }

    /**
     * Create or update template.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function save_template($request) {
        $data = $request->get_json_params();

        if (!is_array($data)) {
            $data = $request->get_body_params();
        }

        if (!is_array($data)) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('The submitted data is invalid.', 'amk-schema-core'),
            ]);
        }

        $id = isset($data['id']) ? absint($data['id']) : 0;

        if (!$id && isset($data['template_id'])) {
            $id = absint($data['template_id']);
        }

        $name = isset($data['name']) ? sanitize_text_field(wp_unslash($data['name'])) : '';

        if ($name === '') {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('Template name is required.', 'amk-schema-core'),
            ]);
        }

        $schema_json = $this->prepare_json_value($data['schema_json'] ?? []);
        $bindings    = $this->prepare_json_value($data['bindings'] ?? []);
        $conditions  = $this->prepare_json_value($data['conditions'] ?? []);

        if ($schema_json === false) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('The JSON-LD structure is invalid.', 'amk-schema-core'),
            ]);
        }

        if ($bindings === false) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('The bindings structure is invalid.', 'amk-schema-core'),
            ]);
        }

        if ($conditions === false) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('The conditions structure is invalid.', 'amk-schema-core'),
            ]);
        }

        $validation = $this->validate_schema_json($schema_json);

        if (!$validation['valid']) {
            return rest_ensure_response([
                'status'   => 'error',
                'message'  => __('JSON-LD is not valid according to the validator.', 'amk-schema-core'),
                'errors'   => $validation['errors'],
                'warnings' => $validation['warnings'],
            ]);
        }

        $type  = SchemaTemplateContract::normalize_type($data['type'] ?? 'custom');
        $scope = SchemaTemplateContract::normalize_scope($data['scope'] ?? 'global');

        $template_data = [
            'name'        => $name,
            'type'        => $type,
            'scope'       => $scope,
            'status'      => $this->sanitize_status($data['status'] ?? 'active'),
            'schema_json' => $schema_json,
            'bindings'    => $bindings,
            'conditions'  => $conditions,
            'priority'    => isset($data['priority']) ? intval($data['priority']) : 0,
            'override'    => !empty($data['override']) ? 1 : 0,
        ];

        if ($id > 0) {
            $updated = $this->repository->update($id, $template_data);

            if (!$updated) {
                return rest_ensure_response([
                    'status'  => 'error',
                    'message' => __('Error updating template.', 'amk-schema-core'),
                ]);
            }

            $this->sync_conditions_table($id, $conditions);

            return rest_ensure_response([
                'status'      => 'success',
                'message'     => __('Template updated successfully.', 'amk-schema-core'),
                'template_id' => $id,
                'template'    => $this->prepare_template_for_response($this->repository->find($id)),
                'warnings'    => $validation['warnings'],
                'contract'    => $this->contract_payload(),
            ]);
        }

        $new_id = $this->repository->create($template_data);

        if (!$new_id) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('Error saving template.', 'amk-schema-core'),
            ]);
        }

        $this->sync_conditions_table($new_id, $conditions);

        return rest_ensure_response([
            'status'      => 'success',
            'message'     => __('Template saved successfully.', 'amk-schema-core'),
            'template_id' => $new_id,
            'template'    => $this->prepare_template_for_response($this->repository->find($new_id)),
            'warnings'    => $validation['warnings'],
            'contract'    => $this->contract_payload(),
        ]);
    }

    /**
     * Delete template.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function delete_template($request) {
        $id = absint($request->get_param('id'));

        if (!$id) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('The template ID is invalid.', 'amk-schema-core'),
            ]);
        }

        $deleted = $this->repository->delete($id);

        if (!$deleted) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('Template deletion failed.', 'amk-schema-core'),
            ]);
        }

        return rest_ensure_response([
            'status'  => 'success',
            'message' => __('Template deleted successfully.', 'amk-schema-core'),
        ]);
    }

    /**
     * Prepare JSON value as decoded array.
     * Repository will encode it before database insert/update.
     *
     * @param mixed $value
     * @return array|false
     */
    private function prepare_json_value($value) {
        if (is_string($value)) {
            $value = trim(wp_unslash($value));

            if ($value === '') {
                return [];
            }

            $decoded = json_decode($value, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return false;
            }

            return is_array($decoded) ? $decoded : [];
        }

        if (is_object($value)) {
            $value = json_decode(json_encode($value), true);
        }

        if (is_array($value)) {
            return $value;
        }

        return [];
    }

    /**
     * Validate schema JSON with current SchemaValidator.
     *
     * @param array $schema_json
     * @return array
     */
    private function validate_schema_json($schema_json) {
        if (!class_exists(SchemaValidator::class)) {
            return [
                'valid'    => true,
                'errors'   => [],
                'warnings' => [],
            ];
        }

        $validator = new SchemaValidator();

        if (method_exists($validator, 'validate_with_report')) {
            $report = $validator->validate_with_report($schema_json);
        } else {
            $valid  = $validator->validate($schema_json);
            $report = [
                'valid'    => $valid,
                'errors'   => method_exists($validator, 'get_errors') ? $validator->get_errors() : [],
                'warnings' => method_exists($validator, 'get_warnings') ? $validator->get_warnings() : [],
            ];
        }

        return [
            'valid'    => !empty($report['valid']),
            'errors'   => isset($report['errors']) && is_array($report['errors']) ? $report['errors'] : [],
            'warnings' => isset($report['warnings']) && is_array($report['warnings']) ? $report['warnings'] : [],
        ];
    }

    /**
     * Keep conditions table aligned when templates are saved through REST.
     *
     * @param int   $template_id
     * @param array $conditions
     * @return void
     */
    private function sync_conditions_table($template_id, $conditions) {
        global $wpdb;

        $template_id = absint($template_id);

        if (!$template_id) {
            return;
        }

        $table = $wpdb->prefix . 'amk_schema_conditions';

        $found = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $table)
        );

        if ($found !== $table) {
            return;
        }

        $wpdb->delete(
            $table,
            ['template_id' => $template_id],
            ['%d']
        );

        $conditions = $this->normalize_conditions($conditions);

        if (empty($conditions)) {
            return;
        }

        $columns = $wpdb->get_col("DESC {$table}", 0);
        $columns = is_array($columns) ? $columns : [];
        $order   = 0;

        foreach ($conditions as $condition) {
            $row = [
                'template_id' => $template_id,
                'field'       => $condition['data_key'],
                'operator'    => $condition['operator'],
                'value'       => $this->encode_condition_value($condition['expected']),
                'action'      => $condition['action'],
                'path'        => $condition['path'],
                'payload'     => !empty($condition['payload']) ? wp_json_encode($condition['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'priority'    => $order,
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
                $this->formats_for_condition_row($row)
            );

            $order++;
        }
    }

    /**
     * Normalize condition rows from REST/template editor formats.
     *
     * @param mixed $conditions
     * @return array
     */
    private function normalize_conditions($conditions) {
        if (is_string($conditions)) {
            $decoded = json_decode($conditions, true);
            $conditions = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        if (!is_array($conditions)) {
            return [];
        }

        $normalized = [];

        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }

            $data_key = $this->array_get_first($condition, ['data_key', 'field', 'key', 'source', 'source_key'], '');
            $operator = $this->array_get_first($condition, ['operator', 'compare'], 'empty');
            $expected = $this->array_get_first($condition, ['expected', 'value', 'compare_value'], null);
            $action   = $this->array_get_first($condition, ['action'], 'remove');
            $path     = $this->array_get_first($condition, ['path', 'target_path', 'schema_path'], '');
            $status   = $this->array_get_first($condition, ['status'], 'active');
            $payload  = $this->array_get_first($condition, ['payload'], []);

            $data_key = sanitize_text_field($data_key);
            $operator = sanitize_key($operator);
            $action   = sanitize_key($action);
            $path     = sanitize_text_field($path);
            $status   = $this->sanitize_status($status);

            if ($data_key === '' || $operator === '') {
                continue;
            }

            if (($action === 'remove' || $action === 'remove_path') && $path === '') {
                continue;
            }

            $normalized[] = [
                'data_key' => $data_key,
                'field'    => $data_key,
                'operator' => $operator,
                'expected' => $expected,
                'value'    => $expected,
                'action'   => $action,
                'path'     => $path,
                'payload'  => is_array($payload) ? $payload : [],
                'status'   => $status,
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private function encode_condition_value($value) {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    /**
     * @param array $row
     * @return array
     */
    private function formats_for_condition_row($row) {
        $formats = [];

        foreach ($row as $key => $value) {
            if (in_array($key, ['template_id', 'priority'], true)) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }

    /**
     * @param array $array
     * @param array $keys
     * @param mixed $default
     * @return mixed
     */
    private function array_get_first($array, $keys, $default = '') {
        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                return $array[$key];
            }
        }

        return $default;
    }

    /**
     * @param array $templates
     * @return array
     */
    private function prepare_templates_for_response($templates) {
        if (empty($templates) || !is_array($templates)) {
            return [];
        }

        $prepared = [];

        foreach ($templates as $template) {
            $prepared[] = $this->prepare_template_for_response($template);
        }

        return $prepared;
    }

    /**
     * @param array|null $template
     * @return array|null
     */
    private function prepare_template_for_response($template) {
        if (empty($template) || !is_array($template)) {
            return null;
        }

        $template['type']  = SchemaTemplateContract::normalize_type($template['type'] ?? 'custom');
        $template['scope'] = SchemaTemplateContract::normalize_scope($template['scope'] ?? 'global');

        $template['type_label']      = SchemaTemplateContract::type_label($template['type']);
        $template['scope_label']     = SchemaTemplateContract::scope_label($template['scope']);
        $template['schema_org_type'] = SchemaTemplateContract::schema_org_type($template['type']);

        return $template;
    }

    /**
     * @return array
     */
    private function contract_payload() {
        return [
            'types'  => SchemaTemplateContract::type_options(),
            'scopes' => SchemaTemplateContract::scope_options(),
        ];
    }

    /**
     * @param \WP_REST_Request $request
     * @param string           $key
     * @param mixed            $default
     * @return mixed
     */
    private function get_request_param($request, $key, $default = '') {
        $value = $request->get_param($key);

        return $value === null ? $default : $value;
    }

    /**
     * @param string $status
     * @return string
     */
    private function sanitize_status($status) {
        $status = sanitize_key($status);

        $allowed = [
            'active',
            'inactive',
            'draft',
        ];

        return in_array($status, $allowed, true) ? $status : 'active';
    }
}