<?php

namespace AMK\SchemaCore\Data;

defined('ABSPATH') || exit;

class ResolverVariableCatalog {

    /**
     * Return grouped resolver variables for admin UI and template editor.
     *
     * @return array
     */
    public static function groups() {
        return [
            'global' => [
                'label'       => __('Global site variables', 'amk-schema-core'),
                'description' => __('For WebSite, WebPage, and general information on all pages.', 'amk-schema-core'),
                'variables'   => [
                    self::variable('site_name', __('Site name', 'amk-schema-core'), 'string', __('Resolver value for Site name.', 'amk-schema-core'), ['global'], 'Noble Power'),
                    self::variable('site_description', __('Site description', 'amk-schema-core'), 'string', __('Resolver value for Site description.', 'amk-schema-core'), ['global'], 'Dog equipment store'),
                    self::variable('site_url', __('Site url', 'amk-schema-core'), 'url', __('Resolver value for Site url.', 'amk-schema-core'), ['global'], 'https://example.com/'),
                    self::variable('home_url', __('Home url', 'amk-schema-core'), 'url', __('Resolver value for Home url.', 'amk-schema-core'), ['global'], 'https://example.com/'),
                    self::variable('current_url', __('Current url', 'amk-schema-core'), 'url', __('Resolver value for Current url.', 'amk-schema-core'), ['global'], 'https://example.com/product/sample/'),
                    self::variable('language', __('Language', 'amk-schema-core'), 'string', __('Resolver value for Language.', 'amk-schema-core'), ['global'], 'fa-IR'),
                    self::variable('website_search_target', __('Website search target', 'amk-schema-core'), 'string', __('Resolver value for Website search target.', 'amk-schema-core'), ['global'], 'https://example.com/?s={search_term_string}'),
                    self::variable('context', __('Context', 'amk-schema-core'), 'string', __('Resolver value for Context.', 'amk-schema-core'), ['global'], 'product'),
                ],
            ],

            'ids' => [
                'label'       => __('Schema identifiers', 'amk-schema-core'),
                'description' => __('For building internal references with @id.', 'amk-schema-core'),
                'variables'   => [
                    self::variable('organization_id', __('Id', 'amk-schema-core'), 'string', __('Resolver value for Id.', 'amk-schema-core'), ['global'], 'https://example.com/#organization'),
                    self::variable('local_store_id', __('Local store id', 'amk-schema-core'), 'string', __('Resolver value for Local store id.', 'amk-schema-core'), ['business', 'ecommerce_local'], 'https://example.com/#local-store'),
                    self::variable('website_id', __('Website id', 'amk-schema-core'), 'string', __('Resolver value for Website id.', 'amk-schema-core'), ['global'], 'https://example.com/#website'),
                    self::variable('webpage_id', __('Webpage id', 'amk-schema-core'), 'string', __('Resolver value for Webpage id.', 'amk-schema-core'), ['global'], 'https://example.com/sample/#webpage'),
                    self::variable('breadcrumb_id', __('Breadcrumb id', 'amk-schema-core'), 'string', __('Resolver value for Breadcrumb id.', 'amk-schema-core'), ['global'], 'https://example.com/sample/#breadcrumb'),
                ],
            ],

            'organization' => [
                'label'       => __('Organization / brand / store', 'amk-schema-core'),
                'description' => __('Data built from the plugin global settings.', 'amk-schema-core'),
                'variables'   => [
                    self::variable('site_profile', __('Site profile', 'amk-schema-core'), 'string', __('Resolver value for Site profile.', 'amk-schema-core'), ['global'], 'ecommerce'),
                    self::variable('organization_type', __('Type', 'amk-schema-core'), 'string', __('Resolver value for Type.', 'amk-schema-core'), ['global'], 'Organization'),
                    self::variable('organization_types', __('Types', 'amk-schema-core'), 'array', __('Resolver value for Types.', 'amk-schema-core'), ['global'], ['Organization', 'OnlineStore']),
                    self::variable('online_store_types', __('Online store types', 'amk-schema-core'), 'array', __('Resolver value for Online store types.', 'amk-schema-core'), ['ecommerce', 'ecommerce_local'], ['Organization', 'OnlineStore']),
                    self::variable('organization_name', __('Name', 'amk-schema-core'), 'string', __('Resolver value for Name.', 'amk-schema-core'), ['global'], 'Noble Power'),
                    self::variable('organization_legal_name', __('Legal name', 'amk-schema-core'), 'string', __('Resolver value for Legal name.', 'amk-schema-core'), ['global'], 'Noble Power Leather'),
                    self::variable('organization_alternate_name', __('Alternate name', 'amk-schema-core'), 'string', __('Resolver value for Alternate name.', 'amk-schema-core'), ['global'], 'NoblePower'),
                    self::variable('organization_url', __('Url', 'amk-schema-core'), 'url', __('Resolver value for Url.', 'amk-schema-core'), ['global'], 'https://example.com/'),
                    self::variable('organization_description', __('Description', 'amk-schema-core'), 'string', __('Resolver value for Description.', 'amk-schema-core'), ['global'], 'Manufacturer and seller of dog equipment'),
                    self::variable('organization_logo', __('Logo', 'amk-schema-core'), 'url', __('Resolver value for Logo.', 'amk-schema-core'), ['global'], 'https://example.com/logo.png'),
                    self::variable('organization_image', __('Image', 'amk-schema-core'), 'url', __('Resolver value for Image.', 'amk-schema-core'), ['global'], 'https://example.com/shop.jpg'),
                    self::variable('organization_telephone', __('Telephone', 'amk-schema-core'), 'string', __('Resolver value for Telephone.', 'amk-schema-core'), ['global'], '+982100000000'),
                    self::variable('organization_email', __('Email', 'amk-schema-core'), 'string', __('Resolver value for Email.', 'amk-schema-core'), ['global'], 'info@example.com'),
                    self::variable('organization_price_range', __('Price range', 'amk-schema-core'), 'string', __('Resolver value for Price range.', 'amk-schema-core'), ['global'], '$$'),
                    self::variable('organization_currencies_accepted', __('Currencies accepted', 'amk-schema-core'), 'string', __('Resolver value for Currencies accepted.', 'amk-schema-core'), ['global'], 'IRR'),
                    self::variable('organization_payment_accepted', __('Payment accepted', 'amk-schema-core'), 'string', __('Resolver value for Payment accepted.', 'amk-schema-core'), ['global'], 'Online Payment, Debit Card'),
                    self::variable('organization_founding_date', __('Founding date', 'amk-schema-core'), 'date', __('Resolver value for Founding date.', 'amk-schema-core'), ['global'], '2020-01-01'),
                    self::variable('organization_tax_id', __('Tax id', 'amk-schema-core'), 'string', __('Resolver value for Tax id.', 'amk-schema-core'), ['global'], '123456'),
                    self::variable('organization_vat_id', __('Vat id', 'amk-schema-core'), 'string', __('Resolver value for Vat id.', 'amk-schema-core'), ['global'], 'IR123456'),

                    self::variable('organization_address', __('Address', 'amk-schema-core'), 'object', __('Resolver value for Address.', 'amk-schema-core'), ['global'], ['@type' => 'PostalAddress']),
                    self::variable('organization_contact_point', __('Contact point', 'amk-schema-core'), 'object|array', __('Resolver value for Contact point.', 'amk-schema-core'), ['global'], [['@type' => 'ContactPoint']]),
                    self::variable('organization_same_as', __('Same as', 'amk-schema-core'), 'array', __('Resolver value for Same as.', 'amk-schema-core'), ['global'], ['https://instagram.com/example']),
                    self::variable('organization_geo', __('Geo', 'amk-schema-core'), 'object', __('Resolver value for Geo.', 'amk-schema-core'), ['business', 'ecommerce_local'], ['@type' => 'GeoCoordinates']),
                    self::variable('organization_opening_hours', __('Opening hours', 'amk-schema-core'), 'array|string', __('Resolver value for Opening hours.', 'amk-schema-core'), ['business', 'ecommerce_local'], ['Mo-Sa 09:00-21:00']),
                    self::variable('organization_opening_hours_specification', __('Opening hours specification', 'amk-schema-core'), 'object|array', __('Resolver value for Opening hours specification.', 'amk-schema-core'), ['business', 'ecommerce_local'], [['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['https://schema.org/Monday'], 'opens' => '09:00', 'closes' => '21:00']]),
                    self::variable('organization_has_map', __('Has map', 'amk-schema-core'), 'url', __('Resolver value for Has map.', 'amk-schema-core'), ['business', 'ecommerce_local'], 'https://maps.google.com/...'),
                    self::variable('local_store_schema', __('Local store schema', 'amk-schema-core'), 'object', __('Resolver value for Local store schema.', 'amk-schema-core'), ['business', 'ecommerce_local'], ['@type' => 'Store', '@id' => 'https://example.com/#local-store']),
                ],
            ],

            'commerce' => [
                'label'       => __('Commerce / shipping / returns', 'amk-schema-core'),
                'description' => __('Global store data for OnlineStore, Store, and Product Offer.', 'amk-schema-core'),
                'variables'   => [
                    self::variable('merchant_return_policy', __('Merchant return policy', 'amk-schema-core'), 'object', __('Resolver value for Merchant return policy.', 'amk-schema-core'), ['ecommerce', 'ecommerce_local', 'product'], ['@type' => 'MerchantReturnPolicy']),
                    self::variable('shipping_service', __('Shipping service', 'amk-schema-core'), 'object|array', __('Resolver value for Shipping service.', 'amk-schema-core'), ['ecommerce', 'ecommerce_local', 'product'], ['@type' => 'ShippingService']),
                    self::variable('product_return_policy', __('Product return policy', 'amk-schema-core'), 'object', __('Resolver value for Product return policy.', 'amk-schema-core'), ['product'], ['@type' => 'MerchantReturnPolicy']),
                    self::variable('product_shipping_details', __('Product shipping details', 'amk-schema-core'), 'object', __('Resolver value for Product shipping details.', 'amk-schema-core'), ['product'], ['@type' => 'OfferShippingDetails']),
                ],
            ],

            'content' => [
                'label'       => __('Page / post / archive', 'amk-schema-core'),
                'description' => __('Data read from the current page, post, or archive.', 'amk-schema-core'),
                'variables'   => [
                    self::variable('id', __('Current content ID', 'amk-schema-core'), 'number', __('Resolver value for Current content ID.', 'amk-schema-core'), ['page', 'single_post', 'product'], 123),
                    self::variable('name', __('Name / main title', 'amk-schema-core'), 'string', __('Resolver value for Name / main title.', 'amk-schema-core'), ['page', 'single_post', 'product', 'archive'], 'Page title'),
                    self::variable('title', __('Title', 'amk-schema-core'), 'string', __('Resolver value for Title.', 'amk-schema-core'), ['page', 'single_post', 'product', 'archive'], 'Page title'),
                    self::variable('description', __('Description', 'amk-schema-core'), 'string', __('Resolver value for Description.', 'amk-schema-core'), ['page', 'single_post', 'product', 'archive'], 'Short page description'),
                    self::variable('excerpt', __('Excerpt', 'amk-schema-core'), 'string', __('Resolver value for Excerpt.', 'amk-schema-core'), ['page', 'single_post'], 'Content excerpt'),
                    self::variable('short_description', __('Short description', 'amk-schema-core'), 'string', __('Resolver value for Short description.', 'amk-schema-core'), ['product'], 'Short product description'),
                    self::variable('url', __('Current page URL', 'amk-schema-core'), 'url', __('Resolver value for Current page URL.', 'amk-schema-core'), ['page', 'single_post', 'product', 'archive'], 'https://example.com/page/'),
                    self::variable('image', __('Featured image', 'amk-schema-core'), 'url', __('Resolver value for Featured image.', 'amk-schema-core'), ['page', 'single_post', 'product'], 'https://example.com/image.jpg'),
                    self::variable('date_published', __('Date published', 'amk-schema-core'), 'datetime', __('Resolver value for Date published.', 'amk-schema-core'), ['page', 'single_post'], '2026-01-01T10:00:00+00:00'),
                    self::variable('date_modified', __('Date modified', 'amk-schema-core'), 'datetime', __('Resolver value for Date modified.', 'amk-schema-core'), ['page', 'single_post'], '2026-01-02T10:00:00+00:00'),
                    self::variable('author_name', __('Author name', 'amk-schema-core'), 'string', __('Resolver value for Author name.', 'amk-schema-core'), ['single_post'], 'John Doe'),
                    self::variable('author_url', __('Author url', 'amk-schema-core'), 'url', __('Resolver value for Author url.', 'amk-schema-core'), ['single_post'], 'https://example.com/author/admin/'),
                ],
            ],

            'product' => [
                'label'       => __('WooCommerce product', 'amk-schema-core'),
                'description' => __('Data for the current WooCommerce product.', 'amk-schema-core'),
                'variables'   => [
                    self::variable('offer_url', __('Offer url', 'amk-schema-core'), 'url', __('Resolver value for Offer url.', 'amk-schema-core'), ['product'], 'https://example.com/product/sample/'),
                    self::variable('sku', __('Product SKU', 'amk-schema-core'), 'string', __('Resolver value for Product SKU.', 'amk-schema-core'), ['product'], 'SKU-123'),
                    self::variable('gtin', __('Product GTIN', 'amk-schema-core'), 'string', __('Resolver value for Product GTIN.', 'amk-schema-core'), ['product'], '1234567890123'),
                    self::variable('mpn', __('Product MPN', 'amk-schema-core'), 'string', __('Resolver value for Product MPN.', 'amk-schema-core'), ['product'], 'MPN-123'),
                    self::variable('brand_name', __('Brand name', 'amk-schema-core'), 'string', __('Resolver value for Brand name.', 'amk-schema-core'), ['product'], 'Noble Power'),
                    self::variable('brand_url', __('Brand url', 'amk-schema-core'), 'url', __('Resolver value for Brand url.', 'amk-schema-core'), ['product'], 'https://example.com/brand/noble-power/'),
                    self::variable('price', __('Price', 'amk-schema-core'), 'number|string', __('Resolver value for Price.', 'amk-schema-core'), ['product'], '2500000'),
                    self::variable('regular_price', __('Regular price', 'amk-schema-core'), 'number|string', __('Resolver value for Regular price.', 'amk-schema-core'), ['product'], '3000000'),
                    self::variable('sale_price', __('Sale price', 'amk-schema-core'), 'number|string', __('Resolver value for Sale price.', 'amk-schema-core'), ['product'], '2500000'),
                    self::variable('min_price', __('Min price', 'amk-schema-core'), 'number|string', __('Resolver value for Min price.', 'amk-schema-core'), ['product'], '2000000'),
                    self::variable('max_price', __('Max price', 'amk-schema-core'), 'number|string', __('Resolver value for Max price.', 'amk-schema-core'), ['product'], '3500000'),
                    self::variable('variation_count', __('Variation count', 'amk-schema-core'), 'number', __('Resolver value for Variation count.', 'amk-schema-core'), ['product'], 3),
                    self::variable('currency', __('Currency', 'amk-schema-core'), 'string', __('Resolver value for Currency.', 'amk-schema-core'), ['product'], 'IRR'),
                    self::variable('availability', __('Schema.org availability', 'amk-schema-core'), 'url', __('Resolver value for Schema.org availability.', 'amk-schema-core'), ['product'], 'https://schema.org/InStock'),
                    self::variable('stock_status', __('Stock status', 'amk-schema-core'), 'string', __('Resolver value for Stock status.', 'amk-schema-core'), ['product'], 'instock'),
                    self::variable('rating_value', __('Rating value', 'amk-schema-core'), 'number|string', __('Resolver value for Rating value.', 'amk-schema-core'), ['product'], '4.8'),
                    self::variable('review_count', __('Review count', 'amk-schema-core'), 'number', __('Resolver value for Review count.', 'amk-schema-core'), ['product'], 12),
                    self::variable('product_type', __('Product type', 'amk-schema-core'), 'string', __('Resolver value for Product type.', 'amk-schema-core'), ['product'], 'simple'),
                    self::variable('date_created', __('Date created', 'amk-schema-core'), 'datetime', __('Resolver value for Date created.', 'amk-schema-core'), ['product'], '2026-01-01T10:00:00+00:00'),
                    self::variable('date_modified', __('Date modified', 'amk-schema-core'), 'datetime', __('Resolver value for Date modified.', 'amk-schema-core'), ['product'], '2026-01-02T10:00:00+00:00'),
                ],
            ],

            'search_breadcrumb' => [
                'label'       => __('Search and breadcrumb', 'amk-schema-core'),
                'description' => __('Variables for search results and breadcrumb trails.', 'amk-schema-core'),
                'variables'   => [
                    self::variable('search_query', __('Search query', 'amk-schema-core'), 'string', __('Resolver value for Search query.', 'amk-schema-core'), ['search'], 'dog collar'),
                    self::variable('breadcrumb_items', __('Breadcrumb items', 'amk-schema-core'), 'array', __('Resolver value for Breadcrumb items.', 'amk-schema-core'), ['global'], [['@type' => 'ListItem', 'position' => 1]]),
                ],
            ],
        ];
    }

    /**
     * Flat list keyed by variable key.
     *
     * @return array
     */
    public static function all() {
        $flat = [];

        foreach (self::groups() as $group_key => $group) {
            foreach ($group['variables'] as $variable) {
                $variable['group']       = $group_key;
                $variable['group_label'] = $group['label'];
                $flat[$variable['key']]  = $variable;
            }
        }

        return $flat;
    }

    /**
     * Get only variable keys.
     *
     * @return array
     */
    public static function keys() {
        return array_keys(self::all());
    }

    /**
     * Get one variable definition.
     *
     * @param string $key
     * @return array|null
     */
    public static function get($key) {
        $key = self::normalize_key($key);
        $all = self::all();

        return isset($all[$key]) ? $all[$key] : null;
    }

    /**
     * Check if variable exists in catalog.
     *
     * @param string $key
     * @return bool
     */
    public static function has($key) {
        return self::get($key) !== null;
    }

    /**
     * Placeholder text for a key.
     *
     * @param string $key
     * @return string
     */
    public static function placeholder($key) {
        return '{{' . self::normalize_key($key) . '}}';
    }

    /**
     * Admin-friendly grouped payload.
     *
     * @return array
     */
    public static function for_admin() {
        $groups = self::groups();

        foreach ($groups as $group_key => $group) {
            foreach ($group['variables'] as $index => $variable) {
                $groups[$group_key]['variables'][$index]['placeholder'] = self::placeholder($variable['key']);
            }
        }

        return $groups;
    }

    /**
     * Variables that are usually useful for a context.
     *
     * @param string $context
     * @return array
     */
    public static function for_context($context) {
        $context = self::normalize_key($context);
        $results = [];

        foreach (self::all() as $key => $variable) {
            $contexts = isset($variable['contexts']) && is_array($variable['contexts']) ? $variable['contexts'] : [];

            if (in_array('global', $contexts, true) || in_array($context, $contexts, true)) {
                $results[$key] = $variable;
            }
        }

        return $results;
    }

    /**
     * Minimal options list for select fields.
     *
     * @return array
     */
    public static function options() {
        $options = [];

        foreach (self::all() as $key => $variable) {
            $options[$key] = $variable['label'] . ' — {{' . $key . '}}';
        }

        return $options;
    }

    /**
     * Normalize placeholder key.
     *
     * @param mixed $key
     * @return string
     */
    private static function normalize_key($key) {
        if (!is_string($key) && !is_numeric($key)) {
            return '';
        }

        $key = trim((string) $key);
        $key = preg_replace('/^\{\{\s*/', '', $key);
        $key = preg_replace('/\s*\}\}$/', '', $key);
        $key = trim($key);

        if (function_exists('sanitize_key')) {
            return sanitize_key($key);
        }

        $key = strtolower($key);
        $key = preg_replace('/[^a-z0-9_\-]/', '', $key);

        return is_string($key) ? $key : '';
    }

    /**
     * Build one variable definition.
     *
     * @param string $key
     * @param string $label
     * @param string $type
     * @param string $description
     * @param array  $contexts
     * @param mixed  $example
     * @return array
     */
    private static function variable($key, $label, $type, $description, $contexts = ['global'], $example = '') {
        return [
            'key'         => self::normalize_key($key),
            'label'       => $label,
            'type'        => $type,
            'description' => $description,
            'contexts'    => array_values(array_unique(array_filter((array) $contexts))),
            'example'     => $example,
        ];
    }
}