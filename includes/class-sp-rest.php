<?php
namespace PRG\SinglePlatform;

if (!defined('ABSPATH')) {
    exit;
}

class Rest
{
    public static function init()
    {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }
    public static function register_routes()
    {
        register_rest_route('prg-sp/v1', '/cache/purge', array(
            'methods' => 'POST',
            'permission_callback' => array(__CLASS__, 'can_edit'),
            'callback' => array(__CLASS__, 'purge'),
            'args' => array('location_id' => array('required' => true, 'type' => 'string'))
        ));

        // New route for fetching menus for the block editor
        register_rest_route('prg-sp/v1', '/menus/(?P<location_id>[\w.-]+)', array(
            'methods' => 'GET',
            'permission_callback' => array(__CLASS__, 'can_edit'),
            'callback' => array(__CLASS__, 'get_menu_for_editor'),
            'args' => array(
                'location_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'SinglePlatform Location ID.',
                ),
            ),
        ));
    }

    public static function can_edit()
    {
        return current_user_can('edit_posts');
    }

    public static function purge(\WP_REST_Request $req)
    {
        $loc = sanitize_text_field((string) $req->get_param('location_id'));
        $count = Cache::delete_by_location($loc);
        return new \WP_REST_Response(array('purged' => true, 'count' => (int) $count), 200);
    }

    public static function get_menu_for_editor(\WP_REST_Request $req)
    {
        $location_id = sanitize_text_field((string) $req->get_param('location_id'));
        if (empty($location_id)) {
            return new \WP_REST_Response(array('error' => 'Location ID cannot be empty.'), 400);
        }

        // Use the snapshot for speed in the editor, but fall back to a live call if needed.
        $menus = Snapshot::get_payload($location_id);

        if ($menus === null) {
            $menus = Client::get_menus($location_id);
        }

        if (is_wp_error($menus)) {
            return new \WP_REST_Response(array(
                'error' => 'API Error',
                'message' => $menus->get_error_message()
            ), 404);
        }

        // Format the data specifically for an ACF select field with optgroups
        $formatted_data = array();
        foreach ($menus as $menu) {
            if (!isset($menu['sections']) || !is_array($menu['sections']))
                continue;

            foreach ($menu['sections'] as $section) {
                if (!isset($section['name']) || empty($section['name']))
                    continue;
                $category_name = (string) $section['name'];

                $group = array(
                    'text' => $category_name, // This will be the <optgroup> label
                    'children' => array()
                );

                if (isset($section['items']) && is_array($section['items'])) {
                    foreach ($section['items'] as $item) {
                        if (!isset($item['name']) || empty($item['name']))
                            continue;
                        $item_name = (string) $item['name'];

                        $group['children'][] = array(
                            'id' => $category_name . '::' . $item_name, // The unique value
                            'text' => $item_name                      // The display text
                        );
                    }
                }

                if (!empty($group['children'])) {
                    $formatted_data[] = $group;
                }
            }
        }

        return new \WP_REST_Response($formatted_data, 200);
    }
}