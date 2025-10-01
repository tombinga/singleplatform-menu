<?php
/**
 * ACF Block Template for SinglePlatform Menu
 * 
 * @var array $block The block settings and attributes.
 * @var string $content The block inner HTML (empty).
 * @var bool $is_preview True during AJAX preview.
 * @var int|string $post_id The post ID this block is saved to.
 */

if (!defined('ABSPATH')) { exit; }

// Call the Block class render method
echo \PRG\SinglePlatform\Block::render($block, $content, $is_preview, $post_id);
