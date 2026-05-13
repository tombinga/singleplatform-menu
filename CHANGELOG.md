# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [0.5.0] - 2026-05-12

### Added

- Added a native Gutenberg dynamic block implementation for the SinglePlatform menu block, including `block.json`, editor controls, generated build assets, and server-side rendering via `register_block_type()`.
- Added Gutenberg block support for highlighting selected menu items as special, featured, or new.

### Changed

- Converted the menu block from an ACF block to a native Gutenberg block and removed the ACF block registration flow.
- Updated block attributes and editor controls to manage Location ID, Menu Name, category filtering, highlighted items, display options, details visibility, and cache TTL directly in the block sidebar.
- Updated the editor menu endpoint to return category options and grouped item options for Gutenberg controls.
- Updated the editor menu endpoint to refresh stale snapshots before falling back to live API data.
- Updated plugin and block metadata versions to `0.5.0`.

### Fixed

- Restored category filtering in the Gutenberg block render path, cache key generation, and normalization flow.

## [0.4.1] - 2025-10-15

### Fixed

- Fixed tab navigation in the menu block.

## Changed

- Updated block render to use `<div>` instead of `<article>` for menu groups.

## [0.4.0] - 2025-10-11

### Added

- Snapshot (durable cache) feature to serve menus from a local, last-good snapshot stored in the database (`includes/class-sp-snapshot.php`).
- Admin settings for Snapshot: enable/disable, TTL, Locations to Sync, Cron frequency, and a "Sync now" button (`includes/class-sp-settings.php`).
- Cron scheduling and activation hooks to install the snapshot table and refresh snapshots on an interval (`singleplatform-menu.php`).

### Changed

- Block render now consults snapshots on cache miss when enabled and persists fresh API results to snapshot (`includes/class-sp-block.php`).
- Plugin version bumped to `0.4.0`.

### Notes

- Disable Snapshot to restore previous behavior with no changes to output.

## [0.3.4] - 2025-10-04

### Changed

- Improved price formatting to omit decimals for whole amounts (includes/class-sp-block.php).

## [0.3.3] - 2025-10-04

### Added

- Integrated Yahnis Elsts’s plugin-update-checker library to enable auto-update functionality for the SinglePlatform Menu plugin. Added guarded loader and admin-only initialization in a new Updates class, updated main plugin file to initialize updates, and vendored the plugin-update-checker library and its dependencies.

## [0.3.2] - 2025-10-02

### Added

- Added options to show/hide per-item nutrition facts and dietary labels in the SinglePlatform menu block. Adds support for rendering nutrition information, controlling label visibility, and selecting items per row (1 or 2 columns) with corresponding CSS grid styles. Removes SinglePlatform attribution from the render output and refines tag extraction to exclude nutrition keys.

## [0.3.1] - 2025-10-02

### Added

- Introduced a new 'Layout' field to the ACF block, allowing users to choose between 'accordion' and 'tabs' layouts for menu display. Updates PHP rendering logic, styles, and JavaScript to support accessible tab navigation. Removes ACF JSON save path filter to restrict JSON changes to admin sync only.

## [0.3.0] - 2025-10-01

### Changed

- Switched the plugin localization domain to `sp-menu`, updating registration calls, block metadata, and strings throughout (`singleplatform-menu.php`, `blocks/singleplatform-menu/*`, `includes/class-sp-*`).
- Allowed fixture mode to satisfy the block without a Location ID by detecting the setting and supplying a sentinel cache key (`includes/class-sp-block.php`).

### Fixed

- Ensured all contexts bypass live API calls when fixture mode is enabled, returning explicit errors for missing fixture data (`includes/class-sp-client.php`).
- Accepted multiple fixture shapes and improved error messaging when decoding fails (`includes/class-sp-client.php`).
