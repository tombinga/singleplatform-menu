<?php
/**
 * Plugin Name: SinglePlatform Menu
 * Description: Server-rendered Gutenberg block that displays a restaurant menu via the SinglePlatform API with caching.
 * Version: 0.6.0
 * Author: Tom Binga
 * Text Domain: sp-menu
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PRG_SP_MENU_VERSION', '0.6.0');
define('PRG_SP_MENU_DIR', plugin_dir_path(__FILE__));
define('PRG_SP_MENU_URL', plugin_dir_url(__FILE__));

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

add_action('init', function () {
    // Register the block
    register_block_type(PRG_SP_MENU_DIR . 'build', array(
        'render_callback' => array('\PRG\SinglePlatform\Block', 'render')
    ));

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
    if ($ts)
        wp_unschedule_event($ts, 'prg_sp_sync_all');
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