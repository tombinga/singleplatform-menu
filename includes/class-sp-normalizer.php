<?php
namespace PRG\SinglePlatform;

if (!defined('ABSPATH')) { exit; }

class Normalizer {
    public static function to_view_model(array $menus, array $select = array()) {
        $menu_name = isset($select['menu_name']) ? (string) $select['menu_name'] : '';
        $category_whitelist = isset($select['category_filter']) && is_array($select['category_filter']) ? $select['category_filter'] : array();
        $currency_override = isset($select['currency_override']) ? (string) $select['currency_override'] : '';

        $menu = null;
        foreach ($menus as $m) {
            if (!is_array($m)) continue;
            if ($menu_name !== '' && isset($m['name']) && strcasecmp($m['name'], $menu_name) == 0) { $menu = $m; break; }
            if ($menu === null) $menu = $m;
        }
        if (!$menu) {
            return array('locationName' => '', 'menuName' => '', 'categories' => array(), 'footnote' => '', 'attribution' => array(), 'currency' => 'USD');
        }

        $currency = $currency_override !== '' ? strtoupper(sanitize_text_field($currency_override))
                                              : (isset($menu['currency']) ? strtoupper((string) $menu['currency']) : 'USD');

        $categories = array();
        $sections = isset($menu['sections']) && is_array($menu['sections']) ? $menu['sections'] : array();
        foreach ($sections as $section) {
            $sec_name = isset($section['name']) ? sanitize_text_field((string) $section['name']) : '';
            if ($sec_name === '') continue;
            if (!empty($category_whitelist) && !in_array($sec_name, $category_whitelist, true)) continue;

            $items = array();
            $raw_items = isset($section['items']) && is_array($section['items']) ? $section['items'] : array();
            foreach ($raw_items as $it) {
                $items[] = array(
                    'name'     => isset($it['name']) ? sanitize_text_field((string) $it['name']) : '',
                    'desc'     => isset($it['description']) ? wp_kses_post((string) $it['description']) : '',
                    'price'    => self::first_price_min($it),
                    'market'   => self::is_market_price($it),
                    'currency' => $currency,
                    'tags'     => self::attribute_tags($it),
                    'additions'=> self::additions($it, $currency),
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
        foreach ($item['attributes'] as $k => $v) {
            if ($v) $tags[] = sanitize_text_field((string) $k);
        }
        return $tags;
    }
}
