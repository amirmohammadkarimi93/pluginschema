<?php

namespace AMK\SchemaCore\Schema\Builders;

defined('ABSPATH') || exit;

/**
 * Builds ItemList schema for archive/listing pages.
 *
 * This class is intentionally responsible only for archive/list ItemList schema.
 * It must not render JSON-LD, merge graphs, or modify unrelated schemas.
 */
class ArchiveItemListBuilder {

    /**
     * Build ItemList schema for the current archive/listing query.
     *
     * @param string $context Current detected schema context.
     * @return array
     */
    public function build($context = '') {
        $context = is_string($context) ? sanitize_key($context) : '';

        if (!$this->should_build($context)) {
            return [];
        }

        $items = $this->build_current_query_items();

        if (empty($items)) {
            return [];
        }

        $url = $this->get_current_url();

        if ($url === '') {
            return [];
        }

        $schema = [
            '@type'           => 'ItemList',
            '@id'             => $this->append_fragment($url, 'itemlist'),
            'name'            => $this->get_current_list_name($context),
            'url'             => $url,
            'numberOfItems'   => count($items),
            'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
            'itemListElement' => $items,
        ];

        /**
         * Filter archive ItemList schema before it is returned.
         *
         * @param array  $schema
         * @param string $context
         */
        $schema = apply_filters('amk_schema_core_archive_itemlist_schema', $schema, $context);

        return is_array($schema) ? $schema : [];
    }

    /**
     * Decide whether ItemList should be built for the current request.
     *
     * @param string $context
     * @return bool
     */
    private function should_build($context) {
        if (is_admin()) {
            return false;
        }

        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return false;
        }

        if (function_exists('wp_is_json_request') && wp_is_json_request()) {
            return false;
        }

        if (function_exists('is_feed') && is_feed()) {
            return false;
        }

        if (function_exists('is_singular') && is_singular()) {
            return false;
        }

        if (function_exists('is_product') && is_product()) {
            return false;
        }

        $allowed_contexts = [
            'product_category',
            'product_tag',
            'collection',
            'blog_archive',
            'category_archive',
            'tag_archive',
            'author_archive',
            'archive',
            'search',
        ];

        /**
         * Filter contexts where archive ItemList is allowed.
         *
         * @param array $allowed_contexts
         */
        $allowed_contexts = apply_filters('amk_schema_core_archive_itemlist_contexts', $allowed_contexts);

        if ($context !== '' && in_array($context, $allowed_contexts, true)) {
            return $this->is_archive_like_request();
        }

        return $this->is_archive_like_request();
    }

    /**
     * Check current WP request type.
     *
     * @return bool
     */
    private function is_archive_like_request() {
        if (function_exists('is_product_category') && is_product_category()) {
            return true;
        }

        if (function_exists('is_product_tag') && is_product_tag()) {
            return true;
        }

        if (function_exists('is_shop') && is_shop()) {
            return true;
        }

        if (function_exists('is_home') && is_home()) {
            return true;
        }

        if (function_exists('is_search') && is_search()) {
            return true;
        }

        if (function_exists('is_archive') && is_archive()) {
            return true;
        }

        return false;
    }

    /**
     * Build ListItem elements from the current main query.
     *
     * Important:
     * We keep items lightweight by default: ListItem + position + url + name.
     * We do not inject full Product/Offer data here, because that belongs to
     * single product pages and can create noisy/wrong category-page markup.
     *
     * @return array
     */
    private function build_current_query_items() {
        global $wp_query;

        if (empty($wp_query) || empty($wp_query->posts) || !is_array($wp_query->posts)) {
            return [];
        }

        $items    = [];
        $position = 1;

        foreach ($wp_query->posts as $post) {
            if (!is_object($post) || empty($post->ID)) {
                continue;
            }

            $post_id = absint($post->ID);

            if (!$post_id) {
                continue;
            }

            $url = get_permalink($post_id);

            if (!$url) {
                continue;
            }

            $name = get_the_title($post_id);
            $name = is_string($name) ? wp_strip_all_tags($name) : '';

            if ($name === '') {
                continue;
            }

            $item = [
                '@type'    => 'ListItem',
                'position' => $position,
                'url'      => esc_url_raw($url),
                'name'     => $name,
            ];

            /**
             * Filter a single archive ItemList element.
             *
             * @param array  $item
             * @param int    $post_id
             * @param int    $position
             * @param object $post
             */
            $item = apply_filters(
                'amk_schema_core_archive_itemlist_item',
                $item,
                $post_id,
                $position,
                $post
            );

            if (is_array($item) && !empty($item)) {
                $items[] = $item;
                $position++;
            }
        }

        return $items;
    }

    /**
     * Get current archive/listing URL.
     *
     * @return string
     */
    private function get_current_url() {
        if (function_exists('get_pagenum_link')) {
            $url = get_pagenum_link();
        } else {
            $url = home_url('/');
        }

        $url = is_string($url) ? trim($url) : '';

        if ($url === '') {
            return '';
        }

        return esc_url_raw($url);
    }

    /**
     * Get readable name for current list/archive.
     *
     * @param string $context
     * @return string
     */
    private function get_current_list_name($context = '') {
        if (function_exists('is_search') && is_search()) {
            $query = function_exists('get_search_query') ? get_search_query() : '';

            if ($query !== '') {
                return sprintf(__('Search results for %s', 'amk-schema-core'), wp_strip_all_tags($query));
            }

            return __('Search results', 'amk-schema-core');
        }

        if (function_exists('is_shop') && is_shop()) {
            $shop_title = $this->get_shop_page_title();

            if ($shop_title !== '') {
                return $shop_title;
            }

            return __('Shop', 'amk-schema-core');
        }

        if (function_exists('single_term_title')) {
            $term_title = single_term_title('', false);

            if (is_string($term_title) && trim($term_title) !== '') {
                return wp_strip_all_tags($term_title);
            }
        }

        if (function_exists('post_type_archive_title')) {
            $archive_title = post_type_archive_title('', false);

            if (is_string($archive_title) && trim($archive_title) !== '') {
                return wp_strip_all_tags($archive_title);
            }
        }

        if (function_exists('is_home') && is_home()) {
            $posts_page_id = absint(get_option('page_for_posts'));

            if ($posts_page_id) {
                $title = get_the_title($posts_page_id);

                if (is_string($title) && trim($title) !== '') {
                    return wp_strip_all_tags($title);
                }
            }

            return get_bloginfo('name');
        }

        if ($context !== '') {
            return ucwords(str_replace('_', ' ', $context));
        }

        return 'Archive';
    }

    /**
     * Get WooCommerce shop page title.
     *
     * @return string
     */
    private function get_shop_page_title() {
        if (!function_exists('wc_get_page_id')) {
            return '';
        }

        $shop_page_id = absint(wc_get_page_id('shop'));

        if (!$shop_page_id) {
            return '';
        }

        $title = get_the_title($shop_page_id);

        if (!is_string($title) || trim($title) === '') {
            return '';
        }

        return wp_strip_all_tags($title);
    }

    /**
     * Append URL fragment safely.
     *
     * @param string $url
     * @param string $fragment
     * @return string
     */
    private function append_fragment($url, $fragment) {
        $url      = is_string($url) ? trim($url) : '';
        $fragment = is_string($fragment) ? sanitize_key($fragment) : '';

        if ($url === '' || $fragment === '') {
            return $url;
        }

        $url = preg_replace('/#.*$/', '', $url);

        if (strpos($url, '?') !== false) {
            return $url . '#' . $fragment;
        }

        return trailingslashit($url) . '#' . $fragment;
    }
}