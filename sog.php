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

/**
 * Enqueue frontend styles and scripts, and localize variables.
 */
function sog_enqueue_scripts() {
    $plugin_url = plugin_dir_url(__FILE__);

    wp_enqueue_style('sog-style', $plugin_url . 'css/sog-style.css', [], time());
    wp_enqueue_script('sog-script', $plugin_url . 'js/sog-script.js', [], time(), true);

    // Localize AJAX URLs, nonce and whitelist URL for JS
    wp_localize_script('sog-script', 'sog_ajax', [
        'ajax_url'      => admin_url('admin-ajax.php'),
        'plugin_url'    => $plugin_url,
        'nonce'         => wp_create_nonce('sog_log_nonce'),
        'whitelist_url' => content_url('uploads/sog/exceptions.json') // exceptions.json for JS
    ]);

    // Localize settings for rel attributes
    wp_localize_script('sog-script', 'sog_settings', [
        'rel_noopener'   => get_option('sog_add_rel_noopener', '1'),
        'rel_noreferrer' => get_option('sog_add_rel_noreferrer', '1'),
    ]);

    // Localize custom appearance options
    wp_localize_script('sog-script', 'sog_custom', [
        'modal_title'    => get_option('sog_modal_title', 'Warning notice'),
        'continue_color' => get_option('sog_continue_color', '#28a745'),
        'cancel_color'   => get_option('sog_cancel_color', '#dc3545'),
    ]);

    // Localization strings for UI text
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


/**
 * Handle AJAX requests to log clicks on external links.
 */
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

    // Anonymize IP address
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

    // IP Geolocation using ipinfo.io
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

    $log_entry = json_encode([
        'timestamp' => $timestamp,
        'ip'        => $ip_display,
        'country'   => $country,
        'action'    => $action_type,
        'url'       => $url
    ]) . "\n";

    $upload_dir = wp_upload_dir();
    $log_dir = trailingslashit($upload_dir['basedir']) . 'sog';

    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0750, true);
    }

    $log_file = $log_dir . '/sog-clicks.log';

    // Rotate log file if bigger than 1MB
    if (file_exists($log_file) && filesize($log_file) > 1024 * 1024) {
        rename($log_file, $log_file . '.' . time() . '.bak');
    }

    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    chmod($log_file, 0600);

    wp_send_json_success('Click logged');
}


/**
 * Load plugin textdomain for translations.
 */
add_action('plugins_loaded', 'sog_load_textdomain');
function sog_load_textdomain() {
    load_plugin_textdomain('sog', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}


/**
 * Add admin menu page for plugin settings.
 */
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


/**
 * Settings page content and logic.
 */
function sog_settings_page() {
    $upload_dir = wp_upload_dir();
    $exceptions_path = trailingslashit($upload_dir['basedir']) . 'sog/exceptions.json';
    $log_path = trailingslashit($upload_dir['basedir']) . 'sog/audit.log';

    $current_token = get_option('sog_ipinfo_token', '');

    // Handle form submissions
    if (
        isset($_POST['sog_token']) ||
        isset($_POST['sog_exceptions']) ||
        isset($_POST['sog_clear_log'])
    ) {
        // Clear audit log
        if (isset($_POST['sog_clear_log']) && check_admin_referer('sog_clear_log')) {
            if (file_exists($log_path)) {
                if (is_writable($log_path)) {
                    unlink($log_path);
                    echo '<div class="notice notice-warning is-dismissible"><p>Audit log deleted.</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Cannot delete audit file: insufficient permissions</p></div>';
                }
            } else {
                echo '<div class="notice notice-info is-dismissible"><p>There is no audit history to delete.</p></div>';
            }
        }

        // Save settings (token, whitelist, rel options, modal appearance)
        if (
            (isset($_POST['sog_token']) || isset($_POST['sog_exceptions'])) &&
            check_admin_referer('sog_save_exceptions')
        ) {
            // Save IPInfo token
            if (isset($_POST['sog_token'])) {
                $sanitized_token = sanitize_text_field($_POST['sog_token']);
                update_option('sog_ipinfo_token', $sanitized_token);

                // Save token to file in uploads/sog/
                $token_file = trailingslashit($upload_dir['basedir']) . 'sog/ipinfo.token';
                $success = false;

                if (!file_exists(dirname($token_file))) {
                    wp_mkdir_p(dirname($token_file));
                }

                if (file_put_contents($token_file, $sanitized_token) !== false) {
                    chmod($token_file, 0600);
                    echo '<div class="notice notice-success is-dismissible"><p>IPInfo token saved successfully.</p></div>';
                    $success = true;
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Error: Could not write IPInfo token file. Check file permissions in <code>' . esc_html(dirname($token_file)) . '</code>.</p></div>';
                }

                if ($success) {
                    $current_token = $sanitized_token;
                }
            }

            // Save rel options
            update_option('sog_add_rel_noopener', isset($_POST['sog_add_rel_noopener']) ? '1' : '0');
            update_option('sog_add_rel_noreferrer', isset($_POST['sog_add_rel_noreferrer']) ? '1' : '0');
            echo '<div class="notice notice-success is-dismissible"><p>Options for <code>rel="noopener"</code> and <code>rel="noreferrer"</code> were saved successfully.</p></div>';

            // Save modal title
            if (isset($_POST['sog_modal_title'])) {
                $title = sanitize_text_field($_POST['sog_modal_title']);
                update_option('sog_modal_title', $title);
            }

            // Save button colors
            if (isset($_POST['sog_continue_color'])) {
                $continue_color = sanitize_hex_color($_POST['sog_continue_color']);
                update_option('sog_continue_color', $continue_color);
            }

            if (isset($_POST['sog_cancel_color'])) {
                $cancel_color = sanitize_hex_color($_POST['sog_cancel_color']);
                update_option('sog_cancel_color', $cancel_color);
            }

            echo '<div class="notice notice-success is-dismissible"><p>Modal appearance settings saved successfully.</p></div>';

            // Send mail when changes were detected.
            $was_email_enabled       = get_option('sog_email_enabled', '0');
            $new_email_enabled       = isset($_POST['sog_email_enabled']) ? '1' : '0';
            $email_enabled_just_now  = ($was_email_enabled === '0' && $new_email_enabled === '1');
            $email_disabled_just_now = ($was_email_enabled === '1' && $new_email_enabled === '0');

            update_option('sog_email_enabled', $new_email_enabled);

            if ($email_enabled_just_now) {
                add_settings_error(
                    'sog_email_enabled',
                    'sog_email_enabled_enabled',
                    'Email notifications have been <strong>enabled</strong>.',
                    'updated'
                );
            } elseif ($email_disabled_just_now) {
                add_settings_error(
                    'sog_email_enabled',
                    'sog_email_enabled_disabled',
                    'Email notifications have been <strong>disabled</strong>.',
                    'warning'
                );
            }


            $admin_email = get_option('admin_email');
            $user        = wp_get_current_user();
            $ip          = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $timestamp   = current_time('mysql');
            $headers     = ['Content-Type: text/html; charset=UTF-8'];
            $sent        = false;

            if ($email_enabled_just_now) {
                $subject = 'SOG Plugin - Email notifications enabled';
                $body  = "<p>The email notifications for the SOG plugin have been <strong>enabled</strong>.</p>";
                $body .= "<p><strong>Date:</strong> $timestamp<br>";
                $body .= "<strong>User:</strong> {$user->display_name} ({$user->user_login})<br>";
                $body .= "<strong>IP:</strong> $ip</p>";
                $sent = wp_mail($admin_email, $subject, $body, $headers);

            } elseif ($email_disabled_just_now) {
                $subject = 'SOG Plugin - Email notifications disabled';
                $body  = "<p>The email notifications for the SOG plugin have been <strong>disabled</strong>.</p>";
                $body .= "<p><strong>Date:</strong> $timestamp<br>";
                $body .= "<strong>User:</strong> {$user->display_name} ({$user->user_login})<br>";
                $body .= "<strong>IP:</strong> $ip</p>";
                $sent = wp_mail($admin_email, $subject, $body, $headers);

            } elseif (get_option('sog_email_enabled', '1') === '1') {
                $subject = 'SOG Plugin - Configuration changed';
                $body  = "<p>A change has been made to the Secure Outbound Gateway plugin configuration.</p>";
                $body .= "<p><strong>Date:</strong> $timestamp<br>";
                $body .= "<strong>User:</strong> {$user->display_name} ({$user->user_login})<br>";
                $body .= "<strong>IP:</strong> $ip</p>";
                $body .= "<p><strong>Changes detected:</strong><ul>";

                if (isset($_POST['sog_token'])) {
                    $body .= "<li><strong>Token IPInfo:</strong> " . esc_html(sanitize_text_field($_POST['sog_token'])) . "</li>";
                }
                if (isset($_POST['sog_modal_title'])) {
                    $body .= "<li><strong>Modal title:</strong> " . esc_html(sanitize_text_field($_POST['sog_modal_title'])) . "</li>";
                }
                if (isset($_POST['sog_continue_color'])) {
                    $body .= "<li><strong>Continue button color:</strong> " . esc_html(sanitize_hex_color($_POST['sog_continue_color'])) . "</li>";
                }
                if (isset($_POST['sog_cancel_color'])) {
                    $body .= "<li><strong>Cancel button color:</strong> " . esc_html(sanitize_hex_color($_POST['sog_cancel_color'])) . "</li>";
                }
                if (isset($_POST['sog_add_rel_noopener']) || isset($_POST['sog_add_rel_noreferrer'])) {
                    $body .= "<li><strong>rel=\"noopener\"</strong>: " . (isset($_POST['sog_add_rel_noopener']) ? 'Yes' : 'No') . "</li>";
                    $body .= "<li><strong>rel=\"noreferrer\"</strong>: " . (isset($_POST['sog_add_rel_noreferrer']) ? 'Yes' : 'No') . "</li>";
                }
                if (isset($_POST['sog_exceptions'])) {
                    $raw   = sanitize_textarea_field($_POST['sog_exceptions']);
                    $lines = array_filter(array_map('trim', explode("\n", $raw)));
                    $body .= "<li><strong>Whitelist updated:</strong><ul>";
                    foreach ($lines as $line) {
                        $body .= "<li>" . esc_html($line) . "</li>";
                    }
                    $body .= "</ul></li>";
                }

                $body .= "</ul></p>";
                $sent = wp_mail($admin_email, $subject, $body, $headers);
            }

            $log_msg = sprintf(
                "[%s] Email notification: %s\nSubject: %s\nBody:\n%s\n%s\n%s\n",
                $timestamp,
                $sent ? 'SENT' : 'NOT SENT',
                $subject ?? '(none)',
                strip_tags($body ?? '(empty)'),
                implode(', ', $headers),
                str_repeat('-', 50)
            );
            file_put_contents($log_path, $log_msg, FILE_APPEND);

            // Log rel options update
	    $log_entry = [
                'type'      => 'rel_attribute_update',
                'timestamp' => $timestamp,
                'user'      => [
                    'display_name' => $user->display_name,
                    'user_login'   => $user->user_login,
                    'ip'           => $ip,
                ],
                'rel_attributes' => [
                    'noopener'   => get_option('sog_add_rel_noopener') === '1',
                    'noreferrer' => get_option('sog_add_rel_noreferrer') === '1',
                ]
            ];
            file_put_contents($log_path, json_encode($log_entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);

            // Process whitelist exceptions
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
                    echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> The following values are not valid URLs or domains:</p><ul>';
                    foreach ($invalid as $bad) {
                        echo '<li><code>' . esc_html($bad) . '</code></li>';
                    }
                    echo '</ul><p>Changes were not saved.</p></div>';

                    // Reload old exceptions from file if invalid input
                    if (file_exists($exceptions_path)) {
                        $json = file_get_contents($exceptions_path);
                        $old_exceptions = json_decode($json, true);
                        $current_exceptions = is_array($old_exceptions) ? $old_exceptions : [];
                    } else {
                        $current_exceptions = [];
                    }
                } else {
                    // Compare with old exceptions before saving
                    $old_exceptions = [];
                    if (file_exists($exceptions_path)) {
                        $json = file_get_contents($exceptions_path);
                        $old_exceptions = json_decode($json, true);
                        if (!is_array($old_exceptions)) {
                            $old_exceptions = [];
                        }
                    }

                    if ($exceptions !== $old_exceptions) {
                        // Save new exceptions to JSON file
                        if (!file_exists(dirname($exceptions_path))) {
                            wp_mkdir_p(dirname($exceptions_path));
                        }
                        file_put_contents(
                            $exceptions_path,
                            json_encode($exceptions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                        );

                        // Log whitelist update
                        $log_entry = [
                            'type'      => 'whitelist_update',
                            'timestamp' => $timestamp,
                            'user'      => [
                                'display_name' => $user->display_name,
                                'user_login'   => $user->user_login,
                                'ip'           => $ip,
                            ],
                            'whitelist' => $exceptions,
                        ];
                        file_put_contents($log_path, json_encode($log_entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);

                        echo '<div class="notice notice-success is-dismissible"><p>Whitelist updated successfully.</p></div>';

                        $current_exceptions = $exceptions;

                    } else {
                        echo '<div class="notice notice-info is-dismissible"><p>No changes detected in whitelist.</p></div>';
                        $current_exceptions = $exceptions;
                    }
                }
            }
        }
    } else {
        // Load exceptions from JSON file if no form submission
        $current_exceptions = [];

        if (file_exists($exceptions_path) && is_readable($exceptions_path)) {
            $json = file_get_contents($exceptions_path);

            $decoded = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $current_exceptions = $decoded;
            } elseif (strlen(trim($json)) > 0) {
                echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong>The <code>exceptions.json</code> file exists, but it is not a valid JSON file. It may be corrupted or have been manually edited incorrectly.</p></div>';
            }
        }
    }

    // Ensure exceptions is always an array before loading the settings page template
    if (!is_array($current_exceptions)) {
        $current_exceptions = [];
    }

    // Show admin messages (success/error)
    settings_errors();


    // Include admin settings page template
    include plugin_dir_path(__FILE__) . 'inc/settings-page.php';
}


/**
 * Add a Settings link on the Plugins page.
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'sog_add_settings_link');

function sog_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=sog') . '">' . __('Settings', 'sog') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}


/**
 * Cleanup on plugin deactivation: remove options and files.
 */
register_deactivation_hook(__FILE__, 'sog_on_deactivation');

function sog_on_deactivation() {
    // Delete saved options
    delete_option('sog_ipinfo_token');
    delete_option('sog_add_rel_noopener');
    delete_option('sog_add_rel_noreferrer');

    // Delete plugin files in uploads/sog/
    $upload_dir = wp_upload_dir();
    $sog_dir = trailingslashit($upload_dir['basedir']) . 'sog';

    if (is_dir($sog_dir)) {
        $files = glob($sog_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($sog_dir); // Remove folder if empty
    }
}


/**
 * Enqueue dismissible notice script in admin.
 */
add_action('admin_enqueue_scripts', function() {
    wp_enqueue_script('wp-dismiss-notice');
});


/**
 * Enqueue admin CSS only on plugin settings page.
 */
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'settings_page_sog') {
        wp_enqueue_style(
            'sog-admin-style',
            plugin_dir_url(__FILE__) . 'css/sog-style.css',
            [],
            filemtime(plugin_dir_path(__FILE__) . 'css/sog-style.css')
        );
    }
});
