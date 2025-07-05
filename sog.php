<?php
/**
 * Plugin Name: SOG
 * Plugin URI:        
 * Description: Protect your visitors by displaying a customizable warning modal whenever they click external links.
 * Version:           1.0.3
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Author: Agustin S
 * Author URI:        
 * License:           MIT License
 * License URI:       https://opensource.org/license/mit
 * Text Domain:       sog
 * Domain Path:       /languages
 */

// Main 
function sog_enqueue_scripts() {
    $plugin_url = plugin_dir_url(__FILE__);

    wp_enqueue_style('sog-style', $plugin_url . 'css/sog-style.css', [], time());
    wp_enqueue_script('sog-script', $plugin_url . 'js/sog-script.js', [], time(), true);

    wp_localize_script('sog-script', 'sog_ajax', [
        'ajax_url'   => admin_url('admin-ajax.php'),
        'plugin_url' => $plugin_url,
        'nonce'      => wp_create_nonce('sog_log_nonce'),
        'whitelist_url' => content_url('uploads/sog/exceptions.json') //For excptions.json in js script
    ]);

    // Extra settings: rel options
    wp_localize_script('sog-script', 'sog_settings', [
        'rel_noopener'   => get_option('sog_add_rel_noopener', '1'),
        'rel_noreferrer' => get_option('sog_add_rel_noreferrer', '1'),
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

    $upload_dir = wp_upload_dir();
    $log_dir = trailingslashit($upload_dir['basedir']) . 'sog';

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
    $log_path = trailingslashit($upload_dir['basedir']) . 'sog/audit.log';

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
                    echo '<div class="notice notice-warning is-dismissible"><p>Audit log deleted.</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Cannot delete audit file: insufficient permissions</p></div>';
                }
            } else {
                echo '<div class="notice notice-info is-dismissible"><p>There is no audit history to delete.</p></div>';
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
    $success = false;

    if (!file_exists(dirname($token_file))) {
        wp_mkdir_p(dirname($token_file));
    }

    if (file_put_contents($token_file, $sanitized_token) !== false) {
        chmod($token_file, 0600);
        echo '<div class="notice notice-success is-dismissible"><p>IPInfo token saved successfully.</p></div>';
        $success = true;
    } else {
        echo '<div class="notice notice-error is-dismissible"><p> Error: Could not write IPInfo token file. Check file permissions in <code>' . esc_html(dirname($token_file)) . '</code>.</p></div>';
    }

    if ($success) {
        $current_token = $sanitized_token;
    }
}
	    //Save rel options
	    update_option('sog_add_rel_noopener', isset($_POST['sog_add_rel_noopener']) ? '1' : '0');
	    update_option('sog_add_rel_noreferrer', isset($_POST['sog_add_rel_noreferrer']) ? '1' : '0'); 

	    echo '<div class="notice notice-success is-dismissible"><p>Options for <code>rel="noopener"</code> and <code>rel="noreferrer"</code> were saved successfully.</p></div>';

	    $user = wp_get_current_user();
	    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
	    $timestamp = current_time('mysql');

	    $log_rel_entry = sprintf(
    		"[%s] Rel attribute options updated by: %s (%s) | IP: %s\n- noopener: %s\n- noreferrer: %s\n%s\n",
    		$timestamp,
    		$user->display_name,
    		$user->user_login,
    		$ip,
    		get_option('sog_add_rel_noopener') === '1' ? 'enabled' : 'disabled',
    		get_option('sog_add_rel_noreferrer') === '1' ? 'enabled' : 'disabled',
    		str_repeat("-", 50)
	    );

	    file_put_contents($log_path, $log_rel_entry, FILE_APPEND);

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
                    echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> The following values are not valid URLs or domains:</p><ul>';
                    foreach ($invalid as $bad) {
                        echo '<li><code>' . esc_html($bad) . '</code></li>';
                    }
                    echo '</ul><p>Changes were not saved.</p></div>';

		    //List reload from exceptions.json 
    		    if (file_exists($exceptions_path)) {
        		$json = file_get_contents($exceptions_path);
        		$old_exceptions = json_decode($json, true);
        		$current_exceptions = is_array($old_exceptions) ? $old_exceptions : [];
    		    } else {
        		$current_exceptions = [];
		   }
                } else {
                    // Read whitelist in order to compare
                    $old_exceptions = [];
                    if (file_exists($exceptions_path)) {
                        $json = file_get_contents($exceptions_path);
                        $old_exceptions = json_decode($json, true);
                        if (!is_array($old_exceptions)) $old_exceptions = [];
                    }

                    if ($exceptions !== $old_exceptions) {
                        // Save new whitelist
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

                        echo '<div class="notice notice-success is-dismissible"><p>Whitelist updated successfully.</p></div>';

                        $current_exceptions = $exceptions;
                    } else {
                        // No changes
                        echo '<div class="notice notice-info is-dismissible"><p>No changes detected in whitelist.</p></div>';
                        $current_exceptions = $exceptions;
                    }
                }
            }
        }
    } else {
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
   // Include template
   include plugin_dir_path(__FILE__) . 'inc/settings-page.php';
}

// Go to settings SOG section from Wordpress plugin page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'sog_add_settings_link');

function sog_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=sog') . '">' . __('Settings', 'sog') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

//Disable plugin SOG from Wordpress Plugin page
// Cleanup
register_deactivation_hook(__FILE__, 'sog_on_deactivation');

function sog_on_deactivation() {
    // Delete token
    delete_option('sog_ipinfo_token');

    //Delete rel options
    delete_option('sog_add_rel_noopener');
    delete_option('sog_add_rel_noreferrer');

    // Delete files
    $upload_dir = wp_upload_dir();
    $sog_dir = trailingslashit($upload_dir['basedir']) . 'sog';

    if (is_dir($sog_dir)) {
        $files = glob($sog_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($sog_dir); // Delete folder if it is empty
    }
}

//Dismissible messages
add_action('admin_enqueue_scripts', function() {
    wp_enqueue_script('wp-dismiss-notice');
});

//CSS for inc/settings-page.php
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

