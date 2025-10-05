<?php
namespace PRG\SinglePlatform;

if (!defined('ABSPATH')) {
    exit;
}

class Updates
{
    public static function init(): void
    {
        // Only load in admin to avoid front-end overhead
        if (!is_admin()) {
            return;
        }

        // Resolve repo URL: use constant override if defined, otherwise default to public repo
        $repo = defined('PRG_SP_MENU_UPDATE_REPO') ? PRG_SP_MENU_UPDATE_REPO : 'https://github.com/tombinga/singleplatform-menu/';
        if (function_exists('apply_filters')) {
            $repo = (string) apply_filters('prg_sp_menu_update_repo', $repo);
        }
        if (empty($repo)) {
            return; // If disabled via filter, skip silently
        }

        // Load PUC library if not already loaded
        if (!class_exists('\\Puc_v4_Factory')) {
            $lib = PRG_SP_MENU_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
            if (file_exists($lib)) {
                require_once $lib;
            } else {
                return; // Library not present; skip
            }
        }

        $pluginFile = PRG_SP_MENU_DIR . 'singleplatform-menu.php';
        // Slug is used internally by PUC; keep stable
        $slug = 'singleplatform-menu';

        // Build update checker via dynamic call to avoid static analyzer errors
        $factoryClass = '\\Puc_v4_Factory';
        try {
            $updateChecker = is_callable([$factoryClass, 'buildUpdateChecker'])
                ? call_user_func([$factoryClass, 'buildUpdateChecker'], $repo, $pluginFile, $slug)
                : null;
        } catch (\Throwable $e) {
            $updateChecker = null;
        }
        if (!$updateChecker) {
            return;
        }

        // Branch (default main)
        $branch = defined('PRG_SP_MENU_UPDATE_BRANCH') ? PRG_SP_MENU_UPDATE_BRANCH : 'main';
        if (method_exists($updateChecker, 'setBranch')) {
            try {
                $updateChecker->setBranch($branch);
            } catch (\Throwable $e) {
            }
        }

        // Optional token for private repo
        $token = null;
        if (defined('PRG_SP_MENU_UPDATE_TOKEN') && PRG_SP_MENU_UPDATE_TOKEN) {
            $token = PRG_SP_MENU_UPDATE_TOKEN;
        } elseif (!empty(getenv('PRG_SP_MENU_UPDATE_TOKEN'))) {
            $token = getenv('PRG_SP_MENU_UPDATE_TOKEN');
        }
        if (!empty($token) && method_exists($updateChecker, 'setAuthentication')) {
            try {
                $updateChecker->setAuthentication($token);
            } catch (\Throwable $e) {
            }
        }

        // Prefer release assets when available
        if (method_exists($updateChecker, 'getVcsApi')) {
            $api = $updateChecker->getVcsApi();
            if ($api && method_exists($api, 'enableReleaseAssets')) {
                try {
                    $api->enableReleaseAssets();
                } catch (\Throwable $e) {
                }
            }
        }
    }
}
