<?php
/**
 * Settings handler for Advanced LLM Tracker
 *
 * @package Advanced_LLM_Tracker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ALLMT_Settings
 *
 * Handles plugin settings.
 */
class ALLMT_Settings {

    /**
     * Get a setting value
     *
     * @param string $key Setting key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public static function get( string $key, $default = null ) {
        $option_name = 'allmt_' . $key;
        return get_option( $option_name, $default );
    }

    /**
     * Set a setting value
     *
     * @param string $key Setting key.
     * @param mixed  $value Setting value.
     * @return bool
     */
    public static function set( string $key, $value ): bool {
        $option_name = 'allmt_' . $key;
        return update_option( $option_name, $value );
    }

    /**
     * Get all settings
     *
     * @return array
     */
    public static function get_all(): array {
        return array(
            'enabled'                  => self::get( 'enabled', true ),
            'tracking_mode'            => self::get( 'tracking_mode', 'standard' ),
            'sampling_rate'            => self::get( 'sampling_rate', 100 ),
            'detailed_tracking_rate'   => self::get( 'detailed_tracking_rate', 10 ),
            'detection_sensitivity'    => self::get( 'detection_sensitivity', 'medium' ),
            'min_confidence_threshold' => self::get( 'min_confidence_threshold', 0.75 ),
            'auto_block_threshold'     => self::get( 'auto_block_threshold', 0.95 ),
            'challenge_threshold'      => self::get( 'challenge_threshold', 0.80 ),
            'enable_consent'           => self::get( 'enable_consent', true ),
            'anonymize_ip'             => self::get( 'anonymize_ip', true ),
            'data_retention_days'      => self::get( 'data_retention_days', 90 ),
            'enable_alerts'            => self::get( 'enable_alerts', true ),
            'alert_email'              => self::get( 'alert_email', get_option( 'admin_email' ) ),
            'ml_enabled'               => self::get( 'ml_enabled', false ),
            'ga4_enabled'              => self::get( 'ga4_enabled', false ),
        );
    }
}
