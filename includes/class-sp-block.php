<?php
namespace PRG\SinglePlatform;

if (!defined('ABSPATH')) { exit; }

class Block {
    public static function render($block = array(), $content = '', $is_preview = false, $post_id = 0) {
        // ACF blocks pass: $block, $content, $is_preview, $post_id
        // Use the block instance ID so ACF returns the block's values on the frontend.
        $fields = array();
        if (function_exists('get_fields')) {
            // For ACF blocks, the block id already contains the `block_` prefix.
            $context_id = isset($block['id']) ? $block['id'] : false;
            if ($context_id) {
                $maybe = get_fields($context_id);
                if (is_array($maybe)) {
                    $fields = $maybe;
                }
            }
            // Fallback for unexpected contexts (e.g., preview without id)
            if (!$fields && isset($block['data']) && is_array($block['data'])) {
                $fields = $block['data'];
            }
        }

        $use_fixture = Settings::use_fixture() && Settings::fixture_json() !== '';

        $location_id = isset($fields['location_id']) ? sanitize_text_field((string) $fields['location_id']) : '';
        if ($location_id === '' && !$use_fixture) {
            return self::wrap_notice(esc_html__('Select a Location ID to display a menu.', 'sp-menu'));
        }

        if ($use_fixture && $location_id === '') {
            $location_id = '__fixture__';
        }

        $category_filter = array();
        if (!empty($fields['category_filter']) && is_array($fields['category_filter'])) {
            foreach ($fields['category_filter'] as $row) {
                if (isset($row['name'])) $category_filter[] = sanitize_text_field((string) $row['name']);
            }
        }

        $menu_name = isset($fields['menu_name']) ? sanitize_text_field((string) $fields['menu_name']) : '';

        $show_prices = !empty($fields['show_prices']);
        $currency = isset($fields['currency']) ? sanitize_text_field((string) $fields['currency']) : 'USD';
        $ttl = isset($fields['cache_ttl']) ? sanitize_ttl($fields['cache_ttl']) : Settings::default_ttl();
        $expanded = isset($fields['expand_behavior']) && $fields['expand_behavior'] === 'expanded';
        $category_display = isset($fields['category_display']) ? sanitize_text_field((string) $fields['category_display']) : 'accordion';
        if (!in_array($category_display, array('accordion', 'expanded'), true)) {
            $category_display = 'accordion';
        }
        $layout = isset($fields['layout']) ? sanitize_text_field((string) $fields['layout']) : 'accordion';
        if (!in_array($layout, array('accordion', 'tabs'), true)) {
            $layout = 'accordion';
        }

        $nutrition_visibility = isset($fields['nutrition_visibility']) ? sanitize_text_field((string) $fields['nutrition_visibility']) : 'hide';
        if (!in_array($nutrition_visibility, array('hide', 'show'), true)) {
            $nutrition_visibility = 'hide';
        }

        $labels_visibility = isset($fields['labels_visibility']) ? sanitize_text_field((string) $fields['labels_visibility']) : 'show';
        if (!in_array($labels_visibility, array('show', 'hide'), true)) {
            $labels_visibility = 'show';
        }

        $item_columns = isset($fields['item_columns']) ? sanitize_text_field((string) $fields['item_columns']) : '1';
        if (!in_array($item_columns, array('1','2'), true)) {
            $item_columns = '1';
        }

        $cache_key = Cache::key($location_id, array(
            'menu_name'       => $menu_name,
            'category_filter' => $category_filter,
            'show_prices'     => $show_prices,
            'currency'        => $currency,
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
                'menu_name'        => $menu_name,
                'category_filter'  => $category_filter,
                'currency_override'=> $currency ?: '',
            );
            $data = Normalizer::to_view_model($menus, $select);
            Cache::set($cache_key, $data, $ttl);
            if ($location_id !== '__fixture__') {
                Cache::index_key($location_id, $cache_key);
            }
        }

        $view = array(
            'data'        => $data,
            'show_prices' => $show_prices,
            'currency'    => $currency,
            'expanded'    => $expanded,
            'layout'      => $layout,
            'category_display' => $category_display,
            'nutrition_visibility' => $nutrition_visibility,
            'labels_visibility'    => $labels_visibility,
            'item_columns'         => $item_columns,
        );

        ob_start();
        $template = PRG_SP_MENU_DIR . 'blocks/singleplatform-menu/render.php';
        include $template;
        return (string) ob_get_clean();
    }

    private static function maybe_editor_error(\WP_Error $err) {
        if (is_admin() && current_user_can('edit_posts')) {
            $msg = esc_html($err->get_error_message());
            return self::wrap_notice(sprintf(__('SinglePlatform error: %s', 'sp-menu'), $msg));
        }
        return self::wrap_notice(esc_html__('Menu temporarily unavailable.', 'sp-menu'));
    }

    private static function wrap_notice($html_text) {
        $attrs = function_exists('get_block_wrapper_attributes') ? get_block_wrapper_attributes(array('class' => 'sp-menu')) : 'class="sp-menu"';
        return '<section ' . $attrs . '><p class="sp-menu__notice">' . $html_text . '</p></section>';
    }
}
