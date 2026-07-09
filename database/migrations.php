<?php

defined('ABSPATH') || exit;

global $wpdb;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('dbDelta')) {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
}

$charset_collate = $wpdb->get_charset_collate();

/*
|--------------------------------------------------------------------------
| Schema Templates Table
|--------------------------------------------------------------------------
*/

$table_templates = $wpdb->prefix . 'amk_schema_templates';

$sql_templates = "CREATE TABLE {$table_templates} (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

    name VARCHAR(255) NOT NULL,
    type VARCHAR(100) NOT NULL DEFAULT 'custom',
    scope VARCHAR(100) NOT NULL DEFAULT 'global',
    status VARCHAR(20) NOT NULL DEFAULT 'active',

    schema_json LONGTEXT NOT NULL,
    bindings LONGTEXT NULL,
    conditions LONGTEXT NULL,

    priority INT NOT NULL DEFAULT 0,
    override TINYINT(1) NOT NULL DEFAULT 0,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY  (id),
    KEY name (name),
    KEY type (type),
    KEY scope (scope),
    KEY status (status),
    KEY priority (priority),
    KEY type_scope (type, scope),
    KEY scope_status (scope, status)
) {$charset_collate};";

dbDelta($sql_templates);

/*
|--------------------------------------------------------------------------
| Schema Conditions Table
|--------------------------------------------------------------------------
*/

$table_conditions = $wpdb->prefix . 'amk_schema_conditions';

$sql_conditions = "CREATE TABLE {$table_conditions} (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

    template_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,

    field VARCHAR(255) NOT NULL,
    operator VARCHAR(50) NOT NULL,
    value LONGTEXT NULL,

    action VARCHAR(50) NOT NULL DEFAULT 'remove',
    path VARCHAR(255) NULL,
    payload LONGTEXT NULL,

    priority INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY  (id),
    KEY template_id (template_id),
    KEY field (field),
    KEY operator (operator),
    KEY status (status),
    KEY priority (priority),
    KEY template_status (template_id, status),
    KEY template_priority (template_id, priority)
) {$charset_collate};";

dbDelta($sql_conditions);

/*
|--------------------------------------------------------------------------
| Safe Column Compatibility
|--------------------------------------------------------------------------
| dbDelta should handle column creation, but this block keeps old installations
| safer if dbDelta fails to add a nullable/legacy column on some servers.
|--------------------------------------------------------------------------
*/

if (!function_exists('amk_schema_core_migration_column_exists')) {

    function amk_schema_core_migration_column_exists($table, $column) {

        global $wpdb;

        if (!is_string($table) || $table === '' || !is_string($column) || $column === '') {
            return false;
        }

        $found = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$table} LIKE %s",
                $column
            )
        );

        return $found === $column;
    }
}

if (!function_exists('amk_schema_core_migration_add_column')) {

    function amk_schema_core_migration_add_column($table, $column, $definition) {

        global $wpdb;

        if (
            !is_string($table) ||
            $table === '' ||
            !is_string($column) ||
            $column === '' ||
            !is_string($definition) ||
            $definition === ''
        ) {
            return false;
        }

        if (amk_schema_core_migration_column_exists($table, $column)) {
            return true;
        }

        $wpdb->query("ALTER TABLE {$table} ADD {$column} {$definition}");

        return amk_schema_core_migration_column_exists($table, $column);
    }
}

/*
|--------------------------------------------------------------------------
| Compatibility Columns For Older Installs
|--------------------------------------------------------------------------
*/

amk_schema_core_migration_add_column($table_templates, 'bindings', 'LONGTEXT NULL');
amk_schema_core_migration_add_column($table_templates, 'conditions', 'LONGTEXT NULL');
amk_schema_core_migration_add_column($table_templates, 'priority', 'INT NOT NULL DEFAULT 0');
amk_schema_core_migration_add_column($table_templates, 'override', 'TINYINT(1) NOT NULL DEFAULT 0');
amk_schema_core_migration_add_column($table_templates, 'created_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP');
amk_schema_core_migration_add_column($table_templates, 'updated_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP');

amk_schema_core_migration_add_column($table_conditions, 'path', 'VARCHAR(255) NULL');
amk_schema_core_migration_add_column($table_conditions, 'payload', 'LONGTEXT NULL');
amk_schema_core_migration_add_column($table_conditions, 'priority', 'INT NOT NULL DEFAULT 0');
amk_schema_core_migration_add_column($table_conditions, 'status', "VARCHAR(20) NOT NULL DEFAULT 'active'");
amk_schema_core_migration_add_column($table_conditions, 'created_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP');
amk_schema_core_migration_add_column($table_conditions, 'updated_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP');

/*
|--------------------------------------------------------------------------
| Migration Version
|--------------------------------------------------------------------------
*/

update_option('amk_schema_core_db_version', '1.0.0', false);
update_option('amk_schema_core_db_migrated_at', current_time('mysql'), false);

/*
|--------------------------------------------------------------------------
| Optional Auto Seed
|--------------------------------------------------------------------------
| Activator.php defines AMK_SCHEMA_CORE_SKIP_AUTO_SEED before requiring this file.
| So during controlled activation/upgrade, seed is run only once by Activator.
| If this migration file is included directly, it can still seed defaults.
|--------------------------------------------------------------------------
*/

if (!defined('AMK_SCHEMA_CORE_SKIP_AUTO_SEED') || !AMK_SCHEMA_CORE_SKIP_AUTO_SEED) {
    $seed_file = defined('AMK_SCHEMA_CORE_PATH')
        ? AMK_SCHEMA_CORE_PATH . 'database/seed-defaults.php'
        : '';

    if ($seed_file && file_exists($seed_file)) {
        require_once $seed_file;
    }
}