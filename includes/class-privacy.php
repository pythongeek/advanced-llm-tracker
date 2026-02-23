<?php
/**
 * Privacy handler for Advanced LLM Tracker
 *
 * @package Advanced_LLM_Tracker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ALLMT_Privacy
 *
 * Handles privacy-related functionality including GDPR compliance.
 */
class ALLMT_Privacy {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'init', array( $this, 'init' ) );
    }

    /**
     * Initialize privacy features
     *
     * @return void
     */
    public function init(): void {
        // Register consent hooks
        add_action( 'wp_footer', array( $this, 'maybe_render_consent_banner' ), 100 );
        
        // Handle consent form submission
        add_action( 'wp_ajax_allmt_save_consent', array( $this, 'ajax_save_consent' ) );
        add_action( 'wp_ajax_nopriv_allmt_save_consent', array( $this, 'ajax_save_consent' ) );

        // Add privacy settings
        add_filter( 'allmt_settings_tabs', array( $this, 'add_privacy_settings_tab' ) );
    }

    /**
     * Maybe render consent banner
     *
     * @return void
     */
    public function maybe_render_consent_banner(): void {
        // Don't show if consent is not required
        if ( ! get_option( 'allmt_enable_consent', true ) ) {
            return;
        }

        // Don't show if already consented
        if ( isset( $_COOKIE['allmt_consent'] ) ) {
            return;
        }

        // Don't show for known bots
        if ( $this->is_bot_request() ) {
            return;
        }

        // Check for third-party consent plugin integration
        if ( $this->has_third_party_consent() ) {
            return;
        }

        $this->render_consent_banner();
    }

    /**
     * Check if request is from a bot
     *
     * @return bool
     */
    private function is_bot_request(): bool {
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        return (bool) preg_match( '/bot|crawler|spider/i', $user_agent );
    }

    /**
     * Check if third-party consent plugin is active
     *
     * @return bool
     */
    private function has_third_party_consent(): bool {
        // Check for WordPress Consent API
        if ( function_exists( 'wp_consent_api_initialize' ) ) {
            return true;
        }

        // Check for CookieYes
        if ( defined( 'CKY_PLUGIN_FILE' ) || function_exists( 'cookieyes_initialize' ) ) {
            return true;
        }

        // Check for OneTrust
        if ( get_option( 'onetrust_active' ) ) {
            return true;
        }

        // Check for Complianz
        if ( defined( 'CMPLZ_PLUGIN_FILE' ) ) {
            return true;
        }

        return false;
    }

    /**
     * Render consent banner
     *
     * @return void
     */
    private function render_consent_banner(): void {
        $banner_type = get_option( 'allmt_consent_banner_type', 'custom' );
        
        if ( $banner_type === 'custom' ) {
            $this->render_custom_banner();
        }
    }

    /**
     * Render custom consent banner
     *
     * @return void
     */
    private function render_custom_banner(): void {
        $position = get_option( 'allmt_consent_banner_position', 'bottom' );
        $theme    = get_option( 'allmt_consent_banner_theme', 'light' );
        
        $styles = $this->get_banner_styles( $position, $theme );
        
        ?>
        <style>
            #allmt-consent-banner {
                position: fixed;
                <?php echo esc_html( $position ); ?>: 0;
                left: 0;
                right: 0;
                z-index: 999999;
                padding: 20px;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                font-size: 14px;
                line-height: 1.5;
                box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
                <?php echo esc_html( $styles ); ?>
            }
            #allmt-consent-banner .allmt-consent-inner {
                max-width: 1200px;
                margin: 0 auto;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 20px;
            }
            #allmt-consent-banner .allmt-consent-text {
                flex: 1;
            }
            #allmt-consent-banner .allmt-consent-title {
                font-weight: 600;
                margin-bottom: 8px;
                font-size: 16px;
            }
            #allmt-consent-banner .allmt-consent-buttons {
                display: flex;
                gap: 10px;
                flex-shrink: 0;
            }
            #allmt-consent-banner button {
                padding: 10px 24px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                transition: opacity 0.2s;
            }
            #allmt-consent-banner button:hover {
                opacity: 0.9;
            }
            #allmt-consent-banner .allmt-btn-accept {
                background: #007cba;
                color: white;
            }
            #allmt-consent-banner .allmt-btn-decline {
                background: transparent;
                border: 1px solid currentColor;
            }
            #allmt-consent-banner .allmt-consent-link {
                text-decoration: underline;
                margin-left: 5px;
            }
            @media (max-width: 768px) {
                #allmt-consent-banner .allmt-consent-inner {
                    flex-direction: column;
                    text-align: center;
                }
                #allmt-consent-banner .allmt-consent-buttons {
                    width: 100%;
                    justify-content: center;
                }
            }
        </style>
        <div id="allmt-consent-banner" role="dialog" aria-label="<?php esc_attr_e( 'Consent Banner', 'advanced-llm-tracker' ); ?>">
            <div class="allmt-consent-inner">
                <div class="allmt-consent-text">
                    <div class="allmt-consent-title">
                        <?php esc_html_e( 'We value your privacy', 'advanced-llm-tracker' ); ?>
                    </div>
                    <div>
                        <?php 
                        echo esc_html__( 
                            'We use cookies and tracking technologies to detect AI bot traffic and improve your experience. ', 
                            'advanced-llm-tracker' 
                        );
                        
                        $privacy_page = get_option( 'wp_page_for_privacy_policy' );
                        if ( $privacy_page ) {
                            printf(
                                '<a href="%s" class="allmt-consent-link" target="_blank">%s</a>',
                                esc_url( get_permalink( $privacy_page ) ),
                                esc_html__( 'Learn more', 'advanced-llm-tracker' )
                            );
                        }
                        ?>
                    </div>
                </div>
                <div class="allmt-consent-buttons">
                    <button type="button" class="allmt-btn-decline" onclick="ALLMT_Consent.decline()">
                        <?php esc_html_e( 'Decline', 'advanced-llm-tracker' ); ?>
                    </button>
                    <button type="button" class="allmt-btn-accept" onclick="ALLMT_Consent.accept()">
                        <?php esc_html_e( 'Accept', 'advanced-llm-tracker' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <script>
            window.ALLMT_Consent = {
                accept: function() {
                    document.cookie = 'allmt_consent=granted; path=/; max-age=31536000; SameSite=Lax';
                    document.getElementById('allmt-consent-banner').style.display = 'none';
                    if (window.ALLMT_SDK) {
                        window.ALLMT_SDK.grantConsent();
                    }
                    this.notifyConsentChange('granted');
                },
                decline: function() {
                    document.cookie = 'allmt_consent=denied; path=/; max-age=31536000; SameSite=Lax';
                    document.getElementById('allmt-consent-banner').style.display = 'none';
                    if (window.ALLMT_SDK) {
                        window.ALLMT_SDK.revokeConsent();
                    }
                    this.notifyConsentChange('denied');
                },
                notifyConsentChange: function(consent) {
                    // Dispatch event for other scripts
                    window.dispatchEvent(new CustomEvent('allmt_consent_changed', { 
                        detail: { consent: consent } 
                    }));
                    
                    // Send to server
                    fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=allmt_save_consent&consent=' + consent + '&nonce=<?php echo esc_attr( wp_create_nonce( 'allmt_consent_nonce' ) ); ?>'
                    });
                }
            };
        </script>
        <?php
    }

    /**
     * Get banner styles based on position and theme
     *
     * @param string $position Banner position.
     * @param string $theme Banner theme.
     * @return string
     */
    private function get_banner_styles( string $position, string $theme ): string {
        $styles = '';

        if ( $theme === 'dark' ) {
            $styles .= "background: #1a1a1a; color: #ffffff;";
        } elseif ( $theme === 'light' ) {
            $styles .= "background: #ffffff; color: #333333;";
        } else {
            $styles .= "background: #f8f9fa; color: #333333; border-top: 1px solid #dee2e6;";
        }

        if ( $position === 'top' ) {
            $styles .= " top: 0; bottom: auto;";
        }

        return $styles;
    }

    /**
     * AJAX handler for saving consent
     *
     * @return void
     */
    public function ajax_save_consent(): void {
        check_ajax_referer( 'allmt_consent_nonce', 'nonce' );

        $consent = isset( $_POST['consent'] ) ? sanitize_text_field( wp_unslash( $_POST['consent'] ) ) : '';
        
        // Log consent for GDPR compliance
        $this->log_consent( $consent );

        wp_send_json_success( array( 'consent' => $consent ) );
    }

    /**
     * Log consent for compliance
     *
     * @param string $consent Consent value.
     * @return void
     */
    private function log_consent( string $consent ): void {
        global $wpdb;

        $ip_address = $this->get_client_ip();
        $ip_hash    = hash( 'sha256', $ip_address . wp_salt() );

        $wpdb->insert(
            $wpdb->prefix . 'allmt_audit_log',
            array(
                'event_type'   => 'consent',
                'event_action' => $consent,
                'ip_address'   => get_option( 'allmt_anonymize_ip', true ) ? '' : $ip_address,
                'user_agent'   => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
                'created_at'   => current_time( 'mysql' ),
            )
        );
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

    /**
     * Add privacy settings tab
     *
     * @param array $tabs Settings tabs.
     * @return array
     */
    public function add_privacy_settings_tab( array $tabs ): array {
        $tabs['privacy'] = array(
            'title'    => __( 'Privacy', 'advanced-llm-tracker' ),
            'callback' => array( $this, 'render_privacy_settings' ),
        );
        return $tabs;
    }

    /**
     * Render privacy settings
     *
     * @return void
     */
    public function render_privacy_settings(): void {
        ?>
        <h2><?php esc_html_e( 'Privacy Settings', 'advanced-llm-tracker' ); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="allmt_enable_consent">
                        <?php esc_html_e( 'Enable Consent Banner', 'advanced-llm-tracker' ); ?>
                    </label>
                </th>
                <td>
                    <input type="checkbox" id="allmt_enable_consent" name="allmt_enable_consent" 
                        value="1" <?php checked( get_option( 'allmt_enable_consent', true ) ); ?>>
                    <p class="description">
                        <?php esc_html_e( 'Show a consent banner for GDPR compliance.', 'advanced-llm-tracker' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="allmt_anonymize_ip">
                        <?php esc_html_e( 'Anonymize IP Addresses', 'advanced-llm-tracker' ); ?>
                    </label>
                </th>
                <td>
                    <input type="checkbox" id="allmt_anonymize_ip" name="allmt_anonymize_ip" 
                        value="1" <?php checked( get_option( 'allmt_anonymize_ip', true ) ); ?>>
                    <p class="description">
                        <?php esc_html_e( 'Store hashed IP addresses instead of full IPs.', 'advanced-llm-tracker' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="allmt_data_retention_days">
                        <?php esc_html_e( 'Data Retention (Days)', 'advanced-llm-tracker' ); ?>
                    </label>
                </th>
                <td>
                    <input type="number" id="allmt_data_retention_days" name="allmt_data_retention_days" 
                        value="<?php echo esc_attr( get_option( 'allmt_data_retention_days', 90 ) ); ?>" 
                        min="1" max="365" class="small-text">
                    <p class="description">
                        <?php esc_html_e( 'Number of days to retain tracking data before automatic deletion.', 'advanced-llm-tracker' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="allmt_enable_differential_privacy">
                        <?php esc_html_e( 'Enable Differential Privacy', 'advanced-llm-tracker' ); ?>
                    </label>
                </th>
                <td>
                    <input type="checkbox" id="allmt_enable_differential_privacy" name="allmt_enable_differential_privacy" 
                        value="1" <?php checked( get_option( 'allmt_enable_differential_privacy', true ) ); ?>>
                    <p class="description">
                        <?php esc_html_e( 'Add noise to coordinate data for enhanced privacy.', 'advanced-llm-tracker' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Anonymize IP address
     *
     * @param string $ip_address IP address.
     * @return string
     */
    public static function anonymize_ip( string $ip_address ): string {
        if ( filter_var( $ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            // For IPv4, remove last octet
            $parts = explode( '.', $ip_address );
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0';
        } elseif ( filter_var( $ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            // For IPv6, keep first 64 bits
            $parts = explode( ':', $ip_address );
            return implode( ':', array_slice( $parts, 0, 4 ) ) . ':0:0:0:0';
        }
        return '';
    }

    /**
     * Export user data
     *
     * @param string $email_address User email.
     * @return array
     */
    public static function export_user_data( string $email_address ): array {
        global $wpdb;

        $user = get_user_by( 'email', $email_address );
        if ( ! $user ) {
            return array();
        }

        $sessions_table = $wpdb->prefix . 'allmt_sessions';
        $sessions       = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$sessions_table} WHERE wp_user_id = %d",
                $user->ID
            ),
            ARRAY_A
        );

        return $sessions;
    }

    /**
     * Erase user data
     *
     * @param string $email_address User email.
     * @return int Number of records deleted.
     */
    public static function erase_user_data( string $email_address ): int {
        global $wpdb;

        $user = get_user_by( 'email', $email_address );
        if ( ! $user ) {
            return 0;
        }

        $sessions_table = $wpdb->prefix . 'allmt_sessions';
        $events_table   = $wpdb->prefix . 'allmt_events';

        // Get session IDs for this user
        $session_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT session_id FROM {$sessions_table} WHERE wp_user_id = %d",
                $user->ID
            )
        );

        if ( empty( $session_ids ) ) {
            return 0;
        }

        $deleted = 0;

        // Delete events
        $placeholders = implode( ',', array_fill( 0, count( $session_ids ), '%s' ) );
        $deleted += $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$events_table} WHERE session_id IN ({$placeholders})",
                ...$session_ids
            )
        );

        // Delete sessions
        $deleted += $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$sessions_table} WHERE wp_user_id = %d",
                $user->ID
            )
        );

        return $deleted;
    }
}
