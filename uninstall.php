<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * This file must not depend on plugin autoloading or plugin classes.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Main data-removal setting:
 *
 * false = keep tables and templates when the plugin is deleted.
 * true  = delete plugin tables and data when the plugin is deleted.
 */

$delete_data_on_uninstall = false;

if (is_multisite()) {

    $site_ids = get_sites([
        'fields' => 'ids',
        'number' => 0,
    ]);

    foreach ($site_ids as $site_id) {
        switch_to_blog($site_id);
        amk_schema_core_uninstall_site($delete_data_on_uninstall);
        restore_current_blog();
    }

    amk_schema_core_uninstall_network_options();

    return;
}

amk_schema_core_uninstall_site($delete_data_on_uninstall);

function amk_schema_core_uninstall_site($delete_data_on_uninstall = false) {

    global $wpdb;

    amk_schema_core_clear_scheduled_hooks();
    amk_schema_core_delete_options();

    if (!$delete_data_on_uninstall) {
        return;
    }

    $tables = [
        $wpdb->prefix . 'amk_schema_templates',
        $wpdb->prefix . 'amk_schema_conditions',

        /*
         * Legacy table names.
         */
        $wpdb->prefix . 'amk_schemas',
    ];

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS `$table`");
    }
}

function amk_schema_core_delete_options() {

    $options = [
        'amk_schema_core_version',
        'amk_schema_core_db_version',
        'amk_schema_core_defaults_seeded',
        'amk_schema_core_defaults_seeded_at',
        'amk_schema_core_defaults_seed_result',
        'amk_schema_core_global_settings',
        'amk_schema_core_settings',
    ];

    foreach ($options as $option) {
        delete_option($option);
    }

    delete_transient('amk_schema_core_cache');
    delete_transient('amk_schema_core_schema_cache');
}

function amk_schema_core_clear_scheduled_hooks() {

    $hooks = [
        'amk_schema_core_daily_cleanup',
        'amk_schema_core_refresh_cache',
    ];

    foreach ($hooks as $hook) {
        wp_clear_scheduled_hook($hook);
    }
}

function amk_schema_core_uninstall_network_options() {

    $network_options = [
        'amk_schema_core_network_version',
        'amk_schema_core_network_settings',
    ];

    foreach ($network_options as $option) {
        delete_site_option($option);
    }
}
