<?php

namespace AMK\SchemaCore\Admin;

defined('ABSPATH') || exit;

class Assets {

    /**
     * Register admin assets hook.
     */
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    /**
     * Enqueue assets only on AMK Schema Core admin pages.
     *
     * @param string $hook
     * @return void
     */
    public function enqueue($hook = '') {

        if (!$this->is_plugin_admin_page($hook)) {
            return;
        }

        $page = $this->current_page();

        $this->enqueue_common_styles();

        switch ($page) {
            case 'amk_schema_builder':
                $this->enqueue_schema_builder_assets();
                break;

            case 'amk_schema_template_editor':
                $this->enqueue_template_editor_assets();
                break;

            case 'amk_schema_settings':
                $this->enqueue_settings_assets();
                break;

            default:
                $this->enqueue_common_scripts();
                break;
        }
    }

    /**
     * Get current plugin page slug.
     *
     * @return string
     */
    private function current_page() {

        return isset($_GET['page'])
            ? sanitize_key(wp_unslash($_GET['page']))
            : '';
    }

    /**
     * Check if current admin screen belongs to this plugin.
     *
     * @param string $hook
     * @return bool
     */
    private function is_plugin_admin_page($hook = '') {

        $page = $this->current_page();

        $allowed_pages = [
            'amk_schema_builder',
            'amk_schema_template_editor',
            'amk_schema_settings',
        ];

        if (in_array($page, $allowed_pages, true)) {
            return true;
        }

        if (is_string($hook) && strpos($hook, 'amk_schema') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Enqueue common admin CSS.
     *
     * @return void
     */
    private function enqueue_common_styles() {

        wp_enqueue_style(
            'amk-schema-admin',
            $this->asset_url('resources/css/admin.css'),
            [],
            $this->version()
        );
    }

    /**
     * Enqueue scripts common to plugin pages.
     *
     * @return void
     */
    private function enqueue_common_scripts() {

        wp_enqueue_script('jquery');
    }

    /**
     * Assets for schema list/builder page.
     *
     * @return void
     */
    private function enqueue_schema_builder_assets() {

        $this->enqueue_common_scripts();

        wp_enqueue_script(
            'amk-schema-priority-ui',
            $this->asset_url('resources/js/priority-ui.js'),
            ['jquery'],
            $this->version(),
            true
        );

        $this->localize_rest_script('amk-schema-priority-ui');

        wp_localize_script(
            'amk-schema-priority-ui',
            'AMKSchemaCore',
            $this->common_payload()
        );
    }

    /**
     * Assets for template editor page.
     *
     * @return void
     */
    private function enqueue_template_editor_assets() {

        $this->enqueue_common_scripts();

        wp_enqueue_script(
            'amk-schema-json-editor',
            $this->asset_url('resources/js/json-editor.js'),
            ['jquery'],
            $this->version(),
            true
        );

        wp_enqueue_script(
            'amk-schema-builder',
            $this->asset_url('resources/js/schema-builder.js'),
            ['jquery', 'amk-schema-json-editor'],
            $this->version(),
            true
        );

        wp_enqueue_script(
            'amk-schema-binding-builder',
            $this->asset_url('resources/js/binding-builder.js'),
            ['jquery', 'amk-schema-builder'],
            $this->version(),
            true
        );

        wp_enqueue_script(
            'amk-schema-condition-builder',
            $this->asset_url('resources/js/condition-builder.js'),
            ['jquery', 'amk-schema-builder'],
            $this->version(),
            true
        );

        wp_enqueue_script(
            'amk-schema-preview',
            $this->asset_url('resources/js/preview.js'),
            ['jquery', 'amk-schema-builder'],
            $this->version(),
            true
        );

        $this->localize_rest_script('amk-schema-builder');
        $this->localize_rest_script('amk-schema-binding-builder');
        $this->localize_rest_script('amk-schema-condition-builder');
        $this->localize_rest_script('amk-schema-preview');

        wp_localize_script(
            'amk-schema-builder',
            'AMKSchemaCore',
            $this->common_payload()
        );

        wp_localize_script(
            'amk-schema-builder',
            'AMKSchemaCoreBinding',
            $this->binding_payload()
        );

        wp_localize_script(
            'amk-schema-binding-builder',
            'AMKSchemaCoreBinding',
            $this->binding_payload()
        );
    }

    /**
     * Assets for global settings page.
     *
     * SettingsPage currently uses inline scripts, so jQuery is enough.
     *
     * @return void
     */
    private function enqueue_settings_assets() {

        $this->enqueue_common_scripts();

        wp_enqueue_style(
            'amk-schema-core-select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            [],
            '4.1.0-rc.0'
        );

        wp_enqueue_script(
            'amk-schema-core-select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            ['jquery'],
            '4.1.0-rc.0',
            true
        );

        wp_enqueue_script(
            'amk-schema-core-country-selector',
            $this->asset_url('resources/js/country-selector.js'),
            [
                'jquery',
                'amk-schema-core-select2'
            ],
            AMK_SCHEMA_CORE_VERSION,
            true
        );

        wp_localize_script(
            'amk-schema-core-country-selector',
            'AMKSchemaCoreI18n',
            $this->i18n_payload()
        );
    }

    /**
     * Localize REST settings for JS files.
     *
     * Several scripts expect wpApiSettings.root and wpApiSettings.nonce.
     *
     * @param string $handle
     * @return void
     */
    private function localize_rest_script($handle) {

        wp_localize_script(
            $handle,
            'wpApiSettings',
            [
                'root'  => esc_url_raw(rest_url()),
                'nonce' => wp_create_nonce('wp_rest'),
            ]
        );

        wp_localize_script(
            $handle,
            'AMKSchemaCoreI18n',
            $this->i18n_payload()
        );
    }

    /**
     * Common JS payload.
     *
     * @return array
     */
    private function common_payload() {

        return [
            'restRoot' => esc_url_raw(rest_url('amk-schema')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url('admin.php'),
            'pages'    => [
                'builder'        => admin_url('admin.php?page=amk_schema_builder'),
                'templateEditor' => admin_url('admin.php?page=amk_schema_template_editor'),
                'settings'       => admin_url('admin.php?page=amk_schema_settings'),
            ],
        ];
    }


    /**
     * Shared translated strings for admin JavaScript.
     *
     * @return array
     */
    private function i18n_payload() {

        return [
            'source_resolver' => __('Resolver variable', 'amk-schema-core'),
            'source_value' => __('Static value', 'amk-schema-core'),
            'source_post_meta' => __('Post Meta', 'amk-schema-core'),
            'source_product_meta' => __('Product Meta', 'amk-schema-core'),
            'source_product_attribute' => __('WooCommerce product attribute', 'amk-schema-core'),
            'source_taxonomy_terms' => __('Taxonomy terms', 'amk-schema-core'),
            'source_term_meta' => __('Term Meta', 'amk-schema-core'),
            'source_user_meta' => __('User Meta', 'amk-schema-core'),
            'source_option' => __('WordPress option', 'amk-schema-core'),
            'source_theme_mod' => __('Theme Mod', 'amk-schema-core'),
            'source_global_setting' => __('Plugin global setting', 'amk-schema-core'),
            'transform_none' => __('No transform', 'amk-schema-core'),
            'transform_string' => __('Convert to string', 'amk-schema-core'),
            'transform_strip_tags' => __('Strip HTML', 'amk-schema-core'),
            'transform_sanitize_text' => __('Sanitize text', 'amk-schema-core'),
            'transform_url' => __('Sanitize URL', 'amk-schema-core'),
            'transform_absint' => __('Positive integer', 'amk-schema-core'),
            'transform_float' => __('Float number', 'amk-schema-core'),
            'transform_bool' => __('Boolean', 'amk-schema-core'),
            'transform_csv_array' => __('CSV to array', 'amk-schema-core'),
            'transform_first' => __('First array value', 'amk-schema-core'),
            'variable_name' => __('Variable name', 'amk-schema-core'),
            'data_source' => __('Data source', 'amk-schema-core'),
            'key_path_value' => __('Key / path / value', 'amk-schema-core'),
            'default_value' => __('Default value', 'amk-schema-core'),
            'value_transform' => __('Value transform', 'amk-schema-core'),
            'remove' => __('Remove', 'amk-schema-core'),
            'example_custom_gtin' => __('For example custom_gtin', 'amk-schema-core'),
            'example_binding_key' => __('For example _gtin, pa_brand, or title', 'amk-schema-core'),
            'optional' => __('Optional', 'amk-schema-core'),
            'resolver_key' => __('Resolver key', 'amk-schema-core'),
            'resolver_key_help' => __('For example title, price, organization_name, or breadcrumb_items.', 'amk-schema-core'),
            'static_value_help' => __('The entered value is used exactly as the placeholder value.', 'amk-schema-core'),
            'post_meta_help' => __('For example _yoast_wpseo_title or a custom field for the post/page.', 'amk-schema-core'),
            'product_meta_help' => __('For example _gtin, _mpn, or any custom product meta.', 'amk-schema-core'),
            'attribute_help' => __('For example pa_brand or pa_color.', 'amk-schema-core'),
            'taxonomy_help' => __('For example product_cat, product_tag, pa_brand, or category.', 'amk-schema-core'),
            'term_meta_help' => __('Meta key for the current term.', 'amk-schema-core'),
            'user_meta_help' => __('Meta key for the content author.', 'amk-schema-core'),
            'option_help' => __('For example blogname or a custom option.', 'amk-schema-core'),
            'theme_mod_help' => __('For example custom_logo or a custom theme mod.', 'amk-schema-core'),
            'settings_path' => __('Settings path', 'amk-schema-core'),
            'settings_path_help' => __('For example organization.name or commerce.return_policy.', 'amk-schema-core'),
            'binding_json_invalid' => __('Binding JSON is invalid: ', 'amk-schema-core'),
            'condition_empty' => __('Is empty', 'amk-schema-core'),
            'condition_not_empty' => __('Is not empty', 'amk-schema-core'),
            'condition_exists' => __('Exists', 'amk-schema-core'),
            'condition_not_exists' => __('Does not exist', 'amk-schema-core'),
            'condition_equals' => __('Equals', 'amk-schema-core'),
            'condition_not_equals' => __('Does not equal', 'amk-schema-core'),
            'condition_contains' => __('Contains', 'amk-schema-core'),
            'condition_not_contains' => __('Does not contain', 'amk-schema-core'),
            'condition_greater_than' => __('Greater than', 'amk-schema-core'),
            'condition_less_than' => __('Less than', 'amk-schema-core'),
            'condition_greater_or_equal' => __('Greater than or equal to', 'amk-schema-core'),
            'condition_less_or_equal' => __('Less than or equal to', 'amk-schema-core'),
            'condition_in' => __('Is in list', 'amk-schema-core'),
            'condition_not_in' => __('Is not in list', 'amk-schema-core'),
            'remove_path' => __('Remove path', 'amk-schema-core'),
            'data_key' => __('Data key', 'amk-schema-core'),
            'operator' => __('Operator', 'amk-schema-core'),
            'value' => __('Value', 'amk-schema-core'),
            'action' => __('Action', 'amk-schema-core'),
            'path_to_remove' => __('Path to remove', 'amk-schema-core'),
            'example_condition_key' => __('For example rating_value or price', 'amk-schema-core'),
            'example_condition_path' => __('For example aggregateRating or offers.price', 'amk-schema-core'),
            'condition_help' => __('When the condition matches, the specified path is removed from the final Schema output.', 'amk-schema-core'),
            'conditions_incomplete' => __('Some conditions are incomplete. Complete the data key, operator, action, and path to remove.', 'amk-schema-core'),
            'conditions_textarea_updated' => __('Conditions were updated in the textarea. To save via REST, save the template first so template_id is created.', 'amk-schema-core'),
            'conditions_rest_missing' => __('wpApiSettings is not available on this page. Conditions were only updated in the textarea.', 'amk-schema-core'),
            'conditions_saved' => __('Conditions saved successfully.', 'amk-schema-core'),
            'conditions_save_error' => __('Error saving conditions.', 'amk-schema-core'),
            'conditions_save_error_console' => __('Error saving conditions. Check the browser console for details.', 'amk-schema-core'),
            'json_field_invalid' => __(' is invalid. Check the JSON structure.', 'amk-schema-core'),
            'preview_button' => __('Preview dynamic output', 'amk-schema-core'),
            'preview_title' => __('JSON-LD Preview', 'amk-schema-core'),
            'copy_json' => __('Copy JSON', 'amk-schema-core'),
            'close' => __('Close', 'amk-schema-core'),
            'json_output' => __('JSON-LD output', 'amk-schema-core'),
            'resolver_data' => __('Resolver data', 'amk-schema-core'),
            'validation_passed' => __('Output passed validation.', 'amk-schema-core'),
            'errors' => __('Errors:', 'amk-schema-core'),
            'warnings' => __('Warnings:', 'amk-schema-core'),
            'preview_missing' => __('Preview output was not received.', 'amk-schema-core'),
            'preview_loading' => __('Building preview...', 'amk-schema-core'),
            'rest_settings_missing' => __('REST settings are not loaded on this page. Check Assets.php.', 'amk-schema-core'),
            'preview_error' => __('Error building preview.', 'amk-schema-core'),
            'preview_fetch_error' => __('Error fetching preview. Check the browser console for details.', 'amk-schema-core'),
            'json_copied' => __('JSON copied.', 'amk-schema-core'),
            'override' => __('Override', 'amk-schema-core'),
            'saving' => __('Saving...', 'amk-schema-core'),
            'save_priorities' => __('Save priorities', 'amk-schema-core'),
            'no_priorities' => __('No templates were found for saving priorities.', 'amk-schema-core'),
            'priorities_saved' => __('Priorities saved successfully.', 'amk-schema-core'),
            'priorities_save_error' => __('Error saving priorities.', 'amk-schema-core'),
            'priorities_save_error_console' => __('Error saving priorities. Check the browser console for details.', 'amk-schema-core'),
            'yes' => __('Yes', 'amk-schema-core'),
            'active' => __('Active', 'amk-schema-core'),
            'country_placeholder' => __('Select countries', 'amk-schema-core'),
            'all_countries' => __('All countries', 'amk-schema-core'),
        ];
    }

    /**
     * Binding builder payload.
     *
     * @return array
     */
    private function binding_payload() {

        return [
            'sources' => [
                'resolver'          => __('Resolver variable', 'amk-schema-core'),
                'value'             => __('Static value', 'amk-schema-core'),
                'post_meta'         => 'Post Meta',
                'product_meta'      => 'Product Meta',
                'product_attribute' => __('WooCommerce product attribute', 'amk-schema-core'),
                'taxonomy_terms'    => __('Taxonomy terms', 'amk-schema-core'),
                'term_meta'         => 'Term Meta',
                'user_meta'         => 'User Meta',
                'option'            => __('WordPress option', 'amk-schema-core'),
                'theme_mod'         => 'Theme Mod',
                'global_setting'    => __('Plugin global setting', 'amk-schema-core'),
            ],
            'transforms' => [
                ''              => __('No transform', 'amk-schema-core'),
                'string'        => __('Convert to string', 'amk-schema-core'),
                'strip_tags'    => __('Strip HTML', 'amk-schema-core'),
                'sanitize_text' => __('Sanitize text', 'amk-schema-core'),
                'url'           => __('Sanitize URL', 'amk-schema-core'),
                'absint'        => __('Positive integer', 'amk-schema-core'),
                'float'         => __('Float number', 'amk-schema-core'),
                'bool'          => 'Boolean',
                'csv_array'     => __('CSV to array', 'amk-schema-core'),
                'first'         => __('First array value', 'amk-schema-core'),
            ],
        ];
    }

    /**
     * Build asset URL safely.
     *
     * @param string $path
     * @return string
     */
    private function asset_url($path) {

        $path = ltrim((string) $path, '/');

        if (defined('AMK_SCHEMA_CORE_URL')) {
            return AMK_SCHEMA_CORE_URL . $path;
        }

        return plugin_dir_url(dirname(__DIR__, 2) . '/amk-schema-core.php') . $path;
    }

    /**
     * Plugin version.
     *
     * @return string
     */
    private function version() {

        if (defined('AMK_SCHEMA_CORE_VERSION')) {
            return AMK_SCHEMA_CORE_VERSION;
        }

        return '1.0.0';
    }
}
