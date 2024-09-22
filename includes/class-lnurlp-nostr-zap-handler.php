<?php

class LNURLPNostrZapHandler {
    private $zap_request_handler;
    private $invoice_generator;
    private $webhook_handler;
    private $nostr_event_handler;
    private $settings_manager;
    private $database_manager;
    private $settings;
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->setup_hooks();
    }

    private function load_dependencies() {
        $dependencies = [
            'ZapRequestHandler',
            'InvoiceGenerator',
            'WebhookHandler',
            'NostrEventHandler',
            'SettingsManager',
            'DatabaseManager'
        ];

        foreach ($dependencies as $class) {
            if (!class_exists($class)) {
                throw new Exception("Required class $class not found. Please ensure all plugin files are present.");
            }
        }

        $this->zap_request_handler = new ZapRequestHandler();
        $this->invoice_generator = new InvoiceGenerator();
        $this->webhook_handler = new WebhookHandler();
        $this->nostr_event_handler = new NostrEventHandler();
        $this->settings_manager = new SettingsManager();
        $this->database_manager = new DatabaseManager();
    }

    private function setup_hooks() {
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this->settings_manager, 'register_settings'));
        add_action('admin_menu', array($this->settings_manager, 'add_admin_menu'));
        add_action('rest_api_init', array($this->webhook_handler, 'register_webhook_route'));
        add_action('wp_ajax_nopriv_lnurlp_request', array($this->zap_request_handler, 'handle_lnurlp_request'));
        add_action('wp_ajax_nopriv_zap_request', array($this->zap_request_handler, 'handle_zap_request'));
    }

    public function init() {
        // Initialize the database if needed
        $db_result = $this->database_manager->create_tables();
        if (is_wp_error($db_result)) {
            error_log('Failed to initialize database: ' . $db_result->get_error_message());
            return;
        }
        
        // Load plugin settings
        $this->load_settings();
    }

    public function run() {
        do_action('lnurlp_nostr_zap_handler_started');
        Logger::log('LNURLP Nostr Zap Handler plugin started');
    }

    private function load_settings() {
        $this->settings = array(
            'lnbits_api_url' => get_option('lnurlp_nostr_zap_handler_lnbits_api_url'),
            'lnbits_api_key' => get_option('lnurlp_nostr_zap_handler_lnbits_api_key'),
            'nostr_private_key' => get_option('lnurlp_nostr_zap_handler_nostr_private_key'),
            'nostr_public_key' => get_option('lnurlp_nostr_zap_handler_nostr_public_key'),
            'webhook_secret' => get_option('lnurlp_nostr_zap_handler_webhook_secret'),
            'nostr_relays' => get_option('lnurlp_nostr_zap_handler_nostr_relays', array()),
        );

        // Validate settings
        foreach ($this->settings as $key => $value) {
            if (empty($value) && $key !== 'nostr_relays') {
                error_log("LNURLP Nostr Zap Handler: Missing required setting: $key");
            }
        }

        // Pass settings to other components that might need them
        $this->invoice_generator->set_api_credentials($this->settings['lnbits_api_url'], $this->settings['lnbits_api_key']);
        $this->nostr_event_handler->set_keys($this->settings['nostr_private_key'], $this->settings['nostr_public_key']);
    }
}
