<?php

namespace AMK\SchemaCore\Schema;

use AMK\SchemaCore\Data\BindingResolver;

defined('ABSPATH') || exit;

class SchemaCompiler {

    /**
     * Data resolved from DataResolver.
     *
     * @var array
     */
    private $data = [];

    /**
     * Template conditions.
     *
     * @var array
     */
    private $conditions = [];

    /**
     * Placeholder bindings.
     *
     * @var array
     */
    private $bindings = [];

    /**
     * Current template metadata used only for filters/debug hooks.
     *
     * @var array
     */
    private $template_meta = [];

    /**
     * @param array $data
     * @param array $conditions
     */
    public function __construct($data = [], $conditions = []) {
        $this->data       = is_array($data) ? $data : [];
        $this->conditions = is_array($conditions) ? $conditions : [];
    }

    /**
     * Set resolver data.
     *
     * @param array $data
     * @return $this
     */
    public function set_data($data) {
        $this->data = is_array($data) ? $data : [];

        return $this;
    }

    /**
     * Set template conditions.
     *
     * @param array $conditions
     * @return $this
     */
    public function set_conditions($conditions) {
        $this->conditions = is_array($conditions) ? $conditions : [];

        return $this;
    }

    /**
     * Compile a schema template into final JSON-LD array.
     *
     * Accepts either:
     * - raw schema JSON string
     * - decoded schema array
     * - DB template row containing schema_json, bindings, conditions
     *
     * @param mixed      $schema
     * @param array|null $data
     * @param array|null $conditions
     * @return array|null
     */
    public function compile($schema, $data = null, $conditions = null) {
        if (is_array($data)) {
            $this->data = $data;
        }

        if (is_array($conditions)) {
            $this->conditions = $conditions;
        }

        $schema_json       = $schema;
        $bindings          = [];
        $this->template_meta = [];

        if (is_object($schema)) {
            $schema = json_decode(json_encode($schema), true);
        }

        if (is_array($schema) && $this->is_assoc($schema) && array_key_exists('schema_json', $schema)) {
            $schema_json = $schema['schema_json'];
            $bindings    = isset($schema['bindings']) ? $schema['bindings'] : [];

            $this->template_meta = $this->extract_template_meta($schema);

            if (!is_array($conditions) && isset($schema['conditions'])) {
                $this->conditions = $this->decode_json_field($schema['conditions']);
            }
        }

        /**
         * Fires before a schema template is decoded and compiled.
         *
         * Useful for debugging why a specific template, especially Product,
         * is not rendered on the frontend.
         *
         * @param mixed $schema_json
         * @param array $data
         * @param array $template_meta
         */
        do_action(
            'amk_schema_core_schema_compiler_before_compile',
            $schema_json,
            $this->data,
            $this->template_meta
        );

        $json = $this->decode_json_field($schema_json);

        if (empty($json) || !is_array($json)) {
            do_action(
                'amk_schema_core_schema_compiler_decode_failed',
                $schema_json,
                $this->data,
                $this->template_meta
            );

            return null;
        }

        $this->bindings = $this->decode_json_field($bindings);

        if (!is_array($this->bindings)) {
            $this->bindings = [];
        }

        $json = $this->apply_bindings($json, $this->bindings);

        do_action(
            'amk_schema_core_schema_compiler_after_bindings',
            $json,
            $this->data,
            $this->bindings,
            $this->template_meta
        );

        $json = $this->apply_conditions($json, $this->conditions);

        do_action(
            'amk_schema_core_schema_compiler_after_conditions',
            $json,
            $this->data,
            $this->conditions,
            $this->template_meta
        );

        $json = $this->cleanup_schema($json, true);

        do_action(
            'amk_schema_core_schema_compiler_after_cleanup',
            $json,
            $this->data,
            $this->template_meta
        );

        if (!$this->is_renderable_root_schema($json)) {
            do_action(
                'amk_schema_core_schema_compiler_non_renderable',
                $json,
                $this->data,
                $this->template_meta
            );

            return null;
        }

        /**
         * Filter compiled schema before SchemaManager receives it.
         *
         * @param array $json
         * @param array $data
         * @param array $template_meta
         */
        $json = apply_filters(
            'amk_schema_core_compiled_schema',
            $json,
            $this->data,
            $this->template_meta
        );

        return $this->is_renderable_root_schema($json) ? $json : null;
    }

    /**
     * Decode a JSON field safely.
     *
     * @param mixed $value
     * @return mixed
     */
    public function decode_json_field($value) {
        if (is_object($value)) {
            return json_decode(json_encode($value), true);
        }

        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return [];
        }

        $value = trim($value);

        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return $decoded;
    }

    /**
     * Apply placeholder bindings and resolver data.
     *
     * @param mixed $node
     * @param array $bindings
     * @return mixed
     */
    public function apply_bindings($node, $bindings) {
        $this->bindings = is_array($bindings) ? $bindings : [];

        return $this->replace_placeholders($node, $this->bindings);
    }

    /**
     * Replace placeholders recursively.
     *
     * Important:
     * If the entire value is one placeholder and resolver value is array/object,
     * return the array/object itself, not a string.
     *
     * @param mixed $node
     * @param array $bindings
     * @return mixed
     */
    public function replace_placeholders($node, $bindings = []) {
        if (is_object($node)) {
            $node = json_decode(json_encode($node), true);
        }

        if (is_string($node)) {
            $single_key = $this->get_single_placeholder_key($node);

            if ($single_key !== null) {
                $value = $this->get_data_value($single_key);

                if ($value === null) {
                    return $node;
                }

                return $value;
            }

            return preg_replace_callback('/\{\{\s*([^}]+)\s*\}\}/', function ($matches) {
                $key   = isset($matches[1]) ? trim($matches[1]) : '';
                $value = $this->get_data_value($key);

                if ($value === null || is_array($value) || is_object($value)) {
                    return isset($matches[0]) ? $matches[0] : '';
                }

                if (is_bool($value)) {
                    return $value ? '1' : '0';
                }

                return (string) $value;
            }, $node);
        }

        if (is_array($node)) {
            foreach ($node as $key => $value) {
                $node[$key] = $this->replace_placeholders($value, $bindings);
            }
        }

        return $node;
    }

    /**
     * Return placeholder key if the string is exactly one placeholder.
     *
     * @param string $value
     * @return string|null
     */
    public function get_single_placeholder_key($value) {
        if (!is_string($value)) {
            return null;
        }

        if (preg_match('/^\s*\{\{\s*([^}]+)\s*\}\}\s*$/', $value, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Resolve a data value with optional bindings.
     *
     * Binding examples supported:
     * - "headline": "title"
     * - "logo": {"data_key":"organization_logo"}
     * - "x": {"value":"static value"}
     * - "custom_gtin": {"source":"product_meta","key":"_gtin"}
     * - "custom_brand": {"source":"product_attribute","key":"pa_brand"}
     * - "custom_option": {"source":"option","key":"my_option_name"}
     *
     * @param string $data_key
     * @return mixed|null
     */
    public function get_data_value($data_key) {
        $data_key = $this->normalize_placeholder_key($data_key);

        if ($data_key === '') {
            return null;
        }

        if (is_array($this->bindings) && array_key_exists($data_key, $this->bindings)) {
            $binding = $this->bindings[$data_key];

            if (class_exists(BindingResolver::class)) {
                $binding_resolver = new BindingResolver($this->data);
                $resolved         = $binding_resolver->resolve($binding, $data_key);

                if ($resolved !== null) {
                    return $resolved;
                }
            }

            // Backward-compatible fallback.
            if (is_string($binding)) {
                return $this->get_nested_data_value($binding);
            }

            if (is_array($binding)) {
                if (array_key_exists('value', $binding)) {
                    return $binding['value'];
                }

                if (isset($binding['data_key'])) {
                    return $this->get_nested_data_value($binding['data_key']);
                }

                if (isset($binding['path'])) {
                    return $this->get_nested_data_value($binding['path']);
                }
            }
        }

        return $this->get_nested_data_value($data_key);
    }

    /**
     * Resolve nested data with dot notation.
     *
     * Direct keys are checked first because resolver keys like organization_address
     * are flat keys, not paths.
     *
     * @param string $path
     * @return mixed|null
     */
    public function get_nested_data_value($path) {
        $path = $this->normalize_placeholder_key($path);

        if ($path === '') {
            return null;
        }

        if (is_array($this->data) && array_key_exists($path, $this->data)) {
            return $this->data[$path];
        }

        $path = preg_replace('/\[(\w+)\]/', '.$1', $path);
        $path = trim($path, '.');

        if ($path === '') {
            return null;
        }

        $segments = explode('.', $path);
        $current  = $this->data;

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            if (is_object($current)) {
                $current = json_decode(json_encode($current), true);
            }

            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
                continue;
            }

            return null;
        }

        return $current;
    }

    /**
     * Apply conditions to compiled schema.
     *
     * Notes:
     * - Conditions may be stored as JSON or array.
     * - Inactive/disabled conditions are ignored.
     * - Lower priority runs first.
     * - This method must work with array placeholders such as product_offer,
     *   product_variants, brand_schema and webpage_main_entity.
     *
     * @param mixed $json
     * @param array $conditions
     * @return mixed
     */
    public function apply_conditions($json, $conditions) {
        $conditions = $this->decode_json_field($conditions);

        if (empty($conditions) || !is_array($conditions)) {
            return $json;
        }

        $conditions = $this->normalize_conditions($conditions);

        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }

            if (!$this->condition_is_active($condition)) {
                continue;
            }

            $data_key = $this->array_get_first($condition, ['data_key', 'field', 'key', 'source', 'source_key']);
            $operator = $this->array_get_first($condition, ['operator', 'compare'], 'empty');
            $expected = $this->array_get_first($condition, ['expected', 'value', 'compare_value'], null);
            $action   = $this->array_get_first($condition, ['action'], 'remove');
            $path     = $this->array_get_first($condition, ['path', 'target_path', 'schema_path'], '');

            if (!is_string($data_key) && !is_numeric($data_key)) {
                continue;
            }

            $data_key = $this->normalize_placeholder_key($data_key);

            if ($data_key === '') {
                continue;
            }

            $value = $this->get_data_value($data_key);

            if (!$this->condition_matches($value, $operator, $expected)) {
                continue;
            }

            $action = is_string($action) ? trim($action) : 'remove';

            if (
                $action === 'remove' ||
                $action === 'remove_path' ||
                $action === 'unset' ||
                $action === 'delete'
            ) {
                $json = $this->remove_path($json, $path);
            }
        }

        return $json;
    }

    /**
     * Normalize and sort condition rows.
     *
     * @param mixed $conditions
     * @return array
     */
    private function normalize_conditions($conditions) {
        if (is_object($conditions)) {
            $conditions = json_decode(json_encode($conditions), true);
        }

        if (!is_array($conditions)) {
            return [];
        }

        $normalized = [];

        foreach ($conditions as $condition) {
            if (is_object($condition)) {
                $condition = json_decode(json_encode($condition), true);
            }

            if (is_array($condition)) {
                $normalized[] = $condition;
            }
        }

        usort($normalized, function ($a, $b) {
            $priority_a = isset($a['priority']) ? intval($a['priority']) : 10;
            $priority_b = isset($b['priority']) ? intval($b['priority']) : 10;

            if ($priority_a !== $priority_b) {
                return $priority_a <=> $priority_b;
            }

            return 0;
        });

        return $normalized;
    }

    /**
     * Check whether a condition row should run.
     *
     * @param array $condition
     * @return bool
     */
    private function condition_is_active($condition) {
        if (!is_array($condition)) {
            return false;
        }

        if (array_key_exists('enabled', $condition)) {
            return (bool) $condition['enabled'];
        }

        if (array_key_exists('is_active', $condition)) {
            return (bool) $condition['is_active'];
        }

        if (array_key_exists('status', $condition)) {
            $status = strtolower(trim((string) $condition['status']));

            if (in_array($status, ['inactive', 'disabled', 'draft', '0', 'false', 'off'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Match a condition against a value.
     *
     * @param mixed       $value
     * @param string      $operator
     * @param string|null $expected
     * @return bool
     */
    public function condition_matches($value, $operator, $expected = null) {
        $operator = is_string($operator) ? trim($operator) : 'empty';

        switch ($operator) {
            case 'empty':
            case 'is_empty':
                return $this->is_empty_value($value);

            case 'not_empty':
            case 'is_not_empty':
            case 'exists':
                return !$this->is_empty_value($value);

            case 'not_exists':
                return $this->is_empty_value($value);

            case 'equals':
            case '=':
            case '==':
                return $this->compare_values($value, $expected) === 0;

            case 'not_equals':
            case '!=':
            case '!==':
                return $this->compare_values($value, $expected) !== 0;

            case 'contains':
                return $this->value_contains($value, $expected);

            case 'not_contains':
                return !$this->value_contains($value, $expected);

            case 'greater_than':
            case '>':
                return is_numeric($value) && is_numeric($expected) && (float) $value > (float) $expected;

            case 'less_than':
            case '<':
                return is_numeric($value) && is_numeric($expected) && (float) $value < (float) $expected;

            case 'greater_or_equal':
            case '>=':
                return is_numeric($value) && is_numeric($expected) && (float) $value >= (float) $expected;

            case 'less_or_equal':
            case '<=':
                return is_numeric($value) && is_numeric($expected) && (float) $value <= (float) $expected;

            case 'in':
                $expected_values = is_array($expected) ? $expected : array_map('trim', explode(',', (string) $expected));
                return in_array($this->scalar_to_string($value), array_map([$this, 'scalar_to_string'], $expected_values), true);

            case 'not_in':
                $expected_values = is_array($expected) ? $expected : array_map('trim', explode(',', (string) $expected));
                return !in_array($this->scalar_to_string($value), array_map([$this, 'scalar_to_string'], $expected_values), true);
        }

        return false;
    }

    /**
     * Compare two scalar/array values as stable strings.
     *
     * @param mixed $value
     * @param mixed $expected
     * @return int
     */
    private function compare_values($value, $expected) {
        return strcmp($this->scalar_to_string($value), $this->scalar_to_string($expected));
    }

    /**
     * Check contains for string and array values.
     *
     * @param mixed $value
     * @param mixed $expected
     * @return bool
     */
    private function value_contains($value, $expected) {
        if (is_array($value)) {
            $expected_string = $this->scalar_to_string($expected);

            foreach ($value as $item) {
                if ($this->scalar_to_string($item) === $expected_string) {
                    return true;
                }
            }

            return false;
        }

        return strpos($this->scalar_to_string($value), $this->scalar_to_string($expected)) !== false;
    }

    /**
     * Convert scalar/array values to a stable string for comparisons.
     *
     * @param mixed $value
     * @return string
     */
    private function scalar_to_string($value) {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return '';
        }

        if (is_array($value) || is_object($value)) {
            $encoded = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded ? $encoded : '';
        }

        return (string) $value;
    }

    /**
     * Remove a dot path from schema.
     *
     * Examples:
     * - aggregateRating
     * - offers.price
     * - @graph.0.aggregateRating
     *
     * @param mixed  $json
     * @param string $path
     * @return mixed
     */
    public function remove_path($json, $path) {
        if (!is_string($path) || trim($path) === '') {
            return $json;
        }

        $path = trim($path);
        $path = preg_replace('/^\$\.?/', '', $path);
        $path = preg_replace('/\[(\w+)\]/', '.$1', $path);
        $path = trim($path, '.');

        if ($path === '') {
            return $json;
        }

        $segments = explode('.', $path);
        $this->remove_path_segments($json, $segments);

        return $json;
    }

    /**
     * Cleanup empty values, unresolved placeholders, and type-only objects.
     *
     * @param mixed $node
     * @param bool  $is_root
     * @return mixed
     */
    public function cleanup_schema($node, $is_root = false) {
        if (is_object($node)) {
            $node = json_decode(json_encode($node), true);
        }

        if (is_string($node)) {
            $node = trim($node);

            if ($node === '' || $this->contains_unresolved_placeholder($node)) {
                return null;
            }

            return $node;
        }

        if (!is_array($node)) {
            return $node;
        }

        if (!$this->is_assoc($node)) {
            $clean = [];

            foreach ($node as $item) {
                $item = $this->cleanup_schema($item, false);

                if (!$this->is_empty_value($item)) {
                    $clean[] = $item;
                }
            }

            return $clean;
        }

        foreach ($node as $key => $value) {
            $cleaned = $this->cleanup_schema($value, false);

            if ($this->is_empty_value($cleaned)) {
                unset($node[$key]);
                continue;
            }

            $node[$key] = $cleaned;
        }

        if (!$is_root && $this->is_type_only_object($node)) {
            return null;
        }

        if (!$is_root && empty($node)) {
            return null;
        }

        return $node;
    }


    /**
     * Remove invalid generic Country nodes.
     *
     * @param array $node
     * @return array
     */
    private function remove_invalid_schema_country_values($node) {

        if (
            isset($node['@type']) &&
            $node['@type'] === 'Country' &&
            isset($node['name'])
        ) {

            $invalid = [
                'worldwide',
                'world wide',
                'all countries',
                'global',
            ];

            if (
                in_array(
                    strtolower(trim((string) $node['name'])),
                    $invalid,
                    true
                )
            ) {
                return [];
            }
        }

        foreach ($node as $key => $value) {

            if (is_array($value)) {
                $node[$key] = $this->remove_invalid_schema_country_values($value);
            }

        }

        return $node;
    }


    /**
     * Check object has only @type after cleanup.
     *
     * @param mixed $node
     * @return bool
     */
    public function is_type_only_object($node) {
        if (!is_array($node) || !$this->is_assoc($node)) {
            return false;
        }

        $keys = array_keys($node);

        return count($keys) === 1 && $keys[0] === '@type';
    }

    /**
     * Check unresolved placeholder in string.
     *
     * @param mixed $value
     * @return bool
     */
    public function contains_unresolved_placeholder($value) {
        if (!is_string($value)) {
            return false;
        }

        return preg_match('/\{\{\s*[^}]+\s*\}\}/', $value) === 1;
    }

    /**
     * Empty value definition for schema cleanup.
     *
     * Important: false and 0 are valid values and must not be removed.
     *
     * @param mixed $value
     * @return bool
     */
    public function is_empty_value($value) {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '' || $this->contains_unresolved_placeholder($value);
        }

        if (is_array($value)) {
            return empty($value);
        }

        return false;
    }

    /**
     * Remove path recursively by reference.
     *
     * @param mixed $node
     * @param array $segments
     * @return void
     */
    private function remove_path_segments(&$node, $segments) {
        if (empty($segments)) {
            return;
        }

        if (is_object($node)) {
            $node = json_decode(json_encode($node), true);
        }

        if (!is_array($node)) {
            return;
        }

        $segment = array_shift($segments);

        if ($segment === '*') {
            foreach ($node as &$child) {
                if (empty($segments)) {
                    $child = null;
                } else {
                    $this->remove_path_segments($child, $segments);
                }
            }
            unset($child);
            return;
        }

        if (empty($segments)) {
            if (array_key_exists($segment, $node)) {
                unset($node[$segment]);
                return;
            }

            if (is_numeric($segment)) {
                $index = (int) $segment;

                if (array_key_exists($index, $node)) {
                    unset($node[$index]);
                    $node = array_values($node);
                }
            }

            return;
        }

        if (array_key_exists($segment, $node)) {
            $this->remove_path_segments($node[$segment], $segments);
            return;
        }

        if (is_numeric($segment)) {
            $index = (int) $segment;

            if (array_key_exists($index, $node)) {
                $this->remove_path_segments($node[$index], $segments);
            }
        }
    }

    /**
     * Get the first available value from array keys.
     *
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
     * Normalize placeholder key.
     *
     * @param string $key
     * @return string
     */
    private function normalize_placeholder_key($key) {
        if (!is_string($key) && !is_numeric($key)) {
            return '';
        }

        $key = trim((string) $key);
        $key = preg_replace('/^\{\{\s*/', '', $key);
        $key = preg_replace('/\s*\}\}$/', '', $key);

        return trim($key);
    }

    /**
     * Extract safe template metadata for debug hooks and filters.
     *
     * @param array $template
     * @return array
     */
    private function extract_template_meta($template) {
        if (!is_array($template)) {
            return [];
        }

        return [
            'id'       => isset($template['id']) ? absint($template['id']) : 0,
            'name'     => isset($template['name']) ? (string) $template['name'] : '',
            'type'     => isset($template['type']) ? (string) $template['type'] : '',
            'scope'    => isset($template['scope']) ? (string) $template['scope'] : '',
            'status'   => isset($template['status']) ? (string) $template['status'] : '',
            'priority' => isset($template['priority']) ? intval($template['priority']) : 0,
        ];
    }

    /**
     * Check whether the compiled root schema is safe to render.
     *
     * A root schema must either:
     * - be a non-empty @graph container
     * - have a valid @type
     *
     * This prevents broken root nodes from passing silently after conditions
     * remove required properties such as @type.
     *
     * @param mixed $schema
     * @return bool
     */
    private function is_renderable_root_schema($schema) {
        if (empty($schema) || !is_array($schema)) {
            return false;
        }

        if (isset($schema['@graph']) && is_array($schema['@graph'])) {
            return !empty($schema['@graph']);
        }

        if (empty($schema['@type'])) {
            return false;
        }

        if (is_string($schema['@type'])) {
            return trim($schema['@type']) !== '' && !$this->contains_unresolved_placeholder($schema['@type']);
        }

        if (is_array($schema['@type'])) {
            return !empty($schema['@type']);
        }

        return false;
    }

    /**
     * Check associative array.
     *
     * @param mixed $array
     * @return bool
     */
    private function is_assoc($array) {
        if (!is_array($array)) {
            return false;
        }

        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
