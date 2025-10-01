<?php
namespace PRG\SinglePlatform;

if (!defined('ABSPATH')) {
    exit;
}

class Client
{
    public static function get_menus($location_id)
    {
        // Fixture mode (admins only) to debug rendering without remote calls.
        if (is_admin() && current_user_can('manage_options') && Settings::use_fixture()) {
            $raw = Settings::fixture_json();
            $json = json_decode($raw, true);
            if (is_array($json) && isset($json['data']['menus']) && is_array($json['data']['menus'])) {
                return $json['data']['menus'];
            }
        }

        $base = trailingslashit(Settings::api_base());
        $api_key = Settings::api_key();
        if (!$api_key) {
            return new \WP_Error('sp_no_api_key', __('Missing API key. Configure it in Settings → SinglePlatform.', 'sp-menu'));
        }

        $endpoint = $base . 'v1/locations/' . rawurlencode($location_id);

        $args = array(
            'headers' => array(
                'Authorization' => $api_key,
                'Accept' => 'application/json',
            ),
            'timeout' => 10,
            'redirection' => 3,
        );

        $resp = wp_remote_get($endpoint, $args);
        if (is_wp_error($resp)) {
            return $resp;
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        if ($code < 200 || $code >= 300) {
            return new \WP_Error('sp_http_' . $code, sprintf(__('HTTP %d from SinglePlatform', 'sp-menu'), $code));
        }

        $json = json_decode($body, true);
        if (!is_array($json) || !isset($json['data']['menus']) || !is_array($json['data']['menus'])) {
            return new \WP_Error('sp_bad_json', __('Invalid JSON from SinglePlatform', 'sp-menu'));
        }
        return $json['data']['menus'];
    }
}
