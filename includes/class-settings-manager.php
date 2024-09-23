<?php

class SettingsManager {
    public function add_admin_menu() {
        add_options_page(
            'LNURLP Nostr Zap Handler Settings',
            'LNURLP Nostr Zap',
            'manage_options',
            'lnurlp-nostr-zap-handler',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('lnurlp_nostr_zap_handler_options', 'lnurlp_nostr_zap_handler_lnbits_api_url');
        register_setting('lnurlp_nostr_zap_handler_options', 'lnurlp_nostr_zap_handler_lnbits_api_key');
        register_setting('lnurlp_nostr_zap_handler_options', 'lnurlp_nostr_zap_handler_nostr_private_key');
        register_setting('lnurlp_nostr_zap_handler_options', 'lnurlp_nostr_zap_handler_nostr_public_key');
        register_setting('lnurlp_nostr_zap_handler_options', 'lnurlp_nostr_zap_handler_nostr_relays', array($this, 'sanitize_relays'));
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>LNURLP Nostr Zap Handler Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('lnurlp_nostr_zap_handler_options');
                do_settings_sections('lnurlp_nostr_zap_handler_options');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">LNbits API URL</th>
                        <td><input type="text" name="lnurlp_nostr_zap_handler_lnbits_api_url" value="<?php echo esc_attr(get_option('lnurlp_nostr_zap_handler_lnbits_api_url')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">LNbits API Key</th>
                        <td><input type="text" name="lnurlp_nostr_zap_handler_lnbits_api_key" value="<?php echo esc_attr(get_option('lnurlp_nostr_zap_handler_lnbits_api_key')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Nostr Private Key</th>
                        <td><input type="text" name="lnurlp_nostr_zap_handler_nostr_private_key" value="<?php echo esc_attr(get_option('lnurlp_nostr_zap_handler_nostr_private_key')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Nostr Public Key</th>
                        <td><input type="text" name="lnurlp_nostr_zap_handler_nostr_public_key" value="<?php echo esc_attr(get_option('lnurlp_nostr_zap_handler_nostr_public_key')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Nostr Relays (comma-separated)</th>
                        <td><input type="text" name="lnurlp_nostr_zap_handler_nostr_relays" value="<?php echo esc_attr($this->get_relays_as_string()); ?>" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function get_relays_as_string() {
        $relays = get_option('lnurlp_nostr_zap_handler_nostr_relays', array());
        if (is_string($relays)) {
            // If it's already a string, return it as is
            return $relays;
        } elseif (is_array($relays)) {
            // If it's an array, implode it
            return implode(',', $relays);
        } else {
            // If it's neither, return an empty string
            return '';
        }
    }

    public function get_setting($key, $default = '') {
        return get_option('lnurlp_nostr_zap_handler_' . $key, $default);
    }

    public function update_setting($key, $value) {
        update_option('lnurlp_nostr_zap_handler_' . $key, $value);
    }

    public function get_all_settings() {
        return [
            'lnbits_api_url' => $this->get_setting('lnbits_api_url', 'https://legend.lnbits.com'),
            'lnbits_api_key' => $this->get_setting('lnbits_api_key', ''),
            'nostr_private_key' => $this->get_setting('nostr_private_key', ''),
            'nostr_public_key' => $this->get_setting('nostr_public_key', ''),
            'webhook_secret' => $this->get_setting('webhook_secret', $this->generate_random_string(32)),
            'nostr_relays' => $this->sanitize_relays($this->get_setting('nostr_relays', ['wss://relay.damus.io', 'wss://nostr-pub.wellorder.net'])),
        ];
    }

    private function generate_random_string($length = 32) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function sanitize_relays($input) {
        if (is_string($input)) {
            // If it's a string, split it into an array
            return array_map('trim', explode(',', $input));
        } elseif (is_array($input)) {
            // If it's already an array, return it as is
            return $input;
        } else {
            // If it's neither, return an empty array
            return array();
        }
    }
}
