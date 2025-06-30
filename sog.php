<?php
/**
 * Plugin Name: SOG
 * Description: Protect your visitors by displaying a customizable warning modal whenever they click external links 
 * Version: 1.0
 * Author: Agustin S
 */

function sog_enqueue_scripts() {
    wp_enqueue_script('sog-script', plugin_dir_url(__FILE__) . 'sog-script.js', [], time(), true);
    wp_enqueue_style('sog-style', plugin_dir_url(__FILE__) . 'sog-style.css', [], time());
    wp_localize_script('sog-script', 'sog_ajax', [
        'ajax_url' => admin_url('admin-ajax.php')
    ]);
}
add_action('wp_enqueue_scripts', 'sog_enqueue_scripts');
