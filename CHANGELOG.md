# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- Layout selector with a new Tabbed layout for menus, including accessible tablist and keyboard support (render template, JS behavior, CSS styles).
- Category Display option to disable category toggling (always expanded); items are always visible and no toggle buttons are rendered (block view data, render template, JS binding).
- Nutrition Information toggle to show/hide per-item nutrition facts when available (ACF field, normalizer mapping, render template, CSS).
- Dietary Labels visibility toggle to show/hide item attribute labels like “vegetarian”, “gluten-free”, etc. (ACF field, render gating, refined tag extraction).
- Items per Row option to render items in 1 or 2 columns with responsive fallback (ACF field, render wrapper class, CSS grid).

### Changed
- Display all available menus by default when no `menu_name` is selected in the SinglePlatform block (`includes/class-sp-normalizer.php`, `blocks/singleplatform-menu/render.php`, `blocks/singleplatform-menu/style.css`).
- Simplified tab styling: default text color and a simple border-bottom on hover/active (`blocks/singleplatform-menu/style.css`).

### Removed
- Removed SinglePlatform attribution from the render output; `.sp-menu__attrib` is no longer rendered (`blocks/singleplatform-menu/render.php`).

## [0.3.0] - 2025-10-01

### Changed
- Switched the plugin localization domain to `sp-menu`, updating registration calls, block metadata, and strings throughout (`singleplatform-menu.php`, `blocks/singleplatform-menu/*`, `includes/class-sp-*`).
- Allowed fixture mode to satisfy the block without a Location ID by detecting the setting and supplying a sentinel cache key (`includes/class-sp-block.php`).

### Fixed
- Ensured all contexts bypass live API calls when fixture mode is enabled, returning explicit errors for missing fixture data (`includes/class-sp-client.php`).
- Accepted multiple fixture shapes and improved error messaging when decoding fails (`includes/class-sp-client.php`).
