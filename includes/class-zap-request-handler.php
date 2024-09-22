<?php

class ZapRequestHandler {
    private $invoice_generator;
    private $nostr_event_handler;

    public function __construct() {
        $this->invoice_generator = new InvoiceGenerator();
        $this->nostr_event_handler = new NostrEventHandler();
    }

    public function handle_lnurlp_request() {
        check_ajax_referer('lnurlp_request_nonce', 'nonce');
        $lightning_address = isset($_GET['lightning_address']) ? sanitize_text_field($_GET['lightning_address']) : '';
        
        if (empty($lightning_address)) {
            wp_send_json_error('Invalid lightning address');
        }

        $response = array(
            'callback' => site_url('wp-admin/admin-ajax.php?action=zap_request'),
            'maxSendable' => 100000000, // 1 BTC in millisatoshis
            'minSendable' => 1000, // 1 sat in millisatoshis
            'metadata' => json_encode([['text/plain', "Zap for {$lightning_address}"]]),
            'tag' => 'payRequest'
        );

        wp_send_json($response);
    }

    public function handle_zap_request() {
        check_ajax_referer('zap_request_nonce', 'nonce');
        $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;
        $nostr_event = isset($_POST['nostr']) ? sanitize_text_field($_POST['nostr']) : '';

        if ($amount <= 0 || empty($nostr_event)) {
            Logger::log('Invalid zap request: Amount=' . $amount . ', Nostr event=' . ($nostr_event ? 'present' : 'missing'), 'error');
            wp_send_json_error('Invalid zap request: Amount must be greater than 0 and Nostr event must be provided');
        }

        if (!$this->validate_zap_request($nostr_event)) {
            Logger::log('Invalid Nostr event: ' . $nostr_event, 'error');
            wp_send_json_error('Invalid Nostr event: The provided event does not meet the required format or signature');
        }

        try {
            $invoice = $this->invoice_generator->generate_invoice($amount, 'Nostr Zap');

            if (!$invoice) {
                throw new Exception('Failed to generate invoice');
            }

            $zap_request_data = array(
                'amount' => $amount,
                'nostr_event' => $nostr_event,
                'payment_hash' => $invoice['payment_hash'],
                'status' => 'pending',
                'bolt11' => $invoice['payment_request']
            );

            // Remove the store_zap_request call
            // $insert_id = $this->store_zap_request($zap_request_data);

            Logger::log('Zap request processed successfully: Amount=' . $amount . ', Payment Hash=' . $invoice['payment_hash'], 'info');

            wp_send_json(array(
                'pr' => $invoice['payment_request'],
                'routes' => []
            ));
        } catch (Exception $e) {
            Logger::log('Error processing zap request: ' . $e->getMessage(), 'error');
            wp_send_json_error('An error occurred while processing the zap request: ' . $e->getMessage());
        }
    }

    public function validate_zap_request($nostr_event) {
        $event = json_decode($nostr_event, true);
        
        if (!$event || !isset($event['id']) || !isset($event['pubkey']) || !isset($event['created_at']) || !isset($event['kind']) || !isset($event['tags']) || !isset($event['content']) || !isset($event['sig'])) {
            Logger::log('Invalid Nostr event structure', 'error');
            return false;
        }

        // Verify event ID
        $calculated_id = $this->nostr_event_handler->calculate_event_id($event);
        if ($calculated_id !== $event['id']) {
            Logger::log('Invalid Nostr event ID', 'error');
            return false;
        }

        // Verify signature
        if (!$this->nostr_event_handler->verify_signature($event)) {
            Logger::log('Invalid Nostr event signature', 'error');
            return false;
        }

        return true;
    }
}
