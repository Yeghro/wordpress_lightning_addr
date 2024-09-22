<?php

class Logger {
    public static function log($message, $level = 'info') {
        $log_file = plugin_dir_path(__FILE__) . '../logs/plugin.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] [$level] $message" . PHP_EOL;
        error_log($log_message, 3, $log_file);
    }
}
