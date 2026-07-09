<?php

namespace AMK\SchemaCore\Core;

defined('ABSPATH') || exit;

use AMK\SchemaCore\Admin\AdminMenu;
use AMK\SchemaCore\Frontend\Output;
use AMK\SchemaCore\REST\SchemaController;
use AMK\SchemaCore\REST\ConditionController;
use AMK\SchemaCore\REST\PreviewController;
use AMK\SchemaCore\REST\PriorityController;
use AMK\SchemaCore\REST\TemplateController;

class Plugin {

    /**
     * Boot plugin.
     *
     * @return void
     */
    public function init() {
        $this->load_textdomain();
        $this->maybe_upgrade();
        $this->boot_modules();
    }

    /**
     * Load plugin translations.
     *
     * @return void
     */
    private function load_textdomain() {
        load_plugin_textdomain(
            'amk-schema-core',
            false,
            dirname(plugin_basename(AMK_SCHEMA_CORE_FILE)) . '/languages'
        );
    }

    /**
     * Run upgrade checks when plugin files changed.
     *
     * Activation hook is not enough because on many sites the plugin files are
     * uploaded/replaced while the plugin is already active. In that case
     * WordPress does not run the activation hook again.
     *
     * This must run before frontend output too. Otherwise replacing plugin files
     * on an already-active site can leave old database templates in place until
     * an admin page is opened, and the homepage may render no schema.
     *
     * @return void
     */
    private function maybe_upgrade() {
        if (!class_exists(Activator::class)) {
            return;
        }

        Activator::maybe_upgrade();
    }

    /**
     * Boot plugin modules.
     *
     * @return void
     */
    private function boot_modules() {

        if (is_admin()) {
            new AdminMenu();
        }

        if (!is_admin()) {
            new Output();
        }

        new SchemaController();
        new ConditionController();
        new PreviewController();
        new PriorityController();
        new TemplateController();
    }
}
