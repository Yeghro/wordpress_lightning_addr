<?php

use Elliptic\EC;

class NostrEventHandler {
    private $nostr_private_key;
    private $nostr_public_key;
    private $relays;
    private $ec;

    public function __construct() {
        $this->ec = new EC('secp256k1');
    }

    public function set_keys($private_key, $public_key) {
        $this->nostr_private_key = $private_key;
        $this->nostr_public_key = $public_key;
    }

    public function set_relays($relays) {
        $this->relays = $relays;
    }

    public function create_zap_receipt_event($zap_request, $payment_hash) {
        $nostr_event = json_decode($zap_request['nostr_event'], true);

        $zap_receipt = array(
            'kind' => 9735,
            'created_at' => time(),
            'tags' => array(
                array('p', $nostr_event['pubkey']),
                array('e', $nostr_event['id']),
                array('bolt11', $zap_request['bolt11']),
                array('description', $nostr_event['content']),
            ),
            'content' => '',
            'pubkey' => $this->nostr_public_key,
        );

        $zap_receipt['id'] = $this->calculate_event_id($zap_receipt);
        $zap_receipt['sig'] = $this->sign_event($zap_receipt);

        return $zap_receipt;
    }

    public function publish_event_to_relays($event) {
        foreach ($this->relays as $relay_url) {
            $this->publish_to_relay($relay_url, $event);
        }
    }

    private function publish_to_relay($relay_url, $event) {
        $ch = curl_init($relay_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('EVENT', $event)));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code !== 200) {
            error_log("Failed to publish event to relay {$relay_url}. HTTP Code: {$http_code}");
        }

        curl_close($ch);
    }

    private function calculate_event_id($event) {
        $serialized = json_encode(array(
            0,
            $event['pubkey'],
            $event['created_at'],
            $event['kind'],
            $event['tags'],
            $event['content']
        ));

        return hash('sha256', $serialized);
    }

    private function sign_event($event) {
        $event_id = $this->calculate_event_id($event);
        $key = $this->ec->keyFromPrivate($this->nostr_private_key, 'hex');
        $signature = $key->sign($event_id, ['canonical' => true]);
        
        $r = str_pad($signature->r->toString(16), 64, '0', STR_PAD_LEFT);
        $s = str_pad($signature->s->toString(16), 64, '0', STR_PAD_LEFT);
        
        return $r . $s;
    }

    public function verify_signature($event) {
        $event_id = $this->calculate_event_id($event);
        $key = $this->ec->keyFromPublic($event['pubkey'], 'hex');
        $signature = [
            'r' => substr($event['sig'], 0, 64),
            's' => substr($event['sig'], 64)
        ];
        
        return $key->verify($event_id, $signature);
    }
}
