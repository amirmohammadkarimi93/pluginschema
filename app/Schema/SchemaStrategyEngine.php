<?php

namespace AMK\SchemaCore\Schema;

defined('ABSPATH') || exit;

use AMK\SchemaCore\Schema\ConflictResolver;

class SchemaStrategyEngine {

    private $context;

    public function __construct($context = []) {
        $this->context = $context;
    }

    /**
     * Resolve schemas based on context, priority, and override rules
     *
     * @param array $schemas
     * @return array
     */
    public function resolve($schemas) {

        if (empty($schemas)) {
            return [];
        }

        // 1. Score calculation (safe mutation-free version)
        foreach ($schemas as $key => $schema) {
            $schemas[$key]['score'] = $this->calculate_score($schema);
        }

        // 2. Sort by score descending
        usort($schemas, function ($a, $b) {
            return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
        });

        // 3. Conflict resolution layer
        $resolver = new ConflictResolver();
        return $resolver->resolve($schemas);
    }

    /**
     * Calculate schema score based on context and business rules
     *
     * @param array $schema
     * @return int
     */
    private function calculate_score($schema) {

        $score = 0;

        $type = $this->context['type'] ?? 'default';
        $detail = $this->context['detail'] ?? [];

        // Scope match bonus
        if (($schema['scope'] ?? '') === $type) {
            $score += 50;
        }

        // Variable product bonus
        if (!empty($detail['has_variations']) && ($schema['type'] ?? '') === 'ProductVariable') {
            $score += 30;
        }

        // On sale bonus
        if (!empty($detail['is_on_sale']) && ($schema['type'] ?? '') === 'ProductSale') {
            $score += 40;
        }

        // In stock bonus
        if (!empty($detail['is_in_stock'])) {
            $score += 10;
        }

        // Manual priority override
        $score += intval($schema['priority'] ?? 0);

        return $score;
    }
}