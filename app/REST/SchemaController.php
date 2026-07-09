<?php

namespace AMK\SchemaCore\REST;

defined('ABSPATH') || exit;

use AMK\SchemaCore\Data\DataResolver;
use AMK\SchemaCore\Schema\SchemaCompiler;

class SchemaController {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {

        register_rest_route('amk-schema', '/schema', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_schema'],
            'permission_callback' => [$this, 'permissions_check'],
        ]);

        register_rest_route('amk-schema', '/schemas', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_schemas'],
            'permission_callback' => [$this, 'permissions_check'],
        ]);
    }

    public function permissions_check() {
        return current_user_can('manage_options');
    }

    public function get_schema($request) {

        $template_id = absint($request->get_param('template_id'));
        $context     = sanitize_key($request->get_param('context') ?: 'default');

        if ($template_id > 0) {
            $template = $this->load_template_by_id($template_id);
        } else {
            $template = $this->load_best_template_by_context($context);
        }

        if (empty($template)) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('No schema templates were found.', 'amk-schema-core'),
                'schema'  => null,
            ]);
        }

        $compiled = $this->compile_template($template, $context);

        if (empty($compiled)) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('JSON-LD output was not generated.', 'amk-schema-core'),
                'schema'  => null,
            ]);
        }

        return rest_ensure_response([
            'status'  => 'success',
            'context' => $context,
            'schema'  => $compiled,
        ]);
    }

    public function get_schemas($request) {

        $context = sanitize_key($request->get_param('context') ?: 'default');

        $templates = $this->load_templates_by_context($context);

        if (empty($templates)) {
            return rest_ensure_response([
                'status'  => 'success',
                'context' => $context,
                'schemas' => [],
            ]);
        }

        $schemas = [];

        foreach ($templates as $template) {
            $compiled = $this->compile_template($template, $context);

            if (!empty($compiled)) {
                $schemas[] = $compiled;
            }
        }

        return rest_ensure_response([
            'status'  => 'success',
            'context' => $context,
            'schemas' => $schemas,
        ]);
    }

    private function load_template_by_id($template_id) {

        global $wpdb;

        $table = $wpdb->prefix . 'amk_schema_templates';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d LIMIT 1",
                $template_id
            ),
            ARRAY_A
        );

        return $row ? $this->parse_template($row) : [];
    }

    private function load_best_template_by_context($context) {

        global $wpdb;

        $table = $wpdb->prefix . 'amk_schema_templates';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table
                 WHERE status = %s
                 AND (scope = %s OR scope = %s)
                 ORDER BY priority DESC, id DESC
                 LIMIT 1",
                'active',
                $context,
                'global'
            ),
            ARRAY_A
        );

        return $row ? $this->parse_template($row) : [];
    }

    private function load_templates_by_context($context) {

        global $wpdb;

        $table = $wpdb->prefix . 'amk_schema_templates';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table
                 WHERE status = %s
                 AND (scope = %s OR scope = %s)
                 ORDER BY priority DESC, id DESC",
                'active',
                $context,
                'global'
            ),
            ARRAY_A
        );

        if (empty($rows) || !is_array($rows)) {
            return [];
        }

        return array_map([$this, 'parse_template'], $rows);
    }

    private function parse_template($row) {

        return [
            'id'          => isset($row['id']) ? absint($row['id']) : 0,
            'name'        => isset($row['name']) ? sanitize_text_field($row['name']) : '',
            'type'        => isset($row['type']) ? sanitize_key($row['type']) : 'custom',
            'scope'       => isset($row['scope']) ? sanitize_key($row['scope']) : 'global',
            'status'      => isset($row['status']) ? sanitize_key($row['status']) : 'active',
            'schema_json' => $this->decode_json($row['schema_json'] ?? ''),
            'bindings'    => $this->decode_json($row['bindings'] ?? ''),
            'conditions'  => $this->decode_json($row['conditions'] ?? ''),
            'priority'    => isset($row['priority']) ? intval($row['priority']) : 0,
            'override'    => !empty($row['override']) ? 1 : 0,
        ];
    }

    private function compile_template($template, $context) {

        if (empty($template) || !is_array($template)) {
            return [];
        }

        $resolver = new DataResolver();
        $data     = $resolver->resolve($context);

        $compiler = new SchemaCompiler($data);

        return $compiler->compile($template);
    }

    private function decode_json($value) {

        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}