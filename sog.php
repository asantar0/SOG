<?php
/**
 * Plugin Name: SOG
 * Description: Protect your visitors by displaying a customizable warning modal whenever they click external links.
 * Version: 1.0
 * Author: Agustin S
 */

function sog_enqueue_scripts() {
    $plugin_url = plugin_dir_url(__FILE__);

    wp_enqueue_style('sog-style', $plugin_url . 'sog-style.css', [], time());
    wp_enqueue_script('sog-script', $plugin_url . 'sog-script.js', [], time(), true);

    wp_localize_script('sog-script', 'sog_ajax', [
        'ajax_url'   => admin_url('admin-ajax.php'),
        'plugin_url' => $plugin_url
    ]);
}
add_action('wp_enqueue_scripts', 'sog_enqueue_scripts');
