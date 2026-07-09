<?php

namespace AMK\SchemaCore\Schema;

defined('ABSPATH') || exit;

class SchemaSelector {

    /**
     * Detect current WordPress/WooCommerce context.
     *
     * Important:
     * - ContactPage and AboutPage are not detected here.
     * - They are selected by Page ID in global settings and normalized later.
     * - This selector only detects broad template scopes.
     *
     * @return string
     */
    public function detect_context() {

        if ($this->is_404_context()) {
            return '404';
        }

        if ($this->is_search_context()) {
            return 'search';
        }

        if ($this->is_home_context()) {
            return 'home';
        }

        if ($this->is_blog_archive_context()) {
            return 'blog_archive';
        }

        if ($this->is_product_context()) {
            return 'product';
        }

        if ($this->is_product_category_context()) {
            return 'product_category';
        }

        if ($this->is_product_tag_context()) {
            return 'product_tag';
        }

        if ($this->is_shop_context()) {
            return 'collection';
        }

        if ($this->is_single_post_context()) {
            return 'single_post';
        }

        if ($this->is_category_archive_context()) {
            return 'category_archive';
        }

        if ($this->is_tag_archive_context()) {
            return 'tag_archive';
        }

        if ($this->is_author_archive_context()) {
            return 'author_archive';
        }

        if ($this->is_generic_archive_context()) {
            return 'archive';
        }

        if ($this->is_page_context()) {
            return 'page';
        }

        return 'default';
    }

    /**
     * Select best matching schema from a list.
     *
     * This method is kept for backward compatibility. The main rendering flow
     * currently loads multiple templates through SchemaManager, but this selector
     * can still be used by older/internal code.
     *
     * @param array $schemas
     * @return array|null
     */
    public function select($schemas) {
        if (empty($schemas) || !is_array($schemas)) {
            return null;
        }

        $context = SchemaTemplateContract::normalize_scope($this->detect_context());

        usort($schemas, function ($a, $b) {
            $priority_a = isset($a['priority']) ? intval($a['priority']) : 0;
            $priority_b = isset($b['priority']) ? intval($b['priority']) : 0;

            if ($priority_a !== $priority_b) {
                return $priority_b <=> $priority_a;
            }

            $id_a = isset($a['id']) ? absint($a['id']) : 0;
            $id_b = isset($b['id']) ? absint($b['id']) : 0;

            return $id_b <=> $id_a;
        });

        foreach ($schemas as $schema) {
            if ($this->matches_exact_context($schema, $context)) {
                return $schema;
            }
        }

        foreach ($schemas as $schema) {
            if ($this->matches_fallback_context($schema, $context)) {
                return $schema;
            }
        }

        foreach ($schemas as $schema) {
            if (SchemaTemplateContract::normalize_scope($schema['scope'] ?? '') === 'global') {
                return $schema;
            }
        }

        foreach ($schemas as $schema) {
            if (SchemaTemplateContract::normalize_scope($schema['scope'] ?? '') === 'default') {
                return $schema;
            }
        }

        return isset($schemas[0]) && is_array($schemas[0]) ? $schemas[0] : null;
    }

    /**
     * Check exact scope match.
     *
     * @param array  $schema
     * @param string $context
     * @return bool
     */
    private function matches_exact_context($schema, $context) {
        if (empty($schema) || !is_array($schema)) {
            return false;
        }

        $scope = SchemaTemplateContract::normalize_scope($schema['scope'] ?? 'default');

        return $scope === $context;
    }

    /**
     * Check fallback scope match.
     *
     * @param array  $schema
     * @param string $context
     * @return bool
     */
    private function matches_fallback_context($schema, $context) {
        if (empty($schema) || !is_array($schema)) {
            return false;
        }

        $scope = SchemaTemplateContract::normalize_scope($schema['scope'] ?? 'default');

        $fallbacks = [
            'product'          => ['page'],
            'product_category' => ['collection', 'archive'],
            'product_tag'      => ['collection', 'archive'],
            'collection'       => ['archive', 'page'],
            'single_post'      => ['page'],
            'blog_archive'     => ['archive', 'collection'],
            'category_archive' => ['blog_archive', 'archive', 'collection'],
            'tag_archive'      => ['blog_archive', 'archive', 'collection'],
            'author_archive'   => ['blog_archive', 'archive', 'collection'],
            'archive'          => ['blog_archive', 'collection'],
            'search'           => ['page'],
            '404'              => ['page'],
            'home'             => ['page'],
        ];

        if (!isset($fallbacks[$context])) {
            return false;
        }

        return in_array($scope, $fallbacks[$context], true);
    }

    /**
     * Detect WooCommerce single product pages.
     *
     * We do not rely only on is_product(), because some themes/builders/plugins
     * may call the schema output before WooCommerce has populated every helper.
     * WordPress' native is_singular('product') is a safe fallback for the same
     * canonical page type.
     *
     * @return bool
     */
    private function is_product_context() {
        if ($this->call_conditional('is_product')) {
            return true;
        }

        if ($this->call_conditional('is_singular', ['product'])) {
            return true;
        }

        $queried_id = $this->get_queried_object_id();

        if ($queried_id && $this->get_post_type($queried_id) === 'product') {
            return true;
        }

        return false;
    }

    /**
     * Detect WooCommerce product category archives.
     *
     * @return bool
     */
    private function is_product_category_context() {
        if ($this->call_conditional('is_product_category')) {
            return true;
        }

        if ($this->call_conditional('is_tax', ['product_cat'])) {
            return true;
        }

        return false;
    }

    /**
     * Detect WooCommerce product tag archives.
     *
     * @return bool
     */
    private function is_product_tag_context() {
        if ($this->call_conditional('is_product_tag')) {
            return true;
        }

        if ($this->call_conditional('is_tax', ['product_tag'])) {
            return true;
        }

        return false;
    }

    /**
     * Detect WooCommerce shop / product archive.
     *
     * @return bool
     */
    private function is_shop_context() {
        if ($this->call_conditional('is_shop')) {
            return true;
        }

        if ($this->call_conditional('is_post_type_archive', ['product'])) {
            return true;
        }

        return false;
    }

    /**
     * Detect front page.
     *
     * @return bool
     */
    private function is_home_context() {
        return $this->call_conditional('is_front_page');
    }

    /**
     * Detect blog posts index.
     *
     * @return bool
     */
    private function is_blog_archive_context() {
        return $this->call_conditional('is_home') && !$this->call_conditional('is_front_page');
    }

    /**
     * Detect single blog posts.
     *
     * @return bool
     */
    private function is_single_post_context() {
        if ($this->call_conditional('is_singular', ['post'])) {
            return true;
        }

        if ($this->call_conditional('is_single') && $this->get_queried_post_type() === 'post') {
            return true;
        }

        return false;
    }

    /**
     * Detect normal WordPress pages.
     *
     * @return bool
     */
    private function is_page_context() {
        if ($this->call_conditional('is_page')) {
            return true;
        }

        if ($this->call_conditional('is_singular', ['page'])) {
            return true;
        }

        return false;
    }

    /**
     * Detect category archive.
     *
     * @return bool
     */
    private function is_category_archive_context() {
        return $this->call_conditional('is_category');
    }

    /**
     * Detect tag archive.
     *
     * @return bool
     */
    private function is_tag_archive_context() {
        return $this->call_conditional('is_tag');
    }

    /**
     * Detect author archive.
     *
     * @return bool
     */
    private function is_author_archive_context() {
        return $this->call_conditional('is_author');
    }

    /**
     * Detect generic archive after more specific archives were checked.
     *
     * @return bool
     */
    private function is_generic_archive_context() {
        return $this->call_conditional('is_archive');
    }

    /**
     * Detect search page.
     *
     * @return bool
     */
    private function is_search_context() {
        return $this->call_conditional('is_search');
    }

    /**
     * Detect 404 page.
     *
     * @return bool
     */
    private function is_404_context() {
        return $this->call_conditional('is_404');
    }

    /**
     * Get current queried object ID safely.
     *
     * @return int
     */
    private function get_queried_object_id() {
        if (!function_exists('get_queried_object_id')) {
            return 0;
        }

        $id = get_queried_object_id();

        return $id ? absint($id) : 0;
    }

    /**
     * Get current queried post type safely.
     *
     * @return string
     */
    private function get_queried_post_type() {
        $id = $this->get_queried_object_id();

        if (!$id) {
            return '';
        }

        return $this->get_post_type($id);
    }

    /**
     * Get post type safely.
     *
     * @param int $post_id
     * @return string
     */
    private function get_post_type($post_id) {
        $post_id = absint($post_id);

        if (!$post_id || !function_exists('get_post_type')) {
            return '';
        }

        $post_type = get_post_type($post_id);

        return is_string($post_type) ? $post_type : '';
    }

    /**
     * Safe wrapper for WordPress/WooCommerce conditional functions.
     *
     * @param string $function
     * @param array  $args
     * @return bool
     */
    private function call_conditional($function, $args = []) {
        if (!is_string($function) || $function === '') {
            return false;
        }

        if (!function_exists($function)) {
            return false;
        }

        if (!is_array($args)) {
            $args = [];
        }

        try {
            $result = call_user_func_array($function, $args);
        } catch (\Throwable $e) {
            return false;
        }

        return (bool) $result;
    }
}