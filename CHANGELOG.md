# Changelog

## 2025-10-01

- **Text domain rename**: Switched the plugin localization domain to `sp-menu`, updating registration calls, block metadata, and strings throughout (`singleplatform-menu.php`, `blocks/singleplatform-menu/*`, `includes/class-sp-*`).
- **Fixture override fallback**: Allowed fixture mode to satisfy the block without a Location ID by detecting the setting and supplying a sentinel key to caching (`includes/class-sp-block.php`).
- **Fixture request short-circuit**: Ensured all contexts bypass live API calls when fixture mode is enabled, returning explicit errors for missing fixture data (`includes/class-sp-client.php`).
- **Fixture JSON parsing improvements**: Accepted multiple fixture shapes and improved error messaging when decoding fails (`includes/class-sp-client.php`).
