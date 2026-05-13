<?php
namespace PRG\SinglePlatform;

if (!defined('ABSPATH')) {
    exit;
}

class Block
{
    /**
     * Render the block.
     *
     * @param array  $attributes The block attributes.
     * @param string $content    The block inner HTML (empty).
     * @return string Rendered HTML.
     */
    public static function render($attributes = array(), $content = '')
    {
        $use_fixture = Settings::use_fixture() && Settings::fixture_json() !== '';

        $location_id = isset($attributes['location_id']) ? sanitize_text_field((string) $attributes['location_id']) : '';

        if ($location_id === '' && !$use_fixture) {
            return self::wrap_notice(esc_html__('Select a Location ID to display a menu.', 'sp-menu'));
        }

        if ($use_fixture && $location_id === '') {
            $location_id = '__fixture__';
        }

        $menu_name = isset($attributes['menu_name']) ? sanitize_text_field((string) $attributes['menu_name']) : '';
        $highlighted_items = isset($attributes['highlighted_items']) && is_array($attributes['highlighted_items']) ? $attributes['highlighted_items'] : array();

        $show_prices = isset($attributes['show_prices']) ? (bool) $attributes['show_prices'] : true;
        $currency = isset($attributes['currency']) ? sanitize_text_field((string) $attributes['currency']) : 'USD';
        $ttl = isset($attributes['cache_ttl']) ? sanitize_ttl($attributes['cache_ttl']) : Settings::default_ttl();
        $expanded = isset($attributes['expand_behavior']) && $attributes['expand_behavior'] === 'expanded';
        $category_display = isset($attributes['category_display']) ? sanitize_text_field((string) $attributes['category_display']) : 'accordion';
        $layout = isset($attributes['layout']) ? sanitize_text_field((string) $attributes['layout']) : 'accordion';
        $nutrition_visibility = isset($attributes['nutrition_visibility']) ? sanitize_text_field((string) $attributes['nutrition_visibility']) : 'hide';
        $labels_visibility = isset($attributes['labels_visibility']) ? sanitize_text_field((string) $attributes['labels_visibility']) : 'show';
        $item_columns = isset($attributes['item_columns']) ? sanitize_text_field((string) $attributes['item_columns']) : '1';

        $cache_key = Cache::key($location_id, array(
            'menu_name' => $menu_name,
            'category_filter' => array(),
            'show_prices' => $show_prices,
            'currency' => $currency,
        ));

        $data = Cache::get($cache_key);
        if ($data === false) {
            $menus = null;
            if ($location_id !== '__fixture__' && Settings::use_snapshot()) {
                $ttl_snap = Settings::snapshot_ttl();
                if (Snapshot::is_stale($location_id, $ttl_snap)) {
                    Snapshot::sync_once($location_id);
                }
                $maybe_menus = Snapshot::get_payload($location_id);
                if (is_array($maybe_menus)) {
                    $menus = $maybe_menus;
                }
            }
            if ($menus === null) {
                $menus = Client::get_menus($location_id);
                if (is_wp_error($menus)) {
                    return self::maybe_editor_error($menus);
                }
                if ($location_id !== '__fixture__' && Settings::use_snapshot()) {
                    Snapshot::upsert($location_id, array('code' => 200, 'data' => array('menus' => $menus)), null, 'ok', '');
                }
            }
            $select = array(
                'menu_name' => $menu_name,
                'category_filter' => array(),
                'currency_override' => $currency ?: '',
            );
            $data = Normalizer::to_view_model($menus, $select);
            Cache::set($cache_key, $data, $ttl);
            if ($location_id !== '__fixture__') {
                Cache::index_key($location_id, $cache_key);
            }
        }

        // Add highlighted items to the data payload for the view
        $data['highlighted_items'] = $highlighted_items;

        $view = array(
            'data' => $data,
            'show_prices' => $show_prices,
            'currency' => $currency,
            'expanded' => $expanded,
            'layout' => $layout,
            'category_display' => $category_display,
            'nutrition_visibility' => $nutrition_visibility,
            'labels_visibility' => $labels_visibility,
            'item_columns' => $item_columns,
        );

        ob_start();
        $template = PRG_SP_MENU_DIR . 'blocks/singleplatform-menu/render.php';
        include $template;
        return (string) ob_get_clean();
    }

    private static function maybe_editor_error(\WP_Error $err)
    {
        if (is_admin() && current_user_can('edit_posts')) {
            $msg = esc_html($err->get_error_message());
            return self::wrap_notice(sprintf(__('SinglePlatform error: %s', 'sp-menu'), $msg));
        }
        return self::wrap_notice(esc_html__('Menu temporarily unavailable.', 'sp-menu'));
    }

    private static function wrap_notice($html_text)
    {
        $attrs = function_exists('get_block_wrapper_attributes') ? get_block_wrapper_attributes(array('class' => 'sp-menu')) : 'class="sp-menu"';
        return '<section ' . $attrs . '><p class="sp-menu__notice">' . $html_text . '</p></section>';
    }
}