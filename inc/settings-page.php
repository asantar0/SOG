<?php
/**
 * Plugin Name: SOG
 * Plugin URI:
 * Description: Protect your visitors by displaying a customizable warning modal whenever they click external links.
 * Version:           1.0.2
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Author: Agustin S
 * Author URI:
 * License:           MIT License
 * License URI:       https://opensource.org/license/mit
 * Text Domain:       sog
 * Domain Path:       /languages
 */

//Variables to get
// $current_token (string)
// $current_exceptions (array)
?>

<div class="wrap">
    <h1>Secure Outbound Gateway (SOG)</h1>

    <form method="post">
        <?php wp_nonce_field('sog_save_exceptions'); ?>

        <h2>IPInfo.io API Token</h2>
        <p>
            You can use your own token for geolocation data.
            <a href="https://ipinfo.io/account/token" target="_blank" rel="noopener noreferrer">Get a free token here</a>.
        </p>
        <input type="text" name="sog_token" value="<?php echo esc_attr($current_token); ?>" class="regular-text" />

        <hr>

        <h2>URL Whitelist</h2>
        <p>Enter one URL per line. Example: <code>example.com</code> or <code>https://site.com/path</code></p>
        <textarea name="sog_exceptions" rows="10" cols="80" class="large-text code"><?php
            echo esc_textarea(implode("\n", $current_exceptions));
        ?></textarea>

        <p><input type="submit" class="button button-primary" value="Save changes"></p>
    </form>

    <form method="post" style="margin-top: 20px;">
        <?php wp_nonce_field('sog_clear_log'); ?>
        <h2>Warning zone</h2>
        <input type="hidden" name="sog_clear_log" value="1">
        <input type="submit" class="button button-secondary" value="Clean audit log"
               onclick="return confirm('Are you sure you want to delete the audit file? This action cannot be undone.');">
    </form>
</div>

