<?php
/**
 * Plugin Name: SOG
 * Description: Protect your visitors by displaying a customizable warning modal whenever they click external links.
 * Version: 1.0.2
 * Author: Agustin S
 */

// Main 
function sog_enqueue_scripts() {
    $plugin_url = plugin_dir_url(__FILE__);

    wp_enqueue_style('sog-style', $plugin_url . 'css/sog-style.css', [], time());
    wp_enqueue_script('sog-script', $plugin_url . 'js/sog-script.js', [], time(), true);

    wp_localize_script('sog-script', 'sog_ajax', [
        'ajax_url'   => admin_url('admin-ajax.php'),
        'plugin_url' => $plugin_url,
        'nonce'      => wp_create_nonce('sog_log_nonce')
    ]);

    // Translate
    wp_localize_script('sog-script', 'sog_i18n', [
	'modal_title'    => __('Warning notice', 'sog'),
        'modal_line_1'   => __('You are leaving %s to access an external site.', 'sog'),
        'modal_line_2'   => __('%s is not responsible for the content, accuracy, availability or security policies of the site that will be redirected. Access is achieved without exclusive liability.', 'sog'),
        'cancel_label'   => __('Cancel', 'sog'),
        'continue_label' => __('Continue', 'sog'),
        'cancel_aria'    => __('Cancel and stay on this site', 'sog'),
        'continue_aria'  => __('Continue and visit the external site', 'sog'),
        'site_name'      => get_bloginfo('name'),
        'site_domain'    => parse_url(home_url(), PHP_URL_HOST),
    ]);
}

add_action('wp_enqueue_scripts', 'sog_enqueue_scripts');

// Logs
add_action('wp_ajax_nopriv_sog_log_click', 'sog_log_click');
add_action('wp_ajax_sog_log_click', 'sog_log_click');

function sog_log_click() {
    if (
        !isset($_POST['url']) ||
        !isset($_POST['nonce']) ||
        !wp_verify_nonce($_POST['nonce'], 'sog_log_nonce')
    ) {
        wp_send_json_error('Unauthorized');
    }

    $url = esc_url_raw($_POST['url']);
    $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : 'unknown';
    $ip_real = $_SERVER['REMOTE_ADDR'];
    $timestamp = current_time('mysql');

    // IP Hidden
    if (filter_var($ip_real, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $ip_parts = explode('.', $ip_real);
        $ip_display = $ip_parts[0] . '.' . $ip_parts[1] . '.***.***';
    } elseif (filter_var($ip_real, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $blocks = explode(':', $ip_real);
        $visible = array_slice($blocks, 0, 2);
        $ip_display = implode(':', $visible) . ':****:****:****:****';
    } else {
        $ip_display = 'unknown';
    }

    // IP Geolocation (ipinfo.io)
    //$token = 'api-token';
    $token = get_option('sog_ipinfo_token', '');
    $country = 'Unknown';
    $geo_url = "https://ipinfo.io/{$ip_real}/json" . ($token ? "?token={$token}" : "");
    $response = wp_remote_get($geo_url);

    if (
        is_array($response) &&
        isset($response['body']) &&
        $body = json_decode($response['body'], true)
    ) {
        if (isset($body['country'])) {
            $country = $body['country'];
        }
    }

    $log_entry = "[$timestamp] IP: $ip_display - Country: $country - Action: $action_type - URL: $url\n";

    $log_dir = WP_CONTENT_DIR . '/sog-logs';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0750, true);
    }

    $log_file = $log_dir . '/sog-clicks.log';

    if (file_exists($log_file) && filesize($log_file) > 1024 * 1024) {
        rename($log_file, $log_file . '.' . time() . '.bak');
    }

    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    chmod($log_file, 0600);

    wp_send_json_success('Click logged');
}

add_action('plugins_loaded', 'sog_load_textdomain');
function sog_load_textdomain() {
	    load_plugin_textdomain('sog', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

//Admin Panel - GUI
add_action('admin_menu', 'sog_add_admin_menu');

function sog_add_admin_menu() {
    add_options_page(
        'Secure Outbound Gateway',
        'SOG',
        'manage_options',
        'sog',
        'sog_settings_page'
    );
}

function sog_settings_page() {
    $upload_dir = wp_upload_dir();
    $exceptions_path = trailingslashit($upload_dir['basedir']) . 'sog/exceptions.json';
    $log_path = trailingslashit($upload_dir['basedir']) . 'sog/exceptions.log';

    //ipinfo.io token variable
    $current_token = get_option('sog_ipinfo_token', '');

    if (
        isset($_POST['sog_token']) ||
        isset($_POST['sog_exceptions']) ||
        isset($_POST['sog_clear_log'])
    ) {
        if (isset($_POST['sog_clear_log']) && check_admin_referer('sog_clear_log')) {
            if (file_exists($log_path)) {
                if (is_writable($log_path)) {
                    unlink($log_path);
                    echo '<div class="notice notice-warning"><p>Audit log deleted.</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Cannot delete audit file: insufficient permissions</p></div>';
                }
            } else {
                echo '<div class="notice notice-info"><p>There is no audit history to delete.</p></div>';
            }
        }

        if (
            (isset($_POST['sog_token']) || isset($_POST['sog_exceptions'])) &&
            check_admin_referer('sog_save_exceptions')
        ) {
            // Save token if it was set
            if (isset($_POST['sog_token'])) {
                $sanitized_token = sanitize_text_field($_POST['sog_token']);
                update_option('sog_ipinfo_token', $sanitized_token);

                // Save token in uploads/sog/ folder
                $token_file = trailingslashit($upload_dir['basedir']) . 'sog/ipinfo.token';
                if (!file_exists(dirname($token_file))) {
                    wp_mkdir_p(dirname($token_file));
                }
                file_put_contents($token_file, $sanitized_token);
                chmod($token_file, 0600);

                $current_token = $sanitized_token;
            }

            // Process whitelist 
            if (isset($_POST['sog_exceptions'])) {
                $raw = sanitize_textarea_field($_POST['sog_exceptions']);
                $lines = array_filter(array_map('trim', explode("\n", $raw)));

                $exceptions = [];
                $invalid = [];

                foreach ($lines as $line) {
                    if (filter_var($line, FILTER_VALIDATE_URL)) {
                        $exceptions[] = $line;
                    } elseif (preg_match('/^([a-z0-9-]+\.)+[a-z]{2,}$/i', $line)) {
                        $exceptions[] = $line;
                    } else {
                        $invalid[] = $line;
                    }
                }

                if (!empty($invalid)) {
                    echo '<div class="notice notice-error"><p><strong>Error:</strong> The following values are not valid URLs or domains:</p><ul>';
                    foreach ($invalid as $bad) {
                        echo '<li><code>' . esc_html($bad) . '</code></li>';
                    }
                    echo '</ul><p>Changes were not saved.</p></div>';
                } else {
                    // Read whitelist in order to compare
                    $old_exceptions = [];
                    if (file_exists($exceptions_path)) {
                        $json = file_get_contents($exceptions_path);
                        $old_exceptions = json_decode($json, true);
                        if (!is_array($old_exceptions)) $old_exceptions = [];
                    }

                    if ($exceptions !== $old_exceptions) {
                        // Guardar whitelist nueva
                        if (!file_exists(dirname($exceptions_path))) {
                            wp_mkdir_p(dirname($exceptions_path));
                        }
                        file_put_contents(
                            $exceptions_path,
                            json_encode($exceptions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                        );

                        // Logging activity only if whitelist was changed
                        $user = wp_get_current_user();
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                        $timestamp = current_time('mysql');
                        $log_entry = sprintf(
                            "[%s] Modified by: %s (%s) | IP: %s\n",
                            $timestamp,
                            $user->display_name,
                            $user->user_login,
                            $ip
                        );
                        foreach ($exceptions as $e) {
                            $log_entry .= "- {$e}\n";
                        }
                        $log_entry .= str_repeat("-", 50) . "\n";
                        file_put_contents($log_path, $log_entry, FILE_APPEND);

                        echo '<div class="notice notice-success"><p>Whitelist updated successfully.</p></div>';

                        $current_exceptions = $exceptions;
                    } else {
                        // No changes
                        echo '<div class="notice notice-info"><p>No changes detected in whitelist.</p></div>';
                        $current_exceptions = $exceptions;
                    }
                }
            }
        }
    } else {
        $current_exceptions = [];
        if (file_exists($exceptions_path)) {
            $json = file_get_contents($exceptions_path);
            $current_exceptions = json_decode($json, true);
            if (!is_array($current_exceptions)) $current_exceptions = [];
        }
    }

    // HTML Form
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
}
