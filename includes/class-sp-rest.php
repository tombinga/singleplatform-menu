<?php
namespace PRG\SinglePlatform;

if (!defined('ABSPATH')) { exit; }

class Rest {
    public static function init() { add_action('rest_api_init', array(__CLASS__, 'register_routes')); }
    public static function register_routes() {
        register_rest_route('prg-sp/v1', '/cache/purge', array(
            'methods' => 'POST',
            'permission_callback' => array(__CLASS__, 'can_purge'),
            'callback' => array(__CLASS__, 'purge'),
            'args' => array('location_id' => array('required' => true, 'type' => 'string'))
        ));
    }
    public static function can_purge() { return current_user_can('edit_posts'); }
    public static function purge(\WP_REST_Request $req) {
        $loc = sanitize_text_field((string) $req->get_param('location_id'));
        $count = Cache::delete_by_location($loc);
        return new \WP_REST_Response(array('purged' => true, 'count' => (int) $count), 200);
    }
}
