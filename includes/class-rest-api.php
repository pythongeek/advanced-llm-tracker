<?php
/**
 * REST API for Advanced LLM Tracker
 *
 * @package Advanced_LLM_Tracker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ALLMT_REST_API
 *
 * Handles REST API endpoints for the plugin.
 */
class ALLMT_REST_API {

    /**
     * API namespace
     *
     * @var string
     */
    private $namespace = 'allmt/v1';

    /**
     * Register REST API routes
     *
     * @return void
     */
    public function register_routes(): void {
        // Track endpoint - receives events from JavaScript SDK
        register_rest_route(
            $this->namespace,
            '/track',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'track_events' ),
                    'permission_callback' => array( $this, 'check_tracking_permission' ),
                    'args'                => array(
                        'session_id' => array(
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'events'     => array(
                            'required' => true,
                            'type'     => 'array',
                        ),
                    ),
                ),
            )
        );

        // Session endpoint - get session details
        register_rest_route(
            $this->namespace,
            '/sessions/(?P<id>[a-zA-Z0-9]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_session' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                ),
            )
        );

        // Sessions list endpoint
        register_rest_route(
            $this->namespace,
            '/sessions',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_sessions' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                    'args'                => array(
                        'page'     => array(
                            'default'           => 1,
                            'sanitize_callback' => 'absint',
                        ),
                        'per_page' => array(
                            'default'           => 20,
                            'sanitize_callback' => 'absint',
                        ),
                        'is_bot'   => array(
                            'default' => null,
                        ),
                        'orderby'  => array(
                            'default' => 'created_at',
                        ),
                        'order'    => array(
                            'default' => 'DESC',
                        ),
                    ),
                ),
            )
        );

        // Stats endpoint
        register_rest_route(
            $this->namespace,
            '/stats',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_stats' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                    'args'                => array(
                        'period' => array(
                            'default' => '24h',
                        ),
                    ),
                ),
            )
        );

        // Alerts endpoint
        register_rest_route(
            $this->namespace,
            '/alerts',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_alerts' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                    'args'                => array(
                        'page'     => array(
                            'default' => 1,
                        ),
                        'per_page' => array(
                            'default' => 20,
                        ),
                        'is_read'  => array(
                            'default' => null,
                        ),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_alert' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                ),
            )
        );

        // Mark alert as read
        register_rest_route(
            $this->namespace,
            '/alerts/(?P<id>\d+)/read',
            array(
                array(
                    'methods'             => WP_REST_Server::POST,
                    'callback'            => array( $this, 'mark_alert_read' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                ),
            )
        );

        // Settings endpoint
        register_rest_route(
            $this->namespace,
            '/settings',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_settings' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                ),
                array(
                    'methods'             => WP_REST_Server::POST,
                    'callback'            => array( $this, 'update_settings' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                ),
            )
        );

        // Known bots endpoint
        register_rest_route(
            $this->namespace,
            '/known-bots',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_known_bots' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                ),
            )
        );

        // Blocklist endpoint
        register_rest_route(
            $this->namespace,
            '/blocklist',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_blocklist' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'add_to_blocklist' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                ),
            )
        );

        // Classification endpoint (for cloud ML)
        register_rest_route(
            $this->namespace,
            '/classify',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'classify_session' ),
                    'permission_callback' => array( $this, 'check_classification_permission' ),
                    'args'                => array(
                        'session_id' => array(
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'features'   => array(
                            'required' => true,
                            'type'     => 'object',
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Check permission for tracking endpoint
     *
     * @return bool
     */
    public function check_tracking_permission(): bool {
        // Check if tracking is enabled
        if ( ! get_option( 'allmt_enabled', true ) ) {
            return false;
        }

        // Verify nonce
        $nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            // Allow for logged-out users with valid session
            return true;
        }

        return true;
    }

    /**
     * Check admin permission
     *
     * @return bool
     */
    public function check_admin_permission(): bool {
        return current_user_can( 'manage_options' );
    }

    /**
     * Check classification permission (for cloud ML)
     *
     * @return bool
     */
    public function check_classification_permission(): bool {
        // Check API key for cloud ML
        $api_key = isset( $_SERVER['HTTP_X_ALLMT_API_KEY'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_ALLMT_API_KEY'] ) ) : '';
        $stored_key = get_option( 'allmt_ml_api_key', '' );

        if ( ! empty( $stored_key ) && hash_equals( $stored_key, $api_key ) ) {
            return true;
        }

        return current_user_can( 'manage_options' );
    }

    /**
     * Track events from JavaScript SDK
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response
     */
    public function track_events( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $session_id = $request->get_param( 'session_id' );
        $events     = $request->get_param( 'events' );

        if ( empty( $events ) || ! is_array( $events ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => 'No events provided',
                ),
                400
            );
        }

        // Get or create session
        $session = $this->get_or_create_session( $session_id );

        if ( ! $session ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => 'Invalid session',
                ),
                400
            );
        }

        // Process events
        $processed_count = 0;
        $events_table    = $wpdb->prefix . 'allmt_events';

        foreach ( $events as $event ) {
            if ( ! isset( $event['type'] ) ) {
                continue;
            }

            $event_data = array(
                'session_id' => $session_id,
                'event_type' => sanitize_text_field( $event['type'] ),
                'event_data' => isset( $event['data'] ) ? wp_json_encode( $event['data'] ) : null,
                'page_url'   => isset( $event['pageUrl'] ) ? esc_url_raw( $event['pageUrl'] ) : null,
                'page_path'  => isset( $event['pageUrl'] ) ? esc_url_raw( wp_parse_url( $event['pageUrl'], PHP_URL_PATH ) ) : null,
                'timestamp'  => isset( $event['timestamp'] ) ? floatval( $event['timestamp'] ) : microtime( true ),
            );

            // Add optional fields
            if ( isset( $event['data']['x'] ) ) {
                $event_data['viewport_x'] = intval( $event['data']['x'] );
            }
            if ( isset( $event['data']['y'] ) ) {
                $event_data['viewport_y'] = intval( $event['data']['y'] );
            }
            if ( isset( $event['data']['scrollDepth'] ) ) {
                $event_data['scroll_depth'] = intval( $event['data']['scrollDepth'] );
            }

            $wpdb->insert( $events_table, $event_data );
            $processed_count++;

            // Process special event types
            $this->process_special_event( $session_id, $event );
        }

        // Update session last activity
        $wpdb->update(
            $wpdb->prefix . 'allmt_sessions',
            array( 'updated_at' => current_time( 'mysql' ) ),
            array( 'session_id' => $session_id )
        );

        // Trigger classification if needed
        $this->maybe_classify_session( $session_id );

        return new WP_REST_Response(
            array(
                'success' => true,
                'processed' => $processed_count,
            ),
            200
        );
    }

    /**
     * Get or create a session
     *
     * @param string $session_id Session ID.
     * @return array|null
     */
    private function get_or_create_session( string $session_id ): ?array {
        global $wpdb;

        $sessions_table = $wpdb->prefix . 'allmt_sessions';

        // Try to get existing session
        $session = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$sessions_table} WHERE session_id = %s",
                $session_id
            ),
            ARRAY_A
        );

        if ( $session ) {
            return $session;
        }

        // Create new session
        $ip_address = $this->get_client_ip();
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

        // Anonymize IP if enabled
        $ip_hash = hash( 'sha256', $ip_address . wp_salt() );

        $session_data = array(
            'session_id'      => $session_id,
            'ip_address'      => get_option( 'allmt_anonymize_ip', true ) ? '' : $ip_address,
            'ip_hash'         => $ip_hash,
            'user_agent'      => $user_agent,
            'user_agent_hash' => hash( 'sha256', $user_agent ),
            'session_start'   => current_time( 'mysql' ),
            'created_at'      => current_time( 'mysql' ),
        );

        $result = $wpdb->insert( $sessions_table, $session_data );

        if ( $result === false ) {
            return null;
        }

        return $session_data;
    }

    /**
     * Process special event types
     *
     * @param string $session_id Session ID.
     * @param array  $event Event data.
     * @return void
     */
    private function process_special_event( string $session_id, array $event ): void {
        global $wpdb;

        $sessions_table = $wpdb->prefix . 'allmt_sessions';

        switch ( $event['type'] ) {
            case 'page_view':
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$sessions_table} SET page_views = page_views + 1, request_count = request_count + 1 WHERE session_id = %s",
                        $session_id
                    )
                );
                break;

            case 'mouse_trajectory':
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$sessions_table} SET has_mouse_data = 1 WHERE session_id = %s",
                        $session_id
                    )
                );
                break;

            case 'scroll_behavior':
            case 'scroll_milestone':
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$sessions_table} SET has_scroll_data = 1 WHERE session_id = %s",
                        $session_id
                    )
                );
                break;
        }
    }

    /**
     * Maybe classify a session based on events
     *
     * @param string $session_id Session ID.
     * @return void
     */
    private function maybe_classify_session( string $session_id ): void {
        // Get event count for this session
        global $wpdb;

        $events_table = $wpdb->prefix . 'allmt_events';
        $event_count  = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$events_table} WHERE session_id = %s",
                $session_id
            )
        );

        // Classify after every 20 events or on page exit
        if ( $event_count % 20 === 0 || $event_count > 50 ) {
            $classifier = new ALLMT_Classifier();
            $classifier->classify_session( $session_id );
        }
    }

    /**
     * Get session details
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response
     */
    public function get_session( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $session_id = $request->get_param( 'id' );

        $sessions_table = $wpdb->prefix . 'allmt_sessions';
        $session        = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$sessions_table} WHERE session_id = %s",
                $session_id
            ),
            ARRAY_A
        );

        if ( ! $session ) {
            return new WP_REST_Response(
                array(
                    'error' => 'Session not found',
                ),
                404
            );
        }

        // Get events for this session
        $events_table = $wpdb->prefix . 'allmt_events';
        $events       = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$events_table} WHERE session_id = %s ORDER BY created_at DESC LIMIT 100",
                $session_id
            ),
            ARRAY_A
        );

        // Get classification
        $classifications_table = $wpdb->prefix . 'allmt_classifications';
        $classification        = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$classifications_table} WHERE session_id = %s",
                $session_id
            ),
            ARRAY_A
        );

        return new WP_REST_Response(
            array(
                'session'        => $session,
                'events'         => $events,
                'classification' => $classification,
            ),
            200
        );
    }

    /**
     * Get sessions list
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response
     */
    public function get_sessions( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $page     = $request->get_param( 'page' );
        $per_page = $request->get_param( 'per_page' );
        $is_bot   = $request->get_param( 'is_bot' );
        $orderby  = sanitize_sql_orderby( $request->get_param( 'orderby' ) . ' ' . $request->get_param( 'order' ) ) ?: 'created_at DESC';

        $sessions_table = $wpdb->prefix . 'allmt_sessions';
        $offset         = ( $page - 1 ) * $per_page;

        $where = '';
        if ( null !== $is_bot ) {
            $where = $wpdb->prepare( ' WHERE is_bot = %d', intval( $is_bot ) );
        }

        $sessions = $wpdb->get_results(
            "SELECT * FROM {$sessions_table}{$where} ORDER BY {$orderby} LIMIT {$offset}, {$per_page}",
            ARRAY_A
        );

        $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$sessions_table}{$where}" );

        return new WP_REST_Response(
            array(
                'sessions' => $sessions,
                'total'    => intval( $total ),
                'page'     => $page,
                'per_page' => $per_page,
                'pages'    => ceil( $total / $per_page ),
            ),
            200
        );
    }

    /**
     * Get statistics
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response
     */
    public function get_stats( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $period = sanitize_text_field( $request->get_param( 'period' ) );

        // Calculate time range
        $time_ranges = array(
            '24h'   => '-1 day',
            '7d'    => '-7 days',
            '30d'   => '-30 days',
            '90d'   => '-90 days',
        );

        $since = isset( $time_ranges[ $period ] ) ? $time_ranges[ $period ] : '-1 day';
        $since_date = gmdate( 'Y-m-d H:i:s', strtotime( $since ) );

        $sessions_table = $wpdb->prefix . 'allmt_sessions';
        $events_table   = $wpdb->prefix . 'allmt_events';
        $alerts_table   = $wpdb->prefix . 'allmt_alerts';

        // Get stats
        $stats = array(
            'total_sessions'   => intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sessions_table} WHERE created_at >= %s", $since_date ) ) ),
            'bot_sessions'     => intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sessions_table} WHERE is_bot = 1 AND created_at >= %s", $since_date ) ) ),
            'total_events'     => intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$events_table} WHERE created_at >= %s", $since_date ) ) ),
            'blocked_sessions' => intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sessions_table} WHERE blocked = 1 AND created_at >= %s", $since_date ) ) ),
            'alerts'           => intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$alerts_table} WHERE created_at >= %s", $since_date ) ) ),
            'period'           => $period,
            'since'            => $since_date,
        );

        // Calculate bot percentage
        $stats['bot_percentage'] = $stats['total_sessions'] > 0 
            ? round( ( $stats['bot_sessions'] / $stats['total_sessions'] ) * 100, 2 ) 
            : 0;

        return new WP_REST_Response( $stats, 200 );
    }

    /**
     * Get alerts
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response
     */
    public function get_alerts( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $page     = $request->get_param( 'page' );
        $per_page = $request->get_param( 'per_page' );
        $is_read  = $request->get_param( 'is_read' );

        $alerts_table = $wpdb->prefix . 'allmt_alerts';
        $offset       = ( $page - 1 ) * $per_page;

        $where = '';
        if ( null !== $is_read ) {
            $where = $wpdb->prepare( ' WHERE is_read = %d', intval( $is_read ) );
        }

        $alerts = $wpdb->get_results(
            "SELECT * FROM {$alerts_table}{$where} ORDER BY created_at DESC LIMIT {$offset}, {$per_page}",
            ARRAY_A
        );

        $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$alerts_table}{$where}" );

        return new WP_REST_Response(
            array(
                'alerts'   => $alerts,
                'total'    => intval( $total ),
                'page'     => $page,
                'per_page' => $per_page,
            ),
            200
        );
    }

    /**
     * Create alert
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response
     */
    public function create_alert( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $params = $request->get_json_params();

        $alert_data = array(
            'alert_type'   => sanitize_text_field( $params['alert_type'] ),
            'severity'     => sanitize_text_field( $params['severity'] ),
            'title'        => sanitize_text_field( $params['title'] ),
            'message'      => sanitize_textarea_field( $params['message'] ),
            'session_id'   => isset( $params['session_id'] ) ? sanitize_text_field( $params['session_id'] ) : null,
            'ip_address'   => isset( $params['ip_address'] ) ? sanitize_text_field( $params['ip_address'] ) : null,
            'related_data' => isset( $params['related_data'] ) ? wp_json_encode( $params['related_data'] ) : null,
            'created_at'   => current_time( 'mysql' ),
        );

        $wpdb->insert( $wpdb->prefix . 'allmt_alerts', $alert_data );

        return new WP_REST_Response(
            array(
                'success' => true,
                'alert_id' => $wpdb->insert_id,
            ),
            201
        );
    }

    /**
     * Mark alert as read
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response
     */
    public function mark_alert_read( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $alert_id = $request->get_param( 'id' );

        $wpdb->update(
            $wpdb->prefix . 'allmt_alerts',
            array(
                'is_read'   => 1,
                'read_by'   => get_current_user_id(),
                'read_at'   => current_time( 'mysql' ),
            ),
            array( 'id' => $alert_id )
        );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Get settings
     *
     * @return WP_REST_Response
     */
    public function get_settings(): WP_REST_Response {
        $settings = array(
            'enabled'                  => get_option( 'allmt_enabled', true ),
            'tracking_mode'            => get_option( 'allmt_tracking_mode', 'standard' ),
            'sampling_rate'            => get_option( 'allmt_sampling_rate', 100 ),
            'detailed_tracking_rate'   => get_option( 'allmt_detailed_tracking_rate', 10 ),
            'detection_sensitivity'    => get_option( 'allmt_detection_sensitivity', 'medium' ),
            'min_confidence_threshold' => get_option( 'allmt_min_confidence_threshold', 0.75 ),
            'auto_block_threshold'     => get_option( 'allmt_auto_block_threshold', 0.95 ),
            'enable_consent'           => get_option( 'allmt_enable_consent', true ),
            'anonymize_ip'             => get_option( 'allmt_anonymize_ip', true ),
            'data_retention_days'      => get_option( 'allmt_data_retention_days', 90 ),
            'enable_alerts'            => get_option( 'allmt_enable_alerts', true ),
            'alert_email'              => get_option( 'allmt_alert_email', get_option( 'admin_email' ) ),
            'ga4_enabled'              => get_option( 'allmt_ga4_enabled', false ),
            'ga4_measurement_id'       => get_option( 'allmt_ga4_measurement_id', '' ),
            'ml_enabled'               => get_option( 'allmt_ml_enabled', false ),
            'ml_cloud_endpoint'        => get_option( 'allmt_ml_cloud_endpoint', '' ),
        );

        return new WP_REST_Response( $settings, 200 );
    }

    /**
     * Update settings
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response
     */
    public function update_settings( WP_REST_Request $request ): WP_REST_Response {
        $params = $request->get_json_params();

        $allowed_settings = array(
            'allmt_enabled',
            'allmt_tracking_mode',
            'allmt_sampling_rate',
            'allmt_detailed_tracking_rate',
            'allmt_detection_sensitivity',
            'allmt_min_confidence_threshold',
            'allmt_auto_block_threshold',
            'allmt_challenge_threshold',
            'allmt_enable_consent',
            'allmt_consent_banner_type',
            'allmt_anonymize_ip',
            'allmt_data_retention_days',
            'allmt_enable_differential_privacy',
            'allmt_dp_epsilon',
            'allmt_enable_caching',
            'allmt_cache_ttl',
            'allmt_batch_size',
            'allmt_batch_interval',
            'allmt_max_events_per_session',
            'allmt_enable_alerts',
            'allmt_alert_email',
            'allmt_alert_on_critical',
            'allmt_alert_on_high',
            'allmt_alert_on_medium',
            'allmt_default_response',
            'allmt_enable_rate_limiting',
            'allmt_rate_limit_requests',
            'allmt_rate_limit_window',
            'allmt_enable_js_challenge',
            'allmt_enable_captcha',
            'allmt_ml_enabled',
            'allmt_ml_cloud_endpoint',
            'allmt_ml_api_key',
            'allmt_ml_local_enabled',
            'allmt_ga4_enabled',
            'allmt_ga4_measurement_id',
            'allmt_ga4_api_secret',
            'allmt_slack_enabled',
            'allmt_slack_webhook',
        );

        foreach ( $allowed_settings as $option_name ) {
            $key = str_replace( 'allmt_', '', $option_name );
            if ( isset( $params[ $key ] ) ) {
                update_option( $option_name, $params[ $key ] );
            }
        }

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Get known bots
     *
     * @return WP_REST_Response
     */
    public function get_known_bots(): WP_REST_Response {
        global $wpdb;

        $known_bots_table = $wpdb->prefix . 'allmt_known_bots';
        $bots             = $wpdb->get_results( "SELECT * FROM {$known_bots_table} WHERE is_active = 1", ARRAY_A );

        return new WP_REST_Response( $bots, 200 );
    }

    /**
     * Get blocklist
     *
     * @return WP_REST_Response
     */
    public function get_blocklist(): WP_REST_Response {
        global $wpdb;

        $blocklist_table = $wpdb->prefix . 'allmt_blocklist';
        $entries         = $wpdb->get_results(
            "SELECT * FROM {$blocklist_table} WHERE is_active = 1 ORDER BY created_at DESC",
            ARRAY_A
        );

        return new WP_REST_Response( $entries, 200 );
    }

    /**
     * Add to blocklist
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response
     */
    public function add_to_blocklist( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $params = $request->get_json_params();

        $block_data = array(
            'ip_address'    => isset( $params['ip_address'] ) ? sanitize_text_field( $params['ip_address'] ) : null,
            'ip_range'      => isset( $params['ip_range'] ) ? sanitize_text_field( $params['ip_range'] ) : null,
            'session_id'    => isset( $params['session_id'] ) ? sanitize_text_field( $params['session_id'] ) : null,
            'block_type'    => sanitize_text_field( $params['block_type'] ?? 'temporary' ),
            'block_reason'  => sanitize_text_field( $params['block_reason'] ),
            'block_duration'=> intval( $params['block_duration'] ?? 3600 ),
            'blocked_by'    => 'manual',
            'evidence'      => isset( $params['evidence'] ) ? wp_json_encode( $params['evidence'] ) : null,
            'expires_at'    => gmdate( 'Y-m-d H:i:s', strtotime( '+' . intval( $params['block_duration'] ?? 3600 ) . ' seconds' ) ),
            'created_at'    => current_time( 'mysql' ),
        );

        $wpdb->insert( $wpdb->prefix . 'allmt_blocklist', $block_data );

        return new WP_REST_Response(
            array(
                'success'   => true,
                'block_id'  => $wpdb->insert_id,
            ),
            201
        );
    }

    /**
     * Classify session (for cloud ML)
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response
     */
    public function classify_session( WP_REST_Request $request ): WP_REST_Response {
        $session_id = $request->get_param( 'session_id' );
        $features   = $request->get_param( 'features' );

        $classifier = new ALLMT_Classifier();
        $result     = $classifier->classify_with_cloud_ml( $session_id, $features );

        return new WP_REST_Response( $result, 200 );
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
