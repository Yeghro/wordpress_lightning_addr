<?php

class WebhookHandler {
    private $database_manager;
    private $nostr_event_handler;

    public function __construct() {
        $this->database_manager = new DatabaseManager();
        $this->nostr_event_handler = new NostrEventHandler();
    }

    public function register_webhook_route() {
        register_rest_route('lnurlp-nostr-zap-handler/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true', // We'll validate the data inside the handler
        ));
    }

    public function handle_webhook($request) {
        $json = $request->get_body();
        $object = json_decode($json);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', 'Invalid JSON payload', array('status' => 415));
        }

        // Log the webhook content for debugging (remember to remove in production)
        error_log('Webhook received: ' . print_r($object, true));

        if (!isset($object->payment_hash) || !isset($object->paid)) {
            return new WP_Error('invalid_webhook', 'Invalid webhook payload', array('status' => 400));
        }

        $payment_hash = sanitize_text_field($object->payment_hash);
        $status = $object->paid ? 'paid' : 'pending';

        $zap_request = $this->database_manager->get_zap_request($payment_hash);

        if (!$zap_request) {
            return new WP_Error('invalid_payment_hash', 'Invalid payment hash', array('status' => 400));
        }

        $this->database_manager->update_zap_request($payment_hash, $status);

        if ($status === 'paid') {
            $zap_receipt_event = $this->nostr_event_handler->create_zap_receipt_event($zap_request, $payment_hash);
            $this->nostr_event_handler->publish_event_to_relays($zap_receipt_event);
        }

        return new WP_REST_Response(array('status' => 'success'), 200);
    }
}