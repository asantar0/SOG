<?php
/**
 * Plugin Name: SOG
 * Description: Protect your visitors by displaying a customizable warning modal whenever they click external links.
 * Version: 1.0.1
 * Author: Agustin S
 */

function sog_enqueue_scripts() {
    $plugin_url = plugin_dir_url(__FILE__);

    wp_enqueue_style('sog-style', $plugin_url . 'sog-style.css', [], time());
    wp_enqueue_script('sog-script', $plugin_url . 'sog-script.js', [], time(), true);

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
    $token = 'api-token';
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
