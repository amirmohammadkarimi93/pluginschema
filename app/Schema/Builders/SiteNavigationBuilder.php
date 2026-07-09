<?php

namespace AMK\SchemaCore\Schema\Builders;

defined('ABSPATH') || exit;

/**
 * Builds SiteNavigationElement schema from the primary WordPress navigation menu.
 *
 * This class only builds navigation schema nodes. It must not render JSON-LD,
 * merge graph nodes, or modify unrelated schemas.
 */
class SiteNavigationBuilder {

    /**
     * Build SiteNavigationElement nodes.
     *
     * @param string $context Current detected schema context.
     * @return array
     */
    public function build($context = '') {
        $context = is_string($context) ? sanitize_key($context) : '';

        if (!$this->should_build($context)) {
            return [];
        }

        $menu_id = $this->get_menu_id($context);

        if (!$menu_id) {
            return [];
        }

        $items = $this->get_menu_items($menu_id);

        if (empty($items)) {
            return [];
        }

        $schema_items = $this->build_schema_items($items, $context);

        if (empty($schema_items)) {
            return [];
        }

        /**
         * Filter final SiteNavigationElement nodes.
         *
         * @param array  $schema_items
         * @param int    $menu_id
         * @param string $context
         */
        $schema_items = apply_filters(
            'amk_schema_core_site_navigation_schema',
            $schema_items,
            $menu_id,
            $context
        );

        return is_array($schema_items) ? array_values($schema_items) : [];
    }

    /**
     * Decide whether navigation schema should be built.
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

        /**
         * Allow developers to disable SiteNavigationElement globally.
         *
         * @param bool   $enabled
         * @param string $context
         */
        $enabled = apply_filters('amk_schema_core_site_navigation_enabled', true, $context);

        return (bool) $enabled;
    }

    /**
     * Resolve the menu ID used for SiteNavigationElement.
     *
     * Priority:
     * 1. Explicit menu ID from filter.
     * 2. Known primary/header menu locations.
     * 3. Optional first-menu fallback if explicitly enabled by filter.
     *
     * @param string $context
     * @return int
     */
    private function get_menu_id($context) {
        if (!function_exists('wp_get_nav_menu_items')) {
            return 0;
        }

        /**
         * Force a specific nav menu ID.
         *
         * @param int    $menu_id
         * @param string $context
         */
        $forced_menu_id = absint(
            apply_filters('amk_schema_core_site_navigation_menu_id', 0, $context)
        );

        if ($this->is_valid_menu_id($forced_menu_id)) {
            return $forced_menu_id;
        }

        $menu_id = $this->get_menu_id_from_locations($context);

        if ($menu_id) {
            return $menu_id;
        }

        /**
         * Fallback to first registered menu.
         *
         * Disabled by default because a random footer/mobile menu can create
         * misleading navigation schema on commercial sites.
         *
         * @param bool   $enabled
         * @param string $context
         */
        $fallback_to_first_menu = apply_filters(
            'amk_schema_core_site_navigation_fallback_to_first_menu',
            false,
            $context
        );

        if (!$fallback_to_first_menu) {
            return 0;
        }

        return $this->get_first_available_menu_id();
    }

    /**
     * Get menu ID from common primary/header locations.
     *
     * @param string $context
     * @return int
     */
    private function get_menu_id_from_locations($context) {
        if (!function_exists('get_nav_menu_locations')) {
            return 0;
        }

        $locations = get_nav_menu_locations();

        if (empty($locations) || !is_array($locations)) {
            return 0;
        }

        $preferred_locations = [
            'primary',
            'main',
            'main-menu',
            'primary-menu',
            'menu-1',
            'header',
            'header-menu',
            'top',
            'top-menu',
        ];

        /**
         * Filter preferred menu locations.
         *
         * @param array  $preferred_locations
         * @param string $context
         */
        $preferred_locations = apply_filters(
            'amk_schema_core_site_navigation_preferred_locations',
            $preferred_locations,
            $context
        );

        if (!is_array($preferred_locations)) {
            $preferred_locations = [];
        }

        foreach ($preferred_locations as $location) {
            $location = is_string($location) ? sanitize_key($location) : '';

            if ($location === '') {
                continue;
            }

            if (empty($locations[$location])) {
                continue;
            }

            $menu_id = absint($locations[$location]);

            if ($this->is_valid_menu_id($menu_id)) {
                return $menu_id;
            }
        }

        return 0;
    }

    /**
     * Get the first available menu ID.
     *
     * This is intentionally not used unless enabled by filter.
     *
     * @return int
     */
    private function get_first_available_menu_id() {
        if (!function_exists('wp_get_nav_menus')) {
            return 0;
        }

        $menus = wp_get_nav_menus();

        if (empty($menus) || !is_array($menus)) {
            return 0;
        }

        foreach ($menus as $menu) {
            if (empty($menu->term_id)) {
                continue;
            }

            $menu_id = absint($menu->term_id);

            if ($this->is_valid_menu_id($menu_id)) {
                return $menu_id;
            }
        }

        return 0;
    }

    /**
     * Validate nav menu ID.
     *
     * @param int $menu_id
     * @return bool
     */
    private function is_valid_menu_id($menu_id) {
        $menu_id = absint($menu_id);

        if (!$menu_id) {
            return false;
        }

        if (!function_exists('wp_get_nav_menu_object')) {
            return true;
        }

        $menu = wp_get_nav_menu_object($menu_id);

        return !empty($menu) && !is_wp_error($menu);
    }

    /**
     * Get menu items.
     *
     * @param int $menu_id
     * @return array
     */
    private function get_menu_items($menu_id) {
        $menu_id = absint($menu_id);

        if (!$menu_id || !function_exists('wp_get_nav_menu_items')) {
            return [];
        }

        $items = wp_get_nav_menu_items($menu_id, [
            'post_status' => 'publish',
        ]);

        if (empty($items) || !is_array($items)) {
            return [];
        }

        return $items;
    }

    /**
     * Build SiteNavigationElement nodes from WP menu items.
     *
     * @param array  $items
     * @param string $context
     * @return array
     */
    private function build_schema_items($items, $context) {
        $schema_items = [];
        $position     = 1;

        /**
         * Top-level only is safer for public/commercial plugins.
         * Deep menu trees can create noisy or misleading structured data.
         *
         * @param bool   $top_level_only
         * @param string $context
         */
        $top_level_only = apply_filters(
            'amk_schema_core_site_navigation_top_level_only',
            true,
            $context
        );

        foreach ($items as $item) {
            if (!is_object($item)) {
                continue;
            }

            if ($top_level_only && !empty($item->menu_item_parent)) {
                continue;
            }

            $name = $this->get_item_name($item);
            $url  = $this->get_item_url($item);

            if ($name === '' || $url === '') {
                continue;
            }

            $schema_item = [
                '@type'    => 'SiteNavigationElement',
                '@id'      => $this->get_navigation_item_id($position),
                'position' => $position,
                'name'     => $name,
                'url'      => $url,
                'isPartOf' => [
                    '@id' => home_url('/#website'),
                ],
            ];

            /**
             * Filter a single SiteNavigationElement node.
             *
             * @param array  $schema_item
             * @param object $item
             * @param int    $position
             * @param string $context
             */
            $schema_item = apply_filters(
                'amk_schema_core_site_navigation_item',
                $schema_item,
                $item,
                $position,
                $context
            );

            if (is_array($schema_item) && !empty($schema_item)) {
                $schema_items[] = $schema_item;
                $position++;
            }
        }

        return $schema_items;
    }

    /**
     * Get clean menu item name.
     *
     * @param object $item
     * @return string
     */
    private function get_item_name($item) {
        $title = isset($item->title) ? $item->title : '';

        if (!is_string($title) || trim($title) === '') {
            return '';
        }

        $title = wp_strip_all_tags($title);
        $title = html_entity_decode($title, ENT_QUOTES, get_bloginfo('charset'));
        $title = trim($title);

        return $title;
    }

    /**
     * Get clean menu item URL.
     *
     * @param object $item
     * @return string
     */
    private function get_item_url($item) {
        $url = isset($item->url) ? trim((string) $item->url) : '';

        if ($url === '' || $url === '#') {
            return '';
        }

        $lower_url = strtolower($url);

        if (
            strpos($lower_url, 'javascript:') === 0 ||
            strpos($lower_url, 'mailto:') === 0 ||
            strpos($lower_url, 'tel:') === 0
        ) {
            return '';
        }

        if (strpos($url, '/') === 0) {
            $url = home_url($url);
        }

        $url = esc_url_raw($url);

        if ($url === '') {
            return '';
        }

        if (!$this->is_http_url($url)) {
            return '';
        }

        /**
         * Allow/disallow external menu URLs in SiteNavigationElement.
         *
         * Default is true because many sites have important external menu
         * destinations, but this can be disabled by developers.
         *
         * @param bool   $allow_external
         * @param string $url
         * @param object $item
         */
        $allow_external = apply_filters(
            'amk_schema_core_site_navigation_allow_external_urls',
            true,
            $url,
            $item
        );

        if (!$allow_external && !$this->is_internal_url($url)) {
            return '';
        }

        return $url;
    }

    /**
     * Check whether URL is http/https.
     *
     * @param string $url
     * @return bool
     */
    private function is_http_url($url) {
        $scheme = wp_parse_url($url, PHP_URL_SCHEME);

        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * Check whether URL belongs to current site.
     *
     * @param string $url
     * @return bool
     */
    private function is_internal_url($url) {
        $url_host  = wp_parse_url($url, PHP_URL_HOST);
        $home_host = wp_parse_url(home_url('/'), PHP_URL_HOST);

        if (!$url_host || !$home_host) {
            return false;
        }

        return strtolower($url_host) === strtolower($home_host);
    }

    /**
     * Build stable SiteNavigationElement ID.
     *
     * @param int $position
     * @return string
     */
    private function get_navigation_item_id($position) {
        $position = max(1, absint($position));

        return home_url('/#site-navigation-' . $position);
    }
}