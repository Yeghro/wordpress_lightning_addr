<?php

class InvoiceGenerator {
    private $lnbits_api_url;
    private $lnbits_api_key;
    private $webhook_url;

    public function __construct() {
        $this->lnbits_api_url = get_option('lnurlp_nostr_zap_handler_lnbits_api_url');
        $this->lnbits_api_key = get_option('lnurlp_nostr_zap_handler_lnbits_api_key');
        $this->webhook_url = get_option('lnurlp_nostr_zap_handler_webhook_url');
    }

    public function generate_invoice($amount, $memo) {
        $endpoint = $this->lnbits_api_url . '/api/v1/payments';
        
        $body = array(
            'out' => false,
            'amount' => $amount,
            'memo' => $memo,
            'webhook' => $this->webhook_url
        );

        $args = array(
            'body' => json_encode($body),
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-Api-Key' => $this->lnbits_api_key,
            ),
            'method' => 'POST',
            'data_format' => 'body',
        );

        $response = wp_remote_post($endpoint, $args);

        if (is_wp_error($response)) {
            error_log('Failed to generate invoice: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['payment_hash']) || !isset($body['payment_request'])) {
            error_log('Invalid response from LNbits API');
            return false;
        }

        return array(
            'payment_hash' => $body['payment_hash'],
            'payment_request' => $body['payment_request'],
        );
    }
}
