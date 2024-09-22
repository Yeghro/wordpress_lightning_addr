<?php
/**
 * Plugin Name: LNURLP Nostr Zap Handler
 * Plugin URI: https://example.com/plugin-name
 * Description: Handles LNURL-P requests and Nostr Zaps for Lightning Addresses
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lnurlp-nostr-zap-handler
 * Domain Path: /languages
 */

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include the main class file
require_once plugin_dir_path(__FILE__) . 'includes/class-lnurlp-nostr-zap-handler.php';

function run_lnurlp_nostr_zap_handler() {
    $plugin = LNURLPNostrZapHandler::get_instance();
    $plugin->run();
}

// Initialize the plugin
add_action('plugins_loaded', 'run_lnurlp_nostr_zap_handler');

// Activation hook
register_activation_hook(__FILE__, 'activate_lnurlp_nostr_zap_handler');

function activate_lnurlp_nostr_zap_handler() {
    // Perform any necessary setup on activation
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'deactivate_lnurlp_nostr_zap_handler');

function deactivate_lnurlp_nostr_zap_handler() {
    // Perform any necessary cleanup on deactivation
}

function lnurlp_nostr_zap_handler_init() {
    $plugin = LNURLPNostrZapHandler::get_instance();
    $plugin->run();

    $settings_manager = new SettingsManager();
    add_action('admin_menu', array($settings_manager, 'add_admin_menu'));
    add_action('admin_init', array($settings_manager, 'register_settings'));
}

add_action('plugins_loaded', 'lnurlp_nostr_zap_handler_init');