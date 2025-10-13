<?php
namespace PRG\SinglePlatform;

if (!defined('ABSPATH')) { exit; }

class Settings {
    const OPT_API_KEY = 'prg_sp_api_key';
    const OPT_API_BASE = 'prg_sp_api_base';
    const OPT_DEFAULT_TTL = 'prg_sp_default_ttl';

    // Fixture options (paste JSON; no file upload needed)
    const OPT_USE_FIXTURE = 'prg_sp_use_fixture';
    const OPT_FIXTURE_JSON = 'prg_sp_fixture_json';

    // Snapshot options
    const OPT_USE_SNAPSHOT   = 'prg_sp_use_snapshot';
    const OPT_SNAPSHOT_TTL   = 'prg_sp_snapshot_ttl';
    const OPT_SYNC_LOCATIONS = 'prg_sp_sync_locations';
    const OPT_CRON_MINUTES   = 'prg_sp_cron_minutes';

    public static function init() {
        add_action('admin_init', array(__CLASS__, 'register'));
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_prg_sp_sync_now', array(__CLASS__, 'handle_sync_now'));
    }

    public static function register() {
        register_setting('prg_sp_settings', self::OPT_API_KEY, array('type' => 'string','sanitize_callback' => 'sanitize_text_field'));
        register_setting('prg_sp_settings', self::OPT_API_BASE, array('type' => 'string','sanitize_callback' => 'esc_url_raw','default' => 'https://customer-api.singleplatform.com/'));
        register_setting('prg_sp_settings', self::OPT_DEFAULT_TTL, array('type' => 'integer','sanitize_callback' => '\PRG\SinglePlatform\sanitize_ttl','default' => 900));

        // Fixture settings
        register_setting('prg_sp_settings', self::OPT_USE_FIXTURE, array('type' => 'boolean','sanitize_callback' => function($v){ return (bool)$v; }, 'default' => false));
        register_setting('prg_sp_settings', self::OPT_FIXTURE_JSON, array('type' => 'string','sanitize_callback' => function($v){ return (string) $v; }));

        // Snapshot settings
        register_setting('prg_sp_settings', self::OPT_USE_SNAPSHOT, array('type' => 'boolean','sanitize_callback' => function($v){ return (bool)$v; }, 'default' => true));
        register_setting('prg_sp_settings', self::OPT_SNAPSHOT_TTL, array('type' => 'integer','sanitize_callback' => 'intval', 'default' => 1800));
        register_setting('prg_sp_settings', self::OPT_SYNC_LOCATIONS, array('type' => 'string','sanitize_callback' => 'sanitize_textarea_field', 'default' => ''));
        register_setting('prg_sp_settings', self::OPT_CRON_MINUTES, array('type' => 'integer','sanitize_callback' => function($v){ $n=(int)$v; return $n>=5?$n:15; }, 'default' => 15));

        add_settings_section('prg_sp_main', __('SinglePlatform API', 'sp-menu'), function () {
            echo '<p>' . esc_html__('Configure credentials and defaults.', 'sp-menu') . '</p>';
        }, 'prg_sp_settings');

        add_settings_field(self::OPT_API_KEY, __('API Key', 'sp-menu'), function () {
            $val = get_option(self::OPT_API_KEY, '');
            echo '<input type="password" name="' . esc_attr(self::OPT_API_KEY) . '" value="' . esc_attr($val) . '" class="regular-text" />';
        }, 'prg_sp_settings', 'prg_sp_main');

        add_settings_field(self::OPT_API_BASE, __('API Base URL', 'sp-menu'), function () {
            $val = get_option(self::OPT_API_BASE, 'https://customer-api.singleplatform.com/');
            echo '<input type="url" name="' . esc_attr(self::OPT_API_BASE) . '" value="' . esc_attr($val) . '" class="regular-text code" />';
        }, 'prg_sp_settings', 'prg_sp_main');

        add_settings_field(self::OPT_DEFAULT_TTL, __('Default Cache TTL (seconds)', 'sp-menu'), function () {
            $val = (int) get_option(self::OPT_DEFAULT_TTL, 900);
            echo '<input type="number" min="60" max="86400" step="1" name="' . esc_attr(self::OPT_DEFAULT_TTL) . '" value="' . esc_attr($val) . '" />';
        }, 'prg_sp_settings', 'prg_sp_main');

        // Fixture UI
        add_settings_section('prg_sp_fixture', __('Fixture (Developer Only)', 'sp-menu'), function () {
            echo '<p>' . esc_html__('Paste a known-good SinglePlatform JSON response to force rendering without hitting the API. Enable only while debugging.', 'sp-menu') . '</p>';
        }, 'prg_sp_settings');

        add_settings_field(self::OPT_USE_FIXTURE, __('Use Fixture JSON', 'sp-menu'), function () {
            $val = (bool) get_option(self::OPT_USE_FIXTURE, false);
            echo '<label><input type="checkbox" name="' . esc_attr(self::OPT_USE_FIXTURE) . '" value="1" ' . checked($val, true, false) . ' /> ' . esc_html__('Enable (admins only)', 'sp-menu') . '</label>';
        }, 'prg_sp_settings', 'prg_sp_fixture');

        add_settings_field(self::OPT_FIXTURE_JSON, __('Fixture JSON', 'sp-menu'), function () {
            $val = get_option(self::OPT_FIXTURE_JSON, '');
            echo '<textarea name="' . esc_attr(self::OPT_FIXTURE_JSON) . '" class="large-text code" rows="10" placeholder="{ \"data\": {\"menus\": [...] } }">' . esc_textarea($val) . '</textarea>';
        }, 'prg_sp_settings', 'prg_sp_fixture');

        // Snapshot UI
        add_settings_section('prg_sp_snapshot', __('Snapshot (Durable Cache)', 'sp-menu'), function(){
            echo '<p>' . esc_html__('Serve menus from a local, last-good snapshot and refresh on a schedule.', 'sp-menu') . '</p>';
        }, 'prg_sp_settings');
        add_settings_field(self::OPT_USE_SNAPSHOT, __('Use snapshot for rendering', 'sp-menu'), function(){
            $val = (bool) get_option(self::OPT_USE_SNAPSHOT, true);
            echo '<label><input type="checkbox" name="' . esc_attr(self::OPT_USE_SNAPSHOT) . '" value="1" ' . checked($val, true, false) . ' /> ' . esc_html__('Enable', 'sp-menu') . '</label>';
        }, 'prg_sp_settings', 'prg_sp_snapshot');
        add_settings_field(self::OPT_SNAPSHOT_TTL, __('Snapshot TTL (seconds)', 'sp-menu'), function(){
            $val = (int) get_option(self::OPT_SNAPSHOT_TTL, 1800);
            echo '<input type="number" min="300" step="60" name="' . esc_attr(self::OPT_SNAPSHOT_TTL) . '" value="' . esc_attr($val) . '" />';
        }, 'prg_sp_settings', 'prg_sp_snapshot');
        add_settings_field(self::OPT_SYNC_LOCATIONS, __('Locations to Sync', 'sp-menu'), function(){
            $val = (string) get_option(self::OPT_SYNC_LOCATIONS, '');
            echo '<textarea name="' . esc_attr(self::OPT_SYNC_LOCATIONS) . '" rows="4" class="large-text code" placeholder="one-location-id-per-line">' . esc_textarea($val) . '</textarea><p class="description">' . esc_html__('Used by the cron job and the “Sync now” button.', 'sp-menu') . '</p>';
        }, 'prg_sp_settings', 'prg_sp_snapshot');
        add_settings_field(self::OPT_CRON_MINUTES, __('Cron frequency (minutes)', 'sp-menu'), function(){
            $val = (int) get_option(self::OPT_CRON_MINUTES, 15);
            echo '<input type="number" min="5" step="5" name="' . esc_attr(self::OPT_CRON_MINUTES) . '" value="' . esc_attr($val) . '" />';
        }, 'prg_sp_settings', 'prg_sp_snapshot');
    }

    public static function menu() {
        add_options_page(__('SinglePlatform', 'sp-menu'), __('SinglePlatform', 'sp-menu'), 'manage_options', 'prg_sp_settings', array(__CLASS__, 'render_page'));
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) { return; }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('SinglePlatform Settings', 'sp-menu') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('prg_sp_settings');
        do_settings_sections('prg_sp_settings');
        submit_button();
        echo '</form>';
        $url = wp_nonce_url(admin_url('admin-post.php?action=prg_sp_sync_now'), 'prg_sp_sync_now');
        echo '<hr><p><a href="' . esc_url($url) . '" class="button button-secondary">' . esc_html__('Sync now (all listed locations)', 'sp-menu') . '</a></p>';
        echo '</div>';
    }

    public static function api_key() {
        $key = get_option(self::OPT_API_KEY);
        if (!$key) $key = getenv('SINGLEPLATFORM_API_KEY');
        return (string) $key;
    }
    public static function api_base() { return (string) get_option(self::OPT_API_BASE, 'https://customer-api.singleplatform.com/'); }
    public static function default_ttl() { return sanitize_ttl((int) get_option(self::OPT_DEFAULT_TTL, 900)); }
    public static function use_fixture() { return (bool) get_option(self::OPT_USE_FIXTURE, false); }
    public static function fixture_json() { return (string) get_option(self::OPT_FIXTURE_JSON, ''); }
    public static function use_snapshot() { return (bool) get_option(self::OPT_USE_SNAPSHOT, true); }
    public static function snapshot_ttl() { return max(300, (int) get_option(self::OPT_SNAPSHOT_TTL, 1800)); }
    public static function sync_locations() {
        $raw = (string) get_option(self::OPT_SYNC_LOCATIONS, '');
        return array_filter(array_map('trim', preg_split('/\R+/', $raw)));
    }
    public static function cron_minutes() { return max(5, (int) get_option(self::OPT_CRON_MINUTES, 15)); }
    public static function handle_sync_now() {
        if (!current_user_can('manage_options') || !check_admin_referer('prg_sp_sync_now')) { wp_die(__('Not allowed', 'sp-menu')); }
        \PRG\SinglePlatform\Snapshot::sync_many(self::sync_locations());
        wp_safe_redirect(admin_url('options-general.php?page=prg_sp_settings&synced=1'));
        exit;
    }
}
