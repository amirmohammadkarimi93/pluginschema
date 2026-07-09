<?php

namespace AMK\SchemaCore\Admin;

use AMK\SchemaCore\Core\GlobalSettings;
use AMK\SchemaCore\Core\IranLocations;
use AMK\SchemaCore\Core\SchemaMaintenance;

defined('ABSPATH') || exit;

class SettingsPage {

    public function render() {

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'amk-schema-core'));
        }

        $notice   = $this->maybe_save_settings();
        $settings = GlobalSettings::get();

        ?>
        <div class="wrap amk-settings-page">

            <div class="amk-settings-shell">

                <div class="amk-settings-hero">
                    <div class="amk-settings-hero-content">
                        <span class="amk-settings-badge">AMK Schema Core</span>

                        <h1><?php esc_html_e('Global Schema Settings', 'amk-schema-core'); ?></h1>

                        <p>
                            <?php esc_html_e('This section defines the central identity of the site. Changing the site type shows only the relevant fields so the panel stays focused.', 'amk-schema-core'); ?>
                        </p>
                    </div>

                    <div class="amk-settings-hero-mark">
                        <span>{ }</span>
                    </div>
                </div>

                <?php if (!empty($notice)) : ?>
                    <?php
                    $notice_type = is_array($notice) && !empty($notice['type']) ? sanitize_html_class($notice['type']) : 'success';
                    $notice_messages = is_array($notice) ? ($notice['messages'] ?? []) : [$notice];
                    $notice_messages = is_array($notice_messages) ? $notice_messages : [$notice_messages];
                    ?>
                    <div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible amk-settings-notice">
                        <?php foreach ($notice_messages as $notice_message) : ?>
                            <p><?php echo esc_html((string) $notice_message); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="" class="amk-settings-form" id="amk-schema-global-settings-form">

                    <?php wp_nonce_field('amk_schema_core_save_settings', 'amk_schema_core_settings_nonce'); ?>

                    <?php $this->render_profile_section($settings); ?>
                    <?php $this->render_special_pages_section($settings); ?>
                    <?php $this->render_organization_section($settings); ?>
                    <?php $this->render_address_section($settings); ?>
                    <?php $this->render_contact_section($settings); ?>
                    <?php $this->render_social_section($settings); ?>
                    <?php $this->render_commerce_section($settings); ?>
                    <?php $this->render_local_business_section($settings); ?>
                    <?php $this->render_maintenance_section(); ?>

                    <div class="amk-settings-actions">
                        <button
                            type="submit"
                            name="amk_schema_core_save_settings"
                            value="1"
                            class="button button-primary amk-settings-submit"
                        >
                            <?php esc_html_e('Save Global Settings', 'amk-schema-core'); ?>
                        </button>
                    </div>

                </form>

            </div>

        </div>

        <?php $this->render_dynamic_visibility_script(); ?>
        <?php $this->render_iran_location_script(); ?>
        <?php $this->render_contact_points_script(); ?>
        <?php $this->render_social_links_script(); ?>
        <?php $this->render_tooltip_styles(); ?>


        <?php
    }

    private function render_profile_section($settings) {

        ?>
        <div class="amk-settings-card amk-settings-card-profile" data-amk-card="profile">

            <div class="amk-settings-card-header">
                <div>
                    <h2><?php esc_html_e('Site Profile', 'amk-schema-core'); ?></h2>
                    <p>
                        <?php esc_html_e('Choose the site type so only fields related to that model are displayed.', 'amk-schema-core'); ?>
                    </p>
                </div>
            </div>

            <div class="amk-settings-grid">
                <?php
                $this->select_field(
                    __('Site type', 'amk-schema-core'),
                    'global_settings[site_profile]',
                    $settings['site_profile'],
                    GlobalSettings::profile_options(),
                    __('For WooCommerce sites, Online store or Online store + physical branch is usually more accurate.', 'amk-schema-core'),
                    [
                        'field_key' => 'site_profile',
                    ]
                );
                ?>
            </div>

        </div>
        <?php
    }

    private function render_special_pages_section($settings) {

        $special_pages = $settings['special_pages'] ?? [];

        ?>
        <div class="amk-settings-card" data-amk-card="special-pages">

            <div class="amk-settings-card-header">
                <div>
                    <h2><?php esc_html_e('Special Site Pages', 'amk-schema-core'); ?></h2>
                    <p>
                        <?php esc_html_e('For Contact and About pages, detection uses the page ID instead of URL or title, so permalink changes do not break it.', 'amk-schema-core'); ?>
                    </p>
                </div>
            </div>

            <div class="amk-settings-grid">
                <?php
                $this->page_select_field(
                    __('Contact page', 'amk-schema-core'),
                    'global_settings[special_pages][contact_page_id]',
                    $special_pages['contact_page_id'] ?? 0,
                    __('If this page is selected, the page schema type becomes ContactPage.', 'amk-schema-core')
                );

                $this->page_select_field(
                    __('About page', 'amk-schema-core'),
                    'global_settings[special_pages][about_page_id]',
                    $special_pages['about_page_id'] ?? 0,
                    __('If this page is selected, the page schema type becomes AboutPage.', 'amk-schema-core')
                );
                ?>
            </div>

        </div>
        <?php
    }

    private function render_organization_section($settings) {

        $org = $settings['organization'];

        ?>
        <div class="amk-settings-card" data-amk-card="organization">

            <div class="amk-settings-card-header">
                <div>
                    <h2><?php esc_html_e('Organization / Brand Identity', 'amk-schema-core'); ?></h2>
                    <p>
                        <?php esc_html_e('Core brand, company, site, or store information. This section is required for every site type.', 'amk-schema-core'); ?>
                    </p>
                </div>
            </div>

            <div class="amk-settings-grid">
                <?php
                $this->multi_select_field(
                    __('Main entity types', 'amk-schema-core'),
                    'global_settings[organization][types]',
                    $org['types'] ?? ($org['type'] ?? 'Organization'),
                    GlobalSettings::organization_type_options(),
                    __('For an online store with a physical branch, select at least Organization + Store + OnlineStore. Selecting only OnlineStore may trigger warnings for physical properties such as geo, hasMap, and openingHoursSpecification.', 'amk-schema-core'),
                    [
                        'field_key' => 'organization_types',
                    ]
                );

                $this->text_field(
                    __('Brand / organization name', 'amk-schema-core'),
                    'global_settings[organization][name]',
                    $org['name'],
                    __('For example: Noble Power', 'amk-schema-core'),
                    __('Used as name in Schema output.', 'amk-schema-core')
                );

                $this->text_field(
                    __('Legal name', 'amk-schema-core'),
                    'global_settings[organization][legal_name]',
                    $org['legal_name'],
                    __('For example: Company ...', 'amk-schema-core'),
                    __('Enter the registered or legal name if available; it is optional.', 'amk-schema-core'),
                    [
                        'profiles' => ['general', 'business', 'ecommerce', 'ecommerce_local'],
                    ]
                );

                $this->text_field(
                    __('Alternate name', 'amk-schema-core'),
                    'global_settings[organization][alternate_name]',
                    $org['alternate_name'],
                    __('For example: Noble Power', 'amk-schema-core'),
                    __('For an English name, short name, or known brand name.', 'amk-schema-core')
                );

                $this->url_field(
                    __('Site URL', 'amk-schema-core'),
                    'global_settings[organization][url]',
                    $org['url'],
                    'https://example.com',
                    __('Main site or store URL.', 'amk-schema-core')
                );

                $this->url_field(
                    __('Logo', 'amk-schema-core'),
                    'global_settings[organization][logo]',
                    $org['logo'],
                    'https://example.com/logo.png',
                    __('Direct logo URL. A WordPress media picker can be added later.', 'amk-schema-core')
                );

                $this->url_field(
                    __('Brand / store image', 'amk-schema-core'),
                    'global_settings[organization][image]',
                    $org['image'],
                    'https://example.com/brand-image.jpg',
                    __('Optional; representative image for the brand or store.', 'amk-schema-core'),
                    [
                        'profiles' => ['general', 'business', 'ecommerce', 'ecommerce_local'],
                    ]
                );

                $this->text_field(
                    __('Primary phone number', 'amk-schema-core'),
                    'global_settings[organization][telephone]',
                    $org['telephone'],
                    '+982112345678',
                    __('Primary phone number for the organization or store.', 'amk-schema-core'),
                    [
                        'profiles' => ['general', 'business', 'ecommerce', 'ecommerce_local'],
                    ]
                );

                $this->email_field(
                    __('Primary email', 'amk-schema-core'),
                    'global_settings[organization][email]',
                    $org['email'],
                    'info@example.com',
                    __('Public email for the organization or store.', 'amk-schema-core'),
                    [
                        'profiles' => ['general', 'business', 'ecommerce', 'ecommerce_local'],
                    ]
                );

                $this->text_field(
                    __('Price range', 'amk-schema-core'),
                    'global_settings[organization][price_range]',
                    $org['price_range'],
                    '$$',
                    __('Useful for Store, LocalBusiness, or shop schemas.', 'amk-schema-core'),
                    [
                        'profiles'  => ['business', 'ecommerce', 'ecommerce_local'],
                        'org_types' => ['OnlineStore', 'Store', 'LocalBusiness'],
                    ]
                );

                $this->text_field(
                    __('Accepted currencies', 'amk-schema-core'),
                    'global_settings[organization][currencies_accepted]',
                    $org['currencies_accepted'],
                    'IRR',
                    __('For example IRR or USD. Important for stores.', 'amk-schema-core'),
                    [
                        'profiles' => ['ecommerce', 'ecommerce_local'],
                    ]
                );

                $this->multi_select_field(
                    __('Payment methods', 'amk-schema-core'),
                    'global_settings[organization][payment_accepted]',
                    $org['payment_accepted'],
                    $this->payment_accepted_options(),
                    __('Select one or more payment methods.', 'amk-schema-core'),
                    [
                        'profiles' => ['business', 'ecommerce', 'ecommerce_local'],
                    ]
                );

                $this->date_field(
                    __('Founding date', 'amk-schema-core'),
                    'global_settings[organization][founding_date]',
                    $org['founding_date'],
                    '2020-01-01',
                    __('Select the date from the calendar. The value is stored in YYYY-MM-DD format.', 'amk-schema-core'),
                    [
                        'profiles' => ['general', 'business', 'ecommerce', 'ecommerce_local'],
                    ]
                );

                $this->text_field(
                    __('Tax ID', 'amk-schema-core'),
                    'global_settings[organization][tax_id]',
                    $org['tax_id'],
                    '',
                    __('Optional; use only if it really appears on the site or business documents.', 'amk-schema-core'),
                    [
                        'profiles' => ['business', 'ecommerce', 'ecommerce_local'],
                    ]
                );

                $this->text_field(
                    __('VAT ID', 'amk-schema-core'),
                    'global_settings[organization][vat_id]',
                    $org['vat_id'],
                    '',
                    __('Optional.', 'amk-schema-core'),
                    [
                        'profiles' => ['business', 'ecommerce', 'ecommerce_local'],
                    ]
                );

                $this->textarea_field(
                    __('Short brand description', 'amk-schema-core'),
                    'global_settings[organization][description]',
                    $org['description'],
                    __('Short description of the brand, site, or store', 'amk-schema-core'),
                    __('This text should be real, short, and consistent with the site content.', 'amk-schema-core')
                );
                ?>
            </div>

        </div>
        <?php
    }

    private function render_address_section($settings) {

    $address = $settings['address'];

    if (empty($address['country'])) {
        $address['country'] = 'IR';
    }

    $country  = isset($address['country']) ? (string) $address['country'] : 'IR';
    $region   = isset($address['region']) ? (string) $address['region'] : '';
    $locality = isset($address['locality']) ? (string) $address['locality'] : '';

    $countries = IranLocations::country_options();
    $provinces = IranLocations::provinces($country);
    $cities    = IranLocations::city_options($country, $region);

    ?>
    <div
        class="amk-settings-card"
        data-amk-card="address"
    >

        <div class="amk-settings-card-header">
            <div>
                <h2><?php esc_html_e('Address', 'amk-schema-core'); ?></h2>
                <p>
                    <?php esc_html_e('Select the country, region/state, and city. Lists update automatically based on the selected country.', 'amk-schema-core'); ?>
                </p>
            </div>
        </div>

        <div class="amk-settings-grid">

            <div class="amk-settings-field">
                <label for="global_settings_address_country"><?php esc_html_e('Country', 'amk-schema-core'); ?></label>

                <select
                    id="global_settings_address_country"
                    name="global_settings[address][country]"
                >
                    <?php foreach ($countries as $country_value => $country_label) : ?>
                        <option
                            value="<?php echo esc_attr($country_value); ?>"
                            <?php selected($country, $country_value); ?>
                        >
                            <?php echo esc_html($country_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <p><?php esc_html_e('Countries are loaded from the plugin global dataset.', 'amk-schema-core'); ?></p>
            </div>

            <div class="amk-settings-field">
                <label for="global_settings_address_region"><?php esc_html_e('Region', 'amk-schema-core'); ?></label>

                <select
                    id="global_settings_address_region"
                    name="global_settings[address][region]"
                >
                    <option value=""><?php esc_html_e('Select region', 'amk-schema-core'); ?></option>

                    <?php foreach ($provinces as $province_value => $province_label) : ?>
                        <option
                            value="<?php echo esc_attr($province_value); ?>"
                            <?php selected($region, $province_value); ?>
                        >
                            <?php echo esc_html($province_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <p><?php esc_html_e('Selecting a region automatically updates the city list.', 'amk-schema-core'); ?></p>
            </div>

            <div class="amk-settings-field">
                <label for="global_settings_address_locality"><?php esc_html_e('City', 'amk-schema-core'); ?></label>

                <select
                    id="global_settings_address_locality"
                    name="global_settings[address][locality]"
                    data-selected-city="<?php echo esc_attr($locality); ?>"
                >
                    <option value=""><?php esc_html_e('Select a region first', 'amk-schema-core'); ?></option>

                    <?php foreach ($cities as $city_value => $city_label) : ?>
                        <option
                            value="<?php echo esc_attr($city_value); ?>"
                            <?php selected($locality, $city_value); ?>
                        >
                            <?php echo esc_html($city_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <p><?php esc_html_e('Cities are displayed based on the selected region.', 'amk-schema-core'); ?></p>
            </div>

            <?php
            $this->text_field(
                __('Full address', 'amk-schema-core'),
                'global_settings[address][street]',
                $address['street'],
                __('Street ...', 'amk-schema-core'),
                'streetAddress'
            );

            $this->text_field(
                __('Postal code', 'amk-schema-core'),
                'global_settings[address][postal_code]',
                $address['postal_code'],
                '',
                'postalCode'
            );
            ?>

        </div>

    </div>
    <?php
}

    private function render_contact_section($settings) {

    $contact_points = $settings['contact_points'] ?? [];

    if (empty($contact_points) || !is_array($contact_points)) {
        $contact_points = [
            [
                'contact_type'       => 'customer support',
                'telephone'          => '',
                'email'              => '',
                'url'                => '',
                'contact_option'     => '',
                'area_served'        => '',
                'available_language' => '',
            ],
        ];
    }

    ?>
    <div class="amk-settings-card" data-amk-card="contact">

        <div class="amk-settings-card-header">
            <div>
                <h2><?php esc_html_e('Contact Points / Support', 'amk-schema-core'); ?></h2>
                <p>
                    <?php esc_html_e('You can define multiple contact types, such as sales, customer support, technical support, order support, or warranty.', 'amk-schema-core'); ?>
                </p>
            </div>
        </div>

        <div
            id="amk-contact-points-repeater"
            class="amk-contact-points-repeater"
            data-next-index="<?php echo esc_attr(count($contact_points)); ?>"
        >
            <?php foreach ($contact_points as $index => $point) : ?>
                <?php $this->render_contact_point_row($index, $point); ?>
            <?php endforeach; ?>
        </div>

        <div class="amk-settings-repeat-actions">
            <button
                type="button"
                class="button button-secondary"
                id="amk-add-contact-point"
            >
                <?php esc_html_e('Add contact point', 'amk-schema-core'); ?>
            </button>
        </div>

        <script type="text/html" id="amk-contact-point-template">
            <?php
            $this->render_contact_point_row(
                '__INDEX__',
                [
                    'contact_type'       => 'customer support',
                    'telephone'          => '',
                    'email'              => '',
                    'url'                => '',
                    'contact_option'     => '',
                    'area_served'        => '',
                    'available_language' => '',
                ],
                true
            );
            ?>
        </script>

    </div>
    <?php
}

    private function render_social_section($settings) {

        $social = $settings['social'];
        $social_rows = $this->social_rows_from_settings($social);

        ?>
        <div class="amk-settings-card" data-amk-card="social">

            <div class="amk-settings-card-header">
                <div>
                    <h2><?php esc_html_e('Social Networks and Official Profiles', 'amk-schema-core'); ?></h2>
                    <p>
                        <?php esc_html_e('These links are used in sameAs and help identify the brand.', 'amk-schema-core'); ?>
                    </p>
                </div>
            </div>

            <div
                class="amk-dynamic-social-list"
                id="amk-social-links"
                data-next-index="<?php echo esc_attr(count($social_rows)); ?>"
            >
                <?php foreach ($social_rows as $index => $row) : ?>
                    <?php $this->render_social_row($index, $row); ?>
                <?php endforeach; ?>
            </div>

            <div class="amk-settings-repeat-actions">
                <button type="button" class="button button-secondary" id="amk-add-social-link">
                    <?php esc_html_e('Add social network', 'amk-schema-core'); ?>
                </button>
            </div>

            <script type="text/html" id="amk-social-link-template">
                <?php $this->render_social_row('__INDEX__', ['network' => '', 'url' => ''], true); ?>
            </script>

        </div>
        <?php
    }

    private function social_rows_from_settings($social) {

        $rows = [];

        if (is_array($social)) {
            foreach ($social as $network => $url) {
                $url = is_string($url) ? trim($url) : '';

                if ($url === '') {
                    continue;
                }

                $rows[] = [
                    'network' => $network,
                    'url'     => $url,
                ];
            }
        }

        if (empty($rows)) {
            $rows[] = ['network' => '', 'url' => ''];
        }

        return $rows;
    }

    private function render_social_row($index, $row, $is_template = false) {

        $row = is_array($row) ? $row : [];
        $network = $row['network'] ?? '';
        $url = $row['url'] ?? '';
        $name_prefix = 'global_settings[social_dynamic][' . $index . ']';

        ?>
        <div class="amk-dynamic-social-row" data-social-row>
            <select name="<?php echo esc_attr($name_prefix . '[network]'); ?>">
                <option value=""><?php esc_html_e('Select network', 'amk-schema-core'); ?></option>
                <?php foreach (GlobalSettings::social_options() as $option_value => $option_label) : ?>
                    <option value="<?php echo esc_attr($option_value); ?>" <?php selected($network, $option_value); ?>>
                        <?php echo esc_html($option_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input
                type="url"
                name="<?php echo esc_attr($name_prefix . '[url]'); ?>"
                value="<?php echo esc_attr($url); ?>"
                placeholder="https://example.com/brand"
                dir="ltr"
            >

            <button type="button" class="button-link-delete amk-remove-social-link">
                <?php esc_html_e('Remove', 'amk-schema-core'); ?>
            </button>
        </div>
        <?php
    }

    private function social_url_placeholders() {

        return [
            'instagram' => 'https://instagram.com/brand',
            'telegram'  => 'https://t.me/brand',
            'linkedin'  => 'https://linkedin.com/company/brand',
            'facebook'  => 'https://facebook.com/brand',
            'threads'   => 'https://threads.net/@brand',
            'youtube'   => 'https://youtube.com/@brand',
            'aparat'    => 'https://aparat.com/brand',
            'x'         => 'https://x.com/brand',
            'tiktok'    => 'https://tiktok.com/@brand',
            'pinterest' => 'https://pinterest.com/brand',
            'whatsapp'  => 'https://wa.me/989121234567',
            'github'    => 'https://github.com/brand',
            'medium'    => 'https://medium.com/@brand',
            'reddit'    => 'https://reddit.com/r/brand',
            'discord'   => 'https://discord.gg/brand',
            'twitch'    => 'https://twitch.tv/brand',
            'snapchat'  => 'https://snapchat.com/add/brand',
            'eitaa'     => 'https://eitaa.com/brand',
            'rubika'    => 'https://rubika.ir/brand',
            'bale'      => 'https://ble.ir/brand',
            'soroush'   => 'https://splus.ir/brand',
        ];
    }

    private function render_commerce_section($settings) {

        $commerce = $settings['commerce'];

        ?>
        <div
            class="amk-settings-card"
            data-amk-card="commerce"
        >

            <div class="amk-settings-card-header">
                <div>
                    <h2><?php esc_html_e('Commerce and Merchant Listings Settings', 'amk-schema-core'); ?></h2>
                    <p>
                        <?php esc_html_e('This section sets store defaults for shipping and return information. These values are used on product offers and matter for Google Merchant listings.', 'amk-schema-core'); ?>
                    </p>
                </div>
            </div>

            <div class="notice notice-info inline" style="margin: 0 0 18px 0;">
                <p>
                    <strong><?php esc_html_e('Section rule:', 'amk-schema-core'); ?></strong>
                    <?php esc_html_e('These settings are the global store defaults. If a product-specific override is added later, that product value will take priority; for now this is the main source for shipping and returns across products.', 'amk-schema-core'); ?>
                </p>
                <p>
                    <?php esc_html_e('Do not enter fake data. If shipping or return policies do not really exist on the site or are not visible to users, keep that section disabled.', 'amk-schema-core'); ?>
                </p>
            </div>

            <div class="amk-settings-grid">

                <?php
                $this->checkbox_field(
                    __('Enable commerce data for Merchant listings', 'amk-schema-core'),
                    'global_settings[commerce][enabled]',
                    $commerce['enabled'],
                    __('When enabled, store data such as shipping, returns, payment methods, and currencies are used in schema. The fields below are always visible for configuration, but are not added to schema output until this option is enabled.', 'amk-schema-core')
                );
                ?>

            </div>

            <div
                class="amk-settings-subtitle"
            >
                <h3><?php esc_html_e('Product Return Policy', 'amk-schema-core'); ?></h3>
                <p>
                    <?php esc_html_e('This section builds hasMerchantReturnPolicy inside Product > Offer. Do not enable it unless return rules are published on the site and actually apply.', 'amk-schema-core'); ?>
                </p>
            </div>

            <div
                class="notice notice-warning inline"
                style="margin: 0 0 18px 0;"
            >
                <p>
                    <strong><?php esc_html_e('Minimum required for returns:', 'amk-schema-core'); ?></strong>
                    <?php esc_html_e('Required for returns: enable return policy, country, policy type, return window, return method, and return fee. The policy page link is optional but recommended for real stores.', 'amk-schema-core'); ?>
                </p>
            </div>

            <div
                class="amk-settings-grid"
            >
                <?php
                $this->checkbox_field(
                    __('Enable return policy', 'amk-schema-core'),
                    'global_settings[commerce][return_policy_enabled]',
                    $commerce['return_policy_enabled'],
                    __('When enabled, MerchantReturnPolicy is added to product offers. Enable it only when this policy really exists on the site.', 'amk-schema-core')
                );

                $this->url_field(
                    __('Return policy page URL', 'amk-schema-core'),
                    'global_settings[commerce][return_policy_url]',
                    $commerce['return_policy_url'],
                    'https://example.com/return-policy/',
                    __('A page where users can view the return rules. Leave empty if you do not have a dedicated page yet.', 'amk-schema-core'),
                    [
                        'requires_checkbox' => 'global_settings_commerce_return_policy_enabled',
                    ]
                );

                $this->country_selector_field(
                    __('Return countries', 'amk-schema-core'),
                    'global_settings[commerce][return_policy_countries]',
                    $commerce['return_policy_countries'] ?? [],
                    [
                        'requires_checkbox' => 'global_settings_commerce_return_policy_enabled',
                    ]
                );

                $this->number_field(
                    __('Return window in days', 'amk-schema-core'),
                    'global_settings[commerce][merchant_return_days]',
                    $commerce['merchant_return_days'],
                    '7',
                    __('If returns are time-limited, enter the real number of days. If the return policy has no time limit, this model is not suitable yet and should be designed separately.', 'amk-schema-core'),
                    [
                        'requires_checkbox' => 'global_settings_commerce_return_policy_enabled',
                    ]
                );

                $this->select_field(
                    __('Return method', 'amk-schema-core'),
                    'global_settings[commerce][return_method]',
                    $commerce['return_method'],
                    GlobalSettings::return_method_options(),
                    __('The real method for receiving returned items, such as return by mail or in-store return.', 'amk-schema-core'),
                    [
                        'requires_checkbox' => 'global_settings_commerce_return_policy_enabled',
                    ]
                );

                $this->select_field(
                    __('Return fee', 'amk-schema-core'),
                    'global_settings[commerce][return_fees]',
                    $commerce['return_fees'],
                    GlobalSettings::return_fees_options(),
                    __('Specify whether returns are free or paid by the customer. The value must match the policy text on the site.', 'amk-schema-core'),
                    [
                        'requires_checkbox' => 'global_settings_commerce_return_policy_enabled',
                    ]
                );

                $this->select_field(
                    __('Refund type', 'amk-schema-core'),
                    'global_settings[commerce][refund_type]',
                    $commerce['refund_type'],
                    GlobalSettings::refund_type_options(),
                    __('Full refund, store credit, or exchange. Select only what is actually offered.', 'amk-schema-core'),
                    [
                        'requires_checkbox' => 'global_settings_commerce_return_policy_enabled',
                    ]
                );
                ?>
            </div>

            <div
                class="amk-settings-subtitle"
            >
                <h3><?php esc_html_e('Product Shipping Policy', 'amk-schema-core'); ?></h3>
                <p>
                    <?php esc_html_e('This section builds shippingDetails inside Product > Offer. Values must reflect the real store shipping policy, not placeholder values used only to remove warnings.', 'amk-schema-core'); ?>
                </p>
            </div>

            <div
                class="notice notice-warning inline"
                style="margin: 0 0 18px 0;"
            >
                <p>
                    <strong><?php esc_html_e('Minimum required for shipping:', 'amk-schema-core'); ?></strong>
                    <?php esc_html_e('Required for shipping: enable shipping policy and destination country. Shipping cost and delivery time are optional, but should be entered here if they are shown on the site.', 'amk-schema-core'); ?>
                </p>
                <p>
                    <?php esc_html_e('Enter shipping cost using the same unit used in WooCommerce. The plugin normalizes monetary schema output to IRR.', 'amk-schema-core'); ?>
                </p>
            </div>

            <div
                class="amk-settings-grid"
            >
                <?php
                $this->checkbox_field(
                    __('Enable shipping policy', 'amk-schema-core'),
                    'global_settings[commerce][shipping_enabled]',
                    $commerce['shipping_enabled'],
                    __('When enabled, shippingDetails is added to product offers. Enable it only when the store really ships products.', 'amk-schema-core')
                );

                $this->text_field(
                    __('Shipping service name', 'amk-schema-core'),
                    'global_settings[commerce][shipping_name]',
                    $commerce['shipping_name'],
                    __('Store order shipping', 'amk-schema-core'),
                    __('Internal or descriptive shipping method name. This is mostly used for the global ShippingService.', 'amk-schema-core'),
                    [
                        'requires_checkbox' => 'global_settings_commerce_shipping_enabled',
                    ]
                );

                $this->textarea_field(
                    __('Shipping description', 'amk-schema-core'),
                    'global_settings[commerce][shipping_description]',
                    $commerce['shipping_description'],
                    __('For example: Orders are shipped by postal service, courier, or freight.', 'amk-schema-core'),
                    __('A short and accurate shipping method description. Do not use long promotional text.', 'amk-schema-core'),
                    [
                        'requires_checkbox' => 'global_settings_commerce_shipping_enabled',
                    ]
                );

                $this->country_selector_field(
                    __('Shipping destination countries', 'amk-schema-core'),
                    'global_settings[commerce][shipping_countries]',
                    $commerce['shipping_countries'] ?? [],
                    [
                        'requires_checkbox' => 'global_settings_commerce_shipping_enabled',
                    ]
                );

                $this->number_field(
                    __('Fixed shipping cost', 'amk-schema-core'),
                    'global_settings[commerce][shipping_rate]',
                    $commerce['shipping_rate'],
                    '0',
                    __('Enter 0 for free shipping. Leave empty if there is no fixed cost or if shipping is calculated by city or weight.', 'amk-schema-core'),
                    [
                        'requires_checkbox' => 'global_settings_commerce_shipping_enabled',
                    ]
                );

                $this->number_field(
                    __('Free shipping from amount', 'amk-schema-core'),
                    'global_settings[commerce][free_shipping_threshold]',
                    $commerce['free_shipping_threshold'],
                    '1000000',
                    __('Optional. Enter this only if the site really has a free shipping threshold.', 'amk-schema-core'),
                    [
                        'requires_checkbox' => 'global_settings_commerce_shipping_enabled',
                    ]
                );

                $this->number_field(
                    __('Minimum handling time', 'amk-schema-core'),
                    'global_settings[commerce][handling_min_days]',
                    $commerce['handling_min_days'],
                    '0',
                    __('In days. For example, 0 means same-day handling.', 'amk-schema-core'),
                    [
                        'requires_checkbox' => 'global_settings_commerce_shipping_enabled',
                    ]
                );

                $this->number_field(
                    __('Maximum handling time', 'amk-schema-core'),
                    'global_settings[commerce][handling_max_days]',
                    $commerce['handling_max_days'],
                    '1',
                    __('In days. Must not be less than the minimum handling time.', 'amk-schema-core'),
                    [
                        'requires_checkbox' => 'global_settings_commerce_shipping_enabled',
                    ]
                );

                $this->number_field(
                    __('Minimum transit time', 'amk-schema-core'),
                    'global_settings[commerce][transit_min_days]',
                    $commerce['transit_min_days'],
                    '1',
                    __('In days. Approximate transit time until the customer receives the shipment.', 'amk-schema-core'),
                    [
                        'requires_checkbox' => 'global_settings_commerce_shipping_enabled',
                    ]
                );

                $this->number_field(
                    __('Maximum transit time', 'amk-schema-core'),
                    'global_settings[commerce][transit_max_days]',
                    $commerce['transit_max_days'],
                    '5',
                    __('In days. Must not be less than the minimum transit time.', 'amk-schema-core'),
                    [
                        'requires_checkbox' => 'global_settings_commerce_shipping_enabled',
                    ]
                );
                ?>
            </div>

        </div>
        <?php
    }

    private function render_local_business_section($settings) {

        $local = $settings['local_business'];

        ?>
        <div
            class="amk-settings-card"
            data-amk-card="local_business"
        >

            <div class="amk-settings-card-header">
                <div>
                    <h2><?php esc_html_e('Physical Store / Local Business', 'amk-schema-core'); ?></h2>
                    <p>
                        <?php esc_html_e('Displayed only for a local business or a store with a physical branch.', 'amk-schema-core'); ?>
                    </p>
                </div>
            </div>

            <div class="amk-settings-grid">
                <?php
                $this->checkbox_field(
                __('Enable local business / physical branch schema', 'amk-schema-core'),
                'global_settings[local_business][enabled]',
                $local['enabled'],
                __('When enabled, address, map, geo coordinates, and opening hours are used in Schema output.', 'amk-schema-core')
            );

                $this->url_field(
                    __('Map URL', 'amk-schema-core'),
                    'global_settings[local_business][has_map]',
                    $local['has_map'],
                    'https://maps.google.com/...',
                    __('Google Maps or another valid map URL.', 'amk-schema-core'),
                );

                $this->text_field(
                    'Latitude',
                    'global_settings[local_business][latitude]',
                    $local['latitude'],
                    '36.2605',
                    __('Optional.', 'amk-schema-core'),
                );

                $this->text_field(
                    'Longitude',
                    'global_settings[local_business][longitude]',
                    $local['longitude'],
                    '59.6168',
                    __('Optional.', 'amk-schema-core'),
                );

                $this->opening_hours_field(
                    'global_settings[local_business][opening_hours_rows]',
                    $local['opening_hours']
                );
                ?>
            </div>

        </div>
        <?php
    }

    private function opening_hours_field($name, $value, $visibility = []) {

        $rows = $this->opening_hours_rows_from_value($value);

        ?>
        <div class="amk-settings-field amk-opening-hours-field" <?php echo $this->visibility_attributes($visibility); ?>>
            <label><?php esc_html_e('Opening hours', 'amk-schema-core'); ?></label>

            <div class="amk-opening-hours-table">
                <?php foreach ($this->week_day_options() as $day_key => $day_label) : ?>
                    <?php
                    $row = $rows[$day_key] ?? ['enabled' => 0, 'opens' => '09:00', 'closes' => '18:00'];
                    ?>
                    <div class="amk-opening-hours-row">
                        <label class="amk-opening-hours-day">
                            <input
                                type="checkbox"
                                name="<?php echo esc_attr($name . '[' . $day_key . '][enabled]'); ?>"
                                value="1"
                                <?php checked(!empty($row['enabled'])); ?>
                            >
                            <span><?php echo esc_html($day_label); ?></span>
                        </label>

                        <input
                            type="time"
                            name="<?php echo esc_attr($name . '[' . $day_key . '][opens]'); ?>"
                            value="<?php echo esc_attr($row['opens']); ?>"
                            aria-label="<?php echo esc_attr($day_label . ' opens'); ?>"
                        >

                        <span class="amk-opening-hours-separator"><?php esc_html_e('to', 'amk-schema-core'); ?></span>

                        <input
                            type="time"
                            name="<?php echo esc_attr($name . '[' . $day_key . '][closes]'); ?>"
                            value="<?php echo esc_attr($row['closes']); ?>"
                            aria-label="<?php echo esc_attr($day_label . ' closes'); ?>"
                        >
                    </div>
                <?php endforeach; ?>
            </div>

            <p><?php esc_html_e('Set opening and closing times for each active day.', 'amk-schema-core'); ?></p>
        </div>
        <?php
    }

    private function opening_hours_rows_from_value($value) {

        $rows = [];

        foreach (array_keys($this->week_day_options()) as $day_key) {
            $rows[$day_key] = [
                'enabled' => 0,
                'opens'   => '09:00',
                'closes'  => '18:00',
            ];
        }

        if (!is_string($value) || trim($value) === '') {
            return $rows;
        }

        $lines = preg_split('/\r\n|\r|\n/', $value);

        foreach ($lines as $line) {
            $line = trim((string) $line);

            if (!preg_match('/^([A-Za-z,\-\s]+)\s+(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', $line, $matches)) {
                continue;
            }

            foreach ($this->expand_opening_hours_day_keys($matches[1]) as $day_key) {
                if (!isset($rows[$day_key])) {
                    continue;
                }

                $rows[$day_key] = [
                    'enabled' => 1,
                    'opens'   => $this->normalize_time_for_input($matches[2]),
                    'closes'  => $this->normalize_time_for_input($matches[3]),
                ];
            }
        }

        return $rows;
    }

    private function week_day_options() {

        return [
            'mo' => __('Monday', 'amk-schema-core'),
            'tu' => __('Tuesday', 'amk-schema-core'),
            'we' => __('Wednesday', 'amk-schema-core'),
            'th' => __('Thursday', 'amk-schema-core'),
            'fr' => __('Friday', 'amk-schema-core'),
            'sa' => __('Saturday', 'amk-schema-core'),
            'su' => __('Sunday', 'amk-schema-core'),
        ];
    }

    private function expand_opening_hours_day_keys($raw_days) {

        $order = ['mo', 'tu', 'we', 'th', 'fr', 'sa', 'su'];
        $aliases = [
            'mo' => 'mo', 'mon' => 'mo', 'monday' => 'mo',
            'tu' => 'tu', 'tue' => 'tu', 'tuesday' => 'tu',
            'we' => 'we', 'wed' => 'we', 'wednesday' => 'we',
            'th' => 'th', 'thu' => 'th', 'thursday' => 'th',
            'fr' => 'fr', 'fri' => 'fr', 'friday' => 'fr',
            'sa' => 'sa', 'sat' => 'sa', 'saturday' => 'sa',
            'su' => 'su', 'sun' => 'su', 'sunday' => 'su',
        ];

        $tokens = preg_split('/\s*,\s*/', strtolower(trim((string) $raw_days)));
        $days = [];

        foreach ($tokens as $token) {
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
                    $days = array_merge($days, $range);
                }

                continue;
            }

            $token = trim($token);
            $day = $aliases[$token] ?? '';

            if ($day !== '') {
                $days[] = $day;
            }
        }

        return array_values(array_unique($days));
    }

    private function normalize_time_for_input($time) {

        if (preg_match('/^(\d{1,2}):(\d{2})$/', (string) $time, $matches)) {
            return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return '09:00';
    }

    private function maybe_save_settings() {

        if (!empty($_POST['amk_schema_core_maintenance_action'])) {
            return $this->handle_maintenance_action();
        }

        if (empty($_POST['amk_schema_core_save_settings'])) {
            return '';
        }

        if (
            empty($_POST['amk_schema_core_settings_nonce']) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['amk_schema_core_settings_nonce'])),
                'amk_schema_core_save_settings'
            )
        ) {
            return [
                'type'     => 'error',
                'messages' => [__('The request is invalid. Refresh the page and try again.', 'amk-schema-core')],
            ];
        }

        $raw = [];

        if (isset($_POST['global_settings']) && is_array($_POST['global_settings'])) {
            $raw = wp_unslash($_POST['global_settings']);
        }

        GlobalSettings::update($raw);

        return __('Global settings saved successfully.', 'amk-schema-core');
    }

    private function handle_maintenance_action() {

        if (!current_user_can('manage_options')) {
            return [
                'type'     => 'error',
                'messages' => [__('You do not have permission to run the maintenance tool.', 'amk-schema-core')],
            ];
        }

        if (
            empty($_POST['amk_schema_core_maintenance_nonce']) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['amk_schema_core_maintenance_nonce'])),
                'amk_schema_core_maintenance'
            )
        ) {
            return [
                'type'     => 'error',
                'messages' => [__('The maintenance tool request is invalid. Refresh the page and try again.', 'amk-schema-core')],
            ];
        }

        $action = sanitize_key(wp_unslash($_POST['amk_schema_core_maintenance_action']));

        if (!class_exists(SchemaMaintenance::class)) {
            return [
                'type'     => 'error',
                'messages' => [__('The schema maintenance class is not available. Check app/Core/SchemaMaintenance.php.', 'amk-schema-core')],
            ];
        }

        if ($action === 'inspect') {
            $report = SchemaMaintenance::inspect();
            return SchemaMaintenance::notice_from_report($report);
        }

        if ($action === 'sync') {
            if (empty($_POST['amk_schema_core_confirm_sync'])) {
                return [
                    'type'     => 'warning',
                    'messages' => [__('Enable the confirmation checkbox before synchronizing default templates.', 'amk-schema-core')],
                ];
            }

            $report = SchemaMaintenance::sync_defaults();
            return SchemaMaintenance::notice_from_report($report);
        }

        return [
            'type'     => 'error',
            'messages' => [__('Unknown maintenance action.', 'amk-schema-core')],
        ];
    }

    private function render_maintenance_section() {

        $last_report = class_exists(SchemaMaintenance::class) ? SchemaMaintenance::last_report() : [];
        $counts = isset($last_report['counts']) && is_array($last_report['counts']) ? $last_report['counts'] : [];
        $last_time = isset($last_report['time']) ? (string) $last_report['time'] : '';

        ?>
        <div class="amk-settings-card amk-settings-card-maintenance" data-amk-card="maintenance">
            <div class="amk-settings-card-header">
                <div>
                    <h2><?php esc_html_e('Schema Maintenance Tools', 'amk-schema-core'); ?></h2>
                    <p>
                        <?php esc_html_e('This section does not blindly reset the database. It only compares or synchronizes the plugin default templates with config/default-schemas.php.', 'amk-schema-core'); ?>
                    </p>
                </div>
            </div>

            <?php wp_nonce_field('amk_schema_core_maintenance', 'amk_schema_core_maintenance_nonce'); ?>

            <div class="amk-maintenance-panel">
                <div class="amk-maintenance-summary">
                    <strong><?php esc_html_e('Last status:', 'amk-schema-core'); ?></strong>

                    <?php if ($last_time !== '') : ?>
                        <span><?php echo esc_html($last_time); ?></span>
                    <?php else : ?>
                        <span><?php esc_html_e('No check has been run yet.', 'amk-schema-core'); ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($counts)) : ?>
                    <ul class="amk-maintenance-stats">
                        <li><?php esc_html_e('Defaults:', 'amk-schema-core'); ?> <strong><?php echo esc_html(absint($counts['defaults'] ?? 0)); ?></strong></li>
                        <li><?php esc_html_e('Existing:', 'amk-schema-core'); ?> <strong><?php echo esc_html(absint($counts['existing'] ?? 0)); ?></strong></li>
                        <li><?php esc_html_e('Missing:', 'amk-schema-core'); ?> <strong><?php echo esc_html(absint($counts['missing'] ?? 0)); ?></strong></li>
                        <li><?php esc_html_e('Needs update:', 'amk-schema-core'); ?> <strong><?php echo esc_html(absint($counts['outdated'] ?? 0)); ?></strong></li>
                        <li><?php esc_html_e('Old placeholders:', 'amk-schema-core'); ?> <strong><?php echo esc_html(absint($counts['old_placeholders'] ?? 0)); ?></strong></li>
                        <li><?php esc_html_e('Deprecated properties:', 'amk-schema-core'); ?> <strong><?php echo esc_html(absint($counts['deprecated_properties'] ?? 0)); ?></strong></li>
                    </ul>
                <?php endif; ?>

                <div class="amk-maintenance-warning">
                    <strong><?php esc_html_e('Important:', 'amk-schema-core'); ?></strong>
                    <?php esc_html_e('Synchronization is designed only for plugin default templates. Manual changes to default templates may be replaced by the current plugin version. Custom templates with different names and scopes should not be deleted.', 'amk-schema-core'); ?>
                </div>

                <label class="amk-maintenance-confirm">
                    <input type="checkbox" name="amk_schema_core_confirm_sync" value="1">
                    <span><?php esc_html_e('I understand that manual changes to default templates may be overwritten.', 'amk-schema-core'); ?></span>
                </label>

                <div class="amk-maintenance-actions">
                    <button
                        type="submit"
                        name="amk_schema_core_maintenance_action"
                        value="inspect"
                        class="button button-secondary"
                    >
                        <?php esc_html_e('Check database differences against default files', 'amk-schema-core'); ?>
                    </button>

                    <button
                        type="submit"
                        name="amk_schema_core_maintenance_action"
                        value="sync"
                        class="button button-primary"
                    >
                        <?php esc_html_e('Repair and synchronize default templates', 'amk-schema-core'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    private function text_field($label, $name, $value, $placeholder = '', $help = '', $visibility = []) {
        $this->input_field('text', $label, $name, $value, $placeholder, $help, '', $visibility);
    }

    private function date_field($label, $name, $value, $placeholder = '', $help = '', $visibility = []) {
        $this->input_field('date', $label, $name, $value, $placeholder, $help, 'ltr', $visibility);
    }

    private function email_field($label, $name, $value, $placeholder = '', $help = '', $visibility = []) {
        $this->input_field('email', $label, $name, $value, $placeholder, $help, 'ltr', $visibility);
    }

    private function url_field($label, $name, $value, $placeholder = '', $help = '', $visibility = []) {
        $this->input_field('url', $label, $name, $value, $placeholder, $help, 'ltr', $visibility);
    }

    private function number_field($label, $name, $value, $placeholder = '', $help = '', $visibility = []) {
        $this->input_field('number', $label, $name, $value, $placeholder, $help, 'ltr', $visibility);
    }

    private function input_field($type, $label, $name, $value, $placeholder = '', $help = '', $dir = '', $visibility = []) {

        $id = $this->field_id($name);

        ?>
        <div class="amk-settings-field" <?php echo $this->visibility_attributes($visibility); ?>>
            <label for="<?php echo esc_attr($id); ?>">
                <?php echo esc_html($label); ?>
            </label>

            <input
                type="<?php echo esc_attr($type); ?>"
                id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($name); ?>"
                value="<?php echo esc_attr($value); ?>"
                placeholder="<?php echo esc_attr($placeholder); ?>"
                <?php echo $dir ? 'dir="' . esc_attr($dir) . '"' : ''; ?>
                <?php echo $type === 'number' ? 'min="0" step="any"' : ''; ?>
                <?php echo $type === 'date' && is_rtl() ? 'lang="fa-IR"' : ''; ?>
            >

            <?php if ($help !== '') : ?>
                <p><?php echo esc_html($help); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function textarea_field($label, $name, $value, $placeholder = '', $help = '', $visibility = []) {

        $id = $this->field_id($name);

        ?>
        <div class="amk-settings-field" <?php echo $this->visibility_attributes($visibility); ?>>
            <label for="<?php echo esc_attr($id); ?>">
                <?php echo esc_html($label); ?>
            </label>

            <textarea
                id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($name); ?>"
                rows="4"
                placeholder="<?php echo esc_attr($placeholder); ?>"
            ><?php echo esc_textarea($value); ?></textarea>

            <?php if ($help !== '') : ?>
                <p><?php echo esc_html($help); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function select_field($label, $name, $value, $options, $help = '', $visibility = []) {

        $id = $this->field_id($name);

        ?>
        <div class="amk-settings-field" <?php echo $this->visibility_attributes($visibility); ?>>
            <label for="<?php echo esc_attr($id); ?>">
                <?php echo esc_html($label); ?>
            </label>

            <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>">
                <?php foreach ($options as $option_value => $option_label) : ?>
                    <option
                        value="<?php echo esc_attr($option_value); ?>"
                        <?php selected($value, $option_value); ?>
                    >
                        <?php echo esc_html($option_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if ($help !== '') : ?>
                <p><?php echo esc_html($help); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function multi_select_field($label, $name, $value, $options, $help = '', $visibility = []) {

        $id = $this->field_id($name);
        $selected_values = $this->normalize_multi_select_values($value);
        $field_name = substr($name, -2) === '[]' ? $name : $name . '[]';

        ?>
        <div class="amk-settings-field" <?php echo $this->visibility_attributes($visibility); ?>>
            <label>
                <?php echo esc_html($label); ?>
            </label>

            <div class="amk-checkbox-list" id="<?php echo esc_attr($id); ?>">
                <?php foreach ($options as $option_value => $option_label) : ?>
                    <label class="amk-checkbox-option">
                        <input
                            type="checkbox"
                            name="<?php echo esc_attr($field_name); ?>"
                            value="<?php echo esc_attr($option_value); ?>"
                            <?php checked(in_array((string) $option_value, $selected_values, true)); ?>
                        >
                        <span><?php echo esc_html($option_label); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <?php if ($help !== '') : ?>
                <p><?php echo esc_html($help); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function normalize_multi_select_values($value) {

        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value)));
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    private function payment_accepted_options() {

        return [
            'Online Payment' => __('Online payment', 'amk-schema-core'),
            'Credit Card'    => __('Credit card', 'amk-schema-core'),
            'Debit Card'     => __('Debit card', 'amk-schema-core'),
            'Bank Transfer'  => __('Bank transfer', 'amk-schema-core'),
            'Cash'           => __('Cash', 'amk-schema-core'),
            'Cash on Delivery' => __('Cash on delivery', 'amk-schema-core'),
            'Installment'    => __('Installment payment', 'amk-schema-core'),
        ];
    }

    private function page_select_field($label, $name, $value, $help = '', $visibility = []) {

        $id = $this->field_id($name);
        $value = absint($value);

        $pages = get_pages([
            'sort_column' => 'post_title',
            'sort_order'  => 'ASC',
            'post_status' => ['publish', 'draft', 'private'],
        ]);

        ?>
        <div class="amk-settings-field" <?php echo $this->visibility_attributes($visibility); ?>>
            <label for="<?php echo esc_attr($id); ?>">
                <?php echo esc_html($label); ?>
            </label>

            <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>">
                <option value="0" <?php selected($value, 0); ?>><?php esc_html_e('Not selected', 'amk-schema-core'); ?></option>

                <?php foreach ($pages as $page) : ?>
                    <option
                        value="<?php echo esc_attr($page->ID); ?>"
                        <?php selected($value, $page->ID); ?>
                    >
                        <?php echo esc_html($page->post_title . ' — ID: ' . $page->ID); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if ($help !== '') : ?>
                <p><?php echo esc_html($help); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function checkbox_field($label, $name, $value, $help = '', $visibility = []) {

    $id = $this->field_id($name);

    ?>
    <div class="amk-settings-field amk-template-toggle-box" <?php echo $this->visibility_attributes($visibility); ?>>

        <div class="amk-toggle-label-row">
            <label>
                <?php echo esc_html($label); ?>
            </label>

            <?php if ($help !== '') : ?>
                <span class="amk-help-tooltip" tabindex="0" aria-label="<?php echo esc_attr($help); ?>">
                    ?
                    <span class="amk-help-tooltip-content">
                        <?php echo esc_html($help); ?>
                    </span>
                </span>
            <?php endif; ?>
        </div>

        <label class="amk-template-toggle" for="<?php echo esc_attr($id); ?>">
            <input
                type="checkbox"
                id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($name); ?>"
                value="1"
                <?php checked(!empty($value)); ?>
            >

            <span></span>
            <strong><?php esc_html_e('Active', 'amk-schema-core'); ?></strong>
        </label>

    </div>
    <?php
}

    private function visibility_attributes($visibility) {

        if (empty($visibility) || !is_array($visibility)) {
            return '';
        }

        $attrs = [];

        if (!empty($visibility['profiles']) && is_array($visibility['profiles'])) {
            $attrs[] = 'data-amk-profiles="' . esc_attr(implode(',', $visibility['profiles'])) . '"';
        }

        if (!empty($visibility['org_types']) && is_array($visibility['org_types'])) {
            $attrs[] = 'data-amk-org-types="' . esc_attr(implode(',', $visibility['org_types'])) . '"';
        }

        if (!empty($visibility['requires_checkbox'])) {
            $attrs[] = 'data-amk-requires-checkbox="' . esc_attr($visibility['requires_checkbox']) . '"';
        }

        if (!empty($visibility['field_key'])) {
            $attrs[] = 'data-amk-field-key="' . esc_attr($visibility['field_key']) . '"';
        }

        return implode(' ', $attrs);
    }

    private function field_id($name) {

        $id = str_replace(['[', ']'], '_', $name);
        $id = preg_replace('/_+/', '_', $id);
        $id = trim($id, '_');

        return sanitize_key($id);
    }

    private function render_dynamic_visibility_script() {

        ?>
        <style>
            .amk-settings-hidden {
                display: none !important;
            }
        </style>

        <script>
            (function () {
                'use strict';

                function splitList(value) {
                    if (!value) {
                        return [];
                    }

                    return String(value)
                        .split(',')
                        .map(function (item) {
                            return item.trim();
                        })
                        .filter(Boolean);
                }

                function isCheckboxCheckedById(id) {
                    var checkbox = document.getElementById(id);
                    return checkbox ? checkbox.checked : false;
                }

                function toggleElement(element, visible) {
                    if (!element) {
                        return;
                    }

                    if (visible) {
                        element.classList.remove('amk-settings-hidden');
                    } else {
                        element.classList.add('amk-settings-hidden');
                    }
                }

                function uniqueList(values) {
                    var output = [];

                    values.forEach(function (value) {
                        value = String(value || '').trim();

                        if (value && output.indexOf(value) === -1) {
                            output.push(value);
                        }
                    });

                    return output;
                }

                function signature(values) {
                    return uniqueList(values).sort().join('|');
                }

                function organizationTypeContainer() {
                    return document.getElementById('global_settings_organization_types') || document.getElementById('global_settings_organization_type');
                }

                function getSelectedOrganizationTypes(orgTypeField) {
                    if (!orgTypeField) {
                        return ['Organization'];
                    }

                    if (orgTypeField.tagName && orgTypeField.tagName.toLowerCase() === 'select') {
                        return orgTypeField.value ? [orgTypeField.value] : ['Organization'];
                    }

                    var values = [];
                    orgTypeField
                        .querySelectorAll('input[type="checkbox"]')
                        .forEach(function (checkbox) {
                            if (checkbox.checked) {
                                values.push(checkbox.value);
                            }
                        });

                    values = uniqueList(values);

                    return values.length ? values : ['Organization'];
                }

                function setSelectedOrganizationTypes(orgTypeField, values) {
                    if (!orgTypeField) {
                        return;
                    }

                    values = uniqueList(values);

                    if (orgTypeField.tagName && orgTypeField.tagName.toLowerCase() === 'select') {
                        orgTypeField.value = values.length ? values[values.length - 1] : 'Organization';
                        return;
                    }

                    orgTypeField
                        .querySelectorAll('input[type="checkbox"]')
                        .forEach(function (checkbox) {
                            checkbox.checked = values.indexOf(checkbox.value) !== -1;
                        });
                }

                function hasAnySelectedType(requiredTypes, selectedTypes) {
                    if (!requiredTypes.length) {
                        return true;
                    }

                    return requiredTypes.some(function (type) {
                        return selectedTypes.indexOf(type) !== -1;
                    });
                }

                function recommendedOrganizationTypes(selectedProfile) {
                    if (selectedProfile === 'ecommerce') {
                        return ['Organization', 'OnlineStore'];
                    }

                    if (selectedProfile === 'ecommerce_local') {
                        return ['Organization', 'Store', 'OnlineStore'];
                    }

                    if (selectedProfile === 'business') {
                        return ['Organization', 'LocalBusiness'];
                    }

                    return ['Organization'];
                }

                function syncRecommendedOrganizationType(selectedProfile, orgTypeField) {
                    if (!orgTypeField) {
                        return;
                    }

                    var previousProfile = orgTypeField.getAttribute('data-amk-last-profile') || '';
                    var currentTypes = getSelectedOrganizationTypes(orgTypeField);
                    var currentSignature = signature(currentTypes);
                    var autoSignatures = [
                        signature(['Organization']),
                        signature(['Organization', 'OnlineStore']),
                        signature(['Organization', 'Store', 'OnlineStore']),
                        signature(['Organization', 'LocalBusiness'])
                    ];

                    if (previousProfile === '' || previousProfile !== selectedProfile) {
                        if (autoSignatures.indexOf(currentSignature) !== -1) {
                            setSelectedOrganizationTypes(orgTypeField, recommendedOrganizationTypes(selectedProfile));
                        }
                    }

                    orgTypeField.setAttribute('data-amk-last-profile', selectedProfile);
                }

                function updateVisibility() {
                    var profileField = document.getElementById('global_settings_site_profile');
                    var orgTypeField = organizationTypeContainer();

                    var selectedProfile = profileField ? profileField.value : 'general';

                    syncRecommendedOrganizationType(selectedProfile, orgTypeField);

                    var selectedOrgTypes = getSelectedOrganizationTypes(orgTypeField);

                    document
                        .querySelectorAll('[data-amk-profiles], [data-amk-org-types], [data-amk-requires-checkbox]')
                        .forEach(function (element) {
                            var visible = true;

                            var profiles = splitList(element.getAttribute('data-amk-profiles'));
                            var orgTypes = splitList(element.getAttribute('data-amk-org-types'));
                            var requiredCheckboxId = element.getAttribute('data-amk-requires-checkbox');

                            if (profiles.length && profiles.indexOf(selectedProfile) === -1) {
                                visible = false;
                            }

                            if (orgTypes.length && !hasAnySelectedType(orgTypes, selectedOrgTypes)) {
                                visible = false;
                            }

                            if (requiredCheckboxId && !isCheckboxCheckedById(requiredCheckboxId)) {
                                visible = false;
                            }

                            toggleElement(element, visible);
                        });
                }

                document.addEventListener('DOMContentLoaded', function () {
                    var form = document.getElementById('amk-schema-global-settings-form');

                    if (!form) {
                        return;
                    }

                    form.addEventListener('change', function () {
                        updateVisibility();
                    });

                    updateVisibility();
                });
            })();
        </script>
        <?php
    }

    private function render_iran_location_script() {

    ?>
    <script>
        (function () {
            'use strict';

            var locationsUrl = <?php echo wp_json_encode(IranLocations::locations_data_url(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            var locationsData = null;
            var isPersianLocale = <?php echo IranLocations::is_persian_locale() ? 'true' : 'false'; ?>;

            function hasPersianText(value) {
                return /[\u0600-\u06FF]/.test(String(value || ''));
            }

            function persianOnlyLabel(value) {
                value = String(value || '').trim();

                if (!hasPersianText(value)) {
                    return '';
                }

                return value.replace(/\s*\([^)]*\)\s*$/u, '').trim();
            }

            function locationLabel(item) {
                item = item || {};

                if (isPersianLocale) {
                    return persianOnlyLabel(item.label) || item.name || item.label || '';
                }

                return item.name || item.label || '';
            }

            function clearOptions(select) {
                while (select.firstChild) {
                    select.removeChild(select.firstChild);
                }
            }

            function addOption(select, value, label, selected) {
                var option = document.createElement('option');

                option.value = value;
                option.textContent = label;

                if (selected) {
                    option.selected = true;
                }

                select.appendChild(option);
            }

            function getStateKey(country, state) {
                return String(country || '') + '|' + String(state || '');
            }

            function updateStateOptions(resetSelected) {
                var countrySelect = document.getElementById('global_settings_address_country');
                var provinceSelect = document.getElementById('global_settings_address_region');
                var citySelect     = document.getElementById('global_settings_address_locality');

                if (!locationsData || !countrySelect || !provinceSelect || !citySelect) {
                    return;
                }

                var country = countrySelect.value || '';
                var states = locationsData.statesByCountry[country] || [];
                var selected = resetSelected ? '' : provinceSelect.value || '';

                clearOptions(provinceSelect);
                clearOptions(citySelect);

                if (!country) {
                    addOption(provinceSelect, '', <?php echo wp_json_encode(__('Select a country first', 'amk-schema-core')); ?>, true);
                    addOption(citySelect, '', <?php echo wp_json_encode(__('Select a region/state first', 'amk-schema-core')); ?>, true);
                    return;
                }

                if (!states.length) {
                    addOption(provinceSelect, '', <?php echo wp_json_encode(__('No region/state is registered for this country', 'amk-schema-core')); ?>, true);
                    addOption(citySelect, '', <?php echo wp_json_encode(__('Select a region/state first', 'amk-schema-core')); ?>, true);
                    return;
                }

                addOption(provinceSelect, '', <?php echo wp_json_encode(__('Select region/state', 'amk-schema-core')); ?>, selected === '');

                states.forEach(function (state) {
                    addOption(provinceSelect, state.name, locationLabel(state), selected === state.name);
                });

                updateCityOptions(resetSelected);
            }

            function updateCityOptions(resetSelected) {
                var countrySelect = document.getElementById('global_settings_address_country');
                var provinceSelect = document.getElementById('global_settings_address_region');
                var citySelect     = document.getElementById('global_settings_address_locality');

                if (!locationsData || !countrySelect || !provinceSelect || !citySelect) {
                    return;
                }

                var country = countrySelect.value || '';
                var province = provinceSelect.value || '';
                var cities = locationsData.citiesByCountryState[getStateKey(country, province)] || [];
                var selected = resetSelected ? '' : (citySelect.getAttribute('data-selected-city') || citySelect.value || '');

                clearOptions(citySelect);

                if (!province) {
                    addOption(citySelect, '', <?php echo wp_json_encode(__('Select a region/state first', 'amk-schema-core')); ?>, true);
                    return;
                }

                if (!cities.length) {
                    addOption(citySelect, '', <?php echo wp_json_encode(__('No city is registered for this region/state', 'amk-schema-core')); ?>, true);
                    return;
                }

                addOption(citySelect, '', <?php echo wp_json_encode(__('Select city', 'amk-schema-core')); ?>, selected === '');

                cities.forEach(function (city) {
                    addOption(citySelect, city.name, locationLabel(city), selected === city.name);
                });

                citySelect.setAttribute('data-selected-city', citySelect.value || '');
            }

            document.addEventListener('DOMContentLoaded', function () {
                var countrySelect = document.getElementById('global_settings_address_country');
                var provinceSelect = document.getElementById('global_settings_address_region');
                var citySelect     = document.getElementById('global_settings_address_locality');

                if (!locationsUrl) {
                    return;
                }

                fetch(locationsUrl, { credentials: 'same-origin' })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Location data could not be loaded.');
                        }

                        return response.json();
                    })
                    .then(function (data) {
                        locationsData = data || {};
                        updateStateOptions(false);
                    })
                    .catch(function () {
                        locationsData = null;
                    });

                if (countrySelect) {
                    countrySelect.addEventListener('change', function () {
                        if (citySelect) {
                            citySelect.setAttribute('data-selected-city', '');
                        }

                        updateStateOptions(true);
                    });
                }

                if (provinceSelect) {
                    provinceSelect.addEventListener('change', function () {
                        if (citySelect) {
                            citySelect.setAttribute('data-selected-city', '');
                        }

                        updateCityOptions(true);
                    });
                }

                if (citySelect) {
                    citySelect.addEventListener('change', function () {
                        citySelect.setAttribute('data-selected-city', citySelect.value || '');
                    });
                }
            });
        })();
    </script>
    <?php
}

private function render_contact_point_row($index, $point, $is_template = false) {

    $point = is_array($point) ? $point : [];

    $contact_type = $point['contact_type'] ?? 'customer support';
    $telephone    = $point['telephone'] ?? '';
    $email        = $point['email'] ?? '';
    $url          = $point['url'] ?? '';
    $option       = $point['contact_option'] ?? '';
    $area_served  = $point['area_served'] ?? '';
    $language     = $point['available_language'] ?? '';

    $row_id = 'amk_contact_point_' . $index;
    $name_prefix = 'global_settings[contact_points][' . $index . ']';

    ?>
    <div class="amk-contact-point-row" data-contact-point-row>

        <div class="amk-contact-point-row-header">
            <strong><?php esc_html_e('Contact point', 'amk-schema-core'); ?></strong>

            <button
                type="button"
                class="button-link-delete amk-remove-contact-point"
            >
                <?php esc_html_e('Remove', 'amk-schema-core'); ?>
            </button>
        </div>

        <div class="amk-settings-grid">

            <div class="amk-settings-field">
                <label for="<?php echo esc_attr($row_id . '_contact_type'); ?>">
                    <?php esc_html_e('Contact type', 'amk-schema-core'); ?>
                </label>

                <select
                    id="<?php echo esc_attr($row_id . '_contact_type'); ?>"
                    name="<?php echo esc_attr($name_prefix . '[contact_type]'); ?>"
                >
                    <?php foreach (GlobalSettings::contact_type_options() as $option_value => $option_label) : ?>
                        <option
                            value="<?php echo esc_attr($option_value); ?>"
                            <?php selected($contact_type, $option_value); ?>
                        >
                            <?php echo esc_html($option_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <p><?php esc_html_e('This value is used as contactType in Schema.', 'amk-schema-core'); ?></p>
            </div>

            <div class="amk-settings-field">
                <label for="<?php echo esc_attr($row_id . '_telephone'); ?>">
                    <?php esc_html_e('Phone number', 'amk-schema-core'); ?>
                </label>

                <input
                    type="text"
                    id="<?php echo esc_attr($row_id . '_telephone'); ?>"
                    name="<?php echo esc_attr($name_prefix . '[telephone]'); ?>"
                    value="<?php echo esc_attr($telephone); ?>"
                    placeholder="+982112345678"
                    dir="ltr"
                >

                <p><?php esc_html_e('Prefer entering the number with the country code.', 'amk-schema-core'); ?></p>
            </div>

            <div class="amk-settings-field">
                <label for="<?php echo esc_attr($row_id . '_email'); ?>">
                    <?php esc_html_e('Email', 'amk-schema-core'); ?>
                </label>

                <input
                    type="email"
                    id="<?php echo esc_attr($row_id . '_email'); ?>"
                    name="<?php echo esc_attr($name_prefix . '[email]'); ?>"
                    value="<?php echo esc_attr($email); ?>"
                    placeholder="support@example.com"
                    dir="ltr"
                >

                <p><?php esc_html_e('Leave this empty if you do not have a separate email.', 'amk-schema-core'); ?></p>
            </div>

            <div class="amk-settings-field">
                <label for="<?php echo esc_attr($row_id . '_available_language'); ?>">
                    <?php esc_html_e('Supported languages', 'amk-schema-core'); ?>
                </label>

                <input
                    type="text"
                    id="<?php echo esc_attr($row_id . '_available_language'); ?>"
                    name="<?php echo esc_attr($name_prefix . '[available_language]'); ?>"
                    value="<?php echo esc_attr($language); ?>"
                    placeholder="fa, en"
                    dir="ltr"
                >

                <p><?php esc_html_e('Separate multiple languages with commas, such as fa, en.', 'amk-schema-core'); ?></p>
            </div>

            <div class="amk-settings-field">
                <label for="<?php echo esc_attr($row_id . '_url'); ?>">
                    <?php esc_html_e('Contact URL', 'amk-schema-core'); ?>
                </label>

                <input
                    type="url"
                    id="<?php echo esc_attr($row_id . '_url'); ?>"
                    name="<?php echo esc_attr($name_prefix . '[url]'); ?>"
                    value="<?php echo esc_attr($url); ?>"
                    placeholder="https://example.com/contact/"
                    dir="ltr"
                >

                <p><?php esc_html_e('Contact or support page URL for this contact method.', 'amk-schema-core'); ?></p>
            </div>

            <div class="amk-settings-field">
                <label for="<?php echo esc_attr($row_id . '_contact_option'); ?>">
                    <?php esc_html_e('Contact options', 'amk-schema-core'); ?>
                </label>

                <input
                    type="text"
                    id="<?php echo esc_attr($row_id . '_contact_option'); ?>"
                    name="<?php echo esc_attr($name_prefix . '[contact_option]'); ?>"
                    value="<?php echo esc_attr($option); ?>"
                    placeholder="https://schema.org/TollFree, https://schema.org/HearingImpairedSupported"
                    dir="ltr"
                >

                <p><?php esc_html_e('Separate multiple options with commas, such as https://schema.org/TollFree.', 'amk-schema-core'); ?></p>
            </div>

            <div class="amk-settings-field">
                <label for="<?php echo esc_attr($row_id . '_area_served'); ?>">
                    <?php esc_html_e('Service area', 'amk-schema-core'); ?>
                </label>

                <input
                    type="text"
                    id="<?php echo esc_attr($row_id . '_area_served'); ?>"
                    name="<?php echo esc_attr($name_prefix . '[area_served]'); ?>"
                    value="<?php echo esc_attr($area_served); ?>"
                    placeholder="IR, Tehran"
                    dir="ltr"
                >

                <p><?php esc_html_e('Separate countries or cities with commas, such as IR, Tehran.', 'amk-schema-core'); ?></p>
            </div>

        </div>

    </div>
    <?php
    }

    private function render_contact_points_script() {

    ?>
    <style>
        .amk-contact-points-repeater {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .amk-contact-point-row {
            border: 1px solid #dcdcde;
            border-radius: 12px;
            padding: 18px;
            background: #fff;
        }

        .amk-contact-point-row-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f1;
        }

        .amk-contact-point-row-header strong {
            font-size: 14px;
            color: #1d2327;
        }

        .amk-settings-repeat-actions {
            margin-top: 16px;
        }
    </style>

    <script>
        jQuery(function ($) {
            'use strict';

            var $wrapper = $('#amk-contact-points-repeater');
            var $template = $('#amk-contact-point-template');

            if (!$wrapper.length || !$template.length) {
                return;
            }

            function getNextIndex() {
                var nextIndex = parseInt($wrapper.attr('data-next-index'), 10);

                if (isNaN(nextIndex)) {
                    nextIndex = $wrapper.find('[data-contact-point-row]').length;
                }

                $wrapper.attr('data-next-index', nextIndex + 1);

                return nextIndex;
            }

            function refreshRemoveButtons() {
                var $rows = $wrapper.find('[data-contact-point-row]');

                if ($rows.length <= 1) {
                    $rows.find('.amk-remove-contact-point').hide();
                } else {
                    $rows.find('.amk-remove-contact-point').show();
                }
            }

            $('#amk-add-contact-point').on('click', function (event) {
                event.preventDefault();

                var index = getNextIndex();
                var html = $template.html();

                html = html.replace(/__INDEX__/g, index);

                $wrapper.append(html);

                refreshRemoveButtons();
            });

            $wrapper.on('click', '.amk-remove-contact-point', function (event) {
                event.preventDefault();

                var $rows = $wrapper.find('[data-contact-point-row]');

                if ($rows.length <= 1) {
                    var $row = $(this).closest('[data-contact-point-row]');

                    $row.find('select').val('customer support');
                    $row.find('input[type="text"], input[type="email"], input[type="url"]').val('');
                    $row.find('input[name*="[available_language]"]').val('');

                    return;
                }

                $(this).closest('[data-contact-point-row]').remove();

                refreshRemoveButtons();
            });

            refreshRemoveButtons();
        });
    </script>
    <?php
}

    private function render_social_links_script() {

        ?>
        <script>
            jQuery(function ($) {
                var $wrapper = $('#amk-social-links');
                var $template = $('#amk-social-link-template');
                var $addButton = $('#amk-add-social-link');

                if (!$wrapper.length || !$template.length || !$addButton.length) {
                    return;
                }

                function nextIndex() {
                    var current = parseInt($wrapper.attr('data-next-index'), 10);

                    if (isNaN(current)) {
                        current = $wrapper.find('[data-social-row]').length;
                    }

                    $wrapper.attr('data-next-index', current + 1);

                    return current;
                }

                function refreshRemoveButtons() {
                    var $rows = $wrapper.find('[data-social-row]');
                    $rows.find('.amk-remove-social-link').toggle($rows.length > 1);
                }

                $addButton.on('click', function (event) {
                    event.preventDefault();

                    var html = $template.html().replace(/__INDEX__/g, String(nextIndex()));
                    $wrapper.append(html);
                    refreshRemoveButtons();
                });

                $wrapper.on('click', '.amk-remove-social-link', function (event) {
                    event.preventDefault();

                    var $rows = $wrapper.find('[data-social-row]');

                    if ($rows.length <= 1) {
                        $(this).closest('[data-social-row]').find('select').val('');
                        $(this).closest('[data-social-row]').find('input').val('');
                        return;
                    }

                    $(this).closest('[data-social-row]').remove();
                    refreshRemoveButtons();
                });

                refreshRemoveButtons();
            });
        </script>
        <?php
    }
private function render_tooltip_styles() {

    ?>
    <style>
        .amk-toggle-label-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .amk-toggle-label-row > label {
            margin: 0;
        }

        .amk-help-tooltip {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 19px;
            height: 19px;
            border-radius: 50%;
            background: #eef3f8;
            border: 1px solid #ccd7e2;
            color: #334155;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            cursor: help;
            user-select: none;
        }

        .amk-help-tooltip-content {
            position: absolute;
            right: 50%;
            bottom: calc(100% + 10px);
            transform: translateX(50%) translateY(4px);
            z-index: 9999;
            width: 280px;
            max-width: 80vw;
            padding: 10px 12px;
            border-radius: 10px;
            background: #111827;
            color: #ffffff;
            font-size: 12px;
            font-weight: 400;
            line-height: 1.9;
            text-align: right;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .22);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity .15s ease, transform .15s ease, visibility .15s ease;
        }

        .amk-help-tooltip-content::after {
            content: "";
            position: absolute;
            right: 50%;
            top: 100%;
            transform: translateX(50%);
            border-width: 6px;
            border-style: solid;
            border-color: #111827 transparent transparent transparent;
        }

        .amk-help-tooltip:hover .amk-help-tooltip-content,
        .amk-help-tooltip:focus .amk-help-tooltip-content,
        .amk-help-tooltip:focus-within .amk-help-tooltip-content {
            opacity: 1;
            visibility: visible;
            transform: translateX(50%) translateY(0);
        }

        .amk-settings-card-maintenance code {
            direction: ltr;
            display: inline-block;
        }

        .amk-maintenance-panel {
            border: 1px solid #dbe3ec;
            background: #f8fafc;
            border-radius: 14px;
            padding: 18px;
        }

        .amk-maintenance-summary {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .amk-maintenance-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 8px;
            margin: 0 0 14px;
        }

        .amk-maintenance-stats li {
            margin: 0;
            padding: 9px 10px;
            background: #ffffff;
            border: 1px solid #e5edf5;
            border-radius: 10px;
        }

        .amk-maintenance-warning {
            margin: 12px 0;
            padding: 12px 14px;
            border-right: 4px solid #d97706;
            background: #fffbeb;
            color: #713f12;
            line-height: 1.9;
        }

        .amk-maintenance-confirm {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 14px 0;
            font-weight: 600;
        }

        .amk-maintenance-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 12px;
        }
    </style>
    <?php
}


    private function country_selector_field($label, $name, $value = [], $args = []) {

        if (!is_array($value)) {
            $value = [];
        }

        $requires = $args['requires_checkbox'] ?? '';

        ?>
        <div
            class="amk-field amk-country-selector-field"
            <?php if ($requires): ?>
            data-requires-checkbox="<?php echo esc_attr($requires); ?>"
            <?php endif; ?>
        >
            <div
                class="amk-country-selector"
                data-country-selector
                data-json-url="<?php echo esc_url(AMK_SCHEMA_CORE_URL . 'resources/data/world-locations.json'); ?>"
            >
                <label>
                    <strong><?php echo esc_html($label); ?></strong>
                </label>

                <select
                    class="amk-country-select"
                    name="<?php echo esc_attr($name); ?>[]"
                    multiple
                    data-placeholder="<?php echo esc_attr__('Select countries', 'amk-schema-core'); ?>"
                >
                    <?php foreach ($value as $country): ?>
                        <option value="<?php echo esc_attr($country); ?>" selected>
                            <?php echo esc_html($country); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <p class="description">
                    <?php esc_html_e('Select countries from the list.', 'amk-schema-core'); ?>
                </p>
            </div>
        </div>
        <?php
    }

}
