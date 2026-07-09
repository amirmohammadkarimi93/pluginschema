<?php

namespace AMK\SchemaCore\Core;

defined('ABSPATH') || exit;

class GlobalSettings {

    const OPTION_KEY = 'amk_schema_core_global_settings';

    const LEGACY_ORG_NAME_OPTION = 'amk_org_name';
    const LEGACY_ORG_URL_OPTION  = 'amk_org_url';

    public static function defaults() {

        return [
            'site_profile' => 'general',

            'special_pages' => [
                'contact_page_id' => 0,
                'about_page_id'   => 0,
            ],

            'organization' => [
                'type'                => 'Organization',
                'types'               => ['Organization'],
                'name'                => get_bloginfo('name'),
                'legal_name'          => '',
                'alternate_name'      => '',
                'url'                 => home_url('/'),
                'description'         => get_bloginfo('description'),
                'logo'                => '',
                'image'               => '',
                'telephone'           => '',
                'email'               => '',
                'price_range'         => '',
                'currencies_accepted' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '',
                'payment_accepted'    => '',
                'founding_date'       => '',
                'tax_id'              => '',
                'vat_id'              => '',
            ],

            'address' => [
                'country'     => '',
                'region'      => '',
                'locality'    => '',
                'street'      => '',
                'postal_code' => '',
            ],

            'contact' => [
                'contact_type'       => 'customer support',
                'telephone'          => '',
                'email'              => '',
                'available_language' => '',
            ],

            'contact_points' => [
                [
                    'contact_type'       => 'customer support',
                    'telephone'          => '',
                    'email'              => '',
                    'url'                => '',
                    'contact_option'     => '',
                    'area_served'        => '',
                    'available_language' => '',
                ],
            ],

            'social' => array_fill_keys(array_keys(self::social_options()), ''),

            'commerce' => [
                'enabled'                 => 0,

                'return_policy_enabled'   => 0,
                'return_policy_url'       => '',
                'return_policy_country'   => '',
                'merchant_return_days'    => '',
                'return_method'           => 'https://schema.org/ReturnByMail',
                'return_fees'             => 'https://schema.org/FreeReturn',
                'refund_type'             => 'https://schema.org/FullRefund',

                'shipping_enabled'        => 0,
                'shipping_name'           => '',
                'shipping_description'    => '',
                'shipping_country'        => '',
                'shipping_rate'           => '',
                'free_shipping_threshold' => '',
                'handling_min_days'       => '',
                'handling_max_days'       => '',
                'transit_min_days'        => '',
                'transit_max_days'        => '',
            ],

            'local_business' => [
                'enabled'       => 0,
                'has_map'       => '',
                'latitude'      => '',
                'longitude'     => '',
                'opening_hours' => '',
            ],
        ];
    }

    public static function get() {

        $stored = get_option(self::OPTION_KEY, null);
        $has_new_settings = is_array($stored);

        if (!is_array($stored)) {
            $stored = [];
        }

        $settings = self::array_merge_recursive_distinct(self::defaults(), $stored);
        $settings = self::migrate_legacy_options($settings, $stored, $has_new_settings);

        return self::sanitize($settings);
    }

    public static function update($settings) {

        update_option(self::OPTION_KEY, self::sanitize($settings), false);
    }


    /**
     * Prefill empty plugin settings from WordPress/WooCommerce platform data.
     *
     * This is intentionally an import step, not a render-time fallback.
     * The plugin settings remain the primary source after this method runs.
     * Existing user-entered plugin values are never overwritten.
     *
     * @param string $reason
     * @return array
     */
    public static function prefill_from_platform_defaults($reason = 'manual') {

        $reason = is_string($reason) ? sanitize_key($reason) : 'manual';
        $stored = get_option(self::OPTION_KEY, null);
        $has_stored_settings = is_array($stored);

        if (!$has_stored_settings) {
            $stored = [];
        }

        $settings = self::array_merge_recursive_distinct(self::defaults(), $stored);
        $platform = self::get_platform_prefill_data();
        $filled   = [];

        if (!empty($platform['organization']) && is_array($platform['organization'])) {
            foreach ($platform['organization'] as $key => $value) {
                self::fill_missing_setting($settings, ['organization', $key], $value, $filled);
            }
        }

        if (!empty($platform['address']) && is_array($platform['address'])) {
            foreach ($platform['address'] as $key => $value) {
                self::fill_missing_setting($settings, ['address', $key], $value, $filled);
            }
        }

        if (!empty($platform['contact']) && is_array($platform['contact'])) {
            foreach ($platform['contact'] as $key => $value) {
                self::fill_missing_setting($settings, ['contact', $key], $value, $filled);
            }
        }

        if (!empty($platform['contact_point']) && is_array($platform['contact_point'])) {
            $settings = self::prefill_primary_contact_point($settings, $platform['contact_point'], $filled);
        }

        if (!empty($platform['commerce']) && is_array($platform['commerce'])) {
            foreach ($platform['commerce'] as $key => $value) {
                self::fill_missing_setting($settings, ['commerce', $key], $value, $filled);
            }
        }

        $updated = !empty($filled) || !$has_stored_settings;

        if ($updated) {
            self::update($settings);
        }

        $result = [
            'reason'       => $reason !== '' ? $reason : 'manual',
            'updated'      => $updated,
            'created'      => !$has_stored_settings,
            'filled'       => array_values($filled),
            'filled_count' => count($filled),
            'source'       => $platform['source'] ?? [],
        ];

        update_option('amk_schema_core_platform_prefill_result', $result, false);

        return $result;
    }

    public static function to_resolver_data($settings = null) {

        $settings = is_array($settings) ? self::sanitize($settings) : self::get();

        $resolved_types       = self::resolve_organization_types($settings);
        $organization_types  = self::resolve_online_organization_types($settings, $resolved_types);
        $organization_type   = self::resolve_primary_organization_type($organization_types);
        $organization        = $settings['organization'];
        $local_store_schema  = self::build_local_store_schema($settings, $organization);
        $special_page_type   = self::get_current_special_page_type($settings);
        $webpage_schema_type = self::get_webpage_schema_type($settings);
        $page_entity_ref     = $special_page_type ? ['@id' => home_url('/#organization')] : [];

        return [
            'site_profile' => $settings['site_profile'],

            'special_page_type'       => $special_page_type,
            'contact_page_id'         => absint($settings['special_pages']['contact_page_id'] ?? 0),
            'about_page_id'           => absint($settings['special_pages']['about_page_id'] ?? 0),
            'webpage_type'            => $webpage_schema_type,
            'webpage_about'           => $page_entity_ref,
            'webpage_main_entity'     => $page_entity_ref,

            'organization_id' => home_url('/#organization'),
            'local_store_id'  => home_url('/#local-store'),
            'website_id'      => home_url('/#website'),
            'webpage_id'      => self::get_current_url() . '#webpage',
            'breadcrumb_id'   => self::get_current_url() . '#breadcrumb',

            // Backward-compatible single type for old templates.
            'organization_type'                => $organization_type,
            // Preferred dynamic type list for the primary online/brand entity.
            'organization_types'               => $organization_types,
            'online_store_types'               => $organization_types,
            'local_store_schema'               => $local_store_schema,
            'organization_name'                => $organization['name'],
            'organization_legal_name'          => $organization['legal_name'],
            'organization_alternate_name'      => $organization['alternate_name'],
            'organization_url'                 => $organization['url'],
            'organization_description'         => $organization['description'],
            'organization_logo'                => $organization['logo'],
            'organization_image'               => $organization['image'],
            'organization_telephone'           => self::format_telephone_for_schema($organization['telephone']),
            'organization_email'               => $organization['email'],
            'organization_price_range'         => $organization['price_range'],
            'organization_currencies_accepted' => $organization['currencies_accepted'],
            'organization_payment_accepted'    => $organization['payment_accepted'],
            'organization_founding_date'       => $organization['founding_date'],
            'organization_tax_id'              => $organization['tax_id'],
            'organization_vat_id'              => $organization['vat_id'],

            'organization_address'       => self::build_address($settings),
            'organization_contact_point' => self::build_contact_point($settings),
            'organization_same_as'       => self::build_same_as($settings),
            'organization_geo'           => self::build_geo($settings),
            'organization_opening_hours' => self::build_opening_hours($settings),
            'organization_opening_hours_specification' => self::build_opening_hours_specification($settings),
            'organization_has_map'       => $settings['local_business']['has_map'],

            'merchant_return_policy' => self::build_return_policy($settings),
            'shipping_service'       => self::build_shipping_service($settings),
        ];
    }

    public static function sanitize($settings) {

        $settings = is_array($settings) ? $settings : [];
        $settings = self::array_merge_recursive_distinct(self::defaults(), $settings);

        $site_profile = sanitize_key($settings['site_profile'] ?? 'general');
        $allowed_profiles = array_keys(self::profile_options());

        if (!in_array($site_profile, $allowed_profiles, true)) {
            $site_profile = 'general';
        }

        $raw_organization = isset($settings['organization']) && is_array($settings['organization']) ? $settings['organization'] : [];
        $raw_org_types = array_key_exists('types', $raw_organization) ? $raw_organization['types'] : null;
        $raw_org_type  = array_key_exists('type', $raw_organization) ? $raw_organization['type'] : 'Organization';

        if (empty($raw_org_types)) {
            $raw_org_types = $raw_org_type;
        }

        $org_types = self::normalize_organization_types($raw_org_types);
        $org_type  = self::resolve_primary_organization_type($org_types);

        return [
            'site_profile' => $site_profile,

            'special_pages' => [
                'contact_page_id' => absint($settings['special_pages']['contact_page_id'] ?? 0),
                'about_page_id'   => absint($settings['special_pages']['about_page_id'] ?? 0),
            ],

            'organization' => [
                'type'                => $org_type,
                'types'               => $org_types,
                'name'                => sanitize_text_field($settings['organization']['name'] ?? ''),
                'legal_name'          => sanitize_text_field($settings['organization']['legal_name'] ?? ''),
                'alternate_name'      => sanitize_text_field($settings['organization']['alternate_name'] ?? ''),
                'url'                 => esc_url_raw($settings['organization']['url'] ?? ''),
                'description'         => sanitize_textarea_field($settings['organization']['description'] ?? ''),
                'logo'                => esc_url_raw($settings['organization']['logo'] ?? ''),
                'image'               => esc_url_raw($settings['organization']['image'] ?? ''),
                'telephone'           => sanitize_text_field($settings['organization']['telephone'] ?? ''),
                'email'               => sanitize_email($settings['organization']['email'] ?? ''),
                'price_range'         => sanitize_text_field($settings['organization']['price_range'] ?? ''),
                'currencies_accepted' => sanitize_text_field($settings['organization']['currencies_accepted'] ?? ''),
                'payment_accepted'    => self::sanitize_csv_text_or_array($settings['organization']['payment_accepted'] ?? ''),
                'founding_date'       => sanitize_text_field($settings['organization']['founding_date'] ?? ''),
                'tax_id'              => sanitize_text_field($settings['organization']['tax_id'] ?? ''),
                'vat_id'              => sanitize_text_field($settings['organization']['vat_id'] ?? ''),
            ],

            'address' => [
                'country'     => sanitize_text_field($settings['address']['country'] ?? ''),
                'region'      => sanitize_text_field($settings['address']['region'] ?? ''),
                'locality'    => sanitize_text_field($settings['address']['locality'] ?? ''),
                'street'      => sanitize_text_field($settings['address']['street'] ?? ''),
                'postal_code' => sanitize_text_field($settings['address']['postal_code'] ?? ''),
            ],

            'contact' => [
                'contact_type'       => sanitize_text_field($settings['contact']['contact_type'] ?? 'customer support'),
                'telephone'          => sanitize_text_field($settings['contact']['telephone'] ?? ''),
                'email'              => sanitize_email($settings['contact']['email'] ?? ''),
                'available_language' => sanitize_text_field($settings['contact']['available_language'] ?? ''),
            ],
            'contact_points' => self::sanitize_contact_points($settings),

            'social' => self::sanitize_social_links($settings['social'] ?? [], $settings['social_dynamic'] ?? null),

            'commerce' => [
                'enabled'                 => !empty($settings['commerce']['enabled']) ? 1 : 0,

                'return_policy_enabled'   => !empty($settings['commerce']['return_policy_enabled']) ? 1 : 0,
                'return_policy_url'       => esc_url_raw($settings['commerce']['return_policy_url'] ?? ''),
                'return_policy_countries' => self::normalize_country_list($settings['commerce']['return_policy_countries'] ?? []),
                'return_policy_mode'      => sanitize_key($settings['commerce']['return_policy_mode'] ?? 'worldwide'),
                'return_policy_countries' => self::normalize_country_list($settings['commerce']['return_policy_countries'] ?? []),
                'return_policy_country'   => self::normalize_address_country($settings['commerce']['return_policy_country'] ?? ''),
                'merchant_return_days'    => self::sanitize_positive_integer_or_empty($settings['commerce']['merchant_return_days'] ?? ''),
                'return_method'           => self::sanitize_schema_url_option($settings['commerce']['return_method'] ?? '', array_keys(self::return_method_options()), 'https://schema.org/ReturnByMail'),
                'return_fees'             => self::sanitize_schema_url_option($settings['commerce']['return_fees'] ?? '', array_keys(self::return_fees_options()), 'https://schema.org/FreeReturn'),
                'refund_type'             => self::sanitize_schema_url_option($settings['commerce']['refund_type'] ?? '', array_keys(self::refund_type_options()), 'https://schema.org/FullRefund'),

                'shipping_enabled'        => !empty($settings['commerce']['shipping_enabled']) ? 1 : 0,
                'shipping_name'           => sanitize_text_field($settings['commerce']['shipping_name'] ?? ''),
                'shipping_description'    => sanitize_textarea_field($settings['commerce']['shipping_description'] ?? ''),
                'shipping_countries'      => self::normalize_country_list($settings['commerce']['shipping_countries'] ?? []),
                'shipping_mode'           => sanitize_key($settings['commerce']['shipping_mode'] ?? 'worldwide'),
                'shipping_countries'      => self::normalize_country_list($settings['commerce']['shipping_countries'] ?? []),
                'shipping_country'        => self::normalize_address_country($settings['commerce']['shipping_country'] ?? ''),
                'shipping_rate'           => self::sanitize_decimal_or_empty($settings['commerce']['shipping_rate'] ?? ''),
                'free_shipping_threshold' => self::sanitize_decimal_or_empty($settings['commerce']['free_shipping_threshold'] ?? ''),
                'handling_min_days'       => self::sanitize_positive_integer_or_empty($settings['commerce']['handling_min_days'] ?? ''),
                'handling_max_days'       => self::sanitize_max_days_or_empty($settings['commerce']['handling_min_days'] ?? '', $settings['commerce']['handling_max_days'] ?? ''),
                'transit_min_days'        => self::sanitize_positive_integer_or_empty($settings['commerce']['transit_min_days'] ?? ''),
                'transit_max_days'        => self::sanitize_max_days_or_empty($settings['commerce']['transit_min_days'] ?? '', $settings['commerce']['transit_max_days'] ?? ''),
            ],

            'local_business' => [
                'enabled'       => !empty($settings['local_business']['enabled']) ? 1 : 0,
                'has_map'       => esc_url_raw($settings['local_business']['has_map'] ?? ''),
                'latitude'      => self::sanitize_coordinate_or_empty($settings['local_business']['latitude'] ?? ''),
                'longitude'     => self::sanitize_coordinate_or_empty($settings['local_business']['longitude'] ?? ''),
                'opening_hours' => self::sanitize_opening_hours_from_settings($settings['local_business'] ?? []),
            ],
        ];
    }

    /**
     * Get current special static page type selected by admin.
     *
     * @param array|null $settings
     * @return string contact|about|''
     */
    public static function get_current_special_page_type($settings = null) {

        $settings = is_array($settings) ? self::sanitize($settings) : self::get();
        $page_id  = self::get_current_page_id();

        if (!$page_id) {
            return '';
        }

        $contact_page_id = absint($settings['special_pages']['contact_page_id'] ?? 0);
        $about_page_id   = absint($settings['special_pages']['about_page_id'] ?? 0);

        if ($contact_page_id && $page_id === $contact_page_id) {
            return 'contact';
        }

        if ($about_page_id && $page_id === $about_page_id) {
            return 'about';
        }

        return '';
    }

    /**
     * Resolve Schema.org page type for current page.
     *
     * @param array|null $settings
     * @return string
     */
    public static function get_webpage_schema_type($settings = null) {

        $special_page_type = self::get_current_special_page_type($settings);

        if ($special_page_type === 'contact') {
            return 'ContactPage';
        }

        if ($special_page_type === 'about') {
            return 'AboutPage';
        }

        return 'WebPage';
    }

    /**
     * Current queried WordPress page ID.
     *
     * @return int
     */
    private static function get_current_page_id() {

        if (function_exists('is_page') && !is_page()) {
            return 0;
        }

        if (function_exists('get_queried_object_id')) {
            $queried_id = absint(get_queried_object_id());

            if ($queried_id) {
                return $queried_id;
            }
        }

        if (function_exists('get_the_ID')) {
            return absint(get_the_ID());
        }

        return 0;
    }

    public static function profile_options() {

        return [
            'general'         => __('General / corporate site', 'amk-schema-core'),
            'content'         => __('Content / blog site', 'amk-schema-core'),
            'business'        => __('Local business', 'amk-schema-core'),
            'ecommerce'       => __('Online store', 'amk-schema-core'),
            'ecommerce_local' => __('Online store + physical branch', 'amk-schema-core'),
        ];
    }

    public static function organization_type_options() {

        return [
            'Organization'  => __('Organization - General organization', 'amk-schema-core'),
            'OnlineStore'   => __('OnlineStore - Online store', 'amk-schema-core'),
            'Store'         => __('Store - Physical store', 'amk-schema-core'),
            'LocalBusiness' => __('LocalBusiness - Local business', 'amk-schema-core'),
        ];
    }

    public static function contact_type_options() {

        return [
            'customer support'  => __('Customer support', 'amk-schema-core'),
            'sales'             => __('Sales', 'amk-schema-core'),
            'technical support' => __('Technical support', 'amk-schema-core'),
            'billing support'   => __('Billing support', 'amk-schema-core'),
            'order support'     => __('Order support', 'amk-schema-core'),
            'returns'           => __('Returns', 'amk-schema-core'),
            'shipping'          => __('Shipping and delivery', 'amk-schema-core'),
            'warranty support'  => __('Warranty and after-sales support', 'amk-schema-core'),
        ];
    }


    public static function social_options() {

        return [
            'instagram' => 'Instagram',
            'telegram'  => 'Telegram',
            'linkedin'  => 'LinkedIn',
            'facebook'  => 'Facebook',
            'threads'   => 'Threads',
            'youtube'   => 'YouTube',
            'aparat'    => 'Aparat',
            'x'         => 'X / Twitter',
            'tiktok'    => 'TikTok',
            'pinterest' => 'Pinterest',
            'whatsapp'  => 'WhatsApp',
            'github'    => 'GitHub',
            'medium'    => 'Medium',
            'reddit'    => 'Reddit',
            'discord'   => 'Discord',
            'twitch'    => 'Twitch',
            'snapchat'  => 'Snapchat',
            'eitaa'     => 'Eitaa',
            'rubika'    => 'Rubika',
            'bale'      => 'Bale',
            'soroush'   => 'Soroush',
        ];
    }

    public static function return_method_options() {

        return [
            'https://schema.org/ReturnByMail'  => __('ReturnByMail - Return by mail or freight', 'amk-schema-core'),
            'https://schema.org/ReturnInStore' => __('ReturnInStore - In-store returns', 'amk-schema-core'),
            'https://schema.org/ReturnAtKiosk' => __('ReturnAtKiosk - Return at kiosk or drop-off point', 'amk-schema-core'),
        ];
    }

    public static function return_fees_options() {

        return [
            'https://schema.org/FreeReturn'                       => __('FreeReturn - Free returns', 'amk-schema-core'),
            'https://schema.org/ReturnFeesCustomerResponsibility' => __('ReturnFeesCustomerResponsibility - Return cost paid by customer', 'amk-schema-core'),
            'https://schema.org/ReturnShippingFees'               => __('ReturnShippingFees - Return shipping fees apply', 'amk-schema-core'),
        ];
    }

    public static function refund_type_options() {

        return [
            'https://schema.org/FullRefund'        => __('FullRefund - Full refund', 'amk-schema-core'),
            'https://schema.org/StoreCreditRefund' => __('StoreCreditRefund - Store credit refund', 'amk-schema-core'),
            'https://schema.org/ExchangeRefund'    => __('ExchangeRefund - Item exchange', 'amk-schema-core'),
        ];
    }

    /**
     * Resolve a backward-compatible single organization type.
     *
     * New templates should use organization_types instead. This method remains
     * for old templates that still contain {{organization_type}}.
     *
     * @param array $settings
     * @return string
     */
    private static function resolve_organization_type($settings) {

        return self::resolve_primary_organization_type(self::resolve_organization_types($settings));
    }

    /**
     * Resolve the actual Schema.org @type list for the site owner entity.
     *
     * The base identity is always Organization. Extra types are added from
     * the selected site profile and enabled feature groups. This lets one site
     * output only Organization, another Organization+OnlineStore, and a hybrid
     * store Organization+Store+OnlineStore without hard-coding the template.
     *
     * @param array $settings
     * @return array
     */
    private static function resolve_organization_types($settings) {

        $profile = sanitize_key($settings['site_profile'] ?? 'general');

        $selected_types = $settings['organization']['types'] ?? ($settings['organization']['type'] ?? 'Organization');
        $types = self::normalize_organization_types($selected_types);
        $selected_type = self::resolve_primary_organization_type($types);

        switch ($profile) {
            case 'ecommerce':
                $types[] = 'OnlineStore';
                break;

            case 'ecommerce_local':
                $types[] = 'Store';
                $types[] = 'OnlineStore';
                break;

            case 'business':
                $types[] = 'LocalBusiness';
                break;
        }

        if (!empty($settings['commerce']['enabled'])) {
            $types[] = 'OnlineStore';
        }

        if (!empty($settings['local_business']['enabled'])) {
            if ($profile === 'ecommerce_local' || in_array('OnlineStore', $types, true) || $selected_type === 'Store') {
                $types[] = 'Store';
            } else {
                $types[] = 'LocalBusiness';
            }
        }

        return self::normalize_organization_types($types);
    }

    /**
     * Resolve Schema.org types for the primary online/brand entity.
     *
     * The public #organization node must describe the brand/online merchant,
     * not the physical branch. Store/LocalBusiness data is emitted as a
     * separate #local-store node when enough local-business data exists.
     *
     * @param array $settings
     * @param array $resolved_types
     * @return array
     */
    private static function resolve_online_organization_types($settings, $resolved_types = []) {

        $profile = sanitize_key($settings['site_profile'] ?? 'general');
        $resolved_types = self::normalize_organization_types($resolved_types);

        $types = ['Organization'];

        if (
            $profile === 'ecommerce' ||
            $profile === 'ecommerce_local' ||
            !empty($settings['commerce']['enabled']) ||
            in_array('OnlineStore', $resolved_types, true)
        ) {
            $types[] = 'OnlineStore';
        }

        foreach (['Corporation', 'NGO'] as $extra_type) {
            if (in_array($extra_type, $resolved_types, true)) {
                $types[] = $extra_type;
            }
        }

        return self::normalize_organization_types($types);
    }

    /**
     * Build a separate physical/local store node.
     *
     * This prevents properties such as address, geo, hasMap and opening hours
     * from being attached to the OnlineStore/Organization node. The local node
     * is only emitted when it has enough real location data to be useful.
     *
     * @param array $settings
     * @param array $organization
     * @return array
     */
    private static function build_local_store_schema($settings, $organization) {

        if (!self::should_build_local_store_schema($settings)) {
            return [];
        }

        $type = self::resolve_local_store_type($settings);

        $data = [
            '@type'                     => $type,
            '@id'                       => home_url('/#local-store'),
            'name'                      => $organization['name'] ?? get_bloginfo('name'),
            'url'                       => $organization['url'] ?? home_url('/'),
            'telephone'                 => self::format_telephone_for_schema($organization['telephone'] ?? ''),
            'email'                     => $organization['email'] ?? '',
            'parentOrganization'        => ['@id' => home_url('/#organization')],
            'address'                   => self::build_address($settings),
            'geo'                       => self::build_geo($settings),
            'hasMap'                    => $settings['local_business']['has_map'] ?? '',
            'openingHoursSpecification' => self::build_opening_hours_specification($settings),
            'priceRange'                => $organization['price_range'] ?? '',
            'currenciesAccepted'        => $organization['currencies_accepted'] ?? '',
            'acceptedPaymentMethod'     => $organization['payment_accepted'] ?? '',
        ];

        $data = self::remove_empty_values($data, true);

        if (!self::is_renderable_local_store_schema($data)) {
            return [];
        }

        /**
         * Filter the separate local store schema node.
         *
         * @param array $data
         * @param array $settings
         */
        $data = apply_filters('amk_schema_core_local_store_schema', $data, $settings);

        return is_array($data) && self::is_renderable_local_store_schema($data) ? $data : [];
    }

    /**
     * Decide whether a separate local store node is appropriate.
     *
     * @param array $settings
     * @return bool
     */
    private static function should_build_local_store_schema($settings) {

        $profile = sanitize_key($settings['site_profile'] ?? 'general');
        $selected_types = $settings['organization']['types'] ?? ($settings['organization']['type'] ?? 'Organization');
        $selected_types = self::normalize_organization_types($selected_types);

        return $profile === 'ecommerce_local'
            || !empty($settings['local_business']['enabled'])
            || in_array('Store', $selected_types, true)
            || in_array('LocalBusiness', $selected_types, true);
    }

    /**
     * Resolve the Schema.org type for the separate local node.
     *
     * @param array $settings
     * @return string
     */
    private static function resolve_local_store_type($settings) {

        $profile = sanitize_key($settings['site_profile'] ?? 'general');
        $selected_types = $settings['organization']['types'] ?? ($settings['organization']['type'] ?? 'Organization');
        $selected_types = self::normalize_organization_types($selected_types);

        if ($profile === 'ecommerce_local' || in_array('Store', $selected_types, true)) {
            return 'Store';
        }

        return 'LocalBusiness';
    }

    /**
     * Check if local store node contains enough local data to render.
     *
     * @param array $schema
     * @return bool
     */
    private static function is_renderable_local_store_schema($schema) {

        if (empty($schema) || !is_array($schema)) {
            return false;
        }

        if (empty($schema['@type']) || empty($schema['@id'])) {
            return false;
        }

        return !empty($schema['address'])
            || !empty($schema['geo'])
            || !empty($schema['hasMap'])
            || !empty($schema['openingHoursSpecification']);
    }

    /**
     * Pick one representative type for legacy placeholders.
     *
     * @param array $types
     * @return string
     */
    private static function resolve_primary_organization_type($types) {

        $types = self::normalize_organization_types($types);

        foreach (['OnlineStore', 'Store', 'LocalBusiness', 'Corporation', 'NGO'] as $preferred) {
            if (in_array($preferred, $types, true)) {
                return $preferred;
            }
        }

        return 'Organization';
    }

    /**
     * Sanitize, de-duplicate and order organization-like types.
     *
     * @param mixed $types
     * @return array
     */
    private static function normalize_organization_types($types) {

        if (is_string($types)) {
            $types = [$types];
        }

        if (!is_array($types)) {
            $types = [];
        }

        $allowed = array_keys(self::organization_type_options());
        $allowed[] = 'Corporation';
        $allowed[] = 'NGO';

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

        $order = ['Organization', 'LocalBusiness', 'Store', 'OnlineStore', 'Corporation', 'NGO'];
        $clean = array_values(array_unique($clean));

        usort($clean, function ($a, $b) use ($order) {
            $a_index = array_search($a, $order, true);
            $b_index = array_search($b, $order, true);

            $a_index = $a_index === false ? 999 : $a_index;
            $b_index = $b_index === false ? 999 : $b_index;

            return $a_index <=> $b_index;
        });

        return $clean;
    }

    private static function build_address($settings) {

        $address = self::get_effective_address_settings($settings);

        $data = [
            '@type'           => 'PostalAddress',
            'streetAddress'   => $address['street'],
            'addressLocality' => $address['locality'],
            'addressRegion'   => $address['region'],
            'postalCode'      => $address['postal_code'],
            'addressCountry'  => $address['country'],
        ];

        $data = self::remove_empty_values($data, true);

        if (!self::is_renderable_address($data)) {
            return [];
        }

        /**
         * Filter the final PostalAddress schema generated from global settings.
         *
         * Plugin settings are the primary source. WooCommerce store address is used
         * only as an automatic fallback for missing fields.
         *
         * @param array $data
         * @param array $settings
         */
        $data = apply_filters('amk_schema_core_organization_address', $data, $settings);

        return is_array($data) && self::is_renderable_address($data) ? $data : [];
    }


    /**
     * Collect first-run defaults from WordPress and WooCommerce.
     *
     * @return array
     */
    private static function get_platform_prefill_data() {

        $blog_name        = sanitize_text_field((string) get_bloginfo('name'));
        $blog_description = sanitize_textarea_field((string) get_bloginfo('description'));
        $home_url         = esc_url_raw(home_url('/'));
        $admin_email      = sanitize_email((string) get_option('admin_email', ''));
        $currency         = self::get_platform_currency();
        $logo_url         = self::get_wordpress_custom_logo_url();
        $address          = self::get_woocommerce_store_address();
        $store_email      = sanitize_email((string) get_option('woocommerce_email_from_address', ''));
        $store_phone      = self::get_platform_store_phone();
        $country          = $address['country'] ?? '';

        $email = $store_email !== '' ? $store_email : $admin_email;

        $source = [];

        if ($blog_name !== '' || $blog_description !== '' || $home_url !== '' || $admin_email !== '' || $logo_url !== '') {
            $source[] = 'wordpress';
        }

        if (self::has_non_empty_values($address) || $store_email !== '' || $store_phone !== '' || $currency !== '') {
            $source[] = 'woocommerce';
        }

        $data = [
            'source' => array_values(array_unique($source)),
            'organization' => [
                'name'                => $blog_name,
                'url'                 => $home_url,
                'description'         => $blog_description,
                'logo'                => $logo_url,
                'image'               => $logo_url,
                'telephone'           => $store_phone,
                'email'               => $email,
                'currencies_accepted' => $currency,
            ],
            'address' => $address,
            'contact' => [
                'telephone'          => $store_phone,
                'email'              => $email,
                'available_language' => self::get_site_language_for_schema(),
            ],
            'contact_point' => [
                'contact_type'       => 'customer support',
                'telephone'          => $store_phone,
                'email'              => $email,
                'area_served'        => $country,
                'available_language' => self::get_site_language_for_schema(),
            ],
            'commerce' => [
                'shipping_country'      => $country,
                'return_policy_country' => $country,
            ],
        ];

        /**
         * Filter platform values imported into AMK Schema Core settings.
         *
         * @param array $data
         */
        $data = apply_filters('amk_schema_core_platform_prefill_data', $data);

        return is_array($data) ? $data : [];
    }

    /**
     * Fill a nested setting only when the plugin value is empty.
     *
     * @param array $settings
     * @param array $path
     * @param mixed $value
     * @param array $filled
     * @return void
     */
    private static function fill_missing_setting(&$settings, $path, $value, &$filled) {

        if (!is_array($path) || empty($path)) {
            return;
        }

        if (is_array($value)) {
            $has_value = !empty(array_filter($value, function ($item) {
                return self::setting_has_value($item);
            }));
        } else {
            $has_value = self::setting_has_value($value);
        }

        if (!$has_value) {
            return;
        }

        $cursor =& $settings;

        foreach ($path as $index => $key) {
            if (!is_array($cursor)) {
                return;
            }

            if ($index === count($path) - 1) {
                $current = $cursor[$key] ?? '';

                if (self::setting_has_value($current)) {
                    return;
                }

                $cursor[$key] = $value;
                $filled[] = implode('.', $path);
                return;
            }

            if (!isset($cursor[$key]) || !is_array($cursor[$key])) {
                $cursor[$key] = [];
            }

            $cursor =& $cursor[$key];
        }
    }

    /**
     * Prefill the first contact point row without overwriting user data.
     *
     * @param array $settings
     * @param array $contact_point
     * @param array $filled
     * @return array
     */
    private static function prefill_primary_contact_point($settings, $contact_point, &$filled) {

        if (empty($contact_point) || !is_array($contact_point)) {
            return $settings;
        }

        if (empty($settings['contact_points']) || !is_array($settings['contact_points'])) {
            $settings['contact_points'] = [self::blank_contact_point_row()];
        }

        if (empty($settings['contact_points'][0]) || !is_array($settings['contact_points'][0])) {
            $settings['contact_points'][0] = self::blank_contact_point_row();
        }

        foreach ($contact_point as $key => $value) {
            if (!array_key_exists($key, $settings['contact_points'][0])) {
                continue;
            }

            if (!self::setting_has_value($value)) {
                continue;
            }

            if (self::setting_has_value($settings['contact_points'][0][$key] ?? '')) {
                continue;
            }

            $settings['contact_points'][0][$key] = $value;
            $filled[] = 'contact_points.0.' . $key;
        }

        return $settings;
    }

    /**
     * Check if a setting value is meaningfully filled.
     *
     * @param mixed $value
     * @return bool
     */
    private static function setting_has_value($value) {

        if (is_array($value)) {
            return self::has_non_empty_values($value);
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value !== '';
        }

        return trim((string) $value) !== '';
    }

    /**
     * Check whether an array contains at least one non-empty value.
     *
     * @param array $values
     * @return bool
     */
    private static function has_non_empty_values($values) {

        if (empty($values) || !is_array($values)) {
            return false;
        }

        foreach ($values as $value) {
            if (is_array($value)) {
                if (self::has_non_empty_values($value)) {
                    return true;
                }

                continue;
            }

            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get WooCommerce currency without requiring WooCommerce runtime objects.
     *
     * @return string
     */
    private static function get_platform_currency() {

        if (function_exists('get_woocommerce_currency')) {
            $currency = sanitize_text_field((string) get_woocommerce_currency());

            if ($currency !== '') {
                return $currency;
            }
        }

        return sanitize_text_field((string) get_option('woocommerce_currency', ''));
    }

    /**
     * Get the WordPress custom logo URL.
     *
     * @return string
     */
    private static function get_wordpress_custom_logo_url() {

        $custom_logo_id = absint(get_theme_mod('custom_logo'));

        if (!$custom_logo_id || !function_exists('wp_get_attachment_image_url')) {
            return '';
        }

        $url = wp_get_attachment_image_url($custom_logo_id, 'full');

        return $url ? esc_url_raw($url) : '';
    }

    /**
     * Try common WooCommerce/store-phone options used by stores and Persian setups.
     *
     * @return string
     */
    private static function get_platform_store_phone() {

        $option_keys = [
            'woocommerce_store_phone',
            'woocommerce_store_phone_number',
            'store_phone',
            'shop_phone',
        ];

        /**
         * Filter option keys used to discover a store phone for first-run import.
         *
         * @param array $option_keys
         */
        $option_keys = apply_filters('amk_schema_core_platform_phone_option_keys', $option_keys);

        if (!is_array($option_keys)) {
            $option_keys = [];
        }

        foreach ($option_keys as $key) {
            $key = is_string($key) ? sanitize_key($key) : '';

            if ($key === '') {
                continue;
            }

            $value = sanitize_text_field((string) get_option($key, ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Get site language in a schema-friendly format.
     *
     * @return string
     */
    private static function get_site_language_for_schema() {

        $locale = function_exists('get_locale') ? (string) get_locale() : '';
        $locale = trim($locale);

        if ($locale === '') {
            return '';
        }

        return str_replace('_', '-', $locale);
    }

    /**
     * Resolve address settings for Schema.org output.
     *
     * Stored plugin settings remain the primary source. If some fields are empty,
     * WooCommerce store address options are used as a safe automatic fallback.
     * This keeps the plugin business-ready without forcing users to duplicate
     * address data that already exists in WooCommerce.
     *
     * @param array $settings
     * @return array
     */
    private static function get_effective_address_settings($settings) {

        $stored = isset($settings['address']) && is_array($settings['address']) ? $settings['address'] : [];
        $stored = [
            'country'     => self::normalize_address_country($stored['country'] ?? ''),
            'region'      => self::normalize_address_region($stored['region'] ?? '', $stored['country'] ?? ''),
            'locality'    => sanitize_text_field($stored['locality'] ?? ''),
            'street'      => sanitize_text_field($stored['street'] ?? ''),
            'postal_code' => sanitize_text_field(self::normalize_persian_digits($stored['postal_code'] ?? '')),
        ];

        $fallback = self::get_woocommerce_store_address();

        foreach ($stored as $key => $value) {
            if ($value !== '') {
                continue;
            }

            if (!empty($fallback[$key])) {
                $stored[$key] = $fallback[$key];
            }
        }

        return $stored;
    }

    /**
     * Read WooCommerce store address as an automatic fallback.
     *
     * @return array
     */
    private static function get_woocommerce_store_address() {

        if (!function_exists('get_option')) {
            return [
                'country'     => '',
                'region'      => '',
                'locality'    => '',
                'street'      => '',
                'postal_code' => '',
            ];
        }

        $default_country = sanitize_text_field((string) get_option('woocommerce_default_country', ''));
        $country         = '';
        $region          = '';

        if ($default_country !== '') {
            $parts   = array_map('trim', explode(':', $default_country, 2));
            $country = self::normalize_address_country($parts[0] ?? '');
            $region  = self::normalize_address_region($parts[1] ?? '', $country);
        }

        $address_1 = sanitize_text_field((string) get_option('woocommerce_store_address', ''));
        $address_2 = sanitize_text_field((string) get_option('woocommerce_store_address_2', ''));
        $street    = trim(implode(' ', array_filter([$address_1, $address_2])));

        return [
            'country'     => $country,
            'region'      => $region,
            'locality'    => sanitize_text_field((string) get_option('woocommerce_store_city', '')),
            'street'      => $street,
            'postal_code' => sanitize_text_field(self::normalize_persian_digits((string) get_option('woocommerce_store_postcode', ''))),
        ];
    }

    /**
     * Normalize country value for addressCountry.
     *
     * Prefer ISO 3166-1 alpha-2 when it can be resolved. Schema.org accepts text,
     * but ISO country codes are cleaner for ecommerce/local-business markup.
     *
     * @param string $country
     * @return string
     */
    private static function normalize_address_country($country) {

        $country = sanitize_text_field(self::normalize_persian_digits($country));
        $country = trim($country);

        if ($country === '') {
            return '';
        }

        $upper = strtoupper($country);

        $iran_aliases = ['IR', 'IRN', 'IRAN', 'IRI', 'ISLAMIC REPUBLIC OF IRAN'];
        $iran_fa_aliases = ['ایران', 'جمهوری اسلامی ایران', 'جمهوري اسلامي ايران'];

        if (in_array($upper, $iran_aliases, true) || in_array($country, $iran_fa_aliases, true)) {
            return 'IR';
        }

        if (preg_match('/^[A-Z]{2}$/', $upper)) {
            return $upper;
        }

        if (function_exists('WC')) {
            $woocommerce = WC();

            if (!empty($woocommerce->countries) && method_exists($woocommerce->countries, 'get_countries')) {
                $countries = $woocommerce->countries->get_countries();

                if (is_array($countries)) {
                    foreach ($countries as $code => $label) {
                        if (strcasecmp($country, (string) $label) === 0) {
                            return strtoupper((string) $code);
                        }
                    }
                }
            }
        }

        return $country;
    }

    /**
     * Normalize region/state value.
     *
     * If WooCommerce stores a state code, convert it to the readable state name
     * when possible. Otherwise keep the sanitized user-provided value.
     *
     * @param string $region
     * @param string $country
     * @return string
     */
    private static function normalize_address_region($region, $country = '') {

        $region = sanitize_text_field(self::normalize_persian_digits($region));
        $region = trim($region);

        if ($region === '') {
            return '';
        }

        $country = self::normalize_address_country($country);

        if ($country !== '' && function_exists('WC')) {
            $woocommerce = WC();

            if (!empty($woocommerce->countries) && method_exists($woocommerce->countries, 'get_states')) {
                $states = $woocommerce->countries->get_states($country);

                if (is_array($states) && isset($states[$region])) {
                    return sanitize_text_field($states[$region]);
                }

                $upper_region = strtoupper($region);

                if (is_array($states) && isset($states[$upper_region])) {
                    return sanitize_text_field($states[$upper_region]);
                }
            }
        }

        return $region;
    }

    /**
     * Decide whether PostalAddress has enough useful data to be rendered.
     *
     * Postal code is useful, but it must not be mandatory. Many real businesses
     * display city/street/map but not postal code. Requiring postalCode was the
     * reason address disappeared from otherwise valid LocalBusiness markup.
     *
     * @param array $address
     * @return bool
     */
    private static function is_renderable_address($address) {

        if (empty($address) || !is_array($address)) {
            return false;
        }

        $has_country = !empty($address['addressCountry']);
        $has_location_detail = !empty($address['streetAddress'])
            || !empty($address['addressLocality'])
            || !empty($address['addressRegion'])
            || !empty($address['postalCode']);

        return $has_country && $has_location_detail;
    }

    /**
     * Format telephone values for Schema.org output.
     *
     * Storage remains unchanged. This method only normalizes the value that is
     * passed to schema templates. For Iranian local numbers it returns an E.164
     * style value such as +989158075737 or +985138000000.
     *
     * @param string $telephone
     * @return string
     */
    private static function format_telephone_for_schema($telephone) {

        $telephone = is_scalar($telephone) ? (string) $telephone : '';
        $telephone = trim($telephone);

        if ($telephone === '') {
            return '';
        }

        $telephone = self::normalize_persian_digits($telephone);
        $telephone = html_entity_decode($telephone, ENT_QUOTES, get_bloginfo('charset'));
        $telephone = preg_replace('/(?:ext|extension|داخلی|داخلي)\s*[:：\-]?\s*\d+$/iu', '', $telephone);
        $telephone = trim($telephone);

        $has_plus = strpos($telephone, '+') === 0;
        $digits   = preg_replace('/\D+/', '', $telephone);

        if ($digits === '') {
            return sanitize_text_field($telephone);
        }

        // 0098... or 00xx... international format.
        if (strpos($digits, '00') === 0 && strlen($digits) > 4) {
            $digits = substr($digits, 2);
            return '+' . $digits;
        }

        // Already international. Keep country code and remove formatting noise.
        if ($has_plus) {
            return '+' . $digits;
        }

        // Iranian country code without plus: 98xxxxxxxxxx.
        if (strpos($digits, '98') === 0 && strlen($digits) >= 12) {
            return '+' . $digits;
        }

        // Iranian local numbers starting with 0: 09..., 021..., 051...
        if (strpos($digits, '0') === 0 && strlen($digits) >= 10) {
            return '+98' . ltrim($digits, '0');
        }

        // Common mobile format without leading zero: 9158075737.
        if (strlen($digits) === 10 && strpos($digits, '9') === 0) {
            return '+98' . $digits;
        }

        // Common landline format without leading zero: 5138000000, 2130000000.
        if (strlen($digits) >= 8 && strlen($digits) <= 10) {
            return '+98' . $digits;
        }

        return sanitize_text_field($telephone);
    }

    /**
     * Convert Persian and Arabic digits to Latin digits.
     *
     * @param string $value
     * @return string
     */
    private static function normalize_persian_digits($value) {

        $value = is_scalar($value) ? (string) $value : '';

        return strtr($value, [
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

    private static function build_contact_point($settings) {

        $points = $settings['contact_points'] ?? [];

        if (!is_array($points)) {
            $points = [];
        }

        $result = [];

        foreach ($points as $point) {
            if (empty($point) || !is_array($point)) {
                continue;
            }

            $contact_type = $point['contact_type'] ?? 'customer support';
            $telephone    = self::format_telephone_for_schema($point['telephone'] ?? '');
            $email        = $point['email'] ?? '';
            $url          = $point['url'] ?? '';
            $options      = self::csv_to_array($point['contact_option'] ?? '');
            $area_served  = self::csv_to_array($point['area_served'] ?? '');
            $languages    = self::csv_to_array($point['available_language'] ?? '');

            if ($telephone === '' && $email === '' && $url === '') {
                continue;
            }

            $data = [
                '@type'             => 'ContactPoint',
                'contactType'       => $contact_type,
                'telephone'         => $telephone,
                'email'             => $email,
                'url'               => $url,
                'contactOption'     => $options,
                'areaServed'        => $area_served,
                'availableLanguage' => $languages,
            ];

            $data = self::remove_empty_values($data, true);

            if (!empty($data)) {
                $result[] = $data;
            }
        }

        if (empty($result)) {
            return [];
        }

        return count($result) === 1 ? $result[0] : $result;
    }

    private static function build_same_as($settings) {

        $links = [];

        foreach (($settings['social'] ?? []) as $url) {
            if (!empty($url)) {
                $links[] = esc_url_raw($url);
            }
        }

        return array_values(array_unique(array_filter($links)));
    }

    private static function build_geo($settings) {

        $latitude  = $settings['local_business']['latitude'] ?? '';
        $longitude = $settings['local_business']['longitude'] ?? '';

        if ($latitude === '' || $longitude === '') {
            return [];
        }

        return [
            '@type'     => 'GeoCoordinates',
            'latitude'  => $latitude,
            'longitude' => $longitude,
        ];
    }

    private static function build_opening_hours($settings) {

        $raw = $settings['local_business']['opening_hours'] ?? '';

        if ($raw === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $clean = [];

        foreach ($lines as $line) {
            $line = self::normalize_opening_hours_line($line);

            if ($line !== '') {
                $clean[] = $line;
            }
        }

        return array_values(array_unique($clean));
    }

    private static function build_opening_hours_specification($settings) {

        $lines = self::build_opening_hours($settings);

        if (empty($lines)) {
            return [];
        }

        $items = [];

        foreach ($lines as $line) {
            $item = self::parse_opening_hours_line($line);

            if (!empty($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private static function parse_opening_hours_line($line) {

        $line = trim((string) $line);

        if ($line === '') {
            return [];
        }

        if (!preg_match('/^(.+?)\s+(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/u', $line, $matches)) {
            return [];
        }

        $days = self::expand_opening_hours_days($matches[1]);

        if (empty($days)) {
            return [];
        }

        $opens  = self::sanitize_time_value($matches[2]);
        $closes = self::sanitize_time_value($matches[3]);

        if (!self::is_valid_opening_hours_range($opens, $closes)) {
            return [];
        }

        return [
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => $days,
            'opens'     => $opens,
            'closes'    => $closes,
        ];
    }

    private static function expand_opening_hours_days($raw_days) {

        $day_map = [
            'mo' => 'https://schema.org/Monday',
            'mon' => 'https://schema.org/Monday',
            'monday' => 'https://schema.org/Monday',
            'tu' => 'https://schema.org/Tuesday',
            'tue' => 'https://schema.org/Tuesday',
            'tuesday' => 'https://schema.org/Tuesday',
            'we' => 'https://schema.org/Wednesday',
            'wed' => 'https://schema.org/Wednesday',
            'wednesday' => 'https://schema.org/Wednesday',
            'th' => 'https://schema.org/Thursday',
            'thu' => 'https://schema.org/Thursday',
            'thursday' => 'https://schema.org/Thursday',
            'fr' => 'https://schema.org/Friday',
            'fri' => 'https://schema.org/Friday',
            'friday' => 'https://schema.org/Friday',
            'sa' => 'https://schema.org/Saturday',
            'sat' => 'https://schema.org/Saturday',
            'saturday' => 'https://schema.org/Saturday',
            'su' => 'https://schema.org/Sunday',
            'sun' => 'https://schema.org/Sunday',
            'sunday' => 'https://schema.org/Sunday',
        ];

        $order = ['mo', 'tu', 'we', 'th', 'fr', 'sa', 'su'];
        $aliases = [
            'mon' => 'mo', 'monday' => 'mo',
            'tue' => 'tu', 'tuesday' => 'tu',
            'wed' => 'we', 'wednesday' => 'we',
            'thu' => 'th', 'thursday' => 'th',
            'fri' => 'fr', 'friday' => 'fr',
            'sat' => 'sa', 'saturday' => 'sa',
            'sun' => 'su', 'sunday' => 'su',
            'دوشنبه' => 'mo',
            'سهشنبه' => 'tu', 'سه شنبه' => 'tu',
            'چهارشنبه' => 'we', 'چهار شنبه' => 'we',
            'پنجشنبه' => 'th', 'پنج شنبه' => 'th',
            'جمعه' => 'fr',
            'شنبه' => 'sa',
            'یکشنبه' => 'su', 'یک شنبه' => 'su',
        ];

        $raw_days = strtolower(trim((string) $raw_days));
        $tokens = preg_split('/\s*,\s*/', $raw_days);
        $days = [];

        foreach ($tokens as $token) {
            $token = trim($token);

            if ($token === '') {
                continue;
            }

            if (strpos($token, '-') !== false) {
                [$start, $end] = array_map('trim', explode('-', $token, 2));
                $start = $aliases[$start] ?? $start;
                $end = $aliases[$end] ?? $end;
                $start_index = array_search($start, $order, true);
                $end_index = array_search($end, $order, true);

                if ($start_index !== false && $end_index !== false) {
                    $range = $start_index <= $end_index
                        ? array_slice($order, $start_index, $end_index - $start_index + 1)
                        : array_merge(array_slice($order, $start_index), array_slice($order, 0, $end_index + 1));

                    foreach ($range as $day) {
                        $days[] = $day_map[$day];
                    }
                }

                continue;
            }

            $token = $aliases[$token] ?? $token;

            if (isset($day_map[$token])) {
                $days[] = $day_map[$token];
            }
        }

        return array_values(array_unique($days));
    }

    private static function normalize_opening_hours_line($line) {

        $line = trim((string) $line);

        if ($line === '') {
            return '';
        }

        if (!preg_match('/^(.+?)\s+(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/u', $line, $matches)) {
            return '';
        }

        $days = self::expand_opening_hours_days($matches[1]);

        if (empty($days)) {
            return '';
        }

        $opens  = self::sanitize_time_value($matches[2]);
        $closes = self::sanitize_time_value($matches[3]);

        if (!self::is_valid_opening_hours_range($opens, $closes)) {
            return '';
        }

        return trim($matches[1]) . ' ' . $opens . '-' . $closes;
    }

    private static function is_valid_opening_hours_range($opens, $closes) {

        $opens  = self::sanitize_time_value($opens);
        $closes = self::sanitize_time_value($closes);

        if ($opens === '' || $closes === '') {
            return false;
        }

        if ($opens === $closes) {
            return false;
        }

        return true;
    }

    private static function normalize_time_value($time) {

        return self::sanitize_time_value($time);
    }


    /**
     * Normalize selected countries from admin selector.
     *
     * Stores only ISO country codes.
     *
     * @param mixed $countries
     * @return array
     */
    private static function normalize_country_list($countries) {

        if (is_string($countries)) {
            $countries = preg_split('/[,|]+/', $countries);
        }

        if (!is_array($countries)) {
            return [];
        }

        $result = [];

        foreach ($countries as $country) {

            $country = strtoupper(
                sanitize_text_field((string) $country)
            );

            if ($country === 'WORLDWIDE') {
                continue;
            }

            if (preg_match('/^[A-Z]{2}$/', $country)) {
                $result[] = $country;
            }

        }

        return array_values(array_unique($result));
    }

    private static function build_return_policy($settings) {

        if (empty($settings['commerce']['enabled']) || empty($settings['commerce']['return_policy_enabled'])) {
            return [];
        }

        $commerce = isset($settings['commerce']) && is_array($settings['commerce']) ? $settings['commerce'] : [];
        $country  = self::normalize_address_country($commerce['return_policy_country'] ?? '');
        $days     = self::sanitize_positive_integer_or_empty($commerce['merchant_return_days'] ?? '');

        // The current UI models only finite-window returns. Do not emit a weak
        // MerchantReturnPolicy without country and real return window data.
        if ($country === '' || $days === '') {
            return [];
        }

        $data = [
            '@type'                => 'MerchantReturnPolicy',
            'url'                  => $commerce['return_policy_url'] ?? '',
            'applicableCountry'    => $country,
            'returnPolicyCountry'  => $country,
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays'   => (int) $days,
            'returnMethod'         => $commerce['return_method'] ?? '',
            'returnFees'           => $commerce['return_fees'] ?? '',
            'refundType'           => $commerce['refund_type'] ?? '',
        ];

        return self::remove_empty_values($data, true);
    }

    private static function build_shipping_service($settings) {

        if (empty($settings['commerce']['enabled']) || empty($settings['commerce']['shipping_enabled'])) {
            return [];
        }

        $commerce = isset($settings['commerce']) && is_array($settings['commerce']) ? $settings['commerce'] : [];
        $country  = self::normalize_address_country($commerce['shipping_country'] ?? '');

        // Shipping schema is only useful when a destination region is known.
        if ($country === '') {
            return [];
        }

        $currency = self::normalize_currency_for_schema($settings['organization']['currencies_accepted'] ?? '');

        $shipping_conditions = [
            '@type' => 'ShippingConditions',
            'shippingDestination' => [
                '@type'          => 'DefinedRegion',
                'addressCountry' => $country,
            ],
        ];

        if (($commerce['shipping_rate'] ?? '') !== '' && is_numeric($commerce['shipping_rate'])) {
            $shipping_conditions['shippingRate'] = [
                '@type'    => 'MonetaryAmount',
                'value'    => (float) $commerce['shipping_rate'],
                'currency' => $currency,
            ];
        }

        if (($commerce['free_shipping_threshold'] ?? '') !== '' && is_numeric($commerce['free_shipping_threshold'])) {
            $shipping_conditions['orderValue'] = [
                '@type'    => 'MonetaryAmount',
                'value'    => (float) $commerce['free_shipping_threshold'],
                'currency' => $currency,
            ];
        }

        $shipping_conditions = self::remove_empty_values($shipping_conditions, true);

        $delivery_time = self::build_shipping_delivery_time($commerce);

        $data = [
            '@type'              => 'ShippingService',
            'name'               => $commerce['shipping_name'] ?? '',
            'description'        => $commerce['shipping_description'] ?? '',
            'fulfillmentType'    => 'https://schema.org/FulfillmentTypeDelivery',
            'shippingConditions' => $shipping_conditions,
            'deliveryTime'       => $delivery_time,
        ];

        return self::remove_empty_values($data, true);
    }

    private static function build_shipping_delivery_time($commerce) {

        $handling_time = self::build_quantitative_value_range(
            $commerce['handling_min_days'] ?? '',
            $commerce['handling_max_days'] ?? '',
            'DAY'
        );

        $transit_time = self::build_quantitative_value_range(
            $commerce['transit_min_days'] ?? '',
            $commerce['transit_max_days'] ?? '',
            'DAY'
        );

        $data = [
            '@type'        => 'ShippingDeliveryTime',
            'handlingTime' => $handling_time,
            'transitTime'  => $transit_time,
        ];

        return self::remove_empty_values($data, true);
    }

    private static function build_quantitative_value_range($min, $max, $unit_code = '') {

        if ($min === '' && $max === '') {
            return [];
        }

        $data = [
            '@type'    => 'QuantitativeValue',
            'minValue' => $min !== '' ? (int) $min : '',
            'maxValue' => $max !== '' ? (int) $max : '',
            'unitCode' => $unit_code,
        ];

        return self::remove_empty_values($data, true);
    }

    private static function migrate_legacy_options($settings, $raw_stored, $has_new_settings) {

        $legacy_name = get_option(self::LEGACY_ORG_NAME_OPTION, '');
        $legacy_url  = get_option(self::LEGACY_ORG_URL_OPTION, '');

        if (!empty($legacy_name) && (!$has_new_settings || empty($raw_stored['organization']['name']))) {
            $settings['organization']['name'] = sanitize_text_field($legacy_name);
        }

        if (!empty($legacy_url) && (!$has_new_settings || empty($raw_stored['organization']['url']))) {
            $settings['organization']['url'] = esc_url_raw($legacy_url);
        }

        return $settings;
    }

    private static function csv_to_array($value) {

        if (empty($value)) {
            return [];
        }

        $items = explode(',', $value);
        $items = array_map('trim', $items);
        $items = array_filter($items);

        return array_values($items);
    }

    private static function sanitize_contact_points($settings) {

        $raw_points = $settings['contact_points'] ?? [];

        if (!is_array($raw_points)) {
            $raw_points = [];
        }

        $allowed_types = array_keys(self::contact_type_options());
        $points = [];

        foreach ($raw_points as $point) {
            $point = self::sanitize_contact_point_row($point, $allowed_types);

            if ($point === null) {
                continue;
            }

            $points[] = $point;
        }

        if (empty($points) && !empty($settings['contact']) && is_array($settings['contact'])) {
            $legacy_point = self::sanitize_contact_point_row($settings['contact'], $allowed_types);

            if ($legacy_point !== null) {
                $points[] = $legacy_point;
            }
        }

        if (empty($points)) {
            $points[] = self::blank_contact_point_row();
        }

        return array_values($points);
    }

    private static function sanitize_contact_point_row($point, $allowed_types) {

        if (!is_array($point)) {
            return null;
        }

        $contact_type = sanitize_text_field($point['contact_type'] ?? 'customer support');

        if (!in_array($contact_type, $allowed_types, true)) {
            $contact_type = 'customer support';
        }

        $telephone = sanitize_text_field($point['telephone'] ?? '');
        $email     = sanitize_email($point['email'] ?? '');
        $url       = esc_url_raw($point['url'] ?? '');
        $option    = self::sanitize_csv_text_or_array($point['contact_option'] ?? '');
        $area      = self::sanitize_csv_text_or_array($point['area_served'] ?? '');
        $language  = sanitize_text_field($point['available_language'] ?? '');

        if ($telephone === '' && $email === '' && $url === '') {
            return null;
        }

        return [
            'contact_type'       => $contact_type,
            'telephone'          => $telephone,
            'email'              => $email,
            'url'                => $url,
            'contact_option'     => $option,
            'area_served'        => $area,
            'available_language' => $language,
        ];
    }

    private static function blank_contact_point_row() {

        return [
            'contact_type'       => 'customer support',
            'telephone'          => '',
            'email'              => '',
            'url'                => '',
            'contact_option'     => '',
            'area_served'        => '',
            'available_language' => '',
        ];
    }

    private static function sanitize_schema_url_option($value, $allowed_values, $default = '') {

        $value = esc_url_raw($value);

        if (!in_array($value, $allowed_values, true)) {
            return $default;
        }

        return $value;
    }

    private static function sanitize_social_links($social, $dynamic_rows = null) {

        $social = is_array($social) ? $social : [];
        $clean = [];

        foreach (array_keys(self::social_options()) as $key) {
            $clean[$key] = esc_url_raw($social[$key] ?? '');
        }

        if (is_array($dynamic_rows)) {
            $clean = array_fill_keys(array_keys(self::social_options()), '');

            foreach ($dynamic_rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $network = sanitize_key($row['network'] ?? '');
                $url = esc_url_raw($row['url'] ?? '');

                if ($network === '' || $url === '' || !array_key_exists($network, $clean)) {
                    continue;
                }

                $clean[$network] = $url;
            }
        }

        return $clean;
    }

    private static function sanitize_opening_hours_from_settings($local_business) {

        $local_business = is_array($local_business) ? $local_business : [];

        if (!empty($local_business['opening_hours_rows']) && is_array($local_business['opening_hours_rows'])) {
            $lines = [];

            foreach (self::opening_hours_day_keys() as $day_key) {
                $row = $local_business['opening_hours_rows'][$day_key] ?? [];

                if (empty($row['enabled']) || !is_array($row)) {
                    continue;
                }

                $opens = self::sanitize_time_value($row['opens'] ?? '');
                $closes = self::sanitize_time_value($row['closes'] ?? '');

                if (!self::is_valid_opening_hours_range($opens, $closes)) {
                    continue;
                }

                $lines[] = $day_key . ' ' . $opens . '-' . $closes;
            }

            return implode("\n", $lines);
        }

        $raw = sanitize_textarea_field($local_business['opening_hours'] ?? '');

        if ($raw === '') {
            return '';
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $clean = [];

        foreach ($lines as $line) {
            $line = self::normalize_opening_hours_line($line);

            if ($line !== '') {
                $clean[] = $line;
            }
        }

        return implode("\n", array_values(array_unique($clean)));
    }

    private static function opening_hours_day_keys() {

        return ['mo', 'tu', 'we', 'th', 'fr', 'sa', 'su'];
    }

    private static function sanitize_time_value($value) {

        $value = is_string($value) ? trim($value) : '';

        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $value, $matches)) {
            return '';
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return '';
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private static function sanitize_positive_integer_or_empty($value) {

        if ($value === '' || $value === null) {
            return '';
        }

        $value = self::normalize_persian_digits((string) $value);

        return (string) absint($value);
    }

    private static function sanitize_max_days_or_empty($min, $max) {

        $min = self::sanitize_positive_integer_or_empty($min);
        $max = self::sanitize_positive_integer_or_empty($max);

        if ($max === '') {
            return '';
        }

        if ($min !== '' && (int) $max < (int) $min) {
            return $min;
        }

        return $max;
    }

    private static function sanitize_decimal_or_empty($value) {

        if ($value === '' || $value === null) {
            return '';
        }

        $value = self::normalize_persian_digits((string) $value);
        $value = str_replace(',', '.', $value);
        $value = preg_replace('/[^0-9.]/', '', $value);

        if ($value === '') {
            return '';
        }

        $parts = explode('.', $value);

        if (count($parts) > 2) {
            $value = array_shift($parts) . '.' . implode('', $parts);
        }

        return $value;
    }

    private static function normalize_currency_for_schema($currency) {

        $currency = strtoupper(trim(sanitize_text_field((string) $currency)));

        if ($currency === '') {
            return '';
        }

        $rial_aliases = ['IRR', 'IRT', 'TOMAN', 'TOOMAN', 'تومان', 'ریال', 'ريال'];

        if (in_array($currency, $rial_aliases, true)) {
            return 'IRR';
        }

        if (preg_match('/^[A-Z]{3}$/', $currency)) {
            return $currency;
        }

        return $currency;
    }

    private static function sanitize_csv_text_or_array($value) {

        if (is_array($value)) {
            $items = array_map('sanitize_text_field', $value);
            $items = array_map('trim', $items);
            $items = array_filter($items);

            return implode(', ', array_values(array_unique($items)));
        }

        return sanitize_text_field($value);
    }

    private static function sanitize_coordinate_or_empty($value) {

        if ($value === '' || $value === null) {
            return '';
        }

        $value = str_replace(',', '.', (string) $value);
        $value = preg_replace('/[^0-9.\-]/', '', $value);

        return $value;
    }

    private static function remove_empty_values($data, $remove_type_only = false) {

        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = self::remove_empty_values($value, $remove_type_only);
            }

            if ($value === '' || $value === null || $value === []) {
                unset($data[$key]);
                continue;
            }

            $data[$key] = $value;
        }

        if ($remove_type_only && count($data) === 1 && isset($data['@type'])) {
            return [];
        }

        return $data;
    }

    private static function array_merge_recursive_distinct(array $base, array $override) {

        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::array_merge_recursive_distinct($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    private static function get_current_url() {

        if (is_admin()) {
            return home_url('/');
        }

        $scheme = is_ssl() ? 'https://' : 'http://';

        $host = isset($_SERVER['HTTP_HOST'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']))
            : wp_parse_url(home_url('/'), PHP_URL_HOST);

        $uri = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
            : '/';

        return esc_url_raw($scheme . $host . $uri);
    }
}
