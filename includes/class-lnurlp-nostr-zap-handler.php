<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('LNURLPNostrZapHandler')) {
    class LNURLPNostrZapHandler {
        private static $instance = null;
        
        // Declare these properties
        private $zap_request_handler;
        private $invoice_generator;
        private $webhook_handler;
        private $nostr_event_handler;
        private $settings_manager;
        
        private function __construct() {
            $this->load_dependencies();
        }
        
        private function __clone() {}
        
        public function __wakeup() {}
        
        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function load_dependencies() {
            $plugin_dir = plugin_dir_path(dirname(__FILE__));
            require_once $plugin_dir . 'vendor/autoload.php';
            require_once $plugin_dir . 'includes/class-zap-request-handler.php';
            require_once $plugin_dir . 'includes/class-invoice-generator.php';
            require_once $plugin_dir . 'includes/class-webhook-handler.php';
            require_once $plugin_dir . 'includes/class-nostr-event-handler.php';
            require_once $plugin_dir . 'includes/class-settings-manager.php';
            require_once $plugin_dir . 'includes/logger.php';

            $this->zap_request_handler = new ZapRequestHandler();
            $this->invoice_generator = new InvoiceGenerator();
            $this->webhook_handler = new WebhookHandler();
            $this->nostr_event_handler = new NostrEventHandler();
            $this->settings_manager = new SettingsManager();
        }

        public function run() {
            $this->load_settings();
            $this->setup_hooks();
            do_action('lnurlp_nostr_zap_handler_started');
            Logger::log('LNURLP Nostr Zap Handler plugin started');
        }

        private function load_settings() {
            $settings = $this->settings_manager->get_all_settings();
            $this->invoice_generator->set_api_credentials($settings['lnbits_api_url'], $settings['lnbits_api_key']);
            $this->nostr_event_handler->set_keys($settings['nostr_private_key'], $settings['nostr_public_key']);
            $this->nostr_event_handler->set_relays($settings['nostr_relays']);
        }

        private function setup_hooks() {
            add_action('wp_ajax_zap_request', array($this->zap_request_handler, 'handle_zap_request'));
            add_action('wp_ajax_nopriv_zap_request', array($this->zap_request_handler, 'handle_zap_request'));
        }
    }
}
