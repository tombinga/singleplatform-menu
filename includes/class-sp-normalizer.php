<?php
namespace PRG\SinglePlatform;

if (!defined('ABSPATH')) { exit; }

class Normalizer {
    public static function to_view_model(array $menus, array $select = array()) {
        $menu_name = isset($select['menu_name']) ? (string) $select['menu_name'] : '';
        $category_whitelist = isset($select['category_filter']) && is_array($select['category_filter']) ? $select['category_filter'] : array();
        $currency_override = isset($select['currency_override']) ? (string) $select['currency_override'] : '';

        if ($menu_name !== '') {
            $match = self::find_menu($menus, $menu_name);
            if ($match === null) {
                $match = self::first_menu($menus);
            }
            $single = $match ? self::menu_to_view($match, $category_whitelist, $currency_override) : self::empty_menu_view();
            return array('menus' => $single ? array($single) : array());
        }

        $views = array();
        foreach ($menus as $menu) {
            if (!is_array($menu)) {
                continue;
            }
            $views[] = self::menu_to_view($menu, $category_whitelist, $currency_override);
        }

        if (empty($views)) {
            $views[] = self::empty_menu_view();
        }

        return array('menus' => $views);
    }

    private static function find_menu(array $menus, string $menu_name = '') {
        foreach ($menus as $menu) {
            if (!is_array($menu)) {
                continue;
            }
            if ($menu_name !== '' && isset($menu['name']) && strcasecmp($menu['name'], $menu_name) === 0) {
                return $menu;
            }
        }

        return null;
    }

    private static function first_menu(array $menus) {
        foreach ($menus as $menu) {
            if (is_array($menu)) {
                return $menu;
            }
        }

        return null;
    }

    private static function menu_to_view(array $menu, array $category_whitelist, string $currency_override) {
        $currency = $currency_override !== ''
            ? strtoupper(sanitize_text_field($currency_override))
            : (isset($menu['currency']) ? strtoupper((string) $menu['currency']) : 'USD');

        $categories = array();
        $sections = isset($menu['sections']) && is_array($menu['sections']) ? $menu['sections'] : array();
        foreach ($sections as $section) {
            $sec_name = isset($section['name']) ? sanitize_text_field((string) $section['name']) : '';
            if ($sec_name === '') {
                continue;
            }
            if (!empty($category_whitelist) && !in_array($sec_name, $category_whitelist, true)) {
                continue;
            }

            $items = array();
            $raw_items = isset($section['items']) && is_array($section['items']) ? $section['items'] : array();
            foreach ($raw_items as $it) {
                $items[] = array(
                    'name'      => isset($it['name']) ? sanitize_text_field((string) $it['name']) : '',
                    'desc'      => isset($it['description']) ? wp_kses_post((string) $it['description']) : '',
                    'price'     => self::first_price_min($it),
                    'market'    => self::is_market_price($it),
                    'currency'  => $currency,
                    'tags'      => self::attribute_tags($it),
                    'additions' => self::additions($it, $currency),
                    'nutrition' => self::nutrition_info($it),
                );
            }

            $categories[] = array(
                'id'    => isset($section['id']) ? sanitize_text_field((string) $section['id']) : '',
                'name'  => $sec_name,
                'items' => $items,
            );
        }

        $attrib = array(
            'img'  => isset($menu['secure_attribution_image']) ? esc_url_raw((string) $menu['secure_attribution_image'])
                                                               : (isset($menu['attribution_image']) ? esc_url_raw((string) $menu['attribution_image']) : ''),
            'href' => isset($menu['secure_attribution_image_link']) ? esc_url_raw((string) $menu['secure_attribution_image_link'])
                                                                     : (isset($menu['attribution_image_link']) ? esc_url_raw((string) $menu['attribution_image_link']) : ''),
        );

        return array(
            'locationName' => isset($menu['location_id']) ? sanitize_text_field((string) $menu['location_id']) : '',
            'menuName'     => isset($menu['name']) ? sanitize_text_field((string) $menu['name']) : '',
            'categories'   => $categories,
            'footnote'     => isset($menu['footnote']) ? wp_kses_post((string) $menu['footnote']) : '',
            'attribution'  => $attrib,
            'currency'     => $currency,
        );
    }

    private static function empty_menu_view() {
        return array(
            'locationName' => '',
            'menuName'     => '',
            'categories'   => array(),
            'footnote'     => '',
            'attribution'  => array(),
            'currency'     => 'USD',
        );
    }

    private static function first_price_min(array $item) {
        if (!isset($item['choices']) || !is_array($item['choices']) || empty($item['choices'])) return null;
        $c = $item['choices'][0];
        if (!isset($c['prices']['min'])) return null;
        $v = $c['prices']['min'];
        if ($v === '' || $v === null) return null;
        return (float) $v;
    }
    private static function is_market_price(array $item) : bool {
        if (!isset($item['choices'][0]['name'])) return false;
        return strtolower((string) $item['choices'][0]['name']) === 'mp';
    }
    private static function additions(array $item, string $currency) : array {
        if (empty($item['additions']) || !is_array($item['additions'])) return array();
        $out = array();
        foreach ($item['additions'] as $add) {
            $price = null;
            if (isset($add['prices']['min']) && $add['prices']['min'] !== '') {
                $price = (float) $add['prices']['min'];
            }
            $out[] = array('name' => isset($add['name']) ? sanitize_text_field((string) $add['name']) : '', 'price' => $price, 'currency' => $currency);
        }
        return $out;
    }
    private static function attribute_tags(array $item) : array {
        $tags = array();
        if (!isset($item['attributes']) || !is_array($item['attributes'])) return $tags;
        // Common nutrition keys to exclude if they appear under attributes
        $nutrition_keys = array(
            'calories','calories_from_fat','fat','total_fat','saturated_fat','trans_fat',
            'cholesterol','sodium','carbohydrates','total_carbohydrate','fiber','dietary_fiber',
            'sugars','sugar','protein','vitamin_a','vitamin_c','calcium','iron','potassium',
            'serving_size','servings','polyunsaturated_fat','monounsaturated_fat'
        );
        foreach ($item['attributes'] as $k => $v) {
            $key = sanitize_text_field((string) $k);
            if (in_array(strtolower($key), $nutrition_keys, true)) continue; // exclude nutrition-like keys
            // Only include boolean true (or the string 'true') as tags; ignore numeric/string values used by nutrition
            $is_true_bool = is_bool($v) && $v === true;
            $is_true_str  = is_string($v) && strtolower(trim($v)) === 'true';
            if ($is_true_bool || $is_true_str) {
                $tags[] = $key;
            }
        }
        return $tags;
    }

    private static function nutrition_info(array $item) : array {
        // Normalize to an array of ['label' => string, 'value' => string]
        $out = array();
        $source = null;
        if (isset($item['nutrition']) && is_array($item['nutrition'])) {
            $source = $item['nutrition'];
        } elseif (isset($item['nutritional_info']) && is_array($item['nutritional_info'])) {
            $source = $item['nutritional_info'];
        }
        if (!$source) return $out;

        // Case 1: already an array of objects with label/value
        $is_pair_list = false;
        foreach ($source as $entry) {
            if (is_array($entry) && (isset($entry['label']) || isset($entry['value']))) { $is_pair_list = true; break; }
        }
        if ($is_pair_list) {
            foreach ($source as $entry) {
                if (!is_array($entry)) continue;
                $label = isset($entry['label']) ? sanitize_text_field((string) $entry['label']) : '';
                $value = isset($entry['value']) ? sanitize_text_field((string) $entry['value']) : '';
                if ($label === '' && $value === '') continue;
                $out[] = array('label' => $label, 'value' => $value);
            }
            return $out;
        }

        // Case 2: associative array of key => value
        foreach ($source as $k => $v) {
            if (is_int($k)) continue; // skip numeric indexes if present
            $label = sanitize_text_field((string) $k);
            $value = sanitize_text_field(is_array($v) ? json_encode($v) : (string) $v);
            if ($label === '' && $value === '') continue;
            $out[] = array('label' => $label, 'value' => $value);
        }
        return $out;
    }
}
