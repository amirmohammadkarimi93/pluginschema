<?php

namespace AMK\SchemaCore\REST;

defined('ABSPATH') || exit;

class PriorityController {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {

        register_rest_route('amk-schema', '/priority', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'save_priority'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);
    }

    public function permissions_check() {
        return current_user_can('manage_options');
    }

    public function save_priority($request) {

        global $wpdb;

        $params = $request->get_json_params();

        if (!is_array($params)) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('The submitted data is invalid.', 'amk-schema-core'),
            ]);
        }

        $items = isset($params['items']) && is_array($params['items']) ? $params['items'] : [];

        if (empty($items)) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('No items were submitted for saving priorities.', 'amk-schema-core'),
            ]);
        }

        $table = $wpdb->prefix . 'amk_schema_templates';

        if (!$this->table_exists($table)) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => __('The schema templates table does not exist.', 'amk-schema-core'),
            ]);
        }

        $updated_count = 0;
        $skipped_count = 0;

        foreach ($items as $item) {

            if (!is_array($item)) {
                $skipped_count++;
                continue;
            }

            $id = isset($item['id']) ? absint($item['id']) : 0;

            if (!$id) {
                $skipped_count++;
                continue;
            }

            $priority = isset($item['priority']) ? intval($item['priority']) : 0;
            $override = !empty($item['override']) ? 1 : 0;

            $result = $wpdb->update(
                $table,
                [
                    'priority'   => $priority,
                    'override'   => $override,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $id],
                ['%d', '%d', '%s'],
                ['%d']
            );

            if ($result === false) {
                $skipped_count++;
                continue;
            }

            $updated_count++;
        }

        return rest_ensure_response([
            'status'        => 'success',
            'message'       => __('Priorities saved successfully.', 'amk-schema-core'),
            'updated_count' => $updated_count,
            'skipped_count' => $skipped_count,
        ]);
    }

    private function table_exists($table) {

        global $wpdb;

        $found = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table
            )
        );

        return $found === $table;
    }
}
