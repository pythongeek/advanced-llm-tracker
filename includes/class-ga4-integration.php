<?php
/**
 * GA4 Integration for Advanced LLM Tracker
 *
 * @package Advanced_LLM_Tracker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ALLMT_GA4_Integration
 *
 * Handles Google Analytics 4 integration.
 */
class ALLMT_GA4_Integration {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'allmt_high_confidence_bot_detected', array( $this, 'send_bot_event' ), 10, 2 );
    }

    /**
     * Send bot detection event to GA4
     *
     * @param string $session_id Session ID.
     * @param array  $classification Classification data.
     * @return void
     */
    public function send_bot_event( string $session_id, array $classification ): void {
        $measurement_id = get_option( 'allmt_ga4_measurement_id', '' );
        $api_secret     = get_option( 'allmt_ga4_api_secret', '' );

        if ( empty( $measurement_id ) || empty( $api_secret ) ) {
            return;
        }

        $url = "https://www.google-analytics.com/mp/collect?measurement_id={$measurement_id}&api_secret={$api_secret}";

        $payload = array(
            'client_id' => $session_id,
            'events'    => array(
                array(
                    'name'   => 'bot_detected',
                    'params' => array(
                        'bot_category'    => $classification['category'] ?? 'unknown',
                        'bot_confidence'  => $classification['confidence'] ?? 0,
                        'bot_method'      => $classification['method'] ?? 'unknown',
                        'session_id'      => $session_id,
                    ),
                ),
            ),
        );

        wp_remote_post( $url, array(
            'body'    => wp_json_encode( $payload ),
            'headers' => array( 'Content-Type' => 'application/json' ),
        ) );
    }
}
