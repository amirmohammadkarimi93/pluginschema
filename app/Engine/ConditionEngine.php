<?php

namespace AMK\SchemaCore\Engine;

defined('ABSPATH') || exit;

class ConditionEngine {

    private $data = [];

    public function __construct($data = []) {
        $this->data = is_array($data) ? $data : [];
    }

    public function apply($schema, $conditions) {

        if (!is_array($schema) || empty($conditions) || !is_array($conditions)) {
            return $schema;
        }

        foreach ($conditions as $condition) {

            if (!is_array($condition)) {
                continue;
            }

            $schema = $this->evaluate_condition($schema, $condition);
        }

        return $schema;
    }

    private function evaluate_condition($schema, $condition) {

        $field    = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? null;
        $value    = $condition['value'] ?? null;
        $action   = $condition['action'] ?? null;
        $path     = $condition['path'] ?? null;
        $payload  = $condition['payload'] ?? null;

        if (!$field || !$operator || !$action) {
            return $schema;
        }

        $data_value = $this->get_data_value($field);

        $match = $this->compare($data_value, $operator, $value);

        if (!$match) {
            return $schema;
        }

        return $this->execute_action($schema, $action, $path, $payload);
    }

    private function compare($data_value, $operator, $value) {

        switch ($operator) {

            case 'exists':
                return !$this->is_empty($data_value);

            case 'not_exists':
                return $this->is_empty($data_value);

            case 'equals':
                return $data_value == $value;

            case 'not_equals':
                return $data_value != $value;

            case 'greater_than':
                return is_numeric($data_value) && is_numeric($value) && $data_value > $value;

            case 'less_than':
                return is_numeric($data_value) && is_numeric($value) && $data_value < $value;

            default:
                return false;
        }
    }

    private function execute_action($schema, $action, $path, $payload) {

        $payload = $this->maybe_decode_json($payload);

        switch ($action) {

            case 'remove':
                return $this->remove_path($schema, $path);

            case 'add':
                return $this->add_path($schema, $path, $payload);

            case 'set':
                return $this->set_value($schema, $path, $payload);

            default:
                return $schema;
        }
    }

    private function remove_path($schema, $path) {

        if (!$path || !is_array($schema)) {
            return $schema;
        }

        $segments = explode('.', $path);
        $ref =& $schema;

        foreach ($segments as $index => $segment) {

            if (!is_array($ref) || !array_key_exists($segment, $ref)) {
                return $schema;
            }

            if ($index === count($segments) - 1) {
                unset($ref[$segment]);
                return $schema;
            }

            $ref =& $ref[$segment];
        }

        return $schema;
    }

    private function add_path($schema, $path, $payload) {

        if (!$path || !is_array($schema)) {
            return $schema;
        }

        $segments = explode('.', $path);
        $ref =& $schema;

        foreach ($segments as $segment) {

            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }

            $ref =& $ref[$segment];
        }

        $ref = $payload;

        return $schema;
    }

    private function set_value($schema, $path, $payload) {

        if (!$path || !is_array($schema)) {
            return $schema;
        }

        $segments = explode('.', $path);
        $ref =& $schema;

        foreach ($segments as $index => $segment) {

            if (!is_array($ref) || !array_key_exists($segment, $ref)) {
                return $schema;
            }

            if ($index === count($segments) - 1) {
                $ref[$segment] = $payload;
                return $schema;
            }

            $ref =& $ref[$segment];
        }

        return $schema;
    }

    private function get_data_value($field) {

        if (!$field) {
            return null;
        }

        if (array_key_exists($field, $this->data)) {
            return $this->data[$field];
        }

        $segments = explode('.', $field);
        $value = $this->data;

        foreach ($segments as $segment) {

            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    private function maybe_decode_json($value) {

        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $first = substr($value, 0, 1);

        if ($first !== '{' && $first !== '[') {
            return $value;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $value;
        }

        return $decoded;
    }

    private function is_empty($value) {

        return $value === null || $value === '' || $value === [];
    }
}