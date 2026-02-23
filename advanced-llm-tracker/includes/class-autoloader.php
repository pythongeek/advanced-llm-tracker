<?php
/**
 * Autoloader for Advanced LLM Tracker
 *
 * @package Advanced_LLM_Tracker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ALLMT_Autoloader
 *
 * Handles autoloading of plugin classes.
 */
class ALLMT_Autoloader {

    /**
     * Plugin namespace prefix
     *
     * @var string
     */
    private static $prefix = 'ALLMT_';

    /**
     * Plugin classes directory
     *
     * @var string
     */
    private static $classes_dir = '';

    /**
     * Class mapping for non-standard class names
     *
     * @var array
     */
    private static $class_map = array();

    /**
     * Register the autoloader
     *
     * @return void
     */
    public static function register(): void {
        self::$classes_dir = ALLMT_PATH . 'includes/';
        
        // Define class mappings for special cases
        self::$class_map = array(
            'ALLMT_Plugin'        => 'class-plugin.php',
            'ALLMT_Installer'     => 'class-installer.php',
            'ALLMT_Tracker'       => 'class-tracker.php',
            'ALLMT_Feature_Extractor' => 'class-feature-extractor.php',
            'ALLMT_Classifier'    => 'class-classifier.php',
            'ALLMT_REST_API'      => 'class-rest-api.php',
            'ALLMT_Response_Engine' => 'class-response-engine.php',
            'ALLMT_Privacy'       => 'class-privacy.php',
            'ALLMT_Admin'         => 'class-admin.php',
            'ALLMT_Settings'      => 'class-settings.php',
            'ALLMT_Alerts'        => 'class-alerts.php',
            'ALLMT_ML_Engine'     => 'class-ml-engine.php',
            'ALLMT_Session'       => 'class-session.php',
            'ALLMT_Events'        => 'class-events.php',
            'ALLMT_Stats'         => 'class-stats.php',
            'ALLMT_Security'      => 'class-security.php',
            'ALLMT_Consent'       => 'class-consent.php',
            'ALLMT_GA4_Integration' => 'class-ga4-integration.php',
            'ALLMT_Cron'          => 'class-cron.php',
        );

        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }

    /**
     * Autoload callback
     *
     * @param string $class Class name to load.
     * @return void
     */
    public static function autoload( string $class ): void {
        // Only handle plugin classes
        if ( strpos( $class, self::$prefix ) !== 0 ) {
            return;
        }

        // Check class map first
        if ( isset( self::$class_map[ $class ] ) ) {
            $file = self::$classes_dir . self::$class_map[ $class ];
            if ( file_exists( $file ) ) {
                require_once $file;
            }
            return;
        }

        // Standard naming convention: class-{lowercase-hyphenated}.php
        $class_name = substr( $class, strlen( self::$prefix ) );
        $file_name  = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
        $file       = self::$classes_dir . $file_name;

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}
