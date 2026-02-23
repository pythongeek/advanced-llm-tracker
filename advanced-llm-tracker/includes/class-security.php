<?php
/**
 * Security handler for Advanced LLM Tracker
 *
 * @package Advanced_LLM_Tracker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ALLMT_Security
 *
 * Handles security features.
 */
class ALLMT_Security {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'init', array( $this, 'init' ) );
    }

    /**
     * Initialize security features
     *
     * @return void
     */
    public function init(): void {
        // Add security headers
        add_action( 'send_headers', array( $this, 'add_security_headers' ) );

        // Log suspicious activity
        add_action( 'allmt_suspicious_activity', array( $this, 'log_suspicious_activity' ), 10, 2 );
    }

    /**
     * Add security headers
     *
     * @return void
     */
    public function add_security_headers(): void {
        // Prevent clickjacking
        header( 'X-Frame-Options: SAMEORIGIN' );

        // XSS protection
        header( 'X-XSS-Protection: 1; mode=block' );

        // Content type sniffing protection
        header( 'X-Content-Type-Options: nosniff' );

        // Referrer policy
        header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    }

    /**
     * Log suspicious activity
     *
     * @param string $type Activity type.
     * @param array  $data Activity data.
     * @return void
     */
    public function log_suspicious_activity( string $type, array $data ): void {
        global $wpdb;

        $audit_table = $wpdb->prefix . 'allmt_audit_log';

        $wpdb->insert(
            $audit_table,
            array(
                'event_type'   => 'security',
                'event_action' => $type,
                'user_id'      => get_current_user_id(),
                'ip_address'   => $this->get_client_ip(),
                'description'  => wp_json_encode( $data ),
                'user_agent'   => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
                'request_uri'  => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
                'created_at'   => current_time( 'mysql' ),
            )
        );
    }

    /**
     * Verify nonce
     *
     * @param string $nonce Nonce to verify.
     * @param string $action Action name.
     * @return bool
     */
    public function verify_nonce( string $nonce, string $action ): bool {
        return wp_verify_nonce( $nonce, $action ) !== false;
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip(): string {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        );

        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                $ips = explode( ',', $ip );
                foreach ( $ips as $single_ip ) {
                    $single_ip = trim( $single_ip );
                    if ( filter_var( $single_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                        return $single_ip;
                    }
                }
            }
        }

        return '0.0.0.0';
    }
}
