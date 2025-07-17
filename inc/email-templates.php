<?php
/**
 * Plugin Name: SOG
 * Plugin URI:
 * Description: Protect your visitors by displaying a customizable warning modal whenever they click external links.
 * Version:           1.0.5
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
 * Email Templates for SOG Plugin
 * 
 * This file contains all the HTML email templates used by the SOG plugin
 * for sending notifications about configuration changes.
 */

function sog_get_email_template_from_file($type, $data) {
    $site_name = get_bloginfo('name');
    $site_url = get_bloginfo('url');
    
    $base_template = sog_get_base_email_template($site_name, $site_url);
    
    switch ($type) {
        case 'email_enabled':
            $content = sog_get_email_enabled_content($data);
            break;
            
        case 'email_disabled':
            $content = sog_get_email_disabled_content($data);
            break;
            
        case 'configuration_changed':
            $content = sog_get_configuration_changed_content($data);
            break;
            
        default:
            $content = '<p>Unknown email template type.</p>';
    }
    
    return str_replace('{{CONTENT}}', $content, $base_template);
}

/**
 * Get the base email template with styles.
 */
function sog_get_base_email_template($site_name, $site_url) {
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>SOG Plugin Notification</title>
        <style>
            body { 
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
                background-color: #f4f4f4; 
            }
            .container { 
                max-width: 600px; 
                margin: 20px auto; 
                background: white; 
                border-radius: 8px; 
                box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
                overflow: hidden; 
            }
            .header { 
                background: linear-gradient(135deg, #002147 0%, #004a94 100%); 
                color: white; 
                padding: 20px; /* Reducido de 30px */
                text-align: center; 
            }
            .header h1 { 
                margin: 0; 
                font-size: 24px; 
                font-weight: 600; 
                color: #111; /* Cambiado a negro */
            }
            .header .subtitle { 
                margin: 10px 0 0 0; 
                opacity: 0.9; 
                font-size: 14px; 
                color: #111; /* Cambiado a negro */
            }
            .content { 
                padding: 30px; 
            }
            .info-grid { 
                display: grid; 
                grid-template-columns: 1fr 1fr; 
                gap: 30px; /* Aumentado de 25px para más separación */
                margin: 25px 0; 
            }
            .info-item { 
                background: #f8f9fa; 
                padding: 20px; 
                border-radius: 6px; 
                border-left: 4px solid #002147; 
                margin-bottom: 15px; /* Agregado margen inferior */
            }
            .info-label { 
                font-weight: 600; 
                color: #002147; 
                font-size: 12px; 
                text-transform: uppercase; 
                letter-spacing: 0.5px; 
                margin-bottom: 5px; 
            }
            .info-value { 
                font-size: 14px; 
                color: #333; 
            }
            .changes-section { 
                margin: 25px 0; 
            }
            .changes-title { 
                font-size: 18px; 
                font-weight: 600; 
                color: #002147; 
                margin-bottom: 15px; 
                border-bottom: 2px solid #e9ecef; 
                padding-bottom: 10px; 
            }
            .change-item { 
                background: #f8f9fa; 
                margin: 10px 0; 
                padding: 15px; 
                border-radius: 6px; 
                border-left: 4px solid #28a745; 
            }
            .change-label { 
                font-weight: 600; 
                color: #28a745; 
                margin-bottom: 5px; 
            }
            .change-value { 
                color: #333; 
            }
            .whitelist-item { 
                background: #e8f4fd; 
                margin: 5px 0; 
                padding: 8px 12px; 
                border-radius: 4px; 
                font-family: monospace; 
                font-size: 13px; 
                color: #0056b3; 
            }
            .footer { 
                background: #f8f9fa; 
                padding: 20px; 
                text-align: center; 
                color: #6c757d; 
                font-size: 12px; 
                border-top: 1px solid #e9ecef; 
            }
            .no-reply { 
                color: #dc3545; 
                font-weight: 600; 
                margin-bottom: 10px; 
            }
            .status-badge { 
                display: inline-block; 
                padding: 4px 8px; 
                border-radius: 12px; 
                font-size: 11px; 
                font-weight: 600; 
                text-transform: uppercase; 
            }
            .status-enabled { 
                background: #d4edda; 
                color: #155724; 
            }
            .status-disabled { 
                background: #f8d7da; 
                color: #721c24; 
            }
            .status-updated { 
                background: #d1ecf1; 
                color: #0c5460; 
            }
            .color-preview { 
                display: inline-block; 
                width: 20px; 
                height: 20px; 
                border-radius: 3px; 
                vertical-align: middle; 
                margin-right: 8px; 
                border: 1px solid #ddd; 
            }
            @media (max-width: 600px) {
                .info-grid { 
                    grid-template-columns: 1fr; 
                }
                .container { 
                    margin: 10px; 
                }
                .content { 
                    padding: 20px; 
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>SOG Plugin</h1>
                <p class="subtitle">Secure Outbound Gateway</p>
            </div>
            <div class="content">
                {{CONTENT}}
            </div>
            <div class="footer">
                <p class="no-reply"> Please do not reply to this email</p>
                <p>This notification was sent from <strong>' . esc_html($site_name) . '</strong></p>
                <p><a href="' . esc_url($site_url) . '" style="color: #6c757d;">' . esc_url($site_url) . '</a></p>
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Get content for email enabled notification.
 */
function sog_get_email_enabled_content($data) {
    return '
        <h2 style="color: #28a745; margin-top: 0;">Email Notifications Enabled</h2>
        <p>The email notifications for the SOG plugin have been <strong>enabled</strong>.</p>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Date & Time</div>
                <div class="info-value">' . esc_html($data['timestamp']) . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">User</div>
                <div class="info-value">' . esc_html($data['user_name']) . ' (' . esc_html($data['user_login']) . ')</div>
            </div>
            <div class="info-item">
                <div class="info-label">IP Address</div>
                <div class="info-value">' . esc_html($data['ip']) . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">Status</div>
                <div class="info-value"><span class="status-badge status-enabled">Enabled</span></div>
            </div>
        </div>';
}

/**
 * Get content for email disabled notification.
 */
function sog_get_email_disabled_content($data) {
    return '
        <h2 style="color: #dc3545; margin-top: 0;">Email Notifications Disabled</h2>
        <p>The email notifications for the SOG plugin have been <strong>disabled</strong>.</p>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Date & Time</div>
                <div class="info-value">' . esc_html($data['timestamp']) . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">User</div>
                <div class="info-value">' . esc_html($data['user_name']) . ' (' . esc_html($data['user_login']) . ')</div>
            </div>
            <div class="info-item">
                <div class="info-label">IP Address</div>
                <div class="info-value">' . esc_html($data['ip']) . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">Status</div>
                <div class="info-value"><span class="status-badge status-disabled">Disabled</span></div>
            </div>
        </div>';
}

/**
 * Get content for configuration changed notification.
 */
function sog_get_configuration_changed_content($data) {
    $changes_html = '';
    
    // Token changes
    if (isset($data['changes']['token'])) {
        $changes_html .= '
            <div class="change-item">
                <div class="change-label">IPInfo Token</div>
                <div class="change-value"><span class="status-badge status-updated">Updated</span></div>
            </div>';
    }
    
    // Modal title changes
    if (isset($data['changes']['modal_title'])) {
        $changes_html .= '
            <div class="change-item">
                <div class="change-label">Modal Title</div>
                <div class="change-value">' . esc_html($data['changes']['modal_title']) . '</div>
            </div>';
    }
    
    // Continue button color changes
    if (isset($data['changes']['continue_color'])) {
        $changes_html .= '
            <div class="change-item">
                <div class="change-label">Continue Button Color</div>
                <div class="change-value">
                    <span class="color-preview" style="background-color: ' . esc_attr($data['changes']['continue_color']) . ';"></span>
                    ' . esc_html($data['changes']['continue_color']) . '
                </div>
            </div>';
    }
    
    // Cancel button color changes
    if (isset($data['changes']['cancel_color'])) {
        $changes_html .= '
            <div class="change-item">
                <div class="change-label">Cancel Button Color</div>
                <div class="change-value">
                    <span class="color-preview" style="background-color: ' . esc_attr($data['changes']['cancel_color']) . ';"></span>
                    ' . esc_html($data['changes']['cancel_color']) . '
                </div>
            </div>';
    }
    
    // Rel attributes changes
    if (isset($data['changes']['rel_attributes'])) {
        foreach ($data['changes']['rel_attributes'] as $attr => $enabled) {
            $status = $enabled ? 'Enabled' : 'Disabled';
            $color = $enabled ? '#28a745' : '#dc3545';
            $changes_html .= '
                <div class="change-item">
                    <div class="change-label">rel="' . esc_html($attr) . '"</div>
                    <div class="change-value"><span class="status-badge" style="background: ' . $color . '; color: white;">' . $status . '</span></div>
                </div>';
        }
    }
    
    // Whitelist changes
    if (isset($data['changes']['whitelist'])) {
        $whitelist_html = '';
        foreach ($data['changes']['whitelist'] as $entry) {
            $whitelist_html .= '<div class="whitelist-item">' . esc_html($entry) . '</div>';
        }
        
        $changes_html .= '
            <div class="change-item">
                <div class="change-label">Whitelist (' . count($data['changes']['whitelist']) . ' entries)</div>
                <div class="change-value">' . $whitelist_html . '</div>
            </div>';
    }
    
    return '
        <h2 style="color: #002147; margin-top: 0;">Configuration Changed</h2>
        <p>A change has been made to the Secure Outbound Gateway plugin configuration.</p>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Date & Time</div>
                <div class="info-value">' . esc_html($data['timestamp']) . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">User</div>
                <div class="info-value">' . esc_html($data['user_name']) . ' (' . esc_html($data['user_login']) . ')</div>
            </div>
            <div class="info-item">
                <div class="info-label">IP Address</div>
                <div class="info-value">' . esc_html($data['ip']) . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">Changes</div>
                <div class="info-value">' . count($data['changes']) . ' item(s)</div>
            </div>
        </div>
        
        <div class="changes-section">
            <div class="changes-title">Changes Detected</div>
            ' . $changes_html . '
        </div>';
} 
