<?php
/**
 * Plugin Name: AMK Schema Core
 * Description: Advanced Schema Engine for WooCommerce (Templates + Rules + Dynamic JSON-LD)
 * Version: 1.0.3
 * Author: AmirMohammad Karimi
 * Text Domain: amk-schema-core
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

/*
|--------------------------------------------------------------------------
| Paths & Constants
|--------------------------------------------------------------------------
*/

if (!defined('AMK_SCHEMA_CORE_VERSION')) {
    define('AMK_SCHEMA_CORE_VERSION', '1.0.3');
}

if (!defined('AMK_SCHEMA_CORE_FILE')) {
    define('AMK_SCHEMA_CORE_FILE', __FILE__);
}

if (!defined('AMK_SCHEMA_CORE_PATH')) {
    define('AMK_SCHEMA_CORE_PATH', plugin_dir_path(__FILE__));
}

if (!defined('AMK_SCHEMA_CORE_URL')) {
    define('AMK_SCHEMA_CORE_URL', plugin_dir_url(__FILE__));
}

if (!defined('AMK_SCHEMA_CORE_APP')) {
    define('AMK_SCHEMA_CORE_APP', AMK_SCHEMA_CORE_PATH . 'app/');
}

/*
|--------------------------------------------------------------------------
| Autoloader
|--------------------------------------------------------------------------
*/

spl_autoload_register(function ($class) {

    $prefix = 'AMK\\SchemaCore\\';

    if (!is_string($class) || strpos($class, $prefix) !== 0) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));

    if ($relative_class === '') {
        return;
    }

    $file = AMK_SCHEMA_CORE_APP . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

/*
|--------------------------------------------------------------------------
| Activation / Deactivation
|--------------------------------------------------------------------------
*/

register_activation_hook(
    __FILE__,
    ['AMK\\SchemaCore\\Core\\Activator', 'activate']
);

register_deactivation_hook(
    __FILE__,
    ['AMK\\SchemaCore\\Core\\Deactivator', 'deactivate']
);

/*
|--------------------------------------------------------------------------
| Bootstrap
|--------------------------------------------------------------------------
*/

add_action('plugins_loaded', function () {

    load_plugin_textdomain(
        'amk-schema-core',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );

    if (!class_exists('AMK\\SchemaCore\\Core\\Plugin')) {
        return;
    }

    $plugin = new AMK\SchemaCore\Core\Plugin();
    $plugin->init();
});