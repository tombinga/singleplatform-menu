<?php
/**
 * Plugin Name: SinglePlatform Menu (ACF Block)
 * Description: Server-rendered ACF block that displays a restaurant menu via the SinglePlatform API with caching.
 * Version: 0.4.0
 * Author: Tom Binga
 * Text Domain: sp-menu
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PRG_SP_MENU_VERSION', '0.4.0');
define('PRG_SP_MENU_DIR', plugin_dir_path(__FILE__));
define('PRG_SP_MENU_URL', plugin_dir_url(__FILE__));

// Make ACF JSON in this plugin visible in the admin (Sync) only.
add_filter('acf/settings/load_json', function ($paths) {
    $paths[] = PRG_SP_MENU_DIR . 'acf-json';
    return $paths;
});

require_once PRG_SP_MENU_DIR . 'includes/helpers-sanitize.php';
require_once PRG_SP_MENU_DIR . 'includes/class-sp-settings.php';
require_once PRG_SP_MENU_DIR . 'includes/class-sp-cache.php';
require_once PRG_SP_MENU_DIR . 'includes/class-sp-client.php';
require_once PRG_SP_MENU_DIR . 'includes/class-sp-normalizer.php';
require_once PRG_SP_MENU_DIR . 'includes/class-sp-block.php';
require_once PRG_SP_MENU_DIR . 'includes/class-sp-rest.php';
require_once PRG_SP_MENU_DIR . 'includes/class-sp-updates.php';
require_once PRG_SP_MENU_DIR . 'includes/class-sp-snapshot.php';

add_action('plugins_loaded', function () {
    load_plugin_textdomain('sp-menu');
    \PRG\SinglePlatform\Updates::init();
});

// Suppress the block supports warning for ACF blocks (known ACF/WP compatibility issue)
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    // Suppress "Trying to access array offset on null" from class-wp-block-supports.php line 98
    if (
        $errno === E_WARNING &&
        strpos($errfile, 'class-wp-block-supports.php') !== false &&
        $errline === 98 &&
        strpos($errstr, 'Trying to access array offset on null') !== false
    ) {
        return true; // Suppress this specific warning
    }
    return false; // Let other errors pass through
}, E_WARNING);

// Register ACF block on acf/init
add_action('acf/init', function () {
    if (!function_exists('acf_register_block_type')) {
        return;
    }

    acf_register_block_type(array(
        'name' => 'prg-singleplatform-menu',
        'title' => __('Restaurant Menu (SinglePlatform)', 'sp-menu'),
        'description' => __('Server-rendered SinglePlatform menu.', 'sp-menu'),
        'category' => 'widgets',
        'icon' => 'food',
        'keywords' => array('menu', 'restaurant', 'singleplatform'),
        'mode' => 'preview',
        'render_template' => PRG_SP_MENU_DIR . 'blocks/singleplatform-menu/template.php',
        'enqueue_style' => PRG_SP_MENU_URL . 'blocks/singleplatform-menu/style.css',
        'supports' => array(
            'align' => true,
            'anchor' => true,
            'customClassName' => true,
            'jsx' => true,
        ),
    ));
});

add_action('init', function () {
    wp_register_style('sp-menu/style', PRG_SP_MENU_URL . 'blocks/singleplatform-menu/style.css', array(), PRG_SP_MENU_VERSION);
    wp_register_style('sp-menu/editor', PRG_SP_MENU_URL . 'blocks/singleplatform-menu/editor.css', array('wp-edit-blocks'), PRG_SP_MENU_VERSION);

    PRG\SinglePlatform\Settings::init();
    PRG\SinglePlatform\Rest::init();
});

// Snapshot: activation/deactivation and cron scheduling
register_activation_hook(__FILE__, function () {
    \PRG\SinglePlatform\Snapshot::install();
    if (!wp_next_scheduled('prg_sp_sync_all')) {
        wp_schedule_event(time() + 60, 'prg_sp_interval', 'prg_sp_sync_all');
    }
});

register_deactivation_hook(__FILE__, function () {
    $ts = wp_next_scheduled('prg_sp_sync_all');
    if ($ts) wp_unschedule_event($ts, 'prg_sp_sync_all');
});

add_filter('cron_schedules', function ($schedules) {
    $mins = \PRG\SinglePlatform\Settings::cron_minutes();
    $schedules['prg_sp_interval'] = array(
        'interval' => max(300, $mins * 60),
        'display' => sprintf(__('Every %d minutes (PRG SP)', 'sp-menu'), $mins)
    );
    return $schedules;
});

add_action('prg_sp_sync_all', function () {
    $list = \PRG\SinglePlatform\Settings::sync_locations();
    \PRG\SinglePlatform\Snapshot::sync_many($list);
});
