<?php
/*
Plugin Name: Cyber Social Auto-Poster
Plugin URI: https://cybersentrysolutions.com/plugins
Description: Automatically share posts to social platforms (Twitter/X, Facebook, LinkedIn, etc.) with custom schedules, image optimization, and hashtag generation. Includes unlimited accounts, content calendar, and post recycling for free.
Version: 1.0
Author: Rick Hayes
Author URI: https://cybersentrysolutions.com
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: cyber-social-auto-poster
Domain Path: /languages
*/

defined('ABSPATH') || exit;

// Define plugin constants
define('CSAP_VERSION', '1.0');
define('CSAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CSAP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include necessary files
require_once CSAP_PLUGIN_DIR . 'includes/class-social-poster.php';
require_once CSAP_PLUGIN_DIR . 'includes/class-settings.php';
require_once CSAP_PLUGIN_DIR . 'includes/class-scheduler.php';

// Initialize the plugin
function csap_init() {
    $social_poster = new CSAP_Social_Poster();
    $settings = new CSAP_Settings();
    $scheduler = new CSAP_Scheduler();
}
add_action('plugins_loaded', 'csap_init');

// Activation hook
function csap_activate() {
    // Set default options
    $default_options = array(
        'platforms' => array('twitter', 'facebook', 'linkedin'),
        'recycle_posts' => false,
        'default_hashtags' => '#WordPress #SocialMedia'
    );
    add_option('csap_options', $default_options);
}
register_activation_hook(__FILE__, 'csap_activate');

// Deactivation hook
function csap_deactivate() {
    // Cleanup if needed
}
register_deactivation_hook(__FILE__, 'csap_deactivate');
