<?php
/**
 * Plugin Name:       Advanced LLM Tracker
 * Plugin URI:        https://codecanyon.net/item/advanced-llm-tracker
 * Description:       Next-gen AI/LLM bot detection with behavioral ML - Detects GPTBot, ClaudeBot, and sophisticated AI crawlers using machine learning
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Your Name
 * Author URI:        https://yourwebsite.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       advanced-llm-tracker
 * Domain Path:       /languages
 * Network:           true
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'ALLMT_VERSION', '1.0.0' );
define( 'ALLMT_PATH', plugin_dir_path( __FILE__ ) );
define( 'ALLMT_URL', plugin_dir_url( __FILE__ ) );
define( 'ALLMT_BASENAME', plugin_basename( __FILE__ ) );
define( 'ALLMT_MIN_PHP', '8.1' );
define( 'ALLMT_MIN_WP', '6.6' );

// Check PHP version - use plain strings to avoid textdomain loading issues
if ( version_compare( PHP_VERSION, ALLMT_MIN_PHP, '<' ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="error"><p>' . 
             esc_html( 'Advanced LLM Tracker requires PHP ' . ALLMT_MIN_PHP . ' or higher. Please upgrade your PHP version.' ) . 
             '</p></div>';
    } );
    return;
}

// Autoloader
require_once ALLMT_PATH . 'includes/class-autoloader.php';

// Register autoloader
if ( method_exists( 'ALLMT_Autoloader', 'register' ) ) {
    ALLMT_Autoloader::register();
}

// Activation hook
register_activation_hook( __FILE__, array( 'ALLMT_Installer', 'activate' ) );

// Deactivation hook
register_deactivation_hook( __FILE__, array( 'ALLMT_Installer', 'deactivate' ) );

// Uninstall hook
register_uninstall_hook( __FILE__, array( 'ALLMT_Installer', 'uninstall' ) );

// Initialize plugin - load textdomain at init, not before
add_action( 'plugins_loaded', array( 'ALLMT_Plugin', 'init' ), 10 );

// Early initialization for tracking (before theme loads)
add_action( 'init', array( 'ALLMT_Tracker', 'early_init' ), 1 );
