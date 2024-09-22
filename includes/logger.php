<?php

class Logger {
    public static function log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            error_log("LNURLP Nostr Zap Handler [$level]: $message");
        }
    }
}
