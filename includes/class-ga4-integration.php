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
     * Measurement ID
     *
     * @var string
     */
    private $measurement_id;

    /**
     * API Secret
     *
     * @var string
     */
    private $api_secret;

    /**
     * Constructor
     */
    public function __construct() {
        $this->measurement_id = get_option( 'allmt_ga4_measurement_id', '' );
        $this->api_secret     = get_option( 'allmt_ga4_api_secret', '' );

        add_action( 'allmt_bot_detected', array( $this, 'send_bot_event' ), 10, 2 );
    }

    /**
     * Send bot detection event to GA4
     *
     * @param string $session_id Session ID.
     * @param array  $classification Classification data.
     * @return void
     */
    public function send_bot_event( string $session_id, array $classification ): void {
        if ( empty( $this->measurement_id ) || empty( $this->api_secret ) ) {
            return;
        }

        $event_data = array(
            'client_id' => $session_id,
            'events'    => array(
                array(
                    'name'   => 'ai_bot_detected',
                    'params' => array(
                        'bot_category'       => $classification['category'] ?? 'unknown',
                        'confidence_score'   => $classification['confidence'] ?? 0,
                        'detection_method'   => $classification['method'] ?? 'unknown',
                        'bot_probability'    => $classification['bot_probability'] ?? 0,
                    ),
                ),
            ),
        );

        $url = sprintf(
            'https://www.google-analytics.com/mp/collect?measurement_id=%s&api_secret=%s',
            $this->measurement_id,
            $this->api_secret
        );

        wp_remote_post( $url, array(
            'body'    => wp_json_encode( $event_data ),
            'headers' => array( 'Content-Type' => 'application/json' ),
        ) );
    }
}
