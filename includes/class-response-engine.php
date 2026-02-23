<?php
/**
 * Response Engine for Advanced LLM Tracker
 *
 * @package Advanced_LLM_Tracker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ALLMT_Response_Engine
 *
 * Handles responses to detected bots.
 */
class ALLMT_Response_Engine {

    /**
     * Response types
     */
    const RESPONSE_ALLOW    = 'allow';
    const RESPONSE_MONITOR  = 'monitor';
    const RESPONSE_CHALLENGE = 'challenge';
    const RESPONSE_BLOCK    = 'block';
    const RESPONSE_TARPIT   = 'tarpit';

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'allmt_high_confidence_bot_detected', array( $this, 'handle_high_confidence_bot' ), 10, 2 );
        add_action( 'template_redirect', array( $this, 'check_blocklist' ), 1 );
    }

    /**
     * Handle high confidence bot detection
     *
     * @param string $session_id Session ID.
     * @param array  $classification Classification result.
     * @return void
     */
    public function handle_high_confidence_bot( string $session_id, array $classification ): void {
        $response_action = get_option( 'allmt_default_response', 'allow' );
        $confidence      = $classification['confidence'] ?? 0;
        $category        = $classification['category'] ?? 'unknown';

        // Determine response based on confidence and category
        if ( $confidence >= get_option( 'allmt_auto_block_threshold', 0.95 ) ) {
            $response_action = self::RESPONSE_BLOCK;
        } elseif ( $confidence >= get_option( 'allmt_challenge_threshold', 0.80 ) ) {
            $response_action = self::RESPONSE_CHALLENGE;
        } elseif ( $confidence >= get_option( 'allmt_min_confidence_threshold', 0.75 ) ) {
            $response_action = self::RESPONSE_MONITOR;
        }

        // Apply category-specific rules
        $response_action = $this->apply_category_rules( $category, $response_action );

        // Execute response
        $this->execute_response( $session_id, $response_action, $classification );
    }

    /**
     * Apply category-specific response rules
     *
     * @param string $category Bot category.
     * @param string $default_response Default response.
     * @return string
     */
    private function apply_category_rules( string $category, string $default_response ): string {
        $category_rules = array(
            ALLMT_Classifier::CATEGORY_TRAINING_HARVESTER => self::RESPONSE_MONITOR,
            ALLMT_Classifier::CATEGORY_SEARCH_INDEXER     => self::RESPONSE_MONITOR,
            ALLMT_Classifier::CATEGORY_RESEARCH_AGGREGATOR => self::RESPONSE_ALLOW,
            ALLMT_Classifier::CATEGORY_MALICIOUS_SCRAPER  => self::RESPONSE_BLOCK,
            ALLMT_Classifier::CATEGORY_UNKNOWN_BOT        => self::RESPONSE_CHALLENGE,
        );

        return $category_rules[ $category ] ?? $default_response;
    }

    /**
     * Execute response action
     *
     * @param string $session_id Session ID.
     * @param string $action Response action.
     * @param array  $classification Classification data.
     * @return void
     */
    private function execute_response( string $session_id, string $action, array $classification ): void {
        global $wpdb;

        // Update session with response action
        $sessions_table = $wpdb->prefix . 'allmt_sessions';
        $wpdb->update(
            $sessions_table,
            array( 'response_action' => $action ),
            array( 'session_id' => $session_id )
        );

        switch ( $action ) {
            case self::RESPONSE_ALLOW:
                // No action needed
                break;

            case self::RESPONSE_MONITOR:
                $this->monitor_session( $session_id, $classification );
                break;

            case self::RESPONSE_CHALLENGE:
                $this->issue_challenge( $session_id, $classification );
                break;

            case self::RESPONSE_BLOCK:
                $this->block_session( $session_id, $classification );
                break;

            case self::RESPONSE_TARPIT:
                $this->tarpit_session( $session_id );
                break;
        }

        do_action( 'allmt_response_executed', $session_id, $action, $classification );
    }

    /**
     * Monitor a session (enhanced logging)
     *
     * @param string $session_id Session ID.
     * @param array  $classification Classification data.
     * @return void
     */
    private function monitor_session( string $session_id, array $classification ): void {
        // Create an alert for monitoring
        $alerts = new ALLMT_Alerts();
        $alerts->create_alert(
            'bot_detected',
            'warning',
            __( 'Bot Detected - Monitoring', 'advanced-llm-tracker' ),
            sprintf(
                /* translators: 1: Session ID, 2: Confidence percentage, 3: Category */
                __( 'Session %1$s flagged as potential bot (confidence: %2$d%%). Category: %3$s. Enhanced monitoring enabled.', 'advanced-llm-tracker' ),
                $session_id,
                intval( $classification['confidence'] * 100 ),
                $classification['category']
            ),
            $session_id
        );
    }

    /**
     * Issue a JavaScript challenge
     *
     * @param string $session_id Session ID.
     * @param array  $classification Classification data.
     * @return void
     */
    private function issue_challenge( string $session_id, array $classification ): void {
        // Don't challenge if already challenged
        if ( isset( $_COOKIE['allmt_challenge_passed'] ) ) {
            return;
        }

        // Set challenge cookie
        setcookie(
            'allmt_challenged',
            '1',
            array(
                'expires'  => time() + 300,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly' => false,
                'samesite' => 'Lax',
            )
        );

        // Create alert
        $alerts = new ALLMT_Alerts();
        $alerts->create_alert(
            'challenge_issued',
            'warning',
            __( 'JS Challenge Issued', 'advanced-llm-tracker' ),
            sprintf(
                /* translators: 1: Session ID, 2: Confidence percentage */
                __( 'JavaScript challenge issued to session %1$s (confidence: %2$d%%).', 'advanced-llm-tracker' ),
                $session_id,
                intval( $classification['confidence'] * 100 )
            ),
            $session_id
        );

        // Add challenge script to page
        add_action( 'wp_footer', array( $this, 'render_challenge_script' ), 1 );
    }

    /**
     * Render challenge script
     *
     * @return void
     */
    public function render_challenge_script(): void {
        if ( ! isset( $_COOKIE['allmt_challenged'] ) || isset( $_COOKIE['allmt_challenge_passed'] ) ) {
            return;
        }
        ?>
        <script>
        (function() {
            // Simple proof-of-work challenge
            function solveChallenge() {
                const start = Date.now();
                let nonce = 0;
                const target = '0000';
                
                while (true) {
                    const hash = btoa(nonce.toString()).slice(0, 4);
                    if (hash === target) {
                        break;
                    }
                    nonce++;
                    
                    // Timeout after 5 seconds
                    if (Date.now() - start > 5000) {
                        return false;
                    }
                }
                
                return true;
            }
            
            if (solveChallenge()) {
                document.cookie = 'allmt_challenge_passed=1; path=/; max-age=86400; SameSite=Lax';
                document.cookie = 'allmt_challenged=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
            }
        })();
        </script>
        <?php
    }

    /**
     * Block a session
     *
     * @param string $session_id Session ID.
     * @param array  $classification Classification data.
     * @return void
     */
    private function block_session( string $session_id, array $classification ): void {
        global $wpdb;

        $sessions_table = $wpdb->prefix . 'allmt_sessions';

        // Get session data
        $session = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$sessions_table}` WHERE session_id = %s",
                $session_id
            ),
            ARRAY_A
        );

        if ( ! $session ) {
            return;
        }

        // Add to blocklist
        $blocklist_table = $wpdb->prefix . 'allmt_blocklist';
        $wpdb->insert(
            $blocklist_table,
            array(
                'session_id'    => $session_id,
                'ip_address'    => $session['ip_address'],
                'block_type'    => 'temporary',
                'block_reason'  => 'High confidence bot detection: ' . $classification['category'],
                'block_duration'=> 3600,
                'blocked_by'    => 'system',
                'evidence'      => wp_json_encode( $classification ),
                'expires_at'    => gmdate( 'Y-m-d H:i:s', strtotime( '+1 hour' ) ),
                'created_at'    => current_time( 'mysql' ),
            )
        );

        // Update session
        $wpdb->update(
            $sessions_table,
            array(
                'blocked'         => 1,
                'block_reason'    => 'High confidence bot detection',
                'block_timestamp' => current_time( 'mysql' ),
            ),
            array( 'session_id' => $session_id )
        );

        // Create critical alert
        $alerts = new ALLMT_Alerts();
        $alerts->create_alert(
            'session_blocked',
            'critical',
            __( 'Session Blocked', 'advanced-llm-tracker' ),
            sprintf(
                /* translators: 1: Session ID, 2: Category, 3: Confidence percentage */
                __( 'Session %1$s has been blocked. Category: %2$s (confidence: %3$d%%).', 'advanced-llm-tracker' ),
                $session_id,
                $classification['category'],
                intval( $classification['confidence'] * 100 )
            ),
            $session_id,
            $session['ip_address']
        );

        // Terminate current request if this is the blocked session
        if ( isset( $_COOKIE['allmt_session'] ) && $_COOKIE['allmt_session'] === $session_id ) {
            wp_die(
                esc_html__( 'Access temporarily restricted. Please try again later.', 'advanced-llm-tracker' ),
                esc_html__( 'Access Restricted', 'advanced-llm-tracker' ),
                array( 'response' => 403 )
            );
        }
    }

    /**
     * Tarpit a session (slow responses)
     *
     * @param string $session_id Session ID.
     * @return void
     */
    private function tarpit_session( string $session_id ): void {
        // Check if current session is tarpitted
        if ( isset( $_COOKIE['allmt_session'] ) && $_COOKIE['allmt_session'] === $session_id ) {
            // Add delay
            sleep( rand( 5, 15 ) );
        }
    }

    /**
     * Check if current request is blocked
     *
     * @return void
     */
    public function check_blocklist(): void {
        global $wpdb;

        if ( ! isset( $_COOKIE['allmt_session'] ) ) {
            return;
        }

        $session_id = sanitize_text_field( wp_unslash( $_COOKIE['allmt_session'] ) );
        $ip_address = $this->get_client_ip();

        $blocklist_table = $wpdb->prefix . 'allmt_blocklist';

        // Check session block
        $blocked = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `{$blocklist_table}` 
                WHERE (session_id = %s OR ip_address = %s) 
                AND is_active = 1 
                AND expires_at > NOW()",
                $session_id,
                $ip_address
            )
        );

        if ( $blocked ) {
            // Update hit count
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE `{$blocklist_table}` 
                    SET hit_count = hit_count + 1, last_hit_at = NOW() 
                    WHERE id = %d",
                    $blocked
                )
            );

            wp_die(
                esc_html__( 'Access temporarily restricted. Please try again later.', 'advanced-llm-tracker' ),
                esc_html__( 'Access Restricted', 'advanced-llm-tracker' ),
                array( 'response' => 403 )
            );
        }
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
     * Apply rate limiting
     *
     * @param string $session_id Session ID.
     * @return bool True if allowed, false if rate limited.
     */
    public function check_rate_limit( string $session_id ): bool {
        if ( ! get_option( 'allmt_enable_rate_limiting', true ) ) {
            return true;
        }

        $cache_key = 'allmt_rate_limit_' . $session_id;
        $requests  = get_transient( $cache_key );

        $limit  = (int) get_option( 'allmt_rate_limit_requests', 60 );
        $window = (int) get_option( 'allmt_rate_limit_window', 60 );

        if ( false === $requests ) {
            $requests = array(
                'count'     => 1,
                'timestamp' => time(),
            );
            set_transient( $cache_key, $requests, $window );
            return true;
        }

        if ( $requests['count'] >= $limit ) {
            return false;
        }

        $requests['count']++;
        set_transient( $cache_key, $requests, $window );

        return true;
    }
}
