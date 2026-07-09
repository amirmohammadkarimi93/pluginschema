<?php

namespace AMK\SchemaCore\Schema;

defined('ABSPATH') || exit;

class ConflictResolver {

    /**
     * Resolve conflicts and merge schemas
     *
     * @param array $schemas Array of compiled schemas
     * @return array Merged schemas
     */
    public function resolve(array $schemas) {

        if (empty($schemas)) {
            return [];
        }

        $final = [];
        $used_types = [];

        foreach ($schemas as $schema) {

            $type = $schema['type'] ?? 'default';

            // override rule
            if (!empty($schema['override'])) {
                $final = [$schema];
                $used_types = [$type];
                continue;
            }

            // If it was already used, merge or skip.
            if (in_array($type, $used_types)) {
                // Merge content.
                $final = $this->merge_schema($final, $schema);
                continue;
            }

            $used_types[] = $type;
            $final[] = $schema;
        }

        return $final;
    }

    /**
     * Merge two schemas
     *
     * @param array $final Current final schemas
     * @param array $new_schema New schema to merge
     * @return array
     */
    private function merge_schema(array $final, array $new_schema) {

        // Simplest merge: append nodes.
        $final[0] = array_merge_recursive($final[0], $new_schema);

        return $final;
    }
}