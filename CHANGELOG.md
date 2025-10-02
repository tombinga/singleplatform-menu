# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- Layout selector with a new Tabbed layout for menus, including accessible tablist and keyboard support (render template, JS behavior, CSS styles).
- Category Display option to disable category toggling (always expanded); items are always visible and no toggle buttons are rendered (block view data, render template, JS binding).

### Changed
- Display all available menus by default when no `menu_name` is selected in the SinglePlatform block (`includes/class-sp-normalizer.php`, `blocks/singleplatform-menu/render.php`, `blocks/singleplatform-menu/style.css`).

## [0.3.0] - 2025-10-01

### Changed
- Switched the plugin localization domain to `sp-menu`, updating registration calls, block metadata, and strings throughout (`singleplatform-menu.php`, `blocks/singleplatform-menu/*`, `includes/class-sp-*`).
- Allowed fixture mode to satisfy the block without a Location ID by detecting the setting and supplying a sentinel cache key (`includes/class-sp-block.php`).

### Fixed
- Ensured all contexts bypass live API calls when fixture mode is enabled, returning explicit errors for missing fixture data (`includes/class-sp-client.php`).
- Accepted multiple fixture shapes and improved error messaging when decoding fails (`includes/class-sp-client.php`).
