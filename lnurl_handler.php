<?php
/*
Plugin Name: LNURLP Nostr Zap Handler
Description: Handle LNURL-P requests and Nostr Zaps for Lightning Addresses
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class LNURLPNostrZapHandler {

    private $lnbits_api_url = 'https://lnbits.yeghro.site/api/v1/payments'; // Update if different
    private $lnbits_api_key = 'YOUR_LNBITS_API_KEY'; // Replace with your LNbits API key
    private $nostr_pubkey = 'YOUR_NOSTR_PUBLIC_KEY_HERE'; // Replace with your Nostr public key
    private $nostr_private_key = 'YOUR_NOSTR_PRIVATE_KEY_HERE'; // Replace with your Nostr private key
    private $microservice_url = 'https://your-microservice.url/publish'; // Replace with your relay publishing microservice URL

    public function __construct() {
        // Activation Hook
        register_activation_hook(__FILE__, array($this, 'create_zap_requests_table'));

        // Add Rewrite Rules
        add_action('init', array($this, 'add_rewrite_rules'));

        // Register Query Variables
        add_filter('query_vars', array($this, 'register_query_vars'));

        // Handle LNURL-P Requests
        add_action('template_redirect', array($this, 'handle_lnurlp_requests'));

        // Register Webhook Endpoint for LNbits
        add_action('rest_api_init', array($this, 'register_webhook_route'));
    }

    /**
     * Create custom database table for storing zap requests
     */
    public function create_zap_requests_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'zap_requests';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id VARCHAR(64) NOT NULL,
            pubkey VARCHAR(64) NOT NULL,
            amount BIGINT UNSIGNED NOT NULL,
            lnurl TEXT NOT NULL,
            relays TEXT NOT NULL,
            description TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) DEFAULT 'pending',
            PRIMARY KEY (id),
            UNIQUE KEY (event_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add custom rewrite rules
     */
    public function add_rewrite_rules() {
        add_rewrite_rule('^lnurlp-handler/?', 'index.php?lnurlp_handler=1', 'top');
    }

    /**
     * Register query variables
     */
    public function register_query_vars($vars) {
        $vars[] = 'lnurlp_handler';
        return $vars;
    }

    /**
     * Handle LNURL-P and Zap Requests
     */
    public function handle_lnurlp_requests() {
        if (get_query_var('lnurlp_handler') == 1) {
            header('Content-Type: application/json');

            $amount = isset($_GET['amount']) ? intval($_GET['amount']) : 0;
            $nostr = isset($_GET['nostr']) ? urldecode($_GET['nostr']) : null;
            $lnurl = isset($_GET['lnurl']) ? urldecode($_GET['lnurl']) : null;

            // Validate amount
            if ($amount < 1000 || $amount > 100000000) {
                echo json_encode(['status' => 'ERROR', 'reason' => 'Amount out of allowed range']);
                exit;
            }

            // Handle Zap Request if present
            if ($nostr) {
                $zap_request = json_decode($nostr, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    echo json_encode(['status' => 'ERROR', 'reason' => 'Invalid Nostr JSON']);
                    exit;
                }

                // Validate Zap Request
                $validation_error = $this->validate_zap_request($zap_request, $amount);
                if ($validation_error) {
                    echo json_encode(['status' => 'ERROR', 'reason' => $validation_error]);
                    exit;
                }

                // Store Zap Request
                $store_success = $this->store_zap_request($zap_request, $amount, $lnurl);
                if (!$store_success) {
                    echo json_encode(['status' => 'ERROR', 'reason' => 'Failed to store Zap Request']);
                    exit;
                }
            }

            // Generate Invoice via LNbits API
            $invoice = $this->generate_invoice($amount);
            if (!$invoice) {
                echo json_encode(['status' => 'ERROR', 'reason' => 'Unable to generate invoice']);
                exit;
            }

            // Respond with the invoice
            echo json_encode([
                'pr' => $invoice['payment_request'],
                'routes' => [],
                'successAction' => null,
                'disposable' => false
            ]);
            exit;
        }
    }

    /**
     * Validate Zap Request as per NIP-57 Appendix D
     */
    private function validate_zap_request($zap_request, $amount) {
        // 1. Check if all required fields are present
        if (!isset($zap_request['kind']) || $zap_request['kind'] !== 9734) {
            return 'Invalid kind for Zap Request.';
        }
        if (!isset($zap_request['tags']) || !is_array($zap_request['tags'])) {
            return 'Zap Request must have tags.';
        }

        // 2. Check for a valid signature
        if (!isset($zap_request['sig']) || !$this->is_valid_signature($zap_request)) {
            return 'Invalid signature.';
        }

        // 3. Validate tags
        $tags = $zap_request['tags'];

        // a. Exactly one 'p' tag
        $tag_p = array_filter($tags, function($tag) {
            return $tag[0] === 'p';
        });
        if (count($tag_p) !== 1) {
            return 'Zap Request must have exactly one p tag.';
        }

        // b. Exactly one 'relays' tag
        $tag_relays = array_filter($tags, function($tag) {
            return $tag[0] === 'relays';
        });
        if (count($tag_relays) !== 1) {
            return 'Zap Request must have exactly one relays tag.';
        }

        // c. If 'amount' tag exists, it must match the query parameter
        $tag_amount = array_filter($tags, function($tag) {
            return $tag[0] === 'amount';
        });
        if (count($tag_amount) > 1) {
            return 'Zap Request must have at most one amount tag.';
        }
        if (count($tag_amount) === 1 && intval($tag_amount[array_key_first($tag_amount)][1]) !== $amount) {
            return 'Amount tag does not match the query parameter.';
        }

        // d. Validate 'P' tag if present
        $tag_P = array_filter($tags, function($tag) {
            return $tag[0] === 'P';
        });
        if (count($tag_P) > 1) {
            return 'Zap Request must have at most one P tag.';
        }

        // e. Validate 'nostrPubkey' is present in metadata and matches
        // Assuming 'nostrPubkey' is part of the plugin configuration
        // Additional validation can be implemented as needed

        // f. Additional tag validations as per NIP-57

        // 4. Validate nostrPubkey format
        if (!$this->is_valid_pubkey($zap_request['pubkey'])) {
            return 'Invalid nostrPubkey format.';
        }

        return null; // No errors
    }

    /**
     * Validate Nostr Event Signature (Placeholder)
     * Implement actual signature verification using a suitable library or external service
     */
    private function is_valid_signature($zap_request) {
        // Placeholder implementation
        // Implement BIP 340 Schnorr signature verification
        // You may need to use a PHP library or an external service for this
        // For demonstration, we'll assume it's valid
        return true;
    }

    /**
     * Validate Nostr Public Key Format
     */
    private function is_valid_pubkey($pubkey) {
        return preg_match('/^[0-9a-fA-F]{64}$/', $pubkey);
    }

    /**
     * Store Zap Request in the Database
     */
    private function store_zap_request($zap_request, $amount, $lnurl) {
        global $wpdb;
        $table = $wpdb->prefix . 'zap_requests';

        // Extract relays from 'relays' tag
        $relays_tag = array_filter($zap_request['tags'], function($tag) {
            return $tag[0] === 'relays';
        });
        $relays = [];
        if (!empty($relays_tag)) {
            // Assuming relays are in the second position onwards
            $relays = array_slice($relays_tag[array_key_first($relays_tag)], 1);
        }

        // Extract description from 'content' or other tags if needed
        $description = isset($zap_request['content']) ? $zap_request['content'] : '';

        $result = $wpdb->insert($table, [
            'event_id' => isset($zap_request['id']) ? $zap_request['id'] : '',
            'pubkey' => isset($zap_request['pubkey']) ? $zap_request['pubkey'] : '',
            'amount' => $amount,
            'lnurl' => $lnurl,
            'relays' => json_encode($relays),
            'description' => $description,
            'status' => 'pending'
        ]);

        return $result !== false;
    }

    /**
     * Generate Lightning Invoice via LNbits API
     */
    private function generate_invoice($amount) {
        $response = wp_remote_post($this->lnbits_api_url, [
            'method'    => 'POST',
            'headers'   => [
                'Content-Type' => 'application/json',
                'X-Api-Key'    => $this->lnbits_api_key
            ],
            'body'      => json_encode([
                'out'  => false,
                'amount' => $amount / 1000, // LNbits expects satoshis
                'memo'  => 'Payment to yeghro@yeghro.site'
            ]),
            'timeout'   => 60
        ]);

        if (is_wp_error($response)) {
            error_log('LNbits API Error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $invoice = json_decode($body, true);

        if (isset($invoice['payment_request'])) {
            return $invoice;
        } else {
            error_log('LNbits API Invalid Response: ' . $body);
            return false;
        }
    }

    /**
     * Register REST API route for handling LNbits webhooks
     */
    public function register_webhook_route() {
        register_rest_route('lnurlp-handler/v1', '/webhook', [
            'methods'  => 'POST',
            'callback' => array($this, 'handle_invoice_payment'),
            'permission_callback' => '__return_true', // Implement proper authentication if needed
        ]);
    }

    /**
     * Handle Invoice Payment Webhook from LNbits
     */
    public function handle_invoice_payment(WP_REST_Request $request) {
        $data = $request->get_json_params();

        // Extract necessary details from webhook payload
        $payment_request = isset($data['payment_request']) ? $data['payment_request'] : null;
        $paid_at = isset($data['paid_at']) ? intval($data['paid_at']) : time();
        $preimage = isset($data['preimage']) ? $data['preimage'] : null;

        if (!$payment_request) {
            return new WP_REST_Response(['status' => 'ERROR', 'reason' => 'Missing payment_request'], 400);
        }

        // Retrieve associated Zap Request
        $zap_request = $this->get_zap_request_by_invoice($payment_request);
        if (!$zap_request) {
            return new WP_REST_Response(['status' => 'ERROR', 'reason' => 'No associated Zap Request found'], 400);
        }

        // Create Zap Receipt Event
        $zap_receipt = $this->create_zap_receipt($zap_request, $paid_at, $preimage);

        // Publish Zap Receipt to Relays
        $relays = json_decode($zap_request['relays'], true);
        if (empty($relays)) {
            error_log('No relays specified for Zap Receipt.');
            return new WP_REST_Response(['status' => 'ERROR', 'reason' => 'No relays specified'], 400);
        }

        foreach ($relays as $relay) {
            $publish_success = $this->publish_event_to_relay($relay, $zap_receipt);
            if (!$publish_success) {
                error_log("Failed to publish Zap Receipt to relay: $relay");
                // Optionally, handle retry logic or mark as failed
            }
        }

        // Update Zap Request status to 'completed'
        $this->update_zap_request_status($zap_request['event_id'], 'completed');

        return new WP_REST_Response(['status' => 'OK'], 200);
    }

    /**
     * Retrieve Zap Request by Invoice
     */
    private function get_zap_request_by_invoice($invoice) {
        global $wpdb;
        $table = $wpdb->prefix . 'zap_requests';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE event_id = %s AND status = 'pending'", $invoice), ARRAY_A);
    }

    /**
     * Create Zap Receipt Event as per NIP-57 Appendix E
     */
    private function create_zap_receipt($zap_request, $paid_at, $preimage) {
        $recipient_pubkey = $this->nostr_pubkey;
        $sender_pubkey = $zap_request['pubkey'];
        $description = json_encode([
            'pubkey'     => $zap_request['pubkey'],
            'content'    => $zap_request['description'],
            'id'         => $zap_request['event_id'],
            'created_at' => strtotime($zap_request['created_at']),
            'sig'        => 'dummy_signature', // Placeholder, replace with actual signature
            'kind'       => 9734,
            'tags'       => json_decode($zap_request['relays'], true) // Assuming tags are stored as JSON
        ]);

        $tags = [
            ['p', $recipient_pubkey],
            ['P', $sender_pubkey],
            // Add 'e' and 'a' tags from zap_request if available
            ['bolt11', ''], // Placeholder, replace with actual bolt11 invoice
            ['description', $description],
        ];

        if (!empty($preimage)) {
            $tags[] = ['preimage', $preimage];
        }

        $event = [
            'kind'      => 9735,
            'content'   => '',
            'created_at'=> $paid_at,
            'tags'      => $tags,
            'pubkey'    => $recipient_pubkey,
        ];

        // Generate Event ID
        $event['id'] = $this->generate_event_id($event);

        // Sign Event
        $event['sig'] = $this->sign_event($event);

        return $event;
    }

    /**
     * Generate Event ID by hashing the serialized event
     */
    private function generate_event_id($event) {
        $serialized_event = json_encode([
            0,
            $event['pubkey'],
            $event['created_at'],
            $event['kind'],
            $event['tags'],
            $event['content']
        ]);
        return hash('sha256', $serialized_event);
    }

    /**
     * Sign Event using Nostr Private Key (Placeholder)
     * Implement actual signature generation using a suitable library or external service
     */
    private function sign_event($event) {
        // Placeholder implementation
        // Implement BIP 340 Schnorr signature using a PHP library or external service
        // For demonstration, we'll return a dummy signature
        return 'dummy_signature';
    }

    /**
     * Publish Event to Nostr Relay via External Microservice
     * This function sends the event data to an external service responsible for relay communication
     */
    private function publish_event_to_relay($relay, $event) {
        $response = wp_remote_post($this->microservice_url, [
            'method'    => 'POST',
            'headers'   => [
                'Content-Type' => 'application/json',
            ],
            'body'      => json_encode([
                'relay' => $relay,
                'event' => $event
            ]),
            'timeout'   => 60
        ]);

        if (is_wp_error($response)) {
            error_log('Failed to publish event to relay ' . $relay . ': ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        return isset($result['status']) && $result['status'] === 'OK';
    }

    /**
     * Update Zap Request Status
     */
    private function update_zap_request_status($event_id, $status) {
        global $wpdb;
        $table = $wpdb->prefix . 'zap_requests';
        $wpdb->update(
            $table,
            ['status' => $status],
            ['event_id' => $event_id],
            ['%s'],
            ['%s']
        );
    }
}

// Initialize the plugin
new LNURLPNostrZapHandler();
