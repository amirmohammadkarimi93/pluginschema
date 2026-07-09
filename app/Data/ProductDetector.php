<?php

namespace AMK\SchemaCore\Data;

defined('ABSPATH') || exit;

class ProductDetector {

    public function is_product_context() {

        // 1. Standard WooCommerce case.
        if (function_exists('is_product') && is_product()) {
            return true;
        }

        // 2. Check global product; some builders provide it.
        global $product;

        if ($product && is_object($product) && method_exists($product, 'get_id')) {
            return true;
        }

        // 3. Check WooCommerce query var.
        if (get_query_var('product')) {
            return true;
        }

        return false;
    }

    public function get_product_id() {

        global $product;

        if ($product && is_object($product)) {
            return $product->get_id();
        }

        if (get_query_var('product')) {
            return absint(get_query_var('product'));
        }

        if (is_singular('product')) {
            return get_the_ID();
        }

        return null;
    }

    public function get_context_type() {

        if ($this->is_product_context()) {
            return 'product';
        }

        return null;
    }
}