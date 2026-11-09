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
        $use_fixture = Settings::use_fixture();

        $location_id = isset($attributes['location_id']) ? sanitize_text_field((string) $attributes['location_id']) : '';
        $configured_location = $location_id;
        $grouped = ($attributes['enable_menu_groups'] ?? false) === true;

        if ($location_id === '' && !$use_fixture) {
            return self::wrap_notice(esc_html__('Select a Location ID to display a menu.', 'sp-menu'));
        }

        if ($use_fixture && $location_id === '') {
            $location_id = '__fixture__';
        }

        $menu_name = isset($attributes['menu_name']) ? sanitize_text_field((string) $attributes['menu_name']) : '';
        $highlighted_items = isset($attributes['highlighted_items']) && is_array($attributes['highlighted_items']) ? $attributes['highlighted_items'] : array();
        $category_filter = array();
        if (isset($attributes['category_filter']) && is_array($attributes['category_filter'])) {
            foreach ($attributes['category_filter'] as $category_name) {
                $category_name = sanitize_text_field((string) $category_name);
                if ($category_name !== '') {
                    $category_filter[] = $category_name;
                }
            }
            $category_filter = array_values(array_unique($category_filter));
            sort($category_filter, SORT_NATURAL | SORT_FLAG_CASE);
        }

        $show_prices = isset($attributes['show_prices']) ? (bool) $attributes['show_prices'] : true;
        $currency = isset($attributes['currency']) ? sanitize_text_field((string) $attributes['currency']) : 'USD';
        $ttl = isset($attributes['cache_ttl']) ? sanitize_ttl($attributes['cache_ttl']) : Settings::default_ttl();
        $expanded = isset($attributes['expand_behavior']) && $attributes['expand_behavior'] === 'expanded';
        $category_display = isset($attributes['category_display']) ? sanitize_text_field((string) $attributes['category_display']) : 'accordion';
        $layout = isset($attributes['layout']) ? sanitize_text_field((string) $attributes['layout']) : 'accordion';
        $nutrition_visibility = isset($attributes['nutrition_visibility']) ? sanitize_text_field((string) $attributes['nutrition_visibility']) : 'hide';
        $labels_visibility = isset($attributes['labels_visibility']) ? sanitize_text_field((string) $attributes['labels_visibility']) : 'show';
        $item_columns = isset($attributes['item_columns']) ? sanitize_text_field((string) $attributes['item_columns']) : '1';

        $select = array(
            'menu_name' => $grouped ? '' : $menu_name,
            'category_filter' => $category_filter,
            'currency_override' => $currency ?: '',
            'omit_empty_menu' => $grouped,
        );
        $cache_key = Cache::key($location_id, array(
            'menu_name' => $select['menu_name'],
            'category_filter' => $category_filter,
            'show_prices' => $show_prices,
            'currency' => $currency,
        ) + ($grouped ? array('catalog_schema' => 1) : array()));

        if ($use_fixture) {
            $menus = Client::get_menus($location_id);
            if (is_wp_error($menus)) {
                return self::maybe_editor_error($menus);
            }
            $data = Normalizer::to_view_model($menus, $select);
        } else {
            $data = Cache::get($cache_key);
        }
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
            'layout' => $grouped ? 'tabs' : $layout,
            'category_display' => $category_display,
            'nutrition_visibility' => $nutrition_visibility,
            'labels_visibility' => $labels_visibility,
            'item_columns' => $item_columns,
        );
        if ($grouped) {
            $config = $attributes['menu_groups'] ?? array();
            $groups = is_array($config) && ($config['location_id'] ?? null) === $configured_location
                ? ($config['groups'] ?? array())
                : array();
            $view['choices'] = self::menu_choices($data['menus'], is_array($groups) ? $groups : array());
        }

        ob_start();
        $template = PRG_SP_MENU_DIR . 'blocks/singleplatform-menu/render.php';
        include $template;
        return (string) ob_get_clean();
    }

    private static function menu_choices(array $menus, array $groups)
    {
        $positions = array();
        foreach ($menus as $position => $menu) {
            $id = $menu['menuId'] ?? '';
            if ($id !== '' && !isset($positions[$id])) {
                $positions[$id] = $position;
            }
        }

        $claimed = array();
        $choices = array();
        foreach ($groups as $group) {
            if (!is_array($group) || !is_string($group['label'] ?? null) || !is_array($group['members'] ?? null)) {
                continue;
            }
            $label = sanitize_text_field($group['label']);
            if ($label === '') {
                continue;
            }

            $members = array();
            foreach ($group['members'] as $member) {
                $id = menu_id(is_array($member) ? ($member['menu_id'] ?? null) : null);
                if (!isset($positions[$id]) || isset($claimed[$id])) {
                    continue;
                }
                $position = $positions[$id];
                $menu = $menus[$position];
                if (is_string($member['label'] ?? null)) {
                    $override = sanitize_text_field($member['label']);
                    if ($override !== '') {
                        $menu['menuName'] = $override;
                    }
                }
                $members[$position] = $menu;
                $claimed[$id] = true;
            }
            if ($members) {
                $choices[min(array_keys($members))] = array('label' => $label, 'menus' => array_values($members));
            }
        }

        foreach ($menus as $position => $menu) {
            $id = $menu['menuId'] ?? '';
            if ($id !== '' && ($positions[$id] !== $position || isset($claimed[$id]))) {
                continue;
            }
            $choices[$position] = array('label' => '', 'menus' => array($menu));
        }
        ksort($choices, SORT_NUMERIC);
        return array_values($choices);
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
