<?php
/**
 * Plugin Name: LNURLP Nostr Zap Handler
 * Description: Handles LNURL-P requests and Nostr Zaps for Lightning Addresses
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') or die('Direct access not allowed');

// At the top of the file
if (!function_exists('write_log')) {
    function write_log($log) {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }
}

// Load required files
write_log('Loading file: ' . plugin_dir_path(__FILE__) . 'includes/class-lnurlp-nostr-zap-handler.php');
require_once plugin_dir_path(__FILE__) . 'includes/class-lnurlp-nostr-zap-handler.php';
write_log('Loading file: ' . plugin_dir_path(__FILE__) . 'includes/class-zap-request-handler.php');
require_once plugin_dir_path(__FILE__) . 'includes/class-zap-request-handler.php';
write_log('Loading file: ' . plugin_dir_path(__FILE__) . 'includes/class-invoice-generator.php');
require_once plugin_dir_path(__FILE__) . 'includes/class-invoice-generator.php';
write_log('Loading file: ' . plugin_dir_path(__FILE__) . 'includes/class-webhook-handler.php');
require_once plugin_dir_path(__FILE__) . 'includes/class-webhook-handler.php';
write_log('Loading file: ' . plugin_dir_path(__FILE__) . 'includes/class-nostr-event-handler.php');
require_once plugin_dir_path(__FILE__) . 'includes/class-nostr-event-handler.php';
write_log('Loading file: ' . plugin_dir_path(__FILE__) . 'includes/class-settings-manager.php');
require_once plugin_dir_path(__FILE__) . 'includes/class-settings-manager.php';
write_log('Loading file: ' . plugin_dir_path(__FILE__) . 'includes/DatabaseManager.php');
require_once plugin_dir_path(__FILE__) . 'includes/DatabaseManager.php';
write_log('Loading file: ' . plugin_dir_path(__FILE__) . 'includes/logger.php');
require_once plugin_dir_path(__FILE__) . 'includes/logger.php';
write_log('Loading file: ' . plugin_dir_path(__FILE__) . 'vendor/autoload.php');
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

// Initialize the main plugin class
function run_lnurlp_nostr_zap_handler() {
    try {
        $plugin = LNURLPNostrZapHandler::get_instance();
        $plugin->init();
        $plugin->run();
    } catch (Exception $e) {
        // Log the error
        error_log('LNURLP Nostr Zap Handler initialization failed: ' . $e->getMessage());
        
        // Optionally, deactivate the plugin
        deactivate_plugins(plugin_basename(__FILE__));
        
        // Add an admin notice
        add_action('admin_notices', function() use ($e) {
            $class = 'notice notice-error';
            $message = sprintf('LNURLP Nostr Zap Handler plugin could not be activated: %s', $e->getMessage());
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
        });
    }
}

run_lnurlp_nostr_zap_handler();

// Activation hook
register_activation_hook(__FILE__, 'lnurlp_nostr_zap_handler_activate');

function lnurlp_nostr_zap_handler_activate() {
    write_log('Attempting to activate LNURLP Nostr Zap Handler plugin');
    
    // Ensure DatabaseManager class is loaded
    require_once plugin_dir_path(__FILE__) . 'includes/DatabaseManager.php';
    
    $database_manager = new DatabaseManager();
    $result = $database_manager->create_tables();
    
    if (is_wp_error($result)) {
        wp_die('Failed to create necessary database tables. Error: ' . $result->get_error_message());
    }
    
    // Add any other activation logic here
    
    write_log('LNURLP Nostr Zap Handler plugin activated successfully');
}

// Add a deactivation hook if needed
register_deactivation_hook(__FILE__, 'lnurlp_nostr_zap_handler_deactivate');

function lnurlp_nostr_zap_handler_deactivate() {
    // Add any cleanup logic here
}