<?php

namespace AMK\SchemaCore\Data;

use AMK\SchemaCore\Core\GlobalSettings;
use AMK\SchemaCore\Schema\SchemaTemplateContract;

defined('ABSPATH') || exit;

class DataResolver {

    /**
     * Cached global WooCommerce price unit detection.
     *
     * @var string|null
     */
    private $schema_price_unit_cache = null;

    /**
     * Cached commerce policy data for Product > Offer merchant listings.
     *
     * @var array|null
     */
    private $schema_commerce_data_cache = null;

    /**
     * Resolve data for current context.
     *
     * @param string $context
     * @return array
     */
    public function resolve($context = 'default') {

        $context = $this->normalize_context($context);

        $global_data = $this->resolve_global_data($context);

        switch ($context) {

            case 'product':
                $context_data = $this->resolve_product();
                break;

            case 'product_category':
            case 'product_tag':
            case 'collection':
                $context_data = $this->resolve_archive($context);
                break;

            case 'single_post':
                $context_data = $this->resolve_single_post();
                break;

            case 'page':
            case 'home':
                $context_data = $this->resolve_page($context);
                break;

            case 'blog_archive':
            case 'category_archive':
            case 'tag_archive':
            case 'author_archive':
            case 'archive':
                $context_data = $this->resolve_archive($context);
                break;

            case 'search':
                $context_data = $this->resolve_search();
                break;

            case '404':
                $context_data = $this->resolve_404();
                break;

            default:
                $context_data = $this->resolve_default();
                break;
        }

        $data = array_merge($global_data, $context_data);

        $current_url = !empty($data['url']) ? $data['url'] : $this->get_current_url();

        $data['context']       = $context;
        $data['current_url']   = $current_url;
        $data['webpage_id']    = trailingslashit($current_url) . '#webpage';
        $data['breadcrumb_id'] = trailingslashit($current_url) . '#breadcrumb';

        if (empty($data['name']) && !empty($data['title'])) {
            $data['name'] = $data['title'];
        }

        if (empty($data['title']) && !empty($data['name'])) {
            $data['title'] = $data['name'];
        }

        if (!isset($data['image'])) {
            $data['image'] = '';
        }

        return $data;
    }

    /**
     * Normalize internal context/scope names.
     *
     * @param mixed $context
     * @return string
     */
    private function normalize_context($context) {

        if (class_exists(SchemaTemplateContract::class)) {
            return SchemaTemplateContract::normalize_scope($context);
        }

        $context = is_string($context) ? sanitize_key($context) : 'default';

        $aliases = [
            'front_page' => 'home',
            'homepage'   => 'home',
            'post'       => 'single_post',
            'category'   => 'category_archive',
            'tag'        => 'tag_archive',
            'shop'       => 'collection',
        ];

        return isset($aliases[$context]) ? $aliases[$context] : $context;
    }

    /**
     * Resolve global site and organization data.
     *
     * @param string $context
     * @return array
     */
    private function resolve_global_data($context = 'default') {

        $global_settings    = GlobalSettings::get();
        $global_schema_data = GlobalSettings::to_resolver_data($global_settings);
        $global_schema_data = $this->normalize_global_schema_data($global_schema_data, $global_settings);

        $site_name = !empty($global_schema_data['organization_name'])
            ? $global_schema_data['organization_name']
            : get_bloginfo('name');

        $site_url = !empty($global_schema_data['organization_url'])
            ? trailingslashit($global_schema_data['organization_url'])
            : home_url('/');

        $current_url = $this->get_current_url();

        $data = [
            'site_name'             => $site_name,
            'site_description'      => get_bloginfo('description'),
            'site_url'              => $site_url,
            'home_url'              => home_url('/'),
            'current_url'           => $current_url,
            'language'              => $this->get_schema_language(),
            'website_search_target' => home_url('/?s={search_term_string}'),
            'context'               => $context,

            'organization_id'       => home_url('/#organization'),
            'website_id'            => home_url('/#website'),
            'webpage_id'            => trailingslashit($current_url) . '#webpage',
            'breadcrumb_id'         => trailingslashit($current_url) . '#breadcrumb',

            'breadcrumb_items'      => $this->build_breadcrumb_items(),
        ];

        return array_merge($data, $global_schema_data);
    }

    /**
     * Normalize global resolver data for template placeholders.
     *
     * This keeps new dynamic placeholders such as {{organization_types}} safe
     * even when an older settings payload or an older GlobalSettings class is
     * still present during an upgrade.
     *
     * @param array $data
     * @param array $settings
     * @return array
     */
    private function normalize_global_schema_data($data, $settings = []) {

        $data     = is_array($data) ? $data : [];
        $settings = is_array($settings) ? $settings : [];

        if (empty($data['organization_types'])) {
            $data['organization_types'] = $this->build_fallback_organization_types($data, $settings);
        }

        $data['organization_types'] = $this->normalize_organization_types($data['organization_types']);

        if (empty($data['organization_type'])) {
            $data['organization_type'] = $this->pick_primary_organization_type($data['organization_types']);
        }

        if (!empty($data['organization_currencies_accepted'])) {
            $data['organization_currencies_accepted'] = $this->normalize_currency_code($data['organization_currencies_accepted']);
        }

        if (!isset($data['organization_opening_hours_specification'])) {
            $data['organization_opening_hours_specification'] = [];
        }

        return $data;
    }

    /**
     * Build organization @type fallback for upgrades and older settings.
     *
     * @param array $data
     * @param array $settings
     * @return array
     */
    private function build_fallback_organization_types($data, $settings = []) {

        $types = ['Organization'];

        if (!empty($data['organization_types'])) {
            $types = array_merge($types, (array) $data['organization_types']);
        } elseif (!empty($settings['organization']['types'])) {
            $types = array_merge($types, (array) $settings['organization']['types']);
        } elseif (!empty($data['organization_type'])) {
            $types[] = $data['organization_type'];
        } elseif (!empty($settings['organization']['type'])) {
            $types[] = $settings['organization']['type'];
        }

        $profile = isset($settings['site_profile']) ? sanitize_key($settings['site_profile']) : '';

        if ($profile === 'ecommerce') {
            $types[] = 'OnlineStore';
        }

        if ($profile === 'ecommerce_local') {
            $types[] = 'Store';
            $types[] = 'OnlineStore';
        }

        if ($profile === 'business') {
            $types[] = 'LocalBusiness';
        }

        if (!empty($settings['commerce']['enabled'])) {
            $types[] = 'OnlineStore';
        }

        if (!empty($settings['local_business']['enabled'])) {
            if ($profile === 'ecommerce_local' || in_array('OnlineStore', $types, true) || in_array('Store', $types, true)) {
                $types[] = 'Store';
            } else {
                $types[] = 'LocalBusiness';
            }
        }

        return $types;
    }

    /**
     * Sanitize and order organization-like Schema.org types.
     *
     * @param mixed $types
     * @return array
     */
    private function normalize_organization_types($types) {

        if (is_string($types)) {
            $types = [$types];
        }

        if (!is_array($types)) {
            $types = [];
        }

        $allowed = [
            'Organization',
            'LocalBusiness',
            'Store',
            'OnlineStore',
            'Corporation',
            'NGO',
        ];

        $clean = [];

        foreach ($types as $type) {
            $type = sanitize_text_field((string) $type);

            if ($type === '' || !in_array($type, $allowed, true)) {
                continue;
            }

            $clean[] = $type;
        }

        if (!in_array('Organization', $clean, true)) {
            array_unshift($clean, 'Organization');
        }

        $order = array_flip($allowed);
        $clean = array_values(array_unique($clean));

        usort($clean, function ($a, $b) use ($order) {
            return ($order[$a] ?? 999) <=> ($order[$b] ?? 999);
        });

        return $clean;
    }

    /**
     * Pick a single compatible type for legacy {{organization_type}} templates.
     *
     * @param array $types
     * @return string
     */
    private function pick_primary_organization_type($types) {

        $types = $this->normalize_organization_types($types);

        foreach (['OnlineStore', 'Store', 'LocalBusiness', 'Corporation', 'NGO'] as $preferred) {
            if (in_array($preferred, $types, true)) {
                return $preferred;
            }
        }

        return 'Organization';
    }

    /**
     * Resolve WooCommerce product data.
     *
     * @return array
     */
    private function resolve_product() {

        $product = $this->get_current_product();

        if (!$product) {
            return $this->resolve_page('product');
        }

        $product_id     = $product->get_id();
        $product_url    = get_permalink($product_id);
        $product_url    = $product_url ? trailingslashit($product_url) : '';
        $is_variable    = $this->product_is_type($product, 'variable');
        $brand          = $this->get_product_brand($product);
        $variation_data = $this->get_product_variation_data($product);
        $image          = $this->get_product_image($product);
        $gallery_images = $this->get_product_gallery_images($product);
        $images         = !empty($gallery_images) ? $gallery_images : ($image ? [$image] : '');
        $offer_data     = $is_variable ? [] : $this->build_product_offer_data($product, $product_url);
        $description    = $this->get_product_schema_description($product);
        $short_desc     = $this->clean_text($product->get_short_description());

        $product_entity_id = $product_url ? $product_url . '#product' : '';

        return [
            'id'                     => $product_id,
            'post_id'                => $product_id,
            'post_type'              => 'product',

            'name'                   => $product->get_name(),
            'title'                  => $product->get_name(),
            'description'            => $description,
            'short_description'      => $short_desc,
            'excerpt'                => $short_desc,

            'url'                    => $product_url,
            'offer_url'              => $product_url,

            'image'                  => $image,
            'images'                 => $images,
            'product_gallery_images' => $gallery_images,

            'sku'                    => $product->get_sku(),
            'gtin'                   => $this->get_product_identifier($product, 'gtin'),
            'mpn'                    => $this->get_product_identifier($product, 'mpn'),

            'brand_name'             => $brand['name'],
            'brand_url'              => $brand['url'],
            'brand_schema'           => $this->build_product_brand_schema($brand),

            'price'                  => $this->format_schema_price($product->get_price(), $product),
            'offer_price'            => $this->format_schema_price($product->get_price(), $product),
            'regular_price'          => $this->format_schema_price($product->get_regular_price(), $product),
            'sale_price'             => $this->format_schema_price($product->get_sale_price(), $product),
            'min_price'              => $variation_data['min_price'],
            'max_price'              => $variation_data['max_price'],
            'price_valid_until'      => $this->get_product_price_valid_until($product),
            'sale_start_date'        => $this->get_product_sale_date($product, 'from'),
            'sale_end_date'          => $this->get_product_sale_date($product, 'to'),
            'is_on_sale'             => $this->product_is_on_sale($product) ? 1 : 0,

            'currency'               => $this->get_schema_price_currency(),
            'availability'           => $this->get_product_availability($product),
            'stock_status'           => $product->get_stock_status(),
            'product_offer'          => $offer_data,
            'product_return_policy'  => $this->get_product_offer_return_policy(),
            'product_shipping_details' => $this->get_product_offer_shipping_details($product),

            'rating_value'           => $this->normalize_rating($product->get_average_rating()),
            'review_count'           => absint($product->get_review_count()),

            'product_type'           => $product->get_type(),
            'product_schema_type'    => $is_variable ? 'ProductGroup' : 'Product',
            'is_variable_product'    => $is_variable ? 1 : 0,
            'is_simple_product'      => $this->product_is_type($product, 'simple') ? 1 : 0,
            'product_entity_id'      => $product_entity_id,
            'product_id_schema'      => $product_entity_id,
            'product_group_id'       => $is_variable ? $product_entity_id : '',
            'webpage_main_entity_id' => $product_entity_id,
            'product_group_id_value' => $this->get_product_group_identifier($product),
            'product_varies_by'      => $variation_data['varies_by'],
            'product_variants'       => $variation_data['variants'],
            'has_variants'           => !empty($variation_data['variants']) ? 1 : 0,
            'variation_count'        => $variation_data['variation_count'],

            'date_created'           => $product->get_date_created() ? $product->get_date_created()->date('c') : '',
            'date_modified'          => $product->get_date_modified() ? $product->get_date_modified()->date('c') : '',

            'date_published'         => get_the_date('c', $product_id),
            'author_name'            => '',
            'author_url'             => '',
        ];
    }

    /**
     * Resolve single post data.
     *
     * @return array
     */
    private function resolve_single_post() {

        $post_id = get_the_ID();

        if (!$post_id) {
            return $this->resolve_default();
        }

        $author_id = absint(get_post_field('post_author', $post_id));

        return [
            'id'             => $post_id,
            'post_id'        => $post_id,
            'post_type'      => get_post_type($post_id),

            'name'           => get_the_title($post_id),
            'title'          => get_the_title($post_id),
            'description'    => $this->get_post_description($post_id),
            'excerpt'        => $this->clean_text(get_the_excerpt($post_id)),
            'url'            => get_permalink($post_id),
            'image'          => get_the_post_thumbnail_url($post_id, 'full') ?: '',
            'images'         => $this->get_post_images($post_id),

            'date_published' => get_the_date('c', $post_id),
            'date_modified'  => get_the_modified_date('c', $post_id),
            'date_created'   => get_the_date('c', $post_id),

            'author_id'      => $author_id,
            'author_name'    => $author_id ? get_the_author_meta('display_name', $author_id) : '',
            'author_url'     => $author_id ? get_author_posts_url($author_id) : '',
        ];
    }

    /**
     * Resolve page/home data.
     *
     * @param string $context
     * @return array
     */
    private function resolve_page($context = 'page') {

        $post_id = get_the_ID();

        if ($context === 'home' && (function_exists('is_front_page') && is_front_page())) {
            $front_page_id = absint(get_option('page_on_front'));

            if ($front_page_id) {
                $post_id = $front_page_id;
            }
        }

        if (!$post_id) {
            return [
                'id'             => 0,
                'post_id'        => 0,
                'post_type'      => '',
                'name'           => get_bloginfo('name'),
                'title'          => get_bloginfo('name'),
                'description'    => get_bloginfo('description'),
                'excerpt'        => get_bloginfo('description'),
                'url'            => home_url('/'),
                'image'          => '',
                'images'         => '',
                'date_published' => '',
                'date_modified'  => '',
                'date_created'   => '',
                'author_name'    => '',
                'author_url'     => '',
            ];
        }

        return [
            'id'             => $post_id,
            'post_id'        => $post_id,
            'post_type'      => get_post_type($post_id),

            'name'           => get_the_title($post_id),
            'title'          => get_the_title($post_id),
            'description'    => $this->get_post_description($post_id),
            'excerpt'        => $this->clean_text(get_the_excerpt($post_id)),
            'url'            => get_permalink($post_id),
            'image'          => get_the_post_thumbnail_url($post_id, 'full') ?: '',
            'images'         => $this->get_post_images($post_id),

            'date_published' => get_the_date('c', $post_id),
            'date_modified'  => get_the_modified_date('c', $post_id),
            'date_created'   => get_the_date('c', $post_id),

            'author_name'    => '',
            'author_url'     => '',
        ];
    }

    /**
     * Resolve archive, category, tag, shop and term pages.
     *
     * @param string $context
     * @return array
     */
    private function resolve_archive($context = 'archive') {

        $queried = get_queried_object();

        $title = function_exists('get_the_archive_title')
            ? $this->clean_text(get_the_archive_title())
            : get_bloginfo('name');

        $description = function_exists('get_the_archive_description')
            ? $this->clean_text(get_the_archive_description())
            : '';

        $url      = $this->get_current_url();
        $image    = '';
        $term_id  = 0;
        $taxonomy = '';
        $slug     = '';

        if ($queried && isset($queried->term_id, $queried->taxonomy)) {

            $term_id  = absint($queried->term_id);
            $taxonomy = sanitize_key($queried->taxonomy);
            $slug     = sanitize_title($queried->slug ?? '');

            $term_link = get_term_link($queried);

            if (!is_wp_error($term_link)) {
                $url = $term_link;
            }

            if (!empty($queried->name)) {
                $title = $queried->name;
            }

            if (!empty($queried->description)) {
                $description = $this->clean_text($queried->description);
            }

            $image = $this->get_term_image($queried);
        }

        if ($context === 'collection' && function_exists('is_shop') && is_shop() && function_exists('wc_get_page_id')) {
            $shop_id = wc_get_page_id('shop');

            if ($shop_id && $shop_id > 0) {
                $title       = get_the_title($shop_id);
                $description = $this->get_post_description($shop_id);
                $url         = get_permalink($shop_id);
                $image       = get_the_post_thumbnail_url($shop_id, 'full') ?: '';
            }
        }

        return [
            'id'             => $term_id,
            'term_id'        => $term_id,
            'taxonomy'       => $taxonomy,
            'term_slug'      => $slug,

            'name'           => $title,
            'title'          => $title,
            'description'    => $description,
            'excerpt'        => $description,
            'url'            => $url,
            'image'          => $image,
            'images'         => $image ? [$image] : '',

            'date_published' => '',
            'date_modified'  => '',
            'date_created'   => '',

            'author_name'    => '',
            'author_url'     => '',
        ];
    }

    /**
     * Resolve search page.
     *
     * @return array
     */
    private function resolve_search() {

        $search_query = get_search_query();

        return [
            'id'             => 0,
            'name'           => __('Search results for: ', 'amk-schema-core') . $search_query,
            'title'          => __('Search results for: ', 'amk-schema-core') . $search_query,
            'description'    => __('Search results page for ', 'amk-schema-core') . $search_query,
            'excerpt'        => '',
            'url'            => $this->get_current_url(),
            'image'          => '',
            'images'         => '',
            'search_query'   => $search_query,
            'date_published' => '',
            'date_modified'  => '',
            'date_created'   => '',
            'author_name'    => '',
            'author_url'     => '',
        ];
    }

    /**
     * Resolve 404 page.
     *
     * @return array
     */
    private function resolve_404() {

        return [
            'id'             => 0,
            'name'           => __('Page not found', 'amk-schema-core'),
            'title'          => __('Page not found', 'amk-schema-core'),
            'description'    => __('The requested page was not found.', 'amk-schema-core'),
            'excerpt'        => '',
            'url'            => $this->get_current_url(),
            'image'          => '',
            'images'         => '',
            'date_published' => '',
            'date_modified'  => '',
            'date_created'   => '',
            'author_name'    => '',
            'author_url'     => '',
        ];
    }

    /**
     * Default data fallback.
     *
     * @return array
     */
    private function resolve_default() {

        return [
            'id'             => 0,
            'name'           => get_bloginfo('name'),
            'title'          => get_bloginfo('name'),
            'description'    => get_bloginfo('description'),
            'excerpt'        => get_bloginfo('description'),
            'url'            => $this->get_current_url(),
            'image'          => '',
            'images'         => '',
            'date_published' => '',
            'date_modified'  => '',
            'date_created'   => '',
            'author_name'    => '',
            'author_url'     => '',
        ];
    }

    /**
     * Get current WooCommerce product.
     *
     * This method must be reliable during wp_head. At that point, some themes,
     * page builders, or WooCommerce hooks may not have populated the global
     * $product object yet, and get_the_ID() can also be unreliable if another
     * loop has already touched the global post.
     *
     * Resolution order:
     * 1. Current queried object ID when it is a product.
     * 2. Native singular product fallback.
     * 3. Global $post when it is a product.
     * 4. get_the_ID() only as a final fallback.
     * 5. Existing global $product only when it matches the queried product or
     *    when no safer product ID is available.
     *
     * @return mixed|null
     */
    private function get_current_product() {

        if (!function_exists('wc_get_product')) {
            return null;
        }

        global $product, $post;

        $queried_product_id = 0;

        if (function_exists('get_queried_object_id')) {
            $queried_id = absint(get_queried_object_id());

            if ($queried_id && function_exists('get_post_type') && get_post_type($queried_id) === 'product') {
                $queried_product_id = $queried_id;
            }
        }

        if (!$queried_product_id && function_exists('is_singular') && is_singular('product')) {
            if (function_exists('get_queried_object_id')) {
                $queried_product_id = absint(get_queried_object_id());
            }
        }

        if (!$queried_product_id && is_object($post) && !empty($post->ID)) {
            $post_id = absint($post->ID);

            if ($post_id && function_exists('get_post_type') && get_post_type($post_id) === 'product') {
                $queried_product_id = $post_id;
            }
        }

        if (!$queried_product_id && function_exists('get_the_ID')) {
            $post_id = absint(get_the_ID());

            if ($post_id && function_exists('get_post_type') && get_post_type($post_id) === 'product') {
                $queried_product_id = $post_id;
            }
        }

        $global_product_id = 0;

        if (is_object($product) && method_exists($product, 'get_id')) {
            $global_product_id = absint($product->get_id());

            if ($queried_product_id && $global_product_id === $queried_product_id) {
                return $product;
            }

            if (!$queried_product_id && $global_product_id) {
                return $product;
            }
        }

        if (!$queried_product_id) {
            return null;
        }

        $resolved_product = wc_get_product($queried_product_id);

        if (!$resolved_product || !is_object($resolved_product) || !method_exists($resolved_product, 'get_id')) {
            return null;
        }

        $product = $resolved_product;

        return $resolved_product;
    }

    /**
     * Product main image URL.
     *
     * @param mixed $product
     * @return string
     */
    private function get_product_image($product) {

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

    /**
     * Product gallery images.
     *
     * @param mixed $product
     * @return array
     */
    private function get_product_gallery_images($product) {

        if (!$product || !method_exists($product, 'get_gallery_image_ids')) {
            return [];
        }

        $ids = $product->get_gallery_image_ids();

        if (empty($ids) || !is_array($ids)) {
            return [];
        }

        $images = [];

        foreach ($ids as $image_id) {
            $url = wp_get_attachment_image_url($image_id, 'full');

            if ($url) {
                $images[] = $url;
            }
        }

        return array_values(array_unique($images));
    }

    /**
     * Post attached/featured images.
     *
     * @param int $post_id
     * @return array|string
     */
    private function get_post_images($post_id) {

        $images = [];

        $featured = get_the_post_thumbnail_url($post_id, 'full');

        if ($featured) {
            $images[] = $featured;
        }

        $attachments = get_attached_media('image', $post_id);

        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                $url = wp_get_attachment_image_url($attachment->ID, 'full');

                if ($url) {
                    $images[] = $url;
                }
            }
        }

        $images = array_values(array_unique($images));

        return !empty($images) ? $images : '';
    }

    /**
     * Schema.org availability.
     *
     * @param mixed $product
     * @return string
     */
    private function get_product_availability($product) {

        if (!$product || !method_exists($product, 'is_in_stock')) {
            return '';
        }

        if ($product->is_in_stock()) {
            return 'https://schema.org/InStock';
        }

        if (method_exists($product, 'is_on_backorder') && $product->is_on_backorder()) {
            return 'https://schema.org/BackOrder';
        }

        return 'https://schema.org/OutOfStock';
    }

    /**
     * Product brand from taxonomy/attribute/meta/global org.
     *
     * @param mixed $product
     * @return array
     */
    private function get_product_brand($product) {

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

        $attribute_brand = $this->get_product_attribute_value($product, ['pa_brand', 'brand']);

        if ($attribute_brand !== '') {
            return [
                'name' => $attribute_brand,
                'url'  => '',
            ];
        }

        $meta_brand = $this->get_first_product_meta($product_id, [
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

    /**
     * Product identifiers.
     *
     * @param mixed  $product
     * @param string $type
     * @return string
     */
    private function get_product_identifier($product, $type) {

        if (!$product || !method_exists($product, 'get_id')) {
            return '';
        }

        $product_id = $product->get_id();

        if ($type === 'gtin') {

            $meta_value = $this->get_first_product_meta($product_id, [
                '_gtin',
                'gtin',
                '_global_unique_id',
                '_wpm_gtin_code',
                '_alg_ean',
                '_ts_gtin',
                '_wc_gpf_gtin',
                '_woocommerce_gpf_data_gtin',
            ]);

            if ($meta_value !== '') {
                return $meta_value;
            }

            return $this->get_product_attribute_value($product, [
                'gtin',
                'pa_gtin',
                'gtin13',
                'pa_gtin13',
                'ean',
                'pa_ean',
            ]);
        }

        if ($type === 'mpn') {

            $meta_value = $this->get_first_product_meta($product_id, [
                '_mpn',
                'mpn',
                '_manufacturer_part_number',
                '_amk_mpn',
            ]);

            if ($meta_value !== '') {
                return $meta_value;
            }

            return $this->get_product_attribute_value($product, [
                'mpn',
                'pa_mpn',
            ]);
        }

        return '';
    }

    /**
     * Variation prices.
     *
     * @param mixed $product
     * @return array
     */
    private function get_product_variation_data($product) {

        $data = [
            'min_price'       => '',
            'max_price'       => '',
            'variation_count' => 0,
            'varies_by'       => [],
            'variants'        => [],
        ];

        if (!$product || !method_exists($product, 'get_price')) {
            return $data;
        }

        if ($this->product_is_type($product, 'variable')) {

            $data['min_price'] = method_exists($product, 'get_variation_price')
                ? $this->format_schema_price($product->get_variation_price('min', true), $product)
                : '';

            $data['max_price'] = method_exists($product, 'get_variation_price')
                ? $this->format_schema_price($product->get_variation_price('max', true), $product)
                : '';

            $children = method_exists($product, 'get_children')
                ? $product->get_children()
                : [];

            $data['variation_count'] = is_array($children) ? count($children) : 0;
            $data['varies_by']       = $this->get_product_varies_by($product);
            $data['variants']        = $this->get_product_variants($product);

            return $data;
        }

        $price = $this->format_schema_price($product->get_price(), $product);

        $data['min_price'] = $price;
        $data['max_price'] = $price;

        return $data;
    }

    /**
     * Check WooCommerce product type safely.
     *
     * @param mixed  $product
     * @param string $type
     * @return bool
     */
    private function product_is_type($product, $type) {

        if (!$product || !method_exists($product, 'is_type')) {
            return false;
        }

        return $product->is_type($type);
    }

    /**
     * Check active WooCommerce sale state safely.
     *
     * @param mixed $product
     * @return bool
     */
    private function product_is_on_sale($product) {

        if (!$product || !method_exists($product, 'is_on_sale')) {
            return false;
        }

        return (bool) $product->is_on_sale();
    }

    /**
     * Build simple Product > Offer data.
     *
     * For simple products, this uses get_price() so an active WooCommerce sale
     * price becomes the schema price. For variable parent products this method
     * should not be used as the final parent offer; variants get their own offers.
     *
     * @param mixed  $product
     * @param string $url
     * @return array
     */
    private function build_product_offer_data($product, $url = '') {

        if (!$product || !method_exists($product, 'get_price')) {
            return [];
        }

        $price = $this->format_schema_price($product->get_price(), $product);

        if ($price === '') {
            return [];
        }

        $offer = [
            '@type'                   => 'Offer',
            'url'                     => $url,
            'price'                   => $price,
            'priceCurrency'           => $this->get_schema_price_currency(),
            'availability'            => $this->get_product_availability($product),
            'itemCondition'           => 'https://schema.org/NewCondition',
            'seller'                  => [
                '@id' => home_url('/#organization'),
            ],
            'priceValidUntil'         => $this->get_product_price_valid_until($product),
            'hasMerchantReturnPolicy' => $this->get_product_offer_return_policy(),
            'shippingDetails'         => $this->get_product_offer_shipping_details($product),
        ];

        return $this->deep_cleanup_array($offer);
    }

    /**
     * Return policy for Product > Offer merchant listing markup.
     *
     * Google reports missing Merchant listing fields specifically inside
     * Product offers, so this intentionally mirrors the global merchant policy
     * into each Offer instead of relying only on Organization-level markup.
     *
     * @return array
     */
    private function get_product_offer_return_policy() {

        $commerce = $this->get_schema_commerce_data();

        return !empty($commerce['merchant_return_policy']) && is_array($commerce['merchant_return_policy'])
            ? $commerce['merchant_return_policy']
            : [];
    }

    /**
     * Shipping details for Product > Offer merchant listing markup.
     *
     * @param mixed $product
     * @return array
     */
    private function get_product_offer_shipping_details($product = null) {

        $commerce = $this->get_schema_commerce_data();

        if (empty($commerce['offer_shipping_details']) || !is_array($commerce['offer_shipping_details'])) {
            return [];
        }

        /**
         * Filter product offer shipping details.
         *
         * @param array $shipping_details
         * @param mixed $product
         */
        $shipping_details = apply_filters(
            'amk_schema_core_product_offer_shipping_details',
            $commerce['offer_shipping_details'],
            $product
        );

        return is_array($shipping_details) ? $this->deep_cleanup_array($shipping_details) : [];
    }

    /**
     * Cached commerce data used by product offers.
     *
     * @return array
     */
    private function get_schema_commerce_data() {

        if ($this->schema_commerce_data_cache !== null) {
            return $this->schema_commerce_data_cache;
        }

        $settings = GlobalSettings::get();
        $resolver_data = GlobalSettings::to_resolver_data($settings);

        $return_policy = !empty($resolver_data['merchant_return_policy']) && is_array($resolver_data['merchant_return_policy'])
            ? $resolver_data['merchant_return_policy']
            : [];

        $offer_shipping_details = $this->build_offer_shipping_details_from_settings($settings);

        /**
         * Filter commerce data used inside Product > Offer markup.
         *
         * @param array $data
         * @param array $settings
         */
        $data = apply_filters('amk_schema_core_product_offer_commerce_data', [
            'merchant_return_policy' => $return_policy,
            'offer_shipping_details' => $offer_shipping_details,
        ], $settings);

        $this->schema_commerce_data_cache = is_array($data) ? $data : [];

        return $this->schema_commerce_data_cache;
    }

    /**
     * Build Schema.org OfferShippingDetails from plugin commerce settings.
     *
     * This does not invent shipping information. It only emits markup when the
     * merchant has enabled shipping data and at least a destination country is
     * known.
     *
     * @param array $settings
     * @return array
     */
    private function build_offer_shipping_details_from_settings($settings) {

        if (empty($settings['commerce']['enabled']) || empty($settings['commerce']['shipping_enabled'])) {
            return [];
        }

        $commerce = isset($settings['commerce']) && is_array($settings['commerce']) ? $settings['commerce'] : [];
        $country = $this->normalize_schema_country_code($commerce['shipping_country'] ?? '');
        $countries = $this->normalize_schema_country_list($commerce['shipping_countries'] ?? []);
        $mode = sanitize_key($commerce['shipping_mode'] ?? 'worldwide');

        if ($mode === 'specific_countries' && empty($countries) && $country === '') {
            return [];
        }

        $shipping_details = [
            '@type' => 'OfferShippingDetails',
            'shippingDestination' => $mode === 'specific_countries'
                ? $this->build_offer_shipping_destinations($countries, $country)
                : [],
            'shippingRate' => $this->build_offer_shipping_rate($commerce),
            'deliveryTime' => $this->build_offer_shipping_delivery_time($commerce),
        ];

        return $this->deep_cleanup_array($shipping_details);
    }

    /**
     * Build shipping rate as MonetaryAmount.
     *
     * @param array $commerce
     * @return array
     */
    private function build_offer_shipping_rate($commerce) {

        $rate = isset($commerce['shipping_rate']) ? $commerce['shipping_rate'] : '';

        if ($rate === '' || !is_numeric($rate)) {
            return [];
        }

        return [
            '@type'    => 'MonetaryAmount',
            'value'    => $this->format_schema_price($rate),
            'currency' => $this->get_schema_price_currency(),
        ];
    }

    /**
     * Build shipping delivery time.
     *
     * @param array $commerce
     * @return array
     */
    private function build_offer_shipping_delivery_time($commerce) {

        $handling_time = $this->build_quantitative_value_range_schema(
            $commerce['handling_min_days'] ?? '',
            $commerce['handling_max_days'] ?? '',
            'DAY'
        );

        $transit_time = $this->build_quantitative_value_range_schema(
            $commerce['transit_min_days'] ?? '',
            $commerce['transit_max_days'] ?? '',
            'DAY'
        );

        return $this->deep_cleanup_array([
            '@type'        => 'ShippingDeliveryTime',
            'handlingTime' => $handling_time,
            'transitTime'  => $transit_time,
        ]);
    }

    /**
     * Build QuantitativeValue range for delivery time.
     *
     * @param mixed  $min
     * @param mixed  $max
     * @param string $unit_code
     * @return array
     */
    private function build_quantitative_value_range_schema($min, $max, $unit_code = 'DAY') {

        $min = ($min !== '' && is_numeric($min)) ? absint($min) : '';
        $max = ($max !== '' && is_numeric($max)) ? absint($max) : '';

        if ($min === '' && $max === '') {
            return [];
        }

        $data = [
            '@type'    => 'QuantitativeValue',
            'unitCode' => $unit_code,
        ];

        if ($min !== '') {
            $data['minValue'] = $min;
        }

        if ($max !== '') {
            $data['maxValue'] = $max;
        }

        return $this->deep_cleanup_array($data);
    }

    /**
     * Normalize a list of country values for Schema.org output.
     *
     * Accepts arrays and legacy comma/pipe-separated strings, removes the
     * non-ISO WORLDWIDE sentinel, normalizes supported aliases, and returns
     * only unique ISO 3166-1 alpha-2 country codes.
     *
     * @param mixed $countries
     * @return array
     */
    private function normalize_schema_country_list($countries) {

        if (is_string($countries)) {
            $countries = preg_split('/[,|]+/', $countries);
        }

        if (!is_array($countries)) {
            return [];
        }

        $normalized_countries = [];

        foreach ($countries as $country) {
            $raw_country = strtoupper(trim((string) $country));

            if ($raw_country === '' || $raw_country === 'WORLDWIDE' || $raw_country === 'WORLD WIDE') {
                continue;
            }

            $normalized_country = $this->normalize_schema_country_code($country);

            if (preg_match('/^[A-Z]{2}$/', $normalized_country)) {
                $normalized_countries[] = $normalized_country;
            }
        }

        return array_values(array_unique($normalized_countries));
    }

    /**
     * Build shipping destination regions for OfferShippingDetails.
     *
     * The legacy single-country value is used only as a fallback when the new
     * multi-country setting is empty. No synthetic worldwide country value is
     * emitted because Schema.org expects real country codes in addressCountry.
     *
     * @param mixed  $countries
     * @param string $fallback_country
     * @return array
     */
    private function build_offer_shipping_destinations($countries, $fallback_country = '') {

        $countries = $this->normalize_schema_country_list($countries);
        $fallback_country = $this->normalize_schema_country_code($fallback_country);

        if (empty($countries) && preg_match('/^[A-Z]{2}$/', $fallback_country)) {
            $countries[] = $fallback_country;
        }

        $destinations = [];

        foreach ($countries as $country) {
            $destinations[] = [
                '@type'          => 'DefinedRegion',
                'addressCountry' => $country,
            ];
        }

        return $destinations;
    }

    /**
     * Normalize country values for schema output.
     *
     * @param mixed $country
     * @return string
     */
    private function normalize_schema_country_code($country) {

        $country = trim((string) $country);

        if ($country === '') {
            return '';
        }

        $country = str_replace(' ', '', $country);

        if (strpos($country, ':') !== false) {
            $parts = explode(':', $country);
            $country = $parts[0];
        }

        $normalized = strtoupper($country);

        $aliases = [
            'IRAN' => 'IR',
            'IRN'  => 'IR',
            'ایران' => 'IR',
        ];

        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        if (isset($aliases[$country])) {
            return $aliases[$country];
        }

        if (preg_match('/^[A-Z]{2}$/', $normalized)) {
            return $normalized;
        }

        return $country;
    }

    /**
     * Build Brand schema from resolved brand data.
     *
     * @param array $brand
     * @return array
     */
    private function build_product_brand_schema($brand) {

        if (empty($brand['name'])) {
            return [];
        }

        $schema = [
            '@type' => 'Brand',
            'name'  => $brand['name'],
            'url'   => !empty($brand['url']) ? $brand['url'] : '',
        ];

        return $this->deep_cleanup_array($schema);
    }

    /**
     * Build stable ProductGroup identifier.
     *
     * @param mixed $product
     * @return string
     */
    private function get_product_group_identifier($product) {

        if (!$product || !method_exists($product, 'get_id')) {
            return '';
        }

        if (method_exists($product, 'get_sku')) {
            $sku = trim((string) $product->get_sku());

            if ($sku !== '') {
                return $sku;
            }
        }

        return 'product-' . absint($product->get_id());
    }

    /**
     * Schema.org variesBy values for WooCommerce variable product attributes.
     *
     * @param mixed $product
     * @return array
     */
    private function get_product_varies_by($product) {

        if (!$product || !method_exists($product, 'get_variation_attributes')) {
            return [];
        }

        $attributes = $product->get_variation_attributes();

        if (empty($attributes) || !is_array($attributes)) {
            return [];
        }

        $values = [];

        foreach ($attributes as $attribute_name => $attribute_values) {
            $field = $this->get_direct_product_field_for_attribute($attribute_name, $this->get_attribute_schema_label($attribute_name, $product));

            if ($field !== '') {
                $values[] = 'https://schema.org/' . $field;
                continue;
            }

            $label = $this->get_attribute_schema_label($attribute_name, $product);

            if ($label !== '') {
                $values[] = $label;
            }
        }

        return array_values(array_unique(array_filter($values)));
    }

    /**
     * Build schema-ready variants for ProductGroup.hasVariant.
     *
     * @param mixed $product
     * @return array
     */
    private function get_product_variants($product) {

        if (!$product || !method_exists($product, 'get_children') || !function_exists('wc_get_product')) {
            return [];
        }

        $parent_id = method_exists($product, 'get_id') ? absint($product->get_id()) : 0;

        if (!$parent_id || !function_exists('get_permalink')) {
            return [];
        }

        $parent_url = trailingslashit(get_permalink($parent_id));
        $group_id   = $parent_url . '#product';
        $children   = $product->get_children();

        if (empty($children) || !is_array($children)) {
            return [];
        }

        $variants = [];

        foreach ($children as $variation_id) {
            $variation_id = absint($variation_id);

            if (!$variation_id) {
                continue;
            }

            $variation = wc_get_product($variation_id);

            if (!$variation) {
                continue;
            }

            if (method_exists($variation, 'get_status') && $variation->get_status() !== 'publish') {
                continue;
            }

            $price = method_exists($variation, 'get_price') ? $this->format_schema_price($variation->get_price(), $variation) : '';

            if ($price === '') {
                continue;
            }

            $variant_url = $this->get_variation_url($variation, $parent_url);

            $variant = [
                '@type'       => 'Product',
                '@id'         => $parent_url . '#variant-' . $variation_id,
                'name'        => $this->get_variation_name($variation),
                'sku'         => $this->get_variation_sku($variation),
                'gtin'        => $this->get_product_identifier($variation, 'gtin'),
                'mpn'         => $this->get_product_identifier($variation, 'mpn'),
                'url'         => $variant_url,
                'image'       => $this->get_variation_image($variation, $product),
                'description' => $this->get_variation_description($variation, $product),
                'isVariantOf' => [
                    '@id' => $group_id,
                ],
                'offers'      => $this->build_product_offer_data($variation, $variant_url),
            ];

            $variant = $this->add_variation_attributes($variant, $variation, $product);
            $variant = $this->deep_cleanup_array($variant);

            if (!empty($variant)) {
                $variants[] = $variant;
            }
        }

        return $variants;
    }

    /**
     * Variation URL with selected attributes as query args.
     *
     * @param mixed  $variation
     * @param string $parent_url
     * @return string
     */
    private function get_variation_url($variation, $parent_url) {

        if (!$variation || !method_exists($variation, 'get_attributes')) {
            return $parent_url;
        }

        $attributes = $variation->get_attributes();

        if (empty($attributes) || !is_array($attributes)) {
            return $parent_url;
        }

        $query_args = [];

        foreach ($attributes as $name => $value) {
            $name  = (string) $name;
            $value = (string) $value;

            if ($name === '' || $value === '') {
                continue;
            }

            $query_args['attribute_' . sanitize_title($name)] = $value;
        }

        return !empty($query_args) ? add_query_arg($query_args, $parent_url) : $parent_url;
    }

    /**
     * Variation display name.
     *
     * @param mixed $variation
     * @return string
     */
    private function get_variation_name($variation) {

        if (!$variation) {
            return '';
        }

        if (method_exists($variation, 'get_name')) {
            return $this->clean_text($variation->get_name());
        }

        return '';
    }

    /**
     * Variation SKU with fallback ID.
     *
     * @param mixed $variation
     * @return string
     */
    private function get_variation_sku($variation) {

        if (!$variation || !method_exists($variation, 'get_id')) {
            return '';
        }

        if (method_exists($variation, 'get_sku')) {
            $sku = trim((string) $variation->get_sku());

            if ($sku !== '') {
                return $sku;
            }
        }

        return 'variation-' . absint($variation->get_id());
    }

    /**
     * Variation image with parent fallback.
     *
     * @param mixed $variation
     * @param mixed $parent_product
     * @return string
     */
    private function get_variation_image($variation, $parent_product) {

        $image = $this->get_product_image($variation);

        if ($image !== '') {
            return $image;
        }

        return $this->get_product_image($parent_product);
    }

    /**
     * Variation description with parent fallback.
     *
     * @param mixed $variation
     * @param mixed $parent_product
     * @return string
     */
    private function get_variation_description($variation, $parent_product) {

        if ($variation && method_exists($variation, 'get_description')) {
            $description = $this->clean_text($variation->get_description());

            if ($description !== '') {
                return $description;
            }
        }

        if ($parent_product && method_exists($parent_product, 'get_short_description')) {
            $short = $this->clean_text($parent_product->get_short_description());

            if ($short !== '') {
                return $short;
            }
        }

        if ($parent_product && method_exists($parent_product, 'get_description')) {
            return $this->clean_text($parent_product->get_description());
        }

        return '';
    }

    /**
     * Add variation attributes to Product schema.
     *
     * @param array $schema
     * @param mixed $variation
     * @param mixed $parent_product
     * @return array
     */
    private function add_variation_attributes($schema, $variation, $parent_product) {

        if (!$variation || !method_exists($variation, 'get_attributes')) {
            return $schema;
        }

        $attributes = $variation->get_attributes();

        if (empty($attributes) || !is_array($attributes)) {
            return $schema;
        }

        $additional = [];

        foreach ($attributes as $attribute_name => $attribute_value) {
            $attribute_name  = (string) $attribute_name;
            $attribute_value = (string) $attribute_value;

            if ($attribute_name === '' || $attribute_value === '') {
                continue;
            }

            $label = $this->get_attribute_schema_label($attribute_name, $parent_product);
            $value = $this->get_attribute_schema_value_label($attribute_name, $attribute_value);

            if ($label === '' || $value === '') {
                continue;
            }

            $additional[] = [
                '@type' => 'PropertyValue',
                'name'  => $label,
                'value' => $value,
            ];

            $direct_field = $this->get_direct_product_field_for_attribute($attribute_name, $label);

            if ($direct_field !== '' && empty($schema[$direct_field])) {
                $schema[$direct_field] = $value;
            }
        }

        if (!empty($additional)) {
            $schema['additionalProperty'] = $additional;
        }

        return $schema;
    }

    /**
     * Attribute label for schema output.
     *
     * @param string $attribute_name
     * @param mixed  $product
     * @return string
     */
    private function get_attribute_schema_label($attribute_name, $product = null) {

        $attribute_name = (string) $attribute_name;
        $clean_name     = preg_replace('/^attribute_/', '', $attribute_name);
        $clean_name     = preg_replace('/^pa_/', '', $clean_name);
        $label          = '';

        if (function_exists('wc_attribute_label')) {
            $label = wc_attribute_label($clean_name, $product);
        }

        if ($label === '' || $label === $clean_name) {
            $label = ucwords(str_replace(['pa_', '-', '_'], ['', ' ', ' '], $clean_name));
        }

        return $this->clean_text($label);
    }

    /**
     * Attribute value label for taxonomy or custom attributes.
     *
     * @param string $attribute_name
     * @param string $attribute_value
     * @return string
     */
    private function get_attribute_schema_value_label($attribute_name, $attribute_value) {

        $attribute_name  = preg_replace('/^attribute_/', '', (string) $attribute_name);
        $attribute_value = (string) $attribute_value;

        if ($attribute_value === '') {
            return '';
        }

        $taxonomy = strpos($attribute_name, 'pa_') === 0 ? $attribute_name : 'pa_' . $attribute_name;

        if (taxonomy_exists($taxonomy)) {
            $term = get_term_by('slug', $attribute_value, $taxonomy);

            if (!$term || is_wp_error($term)) {
                $term = get_term_by('name', $attribute_value, $taxonomy);
            }

            if ($term && !is_wp_error($term) && !empty($term->name)) {
                return $this->clean_text($term->name);
            }
        }

        return $this->clean_text(str_replace(['-', '_'], ' ', $attribute_value));
    }

    /**
     * Map common variation attributes to direct Product fields.
     *
     * @param string $attribute_name
     * @param string $label
     * @return string
     */
    private function get_direct_product_field_for_attribute($attribute_name, $label = '') {

        $key = strtolower((string) $attribute_name . ' ' . (string) $label);

        if (strpos($key, 'color') !== false || strpos($key, 'colour') !== false || strpos($key, 'رنگ') !== false) {
            return 'color';
        }

        if (strpos($key, 'size') !== false || strpos($key, 'سایز') !== false || strpos($key, 'اندازه') !== false) {
            return 'size';
        }

        if (strpos($key, 'material') !== false || strpos($key, 'متریال') !== false || strpos($key, 'جنس') !== false) {
            return 'material';
        }

        if (strpos($key, 'pattern') !== false || strpos($key, 'طرح') !== false) {
            return 'pattern';
        }

        return '';
    }

    /**
     * Normalize currency codes for Schema.org/Google output.
     *
     * Schema.org and Google expect ISO 4217-style currency codes. Iran's
     * official ISO code is IRR. Toman codes used by Persian WooCommerce plugins
     * are normalized to IRR and their amounts are converted separately.
     *
     * @param mixed $currency
     * @return string
     */
    private function normalize_currency_code($currency) {

        $currency = strtoupper(trim((string) $currency));

        $toman_codes = [
            'IRT',
            'TOM',
            'TOMAN',
            'IRHT',
            'IRTMN',
        ];

        if (in_array($currency, $toman_codes, true)) {
            return 'IRR';
        }

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            return '';
        }

        return $currency;
    }

    /**
     * Get Schema.org-safe WooCommerce currency code.
     *
     * @return string
     */
    private function get_schema_price_currency() {

        $currency = function_exists('get_woocommerce_currency')
            ? get_woocommerce_currency()
            : '';

        $currency = $this->normalize_currency_code($currency);

        /**
         * Filter schema price currency.
         *
         * @param string $currency
         */
        $currency = apply_filters('amk_schema_core_schema_price_currency', $currency);

        return is_string($currency) ? strtoupper(trim($currency)) : '';
    }

    /**
     * Format price for schema.
     *
     * For Iranian stores, the schema output should use IRR. If WooCommerce is
     * effectively storing/displaying prices as toman, the numeric amount is
     * converted to rial automatically before rendering JSON-LD.
     *
     * @param mixed $price
     * @param mixed $product Optional WooCommerce product object for stronger detection.
     * @return string
     */
    private function format_schema_price($price, $product = null) {

        if ($price === '' || $price === null || !is_numeric($price)) {
            return '';
        }

        $price = (float) $price;
        $price = $this->convert_price_to_schema_currency($price, $product);

        /**
         * Filter final schema price amount before formatting.
         *
         * @param float $price
         * @param mixed $product
         */
        $price = apply_filters('amk_schema_core_schema_price_amount', $price, $product);

        if (!is_numeric($price)) {
            return '';
        }

        $decimals = $this->get_schema_price_decimals();

        if (function_exists('wc_format_decimal')) {
            return (string) wc_format_decimal($price, $decimals);
        }

        return number_format((float) $price, $decimals, '.', '');
    }

    /**
     * Convert WooCommerce price amount to the schema currency amount.
     *
     * @param float $price
     * @param mixed $product
     * @return float
     */
    private function convert_price_to_schema_currency($price, $product = null) {

        $currency = $this->get_schema_price_currency();

        if ($currency !== 'IRR') {
            return $price;
        }

        $unit = $this->detect_schema_price_unit($price, $product);

        if ($unit === 'toman') {
            return $price * 10;
        }

        return $price;
    }

    /**
     * Detect whether WooCommerce numeric prices are effectively toman or rial.
     *
     * This is intentionally automatic for commercial use. It uses, in order:
     * - explicit developer filter result
     * - product price HTML compared with raw price and raw/10
     * - WooCommerce currency code
     * - WooCommerce currency symbol / wc_price(1) output
     *
     * @param float $price
     * @param mixed $product
     * @return string 'toman' or 'rial'
     */
    private function detect_schema_price_unit($price = 0.0, $product = null) {

        /**
         * Force detected price unit for schema conversion.
         *
         * Return 'toman' or 'rial' to override automatic detection.
         * Return empty/null to keep automatic detection.
         *
         * @param string|null $unit
         * @param float       $price
         * @param mixed       $product
         */
        $forced = apply_filters('amk_schema_core_detected_price_unit', null, $price, $product);

        if ($forced === 'toman' || $forced === 'rial') {
            return $forced;
        }

        $product_unit = $this->detect_price_unit_from_product_html($price, $product);

        if ($product_unit !== '') {
            return $product_unit;
        }

        $global_unit = $this->detect_global_price_unit();

        return $global_unit !== '' ? $global_unit : 'rial';
    }

    /**
     * Detect price unit from product price HTML.
     *
     * This catches the most common Persian WooCommerce cases:
     * - raw price 5986000 and HTML "5,986,000 toman" => raw is toman
     * - raw price 5986000 and HTML "598,600 toman" => raw is rial, displayed as toman
     *
     * @param float $price
     * @param mixed $product
     * @return string
     */
    private function detect_price_unit_from_product_html($price, $product = null) {

        if (!$product || !method_exists($product, 'get_price_html')) {
            return '';
        }

        $html = (string) $product->get_price_html();
        $text = $this->normalize_price_text($html);

        if ($text === '' || !$this->text_has_toman_hint($text)) {
            return '';
        }

        $numbers = $this->extract_integer_numbers_from_text($text);

        if (empty($numbers)) {
            return '';
        }

        $raw_price       = (int) round(abs((float) $price));
        $toman_from_rial = (int) round($raw_price / 10);

        if ($raw_price <= 0) {
            return '';
        }

        $has_raw_price       = in_array($raw_price, $numbers, true);
        $has_toman_from_rial = $toman_from_rial > 0 && in_array($toman_from_rial, $numbers, true);

        if ($has_toman_from_rial && !$has_raw_price) {
            return 'rial';
        }

        if ($has_raw_price) {
            return 'toman';
        }

        return '';
    }

    /**
     * Detect global WooCommerce price unit.
     *
     * @return string
     */
    private function detect_global_price_unit() {

        if ($this->schema_price_unit_cache !== null) {
            return $this->schema_price_unit_cache;
        }

        $currency = function_exists('get_woocommerce_currency')
            ? strtoupper(trim((string) get_woocommerce_currency()))
            : '';

        $toman_codes = [
            'IRT',
            'TOM',
            'TOMAN',
            'IRHT',
            'IRTMN',
        ];

        if (in_array($currency, $toman_codes, true)) {
            $this->schema_price_unit_cache = 'toman';
            return $this->schema_price_unit_cache;
        }

        $symbol_text = '';

        if (function_exists('get_woocommerce_currency_symbol')) {
            $symbol_text .= ' ' . get_woocommerce_currency_symbol($currency ?: 'IRR');
        }

        if (function_exists('wc_price')) {
            $symbol_text .= ' ' . wp_strip_all_tags(wc_price(1, [
                'currency' => $currency ?: 'IRR',
            ]));
        }

        $symbol_text = $this->normalize_price_text($symbol_text);

        if ($this->text_has_toman_hint($symbol_text)) {
            $this->schema_price_unit_cache = 'toman';
            return $this->schema_price_unit_cache;
        }

        $this->schema_price_unit_cache = 'rial';
        return $this->schema_price_unit_cache;
    }

    /**
     * Schema price decimals.
     *
     * @return int
     */
    private function get_schema_price_decimals() {

        $currency = $this->get_schema_price_currency();

        if ($currency === 'IRR') {
            return 0;
        }

        return function_exists('wc_get_price_decimals')
            ? absint(wc_get_price_decimals())
            : 2;
    }

    /**
     * Normalize price text for detection.
     *
     * @param string $text
     * @return string
     */
    private function normalize_price_text($text) {

        $text = wp_strip_all_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
        $text = $this->normalize_digits($text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return strtolower(trim($text));
    }

    /**
     * Convert Persian/Arabic digits to Latin digits.
     *
     * @param string $text
     * @return string
     */
    private function normalize_digits($text) {

        return strtr((string) $text, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]);
    }

    /**
     * Check text for toman hints.
     *
     * @param string $text
     * @return bool
     */
    private function text_has_toman_hint($text) {

        $text = strtolower((string) $text);

        $hints = [
            'تومان',
            'تومن',
            'toman',
            'tomans',
            'irt',
        ];

        foreach ($hints as $hint) {
            if ($hint !== '' && strpos($text, $hint) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract integer amounts from a price text.
     *
     * @param string $text
     * @return array
     */
    private function extract_integer_numbers_from_text($text) {

        $text = $this->normalize_digits($text);

        if (!preg_match_all('/[0-9][0-9\s,\.٬،]*/u', $text, $matches)) {
            return [];
        }

        $numbers = [];

        foreach ($matches[0] as $match) {
            $digits = preg_replace('/[^0-9]/', '', $match);

            if ($digits === '') {
                continue;
            }

            $numbers[] = (int) $digits;
        }

        return array_values(array_unique($numbers));
    }

    /**
     * Future sale end date for Offer.priceValidUntil.
     *
     * @param mixed $product
     * @return string
     */
    private function get_product_price_valid_until($product) {

        if (!$this->product_is_on_sale($product)) {
            return '';
        }

        $date = $this->get_product_sale_date_object($product, 'to');

        if (!$date) {
            return '';
        }

        $timestamp = method_exists($date, 'getTimestamp') ? $date->getTimestamp() : 0;

        if (!$timestamp || $timestamp <= time()) {
            return '';
        }

        return method_exists($date, 'date') ? $date->date('Y-m-d') : '';
    }

    /**
     * Sale start/end date in ISO 8601 format.
     *
     * @param mixed  $product
     * @param string $direction from|to
     * @return string
     */
    private function get_product_sale_date($product, $direction = 'to') {

        $date = $this->get_product_sale_date_object($product, $direction);

        return ($date && method_exists($date, 'date')) ? $date->date('c') : '';
    }

    /**
     * WooCommerce sale date object.
     *
     * @param mixed  $product
     * @param string $direction from|to
     * @return mixed|null
     */
    private function get_product_sale_date_object($product, $direction = 'to') {

        if (!$product) {
            return null;
        }

        if ($direction === 'from' && method_exists($product, 'get_date_on_sale_from')) {
            return $product->get_date_on_sale_from();
        }

        if (method_exists($product, 'get_date_on_sale_to')) {
            return $product->get_date_on_sale_to();
        }

        return null;
    }

    /**
     * Remove empty values recursively. Keeps false and zero.
     *
     * @param mixed $value
     * @return mixed
     */
    private function deep_cleanup_array($value) {

        if (!is_array($value)) {
            return $value;
        }

        $clean = [];

        foreach ($value as $key => $item) {
            $item = $this->deep_cleanup_array($item);

            if ($item === null) {
                continue;
            }

            if (is_string($item) && trim($item) === '') {
                continue;
            }

            if (is_array($item) && empty($item)) {
                continue;
            }

            $clean[$key] = $item;
        }

        return $clean;
    }

    /**
     * Product attribute value.
     *
     * @param mixed $product
     * @param array $attributes
     * @return string
     */
    private function get_product_attribute_value($product, $attributes) {

        if (!$product || !method_exists($product, 'get_attribute')) {
            return '';
        }

        foreach ($attributes as $attribute) {

            $value = $product->get_attribute($attribute);

            if (!empty($value)) {
                return $this->clean_text($value);
            }
        }

        return '';
    }

    /**
     * First non-empty product meta.
     *
     * @param int   $product_id
     * @param array $meta_keys
     * @return string
     */
    private function get_first_product_meta($product_id, $meta_keys) {

        foreach ($meta_keys as $meta_key) {

            $value = get_post_meta($product_id, $meta_key, true);

            if ($value !== '' && $value !== null) {
                return is_scalar($value) ? sanitize_text_field((string) $value) : '';
            }
        }

        return '';
    }

    /**
     * Product description optimized for schema output.
     *
     * Priority:
     * 1. SEO meta description
     * 2. WooCommerce short description
     * 3. WordPress excerpt
     * 4. Full product description
     *
     * Full content can contain videos, galleries, builders and shortcodes. Schema
     * description must be plain, concise, and human-readable.
     *
     * @param mixed $product
     * @return string
     */
    private function get_product_schema_description($product) {

        if (!$product || !method_exists($product, 'get_id')) {
            return '';
        }

        $product_id = absint($product->get_id());
        $candidates = [];

        if ($product_id) {
            $candidates[] = get_post_meta($product_id, '_yoast_wpseo_metadesc', true);
            $candidates[] = get_post_meta($product_id, 'rank_math_description', true);
            $candidates[] = get_post_meta($product_id, '_aioseo_description', true);
        }

        if (method_exists($product, 'get_short_description')) {
            $candidates[] = $product->get_short_description();
        }

        if ($product_id && function_exists('get_the_excerpt')) {
            $candidates[] = get_the_excerpt($product_id);
        }

        if (method_exists($product, 'get_description')) {
            $candidates[] = $product->get_description();
        }

        foreach ($candidates as $candidate) {
            $description = $this->clean_schema_description($candidate, 55);

            if ($description !== '') {
                /**
                 * Filter product schema description.
                 *
                 * @param string $description
                 * @param mixed  $product
                 */
                return apply_filters('amk_schema_core_product_schema_description', $description, $product);
            }
        }

        return '';
    }

    /**
     * Clean long schema descriptions.
     *
     * @param mixed $text
     * @param int   $word_limit
     * @return string
     */
    private function clean_schema_description($text, $word_limit = 55) {

        $text = $this->clean_text($text);

        if ($text === '') {
            return '';
        }

        $word_limit = max(20, absint($word_limit));

        if (function_exists('wp_trim_words')) {
            $text = wp_trim_words($text, $word_limit, '');
        }

        return trim($text);
    }

    /**
     * Post description from excerpt/content/meta.
     *
     * @param int $post_id
     * @return string
     */
    private function get_post_description($post_id) {

        $seo_description = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);

        if (!empty($seo_description)) {
            return $this->clean_text($seo_description);
        }

        $rank_math_description = get_post_meta($post_id, 'rank_math_description', true);

        if (!empty($rank_math_description)) {
            return $this->clean_text($rank_math_description);
        }

        $excerpt = get_the_excerpt($post_id);

        if (!empty($excerpt)) {
            return $this->clean_text($excerpt);
        }

        $content = get_post_field('post_content', $post_id);

        return wp_trim_words($this->clean_text($content), 35, '');
    }


    /**
     * Get Schema.org language code from WordPress locale.
     *
     * @return string
     */
    private function get_schema_language() {

        $locale = '';

        if (function_exists('determine_locale')) {
            $locale = determine_locale();
        }

        if (!$locale && function_exists('get_locale')) {
            $locale = get_locale();
        }

        if (!$locale) {
            $locale = get_bloginfo('language');
        }

        if (!$locale) {
            $locale = 'en-US';
        }

        return str_replace('_', '-', sanitize_text_field($locale));
    }

    /**
     * Localized static breadcrumb labels.
     *
     * @param string $key
     * @return string
     */
    private function get_breadcrumb_label($key) {

        $language = strtolower($this->get_schema_language());

        $labels = [
            'fa' => [
                'home'      => __('Home', 'amk-schema-core'),
                'shop'      => __('Shop', 'amk-schema-core'),
                'search'    => __('Search', 'amk-schema-core'),
                'not_found' => __('Page not found', 'amk-schema-core'),
            ],
            'ar' => [
                'home'      => __('Home', 'amk-schema-core'),
                'shop'      => __('Shop', 'amk-schema-core'),
                'search'    => __('Search', 'amk-schema-core'),
                'not_found' => __('Page not found', 'amk-schema-core'),
            ],
            'en' => [
                'home'      => 'Home',
                'shop'      => 'Shop',
                'search'    => 'Search',
                'not_found' => 'Page not found',
            ],
        ];

        if (strpos($language, 'fa') === 0) {
            return $labels['fa'][$key] ?? $labels['fa']['home'];
        }

        if (strpos($language, 'ar') === 0) {
            return $labels['ar'][$key] ?? $labels['ar']['home'];
        }

        return $labels['en'][$key] ?? $labels['en']['home'];
    }

    /**
     * Build BreadcrumbList itemListElement.
     *
     * @return array
     */
    private function build_breadcrumb_items() {

        if (function_exists('is_front_page') && is_front_page()) {
            return [];
        }

        $items = [];

        $this->add_breadcrumb_item($items, $this->get_breadcrumb_label('home'), home_url('/'));

        if (function_exists('is_singular') && is_singular('product') && function_exists('wc_get_page_permalink')) {

            $shop_url = wc_get_page_permalink('shop');

            if (!empty($shop_url)) {
                $this->add_breadcrumb_item($items, $this->get_breadcrumb_label('shop'), $shop_url);
            }

            $product_id = get_the_ID();
            $terms     = get_the_terms($product_id, 'product_cat');

            if (!empty($terms) && !is_wp_error($terms)) {
                $term = $this->get_deepest_term($terms);
                $this->add_term_ancestors_to_breadcrumb($items, $term, 'product_cat');
            }

            $this->add_breadcrumb_item($items, get_the_title($product_id), get_permalink($product_id));

            return $items;
        }

        if (function_exists('is_product_category') && is_product_category()) {

            if (function_exists('wc_get_page_permalink')) {
                $shop_url = wc_get_page_permalink('shop');

                if (!empty($shop_url)) {
                    $this->add_breadcrumb_item($items, $this->get_breadcrumb_label('shop'), $shop_url);
                }
            }

            $term = get_queried_object();

            if ($term && isset($term->term_id, $term->taxonomy)) {
                $this->add_term_ancestors_to_breadcrumb($items, $term, $term->taxonomy);
            }

            return $items;
        }

        if (function_exists('is_product_tag') && is_product_tag()) {

            if (function_exists('wc_get_page_permalink')) {
                $shop_url = wc_get_page_permalink('shop');

                if (!empty($shop_url)) {
                    $this->add_breadcrumb_item($items, $this->get_breadcrumb_label('shop'), $shop_url);
                }
            }

            $term = get_queried_object();

            if ($term && !empty($term->name)) {
                $this->add_breadcrumb_item($items, $term->name, get_term_link($term));
            }

            return $items;
        }

        if (function_exists('is_shop') && is_shop()) {

            if (function_exists('wc_get_page_permalink')) {
                $this->add_breadcrumb_item($items, $this->get_breadcrumb_label('shop'), wc_get_page_permalink('shop'));
            }

            return $items;
        }

        if (function_exists('is_singular') && is_singular('post')) {

            $post_id    = get_the_ID();
            $categories = get_the_category($post_id);

            if (!empty($categories)) {
                $category = $this->get_deepest_term($categories);
                $this->add_term_ancestors_to_breadcrumb($items, $category, 'category');
            }

            $this->add_breadcrumb_item($items, get_the_title($post_id), get_permalink($post_id));

            return $items;
        }

        if (function_exists('is_page') && is_page()) {

            $post_id   = get_the_ID();
            $ancestors = array_reverse(get_post_ancestors($post_id));

            foreach ($ancestors as $ancestor_id) {
                $this->add_breadcrumb_item($items, get_the_title($ancestor_id), get_permalink($ancestor_id));
            }

            $this->add_breadcrumb_item($items, get_the_title($post_id), get_permalink($post_id));

            return $items;
        }

        if (function_exists('is_category') && is_category()) {

            $term = get_queried_object();

            if ($term && isset($term->term_id, $term->taxonomy)) {
                $this->add_term_ancestors_to_breadcrumb($items, $term, $term->taxonomy);
            }

            return $items;
        }

        if (function_exists('is_tag') && is_tag()) {

            $term = get_queried_object();

            if ($term && !empty($term->name)) {
                $this->add_breadcrumb_item($items, $term->name, get_term_link($term));
            }

            return $items;
        }

        if (function_exists('is_author') && is_author()) {
            $author = get_queried_object();

            if ($author && !empty($author->display_name)) {
                $this->add_breadcrumb_item($items, $author->display_name, get_author_posts_url($author->ID));
            }

            return $items;
        }

        if (function_exists('is_search') && is_search()) {
            $this->add_breadcrumb_item($items, $this->get_breadcrumb_label('search'), $this->get_current_url());
            return $items;
        }

        if (function_exists('is_404') && is_404()) {
            $this->add_breadcrumb_item($items, $this->get_breadcrumb_label('not_found'), $this->get_current_url());
            return $items;
        }

        if (function_exists('is_archive') && is_archive()) {
            $this->add_breadcrumb_item($items, $this->clean_text(get_the_archive_title()), $this->get_current_url());
            return $items;
        }

        return $items;
    }

    /**
     * Add term ancestors + current term to breadcrumb.
     *
     * @param array  $items
     * @param object $term
     * @param string $taxonomy
     * @return void
     */
    private function add_term_ancestors_to_breadcrumb(&$items, $term, $taxonomy) {

        if (!$term || empty($term->term_id) || empty($taxonomy)) {
            return;
        }

        $ancestors = array_reverse(get_ancestors($term->term_id, $taxonomy));

        foreach ($ancestors as $ancestor_id) {
            $ancestor = get_term($ancestor_id, $taxonomy);

            if (!$ancestor || is_wp_error($ancestor)) {
                continue;
            }

            $this->add_breadcrumb_item($items, $ancestor->name, get_term_link($ancestor));
        }

        $this->add_breadcrumb_item($items, $term->name, get_term_link($term));
    }

    /**
     * Add one breadcrumb item.
     *
     * @param array  $items
     * @param string $name
     * @param string $url
     * @return void
     */
    private function add_breadcrumb_item(&$items, $name, $url) {

        if (empty($name)) {
            return;
        }

        if (is_wp_error($url)) {
            $url = '';
        }

        $items[] = [
            '@type'    => 'ListItem',
            'position' => count($items) + 1,
            'name'     => $this->clean_text($name),
            'item'     => esc_url_raw($url),
        ];
    }

    /**
     * Get deepest term from list.
     *
     * @param array $terms
     * @return mixed
     */
    private function get_deepest_term($terms) {

        $deepest   = reset($terms);
        $max_depth = -1;

        foreach ($terms as $term) {

            if (!$term || empty($term->term_id) || empty($term->taxonomy)) {
                continue;
            }

            $depth = count(get_ancestors($term->term_id, $term->taxonomy));

            if ($depth > $max_depth) {
                $max_depth = $depth;
                $deepest   = $term;
            }
        }

        return $deepest;
    }

    /**
     * Term image for product/category archives.
     *
     * @param object $term
     * @return string
     */
    private function get_term_image($term) {

        if (!$term || empty($term->term_id)) {
            return '';
        }

        $thumbnail_id = get_term_meta($term->term_id, 'thumbnail_id', true);

        if ($thumbnail_id) {
            $image = wp_get_attachment_image_url($thumbnail_id, 'full');

            if ($image) {
                return $image;
            }
        }

        $image_url = get_term_meta($term->term_id, 'image', true);

        return is_string($image_url) ? esc_url_raw($image_url) : '';
    }

    /**
     * Normalize rating.
     *
     * @param mixed $rating
     * @return string
     */
    private function normalize_rating($rating) {

        if ($rating === '' || $rating === null) {
            return '';
        }

        if (!is_numeric($rating)) {
            return '';
        }

        $rating = (float) $rating;

        return $rating > 0 ? (string) $rating : '';
    }

    /**
     * Clean text for schema.
     *
     * @param mixed $text
     * @return string
     */
    private function clean_text($text) {

        if (!is_string($text) && !is_numeric($text)) {
            return '';
        }

        $text = (string) $text;

        $text = preg_replace('/<!--\s*wp:[\s\S]*?-->/u', ' ', $text);
        $text = preg_replace('/\[(video|audio|playlist|gallery|embed|caption|wpvideo)[^\]]*\](?:[\s\S]*?\[\/\1\])?/iu', ' ', $text);

        if (function_exists('strip_shortcodes')) {
            $text = strip_shortcodes($text);
        }

        $text = preg_replace('/\[[^\]]+\]/u', ' ', $text);
        $text = preg_replace('/https?:\/\/\S+\.(?:mp4|webm|mov|m4v|mp3|wav|ogg)(?:\?\S*)?/iu', ' ', $text);
        $text = wp_strip_all_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * Current frontend URL.
     *
     * @return string
     */
    private function get_current_url() {

        if (is_admin()) {
            return home_url('/');
        }

        $scheme = is_ssl() ? 'https://' : 'http://';

        $host = isset($_SERVER['HTTP_HOST'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']))
            : wp_parse_url(home_url('/'), PHP_URL_HOST);

        $request_uri = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
            : '/';

        return esc_url_raw($scheme . $host . $request_uri);
    }
}