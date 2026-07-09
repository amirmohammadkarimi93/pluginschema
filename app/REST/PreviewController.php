<?php

namespace AMK\SchemaCore\REST;

use AMK\SchemaCore\Data\DataResolver;
use AMK\SchemaCore\Repository\TemplateRepository;
use AMK\SchemaCore\Schema\SchemaCompiler;
use AMK\SchemaCore\Schema\SchemaTemplateContract;
use AMK\SchemaCore\Schema\SchemaValidator;

defined('ABSPATH') || exit;

class PreviewController {

    /**
     * @var TemplateRepository
     */
    private $repository;

    public function __construct() {
        $this->repository = new TemplateRepository();

        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register preview route.
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route('amk-schema', '/preview', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'preview_schema'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'preview_schema'],
                'permission_callback' => [$this, 'permissions_check'],
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
     * Build schema preview.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function preview_schema($request) {
        $data = $this->get_request_data($request);

        $template_id = $this->get_template_id($data, $request);
        $context     = $this->get_context($data, $request);

        if ($this->has_posted_schema($data, $request)) {
            $template = $this->build_template_from_request($data, $request);
        } else {
            $template = $this->load_template($template_id);
        }

        if (empty($template) || !is_array($template)) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('The schema template was not found or the submitted data is invalid.', 'amk-schema-core'),
                'json_ld' => null,
                'jsonld'  => null,
            ]);
        }

        $template = SchemaTemplateContract::normalize_template_row($template);

        $compiled_result = $this->compile_template($template, $context, $data);

        if (empty($compiled_result['schema']) || !is_array($compiled_result['schema'])) {
            return rest_ensure_response([
                'status'      => 'error',
                'message'     => __('Preview output was not generated. The JSON may be empty or all fields may have been removed after cleanup.', 'amk-schema-core'),
                'template_id' => $template_id,
                'context'     => $context,
                'json_ld'     => null,
                'jsonld'      => null,
                'resolver'    => $compiled_result['resolver'] ?? [],
            ]);
        }

        $validation = $this->validate_schema($compiled_result['schema']);

        return rest_ensure_response([
            'status'      => 'success',
            'message'     => __('Preview generated successfully.', 'amk-schema-core'),
            'template_id' => $template_id,
            'context'     => $context,
            'type'        => $template['type'] ?? 'custom',
            'scope'       => $template['scope'] ?? 'global',

            'json_ld'     => $compiled_result['schema'],
            'jsonld'      => $compiled_result['schema'],

            'resolver'    => $compiled_result['resolver'],
            'validation'  => $validation,
        ]);
    }

    /**
     * @param \WP_REST_Request $request
     * @return array
     */
    private function get_request_data($request) {
        $data = $request->get_json_params();

        if (!is_array($data)) {
            $data = $request->get_body_params();
        }

        if (!is_array($data)) {
            $data = [];
        }

        return $data;
    }

    /**
     * @param array            $data
     * @param \WP_REST_Request $request
     * @return int
     */
    private function get_template_id($data, $request) {
        if (isset($data['template_id'])) {
            return absint($data['template_id']);
        }

        if (isset($data['id'])) {
            return absint($data['id']);
        }

        return absint($request->get_param('template_id') ?: $request->get_param('id'));
    }

    /**
     * @param array            $data
     * @param \WP_REST_Request $request
     * @return string
     */
    private function get_context($data, $request) {
        if (!empty($data['context'])) {
            return SchemaTemplateContract::normalize_scope($data['context']);
        }

        if (!empty($data['scope'])) {
            return SchemaTemplateContract::normalize_scope($data['scope']);
        }

        $context = $request->get_param('context');

        if ($context) {
            return SchemaTemplateContract::normalize_scope($context);
        }

        $scope = $request->get_param('scope');

        return $scope ? SchemaTemplateContract::normalize_scope($scope) : 'default';
    }

    /**
     * @param array            $data
     * @param \WP_REST_Request $request
     * @return bool
     */
    private function has_posted_schema($data, $request) {
        if (array_key_exists('schema_json', $data)) {
            return true;
        }

        return $request->get_param('schema_json') !== null;
    }

    /**
     * @param array            $data
     * @param \WP_REST_Request $request
     * @return array
     */
    private function build_template_from_request($data, $request) {
        $template_id = $this->get_template_id($data, $request);

        $schema_json = $this->prepare_json_value($this->get_mixed_value($data, $request, 'schema_json', []));
        $bindings    = $this->prepare_json_value($this->get_mixed_value($data, $request, 'bindings', []));
        $conditions  = $this->prepare_json_value($this->get_mixed_value($data, $request, 'conditions', []));

        if ($schema_json === false) {
            $schema_json = [];
        }

        if ($bindings === false) {
            $bindings = [];
        }

        if ($conditions === false) {
            $conditions = [];
        }

        return [
            'id'          => $template_id,
            'name'        => isset($data['name']) ? sanitize_text_field(wp_unslash($data['name'])) : sanitize_text_field($request->get_param('name') ?: 'Preview Template'),
            'type'        => SchemaTemplateContract::normalize_type($this->get_mixed_value($data, $request, 'type', 'custom')),
            'scope'       => SchemaTemplateContract::normalize_scope($this->get_mixed_value($data, $request, 'scope', 'default')),
            'status'      => 'active',
            'schema_json' => $schema_json,
            'bindings'    => $bindings,
            'conditions'  => $conditions,
            'priority'    => isset($data['priority']) ? intval($data['priority']) : intval($request->get_param('priority') ?: 0),
            'override'    => !empty($data['override']) || !empty($request->get_param('override')) ? 1 : 0,
        ];
    }

    /**
     * @param array            $data
     * @param \WP_REST_Request $request
     * @param string           $key
     * @param mixed            $default
     * @return mixed
     */
    private function get_mixed_value($data, $request, $key, $default = []) {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        $value = $request->get_param($key);

        return $value !== null ? $value : $default;
    }

    /**
     * Decode JSON-like value.
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
     * @param int $template_id
     * @return array|null
     */
    private function load_template($template_id) {
        $template_id = absint($template_id);

        if (!$template_id) {
            return null;
        }

        return $this->repository->find($template_id);
    }

    /**
     * Resolve data and compile template.
     *
     * @param array  $template
     * @param string $context
     * @param array  $request_data
     * @return array
     */
    private function compile_template($template, $context, $request_data = []) {
        $resolver = new DataResolver();
        $data     = $resolver->resolve($context);

        $overrides = $this->extract_data_overrides($request_data);

        if (!empty($overrides)) {
            $data = array_merge($data, $overrides);
        }

        $compiler = new SchemaCompiler($data);
        $schema   = $compiler->compile($template);

        return [
            'schema'   => $schema,
            'resolver' => $this->prepare_resolver_preview($data),
        ];
    }

    /**
     * Optional preview-only resolver data overrides.
     *
     * @param array $request_data
     * @return array
     */
    private function extract_data_overrides($request_data) {
        if (empty($request_data['data_overrides']) || !is_array($request_data['data_overrides'])) {
            return [];
        }

        $overrides = [];

        foreach ($request_data['data_overrides'] as $key => $value) {
            if (!is_string($key) && !is_numeric($key)) {
                continue;
            }

            $key = sanitize_text_field((string) $key);

            if ($key === '') {
                continue;
            }

            $overrides[$key] = $value;
        }

        return $overrides;
    }

    /**
     * Keep resolver preview readable.
     *
     * @param array $data
     * @return array
     */
    private function prepare_resolver_preview($data) {
        if (!is_array($data)) {
            return [];
        }

        $preview = [];
        $limit   = 80;
        $count   = 0;

        foreach ($data as $key => $value) {
            if ($count >= $limit) {
                break;
            }

            if (!is_string($key) && !is_numeric($key)) {
                continue;
            }

            $preview[$key] = $this->compact_preview_value($value);
            $count++;
        }

        return $preview;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function compact_preview_value($value) {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return json_decode(json_encode($value), true);
        }

        if (is_string($value) && strlen($value) > 240) {
            return substr($value, 0, 240) . '...';
        }

        return $value;
    }

    /**
     * Validate compiled schema.
     *
     * @param array $schema
     * @return array
     */
    private function validate_schema($schema) {
        if (!class_exists(SchemaValidator::class)) {
            return [
                'valid'    => true,
                'errors'   => [],
                'warnings' => [],
            ];
        }

        $validator = new SchemaValidator();

        if (method_exists($validator, 'validate_with_report')) {
            $report = $validator->validate_with_report($schema);
        } else {
            $valid = $validator->validate($schema);

            $report = [
                'valid'    => $valid,
                'errors'   => method_exists($validator, 'get_errors') ? $validator->get_errors() : [],
                'warnings' => method_exists($validator, 'get_warnings') ? $validator->get_warnings() : [],
            ];
        }

        return [
            'valid'    => !empty($report['valid']),
            'errors'   => $this->normalize_validation_messages($report['errors'] ?? []),
            'warnings' => $this->normalize_validation_messages($report['warnings'] ?? []),
        ];
    }

    /**
     * @param array $messages
     * @return array
     */
    private function normalize_validation_messages($messages) {
        if (empty($messages) || !is_array($messages)) {
            return [];
        }

        $normalized = [];

        foreach ($messages as $message) {
            if (is_array($message)) {
                $path = isset($message['path']) ? $message['path'] : '';
                $text = isset($message['message']) ? $message['message'] : wp_json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $normalized[] = $path ? $path . ': ' . $text : $text;
                continue;
            }

            if (is_object($message)) {
                $normalized[] = wp_json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                continue;
            }

            $normalized[] = (string) $message;
        }

        return $normalized;
    }
}