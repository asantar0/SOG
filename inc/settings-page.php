<?php
/**
 * Plugin Name: SOG
 * Plugin URI:
 * Description: Protect your visitors by displaying a customizable warning modal whenever they click external links.
 * Version:           1.0.4
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

        <h2>External Links Behavior</h2>
        <p>Choose which <code>rel</code> attributes to apply to external links:</p>

        <p>
            <label>
                <input type="checkbox" name="sog_add_rel_noopener" value="1" <?php checked(get_option('sog_add_rel_noopener', '1'), '1'); ?> />
                Add <code>rel="noopener"</code>
            </label>
        </p>

        <p>
            <label>
                <input type="checkbox" name="sog_add_rel_noreferrer" value="1" <?php checked(get_option('sog_add_rel_noreferrer', '1'), '1'); ?> />
                Add <code>rel="noreferrer"</code>
            </label>
        </p>

        <hr>

        <h2>URL Whitelist</h2>
        <p>Enter one URL per line. Example: <code>example.com</code> or <code>https://site.com/path</code></p>
        <textarea name="sog_exceptions" rows="10" cols="80" class="large-text code"><?php echo esc_textarea(implode("\n", is_array($current_exceptions) ? $current_exceptions : [])); ?></textarea>

        <hr>

        <h2>Modal Appearance</h2>

        <p>
            <label for="sog_modal_title">Modal Title:</label><br>
            <input type="text" name="sog_modal_title" id="sog_modal_title" value="<?php echo esc_attr(get_option('sog_modal_title', 'Warning notice')); ?>" class="regular-text" />
        </p>

        <p>
            <label for="sog_continue_color">Continue Button Color:</label><br>
            <input type="color" name="sog_continue_color" id="sog_continue_color" value="<?php echo esc_attr(get_option('sog_continue_color', '#28a745')); ?>">
        </p>

        <p>
            <label for="sog_cancel_color">Cancel Button Color:</label><br>
            <input type="color" name="sog_cancel_color" id="sog_cancel_color" value="<?php echo esc_attr(get_option('sog_cancel_color', '#dc3545')); ?>">
        </p>

	<hr>
	<h2>Email Notification</h2>
	<p>
		<label>
			<input type="checkbox" name="sog_email_enabled" value="1" <?php checked(get_option('sog_email_enabled', '1'), '1'); ?> />
			Enable email notification when settings change
		</label>
	</p>
	<br>
        <p>
            <input type="submit" class="button button-primary" value="Save changes">
        </p>

    </form>

    <hr>
    <div class="sog-warning-zone">
        <h2>Warning Zone</h2>
        <p>This action will permanently delete the audit log. This cannot be undone.</p>

        <form method="post">
            <?php wp_nonce_field('sog_clear_log'); ?>
            <input type="hidden" name="sog_clear_log" value="1">
            <input type="submit" class="button button-secondary" value="Clean audit log"
                   onclick="return confirm('Are you sure you want to delete the audit file? This action cannot be undone.');">
        </form>
    </div>
</div>

