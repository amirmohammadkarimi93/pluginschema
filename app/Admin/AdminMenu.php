<?php

namespace AMK\SchemaCore\Admin;

defined('ABSPATH') || exit;

class AdminMenu {

    /**
     * Main capability required for plugin admin pages.
     *
     * @var string
     */
    private $capability = 'manage_options';

    /**
     * Main menu slug.
     *
     * @var string
     */
    private $menu_slug = 'amk_schema_builder';

    /**
     * Settings page slug.
     *
     * @var string
     */
    private $settings_slug = 'amk_schema_settings';

    /**
     * Template editor page slug.
     *
     * @var string
     */
    private $template_editor_slug = 'amk_schema_template_editor';

    public function __construct() {
        $this->capability = $this->resolve_capability();

        add_action('admin_menu', [$this, 'register_menu'], 9);
        add_action('admin_menu', [$this, 'register_legacy_hidden_pages'], 99);

        new Assets();
    }

    /**
     * Register plugin admin menu pages.
     *
     * @return void
     */
    public function register_menu() {

        add_menu_page(
            __('AMK Schema Core', 'amk-schema-core'),
            __('AMK Schema Core', 'amk-schema-core'),
            $this->capability,
            $this->menu_slug,
            [$this, 'render_builder_page'],
            'dashicons-networking',
            58
        );

        add_submenu_page(
            $this->menu_slug,
            __('Schema Templates', 'amk-schema-core'),
            __('Schema Templates', 'amk-schema-core'),
            $this->capability,
            $this->menu_slug,
            [$this, 'render_builder_page']
        );

        add_submenu_page(
            $this->menu_slug,
            __('Add / Edit Template', 'amk-schema-core'),
            __('Add Template', 'amk-schema-core'),
            $this->capability,
            $this->template_editor_slug,
            [$this, 'render_template_editor_page']
        );

        add_submenu_page(
            $this->menu_slug,
            __('Schema Settings', 'amk-schema-core'),
            __('Settings', 'amk-schema-core'),
            $this->capability,
            $this->settings_slug,
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register hidden legacy pages so old bookmarks / old plugin links do not
     * trigger WordPress "You are not allowed to access this page" errors.
     *
     * @return void
     */
    public function register_legacy_hidden_pages() {

        $legacy_settings_slugs = [
            'amk_schema_core_settings',
            'amk_schema_global_settings',
            'amk-schema-settings',
            'amk_schema_setting',
        ];

        foreach ($legacy_settings_slugs as $legacy_slug) {
            add_submenu_page(
                null,
                __('Schema Settings', 'amk-schema-core'),
                __('Schema Settings', 'amk-schema-core'),
                $this->capability,
                $legacy_slug,
                [$this, 'redirect_to_settings_page']
            );
        }
    }

    /**
     * Render schema builder page.
     *
     * @return void
     */
    public function render_builder_page() {
        $this->ensure_access();

        $page = new SchemaBuilderPage();
        $page->render();
    }

    /**
     * Render template editor page.
     *
     * @return void
     */
    public function render_template_editor_page() {
        $this->ensure_access();

        $template_id = isset($_GET['template_id']) ? absint(wp_unslash($_GET['template_id'])) : null;

        $page = new TemplateEditorPage();
        $page->render($template_id);
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public function render_settings_page() {
        $this->ensure_access();

        $page = new SettingsPage();
        $page->render();
    }

    /**
     * Redirect legacy settings slugs to the canonical settings page.
     *
     * @return void
     */
    public function redirect_to_settings_page() {
        $this->ensure_access();

        wp_safe_redirect(admin_url('admin.php?page=' . $this->settings_slug));
        exit;
    }

    /**
     * Resolve admin capability.
     *
     * @return string
     */
    private function resolve_capability() {
        $capability = 'manage_options';

        /**
         * Filter AMK Schema Core admin capability.
         *
         * Keep this as manage_options by default. Do not lower it unless the
         * site owner intentionally wants non-admin roles to manage schema output.
         */
        $capability = apply_filters('amk_schema_core_admin_capability', $capability);

        $capability = is_string($capability) ? sanitize_key($capability) : 'manage_options';

        return $capability !== '' ? $capability : 'manage_options';
    }

    /**
     * Stop rendering with a controlled message if access is really invalid.
     *
     * @return void
     */
    private function ensure_access() {
        if (current_user_can($this->capability)) {
            return;
        }

        wp_die(
            esc_html__('You do not have permission to access AMK Schema Core pages.', 'amk-schema-core'),
            esc_html__('Permission denied', 'amk-schema-core'),
            ['response' => 403]
        );
    }
}
