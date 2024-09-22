<?php

class DatabaseManager {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'lnurlp_nostr_zap_requests';
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            payment_hash varchar(64) NOT NULL,
            amount bigint(20) NOT NULL,
            nostr_event text NOT NULL,
            status varchar(20) NOT NULL,
            bolt11 text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY payment_hash (payment_hash)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);

        // Check for errors
        if (!empty($wpdb->last_error)) {
            return new WP_Error('db_create_error', 'Failed to create database table: ' . $wpdb->last_error);
        }

        return true;
    }

    public function insert_zap_request($zap_request_data) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'payment_hash' => $zap_request_data['payment_hash'],
                'amount' => $zap_request_data['amount'],
                'nostr_event' => $zap_request_data['nostr_event'],
                'status' => $zap_request_data['status'],
                'bolt11' => $zap_request_data['bolt11'],
            ),
            array('%s', '%d', '%s', '%s', '%s')
        );

        if ($result === false) {
            Logger::log('Database insert failed: ' . $wpdb->last_error, 'error');
            return false;
        }

        return $wpdb->insert_id;
    }

    public function update_zap_request($payment_hash, $status, $bolt11 = null) {
        global $wpdb;

        $update_data = array('status' => $status);
        $update_format = array('%s');

        if ($bolt11 !== null) {
            $update_data['bolt11'] = $bolt11;
            $update_format[] = '%s';
        }

        $wpdb->update(
            $this->table_name,
            $update_data,
            array('payment_hash' => $payment_hash),
            $update_format,
            array('%s')
        );
    }

    public function get_zap_request($payment_hash) {
        global $wpdb;

        $query = $wpdb->prepare("SELECT * FROM $this->table_name WHERE payment_hash = %s", $payment_hash);
        return $wpdb->get_row($query, ARRAY_A);
    }
}
