<?php
// Run with php tests/menu-groups.php. Stub WordPress to exercise real plugin rendering.
define('ABSPATH', __DIR__);
define('PRG_SP_MENU_DIR', dirname(__DIR__) . '/');
define('ARRAY_A', 'ARRAY_A');
function sanitize_text_field($text) { return trim(strip_tags($text)); }
function sanitize_html_class($text) { return preg_replace('/[^a-z0-9_-]/i', '', $text); }
function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES); }
function esc_attr($text) { return esc_html($text); }
function __($text, $domain) { return $text; }
function esc_html__($text, $domain) { return esc_html($text); }
function esc_attr__($text, $domain) { return esc_attr($text); }
function wp_kses_post($text) { return $text; }
function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_json_encode($value) { return json_encode($value); }
function number_format_i18n($value, $decimals) { return number_format($value, $decimals); }
function current_time($format) { return '2026-11-07 19:00:00'; }
function update_option($name, $value, $autoload) { $GLOBALS['options'][$name] = $value; }
function trailingslashit($text) { return rtrim($text, '/') . '/'; }
class WP_Error {
    public function __construct($code, private $message) {}
    public function get_error_message() { return $this->message; }
}
class WP_REST_Request {
    public function get_param($key) { return 'test-location'; }
}
class WP_REST_Response {
    public function __construct(public $data, public $status) {}
}

foreach (array('helpers-sanitize', 'class-sp-settings', 'class-sp-cache', 'class-sp-client', 'class-sp-normalizer', 'class-sp-block', 'class-sp-rest', 'class-sp-snapshot') as $file) {
    require PRG_SP_MENU_DIR . 'includes/' . $file . '.php';
}

use PRG\SinglePlatform\Block;
use PRG\SinglePlatform\Cache;
use PRG\SinglePlatform\Rest;
use PRG\SinglePlatform\Snapshot;

function menu($id, $name, $category, $price, $item = 'Shared dish') {
    return array('id' => $id, 'name' => $name, 'sections' => array(array(
        'name' => $category,
        'items' => array(array('name' => $item, 'choices' => array(array('prices' => array('min' => $price))))),
    )));
}

function expect($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$options = array('prg_sp_use_fixture' => true, 'prg_sp_fixture_json' => json_encode(array('menus' => array(
    menu(1, 'Weekday', 'Mains', 21), menu(2, 'Drinks', 'Beverages', 8), menu('3', 'Weekend', 'Starters', 34),
))));
$attributes = array(
    'layout' => 'tabs',
    'enable_menu_groups' => true,
    'menu_groups' => array('location_id' => '', 'groups' => array(
        array('label' => 'Brunch', 'members' => array(
            array('menu_id' => 'missing'), array('menu_id' => '3', 'label' => 'Weekend service'), array('menu_id' => '1'),
        )),
        array('label' => 'Duplicate', 'members' => array(array('menu_id' => '1'))),
    )),
);

$html = Block::render($attributes);
expect(substr_count($html, 'role="tablist"') === 2, 'Expected a top-level and member tab list.');
expect(strpos($html, 'Brunch') < strpos($html, 'Drinks'), 'Expected group at its earliest source member.');
expect(preg_match('/aria-selected="true"[^>]*>\s*Weekend service/', $html), 'Expected the first available member active.');
expect(strpos($html, 'Duplicate') === false, 'Expected duplicate assignment to be omitted.');
expect(strpos($html, '$21') !== false && strpos($html, '$34') !== false, 'Expected each service price.');
expect(strpos($html, 'Mains') !== false && strpos($html, 'Starters') !== false, 'Expected each service section.');
$catalog = Rest::get_menu_for_editor(new WP_REST_Request())->data;
expect(array_column($catalog['menus'], 'id') === array('1', '2', '3'), 'Expected string menu IDs from fixture discovery.');
expect(count($catalog['items']) === 3 && count($catalog['categories']) === 3, 'Expected legacy editor lookup fields.');

$attributes['show_prices'] = false;
expect(strpos(Block::render($attributes), 'class="sp-menu__price"') === false, 'Expected no price markup.');
$attributes['enable_menu_groups'] = false;
$attributes['menu_name'] = 'unknown';
$legacy = Block::render($attributes);
expect(strpos($legacy, 'Weekday') !== false && strpos($legacy, 'Weekend') === false, 'Expected legacy name fallback.');

$diors = array(
    menu('fixture-lunch', 'Dining Room - Lunch', 'Mains', 41, 'Ravioles Brodées'),
    menu('fixture-dinner', 'Dining Room - Dinner', 'Starters', 38, 'Ravioles Brodées'),
);
$diors[0]['sections'][0]['items'][0]['additions'] = array(array('name' => 'Supplement', 'prices' => array('min' => 10)));
$diors[1]['sections'][0]['items'][] = array('name' => 'Market special', 'choices' => array(array('name' => 'mp')));
foreach (array('Lounge', 'Cocktails', 'Wine', 'Tasting Menu', 'Vegetarian Tasting Menu', 'Afternoon Tea') as $index => $name) {
    $diors[] = menu('fixture-' . $index, $name, 'Menu', 20);
}
$options['prg_sp_fixture_json'] = json_encode(array('menus' => $diors));
$dior_attributes = array(
    'enable_menu_groups' => true,
    'show_prices' => false,
    'menu_groups' => array('location_id' => '', 'groups' => array(
        array('label' => 'Dining Room', 'members' => array(
            array('menu_id' => 'fixture-lunch', 'label' => 'Lunch'),
            array('menu_id' => 'fixture-dinner', 'label' => 'Dinner'),
        )),
    )),
);
$dior = Block::render($dior_attributes);
expect(substr_count($dior, 'role="tab"') === 9, 'Expected seven top-level choices and two service tabs.');
expect(preg_match('/aria-selected="true"[^>]*>\s*Lunch/', $dior), 'Expected Lunch active initially.');
expect(preg_match('/>\s*Mains\s*<\/button>.*?Ravioles Brodées.*?>\s*Starters\s*<\/button>.*?Ravioles Brodées/s', $dior),
    'Expected each Dining Room service to retain its section and item.');
expect(strpos($dior, 'class="sp-menu__price"') === false, 'Expected no prices in any Dior panel.');
expect(strpos($dior, 'sp-menu__addition-price') === false && strpos($dior, 'Market special') !== false, 'Expected price suppression for additions and market items.');
$priced_dior = Block::render(array_replace($dior_attributes, array('show_prices' => true)));
expect(strpos($priced_dior, 'sp-menu__addition-price') !== false && preg_match('/>\s*MP\s*<\/span>/', $priced_dior), 'Expected addition and market pricing when enabled.');
Snapshot::sync_once('test-location'); // Fixture mode must work without a snapshot database.

$options['prg_sp_fixture_json'] = '{"menus":[]}';
$empty = Block::render($dior_attributes);
expect(strpos($empty, 'No menus available') !== false && strpos($empty, 'role="tab"') === false, 'Expected no phantom tab for an empty catalog.');

$options['prg_sp_use_fixture'] = false;
$wpdb = new class {
    public $prefix = 'test_';
    public $row = array('last_success' => '2026-10-01 00:00:00');
    public function prepare($sql, $location) { return $sql; }
    public function get_row($sql, $mode) { return $this->row; }
    public function update($table, $values, $where, $formats, $where_formats) {
        $this->row = array_replace($this->row, $values);
    }
};
$wpdb->row['payload'] = json_encode(array('data' => array('menus' => $diors)));
$dior_attributes['location_id'] = 'test-location';
$dior_attributes['menu_groups']['location_id'] = 'test-location';
$legacy_key = Cache::key('test-location', array('menu_name' => '', 'category_filter' => array(), 'show_prices' => false, 'currency' => 'USD'));
$transients[$legacy_key] = array('menus' => array(array('menuName' => 'Legacy transient')));
$fallback = Block::render($dior_attributes);
expect(strpos($fallback, 'Lunch') !== false && strpos($fallback, 'Legacy transient') === false, 'Expected last-good snapshot instead of an ID-less transient.');
expect($wpdb->row['status'] === 'error' && Snapshot::get_payload('test-location') === $diors, 'Expected refresh error to preserve the last-good payload.');
$dior_attributes['menu_groups']['groups'][0]['label'] = 'Updated Dining Room';
$wpdb = null; // Warm cache must serve a new label without reading the snapshot.
expect(strpos(Block::render($dior_attributes), 'Updated Dining Room') !== false, 'Expected grouping configuration applied after the cache read.');
$dior_attributes['menu_groups']['location_id'] = 'another-location';
expect(strpos(Block::render($dior_attributes), 'Updated Dining Room') === false, 'Expected stale grouping configuration to be ignored.');

if (isset($argv[1])) {
    file_put_contents($argv[1], '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><link rel="stylesheet" href="../blocks/singleplatform-menu/style.css">' .
        $html . $dior . '<script src="../build/view.js"></script></html>');
}

echo "Menu group rendering checks passed.\n";
