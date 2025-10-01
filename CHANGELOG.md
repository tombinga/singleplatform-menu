# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [0.3.0] - 2025-10-01

### Changed
- Switched the plugin localization domain to `sp-menu`, updating registration calls, block metadata, and strings throughout (`singleplatform-menu.php`, `blocks/singleplatform-menu/*`, `includes/class-sp-*`).
- Allowed fixture mode to satisfy the block without a Location ID by detecting the setting and supplying a sentinel cache key (`includes/class-sp-block.php`).

### Fixed
- Ensured all contexts bypass live API calls when fixture mode is enabled, returning explicit errors for missing fixture data (`includes/class-sp-client.php`).
- Accepted multiple fixture shapes and improved error messaging when decoding fails (`includes/class-sp-client.php`).
