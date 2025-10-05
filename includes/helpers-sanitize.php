<?php
namespace PRG\SinglePlatform;

if (!defined('ABSPATH')) { exit; }

function format_price($number, $currency) {
    $number = is_numeric($number) ? (float) $number : 0.0;
    $symbol = currency_symbol($currency);
    // Round to 2 for consistency, then drop decimals if it's effectively a whole amount.
    $rounded = round($number, 2);
    $is_whole = abs($rounded - floor($rounded)) < 0.005; // guard against float precision
    $decimals = $is_whole ? 0 : 2;
    return $symbol . number_format_i18n($rounded, $decimals);
}

function currency_symbol($code) {
    $code = strtoupper(sanitize_text_field((string) $code));
    switch ($code) {
        case 'USD': return '$';
        case 'EUR': return '€';
        case 'GBP': return '£';
        case 'JPY': return '¥';
        default: return '$';
    }
}

function sanitize_bool($val) { return (bool) $val; }

function sanitize_ttl($val) {
    $n = (int) $val;
    if ($n < 60) $n = 60;
    if ($n > 86400) $n = 86400;
    return $n;
}
