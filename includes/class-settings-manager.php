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
        register_setting('lnurlp_nostr_zap_handler_options', 'lnurlp_nostr_zap_handler_webhook_id');
        register_setting('lnurlp_nostr_zap_handler_options', 'lnurlp_nostr_zap_handler_nostr_relays');
        register_setting('lnurlp_nostr_zap_handler_options', 'lnurlp_nostr_zap_handler_webhook_url');
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
                        <th scope="row">LNbits Webhook ID</th>
                        <td><input type="text" name="lnurlp_nostr_zap_handler_webhook_id" value="<?php echo esc_attr(get_option('lnurlp_nostr_zap_handler_webhook_id')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Nostr Relays (comma-separated)</th>
                        <td><input type="text" name="lnurlp_nostr_zap_handler_nostr_relays" value="<?php echo esc_attr(implode(',', get_option('lnurlp_nostr_zap_handler_nostr_relays', array()))); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Webhook URL</th>
                        <td><input type="text" name="lnurlp_nostr_zap_handler_webhook_url" value="<?php echo esc_attr(get_option('lnurlp_nostr_zap_handler_webhook_url')); ?>" size="60" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
