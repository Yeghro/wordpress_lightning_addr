<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

global $wpdb;
$table_name = $wpdb->prefix . 'lnurlp_nostr_zap_requests';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

delete_option('lnurlp_nostr_zap_handler_lnbits_api_url');
delete_option('lnurlp_nostr_zap_handler_lnbits_api_key');
delete_option('lnurlp_nostr_zap_handler_nostr_private_key');
delete_option('lnurlp_nostr_zap_handler_nostr_public_key');
delete_option('lnurlp_nostr_zap_handler_webhook_secret');
delete_option('lnurlp_nostr_zap_handler_nostr_relays');
