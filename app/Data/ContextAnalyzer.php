<?php

namespace AMK\SchemaCore\Data;

defined('ABSPATH') || exit;

class ContextAnalyzer {

    private $detector;

    public function __construct() {
        $this->detector = new ProductDetector();
    }

    public function analyze() {

        return [
            'type'   => $this->get_type(),
            'intent' => $this->get_intent(),
            'detail' => $this->get_detail(),
        ];
    }

    private function get_type() {

        if ($this->detector && method_exists($this->detector, 'is_product_context') && $this->detector->is_product_context()) {
            return 'product';
        }

        if (function_exists('is_product') && is_product()) {
            return 'product';
        }

        if (function_exists('is_shop') && is_shop()) {
            return 'shop';
        }

        if (function_exists('is_product_category') && is_product_category()) {
            return 'product_category';
        }

        if (function_exists('is_product_tag') && is_product_tag()) {
            return 'product_tag';
        }

        if (function_exists('is_front_page') && is_front_page()) {
            return 'home';
        }

        if (function_exists('is_home') && is_home()) {
            return 'blog_archive';
        }

        if (function_exists('is_singular') && is_singular('post')) {
            return 'single_post';
        }

        if (function_exists('is_category') && is_category()) {
            return 'category_archive';
        }

        if (function_exists('is_tag') && is_tag()) {
            return 'tag_archive';
        }

        if (function_exists('is_author') && is_author()) {
            return 'author_archive';
        }

        if (function_exists('is_search') && is_search()) {
            return 'search';
        }

        if (function_exists('is_404') && is_404()) {
            return '404';
        }

        if (function_exists('is_page') && is_page()) {
            return 'page';
        }

        if (function_exists('is_archive') && is_archive()) {
            return 'archive';
        }

        return 'unknown';
    }

    private function get_intent() {

        if (function_exists('is_product') && is_product()) {
            return 'transactional';
        }

        if (function_exists('is_shop') && is_shop()) {
            return 'commercial';
        }

        if (function_exists('is_product_category') && is_product_category()) {
            return 'commercial_navigation';
        }

        if (function_exists('is_product_tag') && is_product_tag()) {
            return 'commercial_navigation';
        }

        if (function_exists('is_search') && is_search()) {
            return 'search';
        }

        if (function_exists('is_singular') && is_singular('post')) {
            return 'informational';
        }

        if (function_exists('is_page') && is_page()) {
            return 'informational';
        }

        if (function_exists('is_archive') && is_archive()) {
            return 'navigation';
        }

        return 'informational';
    }

    private function get_detail() {

        if (!(function_exists('is_product') && is_product())) {
            return null;
        }

        $product = $this->get_current_product();

        if (!$product) {
            return null;
        }

        return [
            'product_id'     => method_exists($product, 'get_id') ? $product->get_id() : 0,
            'product_type'   => method_exists($product, 'get_type') ? $product->get_type() : '',
            'is_on_sale'     => method_exists($product, 'is_on_sale') ? $product->is_on_sale() : false,
            'is_in_stock'    => method_exists($product, 'is_in_stock') ? $product->is_in_stock() : false,
            'has_variations' => method_exists($product, 'is_type') ? $product->is_type('variable') : false,
        ];
    }

    private function get_current_product() {

        if (!function_exists('wc_get_product')) {
            return null;
        }

        global $product;

        if (is_object($product) && method_exists($product, 'get_id')) {
            return $product;
        }

        $post_id = function_exists('get_the_ID') ? get_the_ID() : 0;

        if (!$post_id) {
            return null;
        }

        $product = wc_get_product($post_id);

        return $product ?: null;
    }
}