<?php
namespace PRG\SinglePlatform;

if (!defined('ABSPATH')) { exit; }

class Cache {
    const INDEX_OPTION = 'prg_sp_cache_index';

    public static function key($location_id, array $flags = array()) {
        ksort($flags);
        return 'sp_menu_' . md5($location_id . '|' . wp_json_encode($flags));
    }
    public static function get($key) { return get_transient($key); }
    public static function set($key, $data, $ttl) { set_transient($key, $data, $ttl); }
    public static function delete_by_location($location_id) {
        $index = get_option(self::INDEX_OPTION, array());
        $keys = isset($index[$location_id]) && is_array($index[$location_id]) ? $index[$location_id] : array();
        $count = 0;
        foreach ($keys as $k) { if (delete_transient($k)) $count++; }
        $index[$location_id] = array();
        update_option(self::INDEX_OPTION, $index, false);
        return $count;
    }
    public static function index_key($location_id, $key) {
        $index = get_option(self::INDEX_OPTION, array());
        if (!isset($index[$location_id]) || !is_array($index[$location_id])) $index[$location_id] = array();
        if (!in_array($key, $index[$location_id], true)) {
            $index[$location_id][] = $key;
            update_option(self::INDEX_OPTION, $index, false);
        }
    }
}
