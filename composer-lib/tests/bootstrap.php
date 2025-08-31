<?php

/**
 * Bootstrap file for PHPUnit tests
 * 
 * @author KS Fraser <kevin@ksfraser.com>
 * @since 1.0.0
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Define test constants
if (!defined('TB_PREF')) {
    define('TB_PREF', '0_');
}

// Mock global functions that might not be available during testing
if (!function_exists('display_notification')) {
    /**
     * Mock FrontAccounting display_notification function
     * 
     * @param string $message Message to display
     * @return void
     */
    function display_notification($message)
    {
        // In tests, we might want to collect these for assertion
        echo "NOTIFICATION: " . $message . "\n";
    }
}

if (!function_exists('html_entity_decode')) {
    /**
     * Mock html_entity_decode if not available
     * 
     * @param string $string String to decode
     * @return string Decoded string
     */
    function html_entity_decode($string)
    {
        return $string;
    }
}
