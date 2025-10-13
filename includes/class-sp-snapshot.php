<?php
namespace PRG\SinglePlatform;

if (!defined('ABSPATH')) { exit; }

class Snapshot {
    const TABLE = 'prg_sp_snapshots';

    public static function install() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            location_id VARCHAR(191) NOT NULL,
            etag VARCHAR(191) NULL,
            payload LONGTEXT NULL,
            last_success DATETIME NULL,
            last_attempt DATETIME NULL,
            status VARCHAR(32) NULL,
            error_excerpt TEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY location (location_id)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function get_row($location_id) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE location_id = %s", $location_id), ARRAY_A);
    }

    public static function get_payload($location_id) {
        $row = self::get_row($location_id);
        if (!$row || empty($row['payload'])) return null;
        $json = json_decode($row['payload'], true);
        if (!is_array($json)) return null;
        if (isset($json['data']['menus']) && is_array($json['data']['menus'])) return $json['data']['menus'];
        return null;
    }

    public static function upsert($location_id, array $decoded, $etag = null, $status = 'ok', $error_excerpt = '') {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $payload = wp_json_encode($decoded);
        $now = current_time('mysql');
        $existing = self::get_row($location_id);
        if ($existing) {
            $wpdb->update(
                $table,
                array(
                    'etag' => $etag,
                    'payload' => $payload,
                    'last_success' => $status === 'ok' ? $now : $existing['last_success'],
                    'last_attempt' => $now,
                    'status' => $status,
                    'error_excerpt' => $error_excerpt,
                ),
                array('location_id' => $location_id),
                array('%s','%s','%s','%s','%s','%s'),
                array('%s')
            );
        } else {
            $wpdb->insert(
                $table,
                array(
                    'location_id' => $location_id,
                    'etag' => $etag,
                    'payload' => $payload,
                    'last_success' => $status === 'ok' ? $now : null,
                    'last_attempt' => $now,
                    'status' => $status,
                    'error_excerpt' => $error_excerpt,
                ),
                array('%s','%s','%s','%s','%s','%s','%s')
            );
        }
    }

    public static function is_stale($location_id, $ttl_seconds) {
        $row = self::get_row($location_id);
        if (!$row || empty($row['last_success'])) return true;
        $last = strtotime($row['last_success']);
        return ($last + max(60, (int) $ttl_seconds)) <= time();
    }

    public static function sync_once($location_id) {
        $resp = Client::get_menus($location_id);
        if (is_wp_error($resp)) {
            $excerpt = sanitize_text_field($resp->get_error_message());
            self::upsert($location_id, array('code' => 0, 'data' => array()), null, 'error', $excerpt);
            return $resp;
        }
        $enveloped = array('code' => 200, 'data' => array('menus' => $resp));
        self::upsert($location_id, $enveloped, null, 'ok', '');
        return $resp;
    }

    public static function sync_many(array $location_ids) {
        foreach ($location_ids as $loc) {
            $loc = trim((string) $loc);
            if ($loc === '') continue;
            self::sync_once($loc);
        }
    }
}
