<?php

namespace AMK\SchemaCore\Data;

defined('ABSPATH') || exit;

class WooCommerceAdapter {

    public function is_available() {
        return function_exists('wc_get_product');
    }

    public function is_product_context() {
        return function_exists('is_product') && is_product();
    }

    public function get_current_product() {

        if (!$this->is_available()) {
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

    public function get_product_data($product = null) {

        if (!$product) {
            $product = $this->get_current_product();
        }

        if (!$product || !method_exists($product, 'get_id')) {
            return [];
        }

        $product_id = $product->get_id();

        $brand = $this->get_brand($product);
        $variation_data = $this->get_variation_data($product);

        return [
            'id'                => $product_id,
            'name'              => $this->safe_call($product, 'get_name'),
            'title'             => $this->safe_call($product, 'get_name'),
            'description'       => wp_strip_all_tags($this->safe_call($product, 'get_description')),
            'short_description' => wp_strip_all_tags($this->safe_call($product, 'get_short_description')),
            'url'               => get_permalink($product_id),
            'image'             => $this->get_image($product),

            'sku'               => $this->safe_call($product, 'get_sku'),
            'gtin'              => $this->get_identifier($product, 'gtin'),
            'mpn'               => $this->get_identifier($product, 'mpn'),

            'brand_name'        => $brand['name'],
            'brand_url'         => $brand['url'],

            'price'             => $this->safe_call($product, 'get_price'),
            'regular_price'     => $this->safe_call($product, 'get_regular_price'),
            'sale_price'        => $this->safe_call($product, 'get_sale_price'),
            'min_price'         => $variation_data['min_price'],
            'max_price'         => $variation_data['max_price'],
            'variation_count'   => $variation_data['variation_count'],

            'currency'          => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '',
            'availability'      => $this->get_availability($product),
            'stock_status'      => $this->safe_call($product, 'get_stock_status'),

            'rating_value'      => $this->safe_call($product, 'get_average_rating'),
            'review_count'      => $this->safe_call($product, 'get_review_count'),

            'product_type'      => $this->safe_call($product, 'get_type'),
            'is_variable'       => $this->is_variable_product($product),
            'is_on_sale'        => method_exists($product, 'is_on_sale') ? (bool) $product->is_on_sale() : false,
            'is_in_stock'       => method_exists($product, 'is_in_stock') ? (bool) $product->is_in_stock() : false,

            'date_created'      => $this->get_product_date($product, 'get_date_created'),
            'date_modified'     => $this->get_product_date($product, 'get_date_modified'),
        ];
    }

    public function get_product_schema_data($product = null) {
        return $this->get_product_data($product);
    }

    public function resolve($product = null) {
        return $this->get_product_data($product);
    }

    private function get_image($product) {

        if (!$product || !method_exists($product, 'get_image_id')) {
            return '';
        }

        $image_id = $product->get_image_id();

        if (!$image_id) {
            return '';
        }

        $image = wp_get_attachment_image_url($image_id, 'full');

        return $image ?: '';
    }

    private function get_availability($product) {

        if (!$product || !method_exists($product, 'is_in_stock')) {
            return '';
        }

        if ($product->is_in_stock()) {
            return 'https://schema.org/InStock';
        }

        return 'https://schema.org/OutOfStock';
    }

    private function get_brand($product) {

        $empty = [
            'name' => '',
            'url'  => '',
        ];

        if (!$product || !method_exists($product, 'get_id')) {
            return $empty;
        }

        $product_id = $product->get_id();

        $taxonomies = [
            'product_brand',
            'pa_brand',
            'brand',
        ];

        foreach ($taxonomies as $taxonomy) {

            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            $terms = get_the_terms($product_id, $taxonomy);

            if (empty($terms) || is_wp_error($terms)) {
                continue;
            }

            $term = reset($terms);

            if (!$term || empty($term->name)) {
                continue;
            }

            $term_link = get_term_link($term);

            return [
                'name' => $term->name,
                'url'  => !is_wp_error($term_link) ? $term_link : '',
            ];
        }

        $attribute_brand = $this->get_attribute_value($product, [
            'pa_brand',
            'brand',
        ]);

        if ($attribute_brand !== '') {
            return [
                'name' => $attribute_brand,
                'url'  => '',
            ];
        }

        $meta_brand = $this->get_first_meta($product_id, [
            '_brand',
            'brand',
            '_product_brand',
            '_amk_brand',
        ]);

        if ($meta_brand !== '') {
            return [
                'name' => $meta_brand,
                'url'  => '',
            ];
        }

        return $empty;
    }

    private function get_identifier($product, $type) {

        if (!$product || !method_exists($product, 'get_id')) {
            return '';
        }

        $product_id = $product->get_id();

        if ($type === 'gtin') {

            $meta_value = $this->get_first_meta($product_id, [
                '_gtin',
                'gtin',
                '_global_unique_id',
                '_wpm_gtin_code',
                '_alg_ean',
                '_ts_gtin',
                '_wc_gpf_gtin',
            ]);

            if ($meta_value !== '') {
                return $meta_value;
            }

            return $this->get_attribute_value($product, [
                'gtin',
                'pa_gtin',
                'gtin13',
                'pa_gtin13',
                'ean',
                'pa_ean',
            ]);
        }

        if ($type === 'mpn') {

            $meta_value = $this->get_first_meta($product_id, [
                '_mpn',
                'mpn',
                '_manufacturer_part_number',
                '_amk_mpn',
                '_wc_gpf_mpn',
            ]);

            if ($meta_value !== '') {
                return $meta_value;
            }

            return $this->get_attribute_value($product, [
                'mpn',
                'pa_mpn',
            ]);
        }

        return '';
    }

    private function get_variation_data($product) {

        $data = [
            'min_price'       => '',
            'max_price'       => '',
            'variation_count' => 0,
        ];

        if (!$product || !method_exists($product, 'get_price')) {
            return $data;
        }

        if ($this->is_variable_product($product)) {

            $data['min_price'] = method_exists($product, 'get_variation_price')
                ? $product->get_variation_price('min', true)
                : '';

            $data['max_price'] = method_exists($product, 'get_variation_price')
                ? $product->get_variation_price('max', true)
                : '';

            $children = method_exists($product, 'get_children')
                ? $product->get_children()
                : [];

            $data['variation_count'] = is_array($children) ? count($children) : 0;

            return $data;
        }

        $price = $product->get_price();

        $data['min_price'] = $price;
        $data['max_price'] = $price;

        return $data;
    }

    private function is_variable_product($product) {

        return $product
            && method_exists($product, 'is_type')
            && $product->is_type('variable');
    }

    private function get_attribute_value($product, $attributes) {

        if (!$product || !method_exists($product, 'get_attribute')) {
            return '';
        }

        foreach ($attributes as $attribute) {

            $value = $product->get_attribute($attribute);

            if (!empty($value)) {
                return wp_strip_all_tags($value);
            }
        }

        return '';
    }

    private function get_first_meta($product_id, $meta_keys) {

        foreach ($meta_keys as $meta_key) {

            $value = get_post_meta($product_id, $meta_key, true);

            if ($value !== '' && $value !== null) {
                return is_scalar($value) ? sanitize_text_field((string) $value) : '';
            }
        }

        return '';
    }

    private function get_product_date($product, $method) {

        if (!$product || !method_exists($product, $method)) {
            return '';
        }

        $date = $product->{$method}();

        if (!$date || !method_exists($date, 'date')) {
            return '';
        }

        return $date->date('c');
    }

    private function safe_call($object, $method, $default = '') {

        if (!$object || !method_exists($object, $method)) {
            return $default;
        }

        $value = $object->{$method}();

        if ($value === null) {
            return $default;
        }

        if (is_bool($value) || is_numeric($value) || is_string($value)) {
            return $value;
        }

        return $default;
    }
}