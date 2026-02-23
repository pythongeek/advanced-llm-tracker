<?php
/**
 * Tracker for Advanced LLM Tracker
 *
 * @package Advanced_LLM_Tracker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ALLMT_Tracker
 *
 * Handles tracking functionality.
 */
class ALLMT_Tracker {

    /**
     * Early initialization
     *
     * @return void
     */
    public static function early_init(): void {
        // Early tracking initialization
        if ( ! get_option( 'allmt_enabled', true ) ) {
            return;
        }

        // Log page request
        self::log_page_request();
    }

    /**
     * Log page request
     *
     * @return void
     */
    private static function log_page_request(): void {
        // This is handled by the JavaScript SDK for client-side tracking
        // Server-side tracking can be added here if needed
    }

    /**
     * Get tracking script
     *
     * @return string
     */
    public static function get_tracking_script(): string {
        $session_id = self::get_session_id();
        
        ob_start();
        ?>
        <script>
        window.ALLMT_SDK_CONFIG = {
            endpoint: '<?php echo esc_url( rest_url( 'allmt/v1/track' ) ); ?>',
            nonce: '<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>',
            sessionId: '<?php echo esc_attr( $session_id ); ?>',
            samplingRate: <?php echo intval( get_option( 'allmt_sampling_rate', 100 ) ); ?>,
            detailedTrackingRate: <?php echo intval( get_option( 'allmt_detailed_tracking_rate', 10 ) ); ?>,
            enableMouseTracking: <?php echo get_option( 'allmt_enable_mouse_tracking', true ) ? 'true' : 'false'; ?>,
            enableScrollTracking: <?php echo get_option( 'allmt_enable_scroll_tracking', true ) ? 'true' : 'false'; ?>,
            consentRequired: <?php echo get_option( 'allmt_enable_consent', true ) ? 'true' : 'false'; ?>,
        };
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Get or create session ID
     *
     * @return string
     */
    public static function get_session_id(): string {
        $cookie_name = 'allmt_session';

        if ( isset( $_COOKIE[ $cookie_name ] ) ) {
            return sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
        }

        $session_id = bin2hex( random_bytes( 32 ) );

        setcookie(
            $cookie_name,
            $session_id,
            array(
                'expires'  => 0,
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            )
        );

        return $session_id;
    }
}
