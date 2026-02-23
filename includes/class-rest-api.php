<?php
/**
 * REST API handler for Advanced LLM Tracker
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
 * Handles REST API endpoints.
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
        // Track endpoint
        register_rest_route(
            $this->namespace,
            '/track',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'track_event' ),
                    'permission_callback' => array( $this, 'check_permission' ),
                    'args'                => array(
                        'sessionId' => array(
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'eventType' => array(
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'eventData' => array(
                            'type' => 'object',
                        ),
                    ),
                ),
            )
        );

        // Batch track endpoint
        register_rest_route(
            $this->namespace,
            '/track/batch',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'track_batch' ),
                    'permission_callback' => array( $this, 'check_permission' ),
                    'args'                => array(
                        'sessionId' => array(
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'events'    => array(
                            'required' => true,
                            'type'     => 'array',
                        ),
                    ),
                ),
            )
        );

        // Get stats endpoint
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
                            'default'           => '24h',
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                    ),
                ),
            )
        );

        // Get sessions endpoint
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
                            'default' => 1,
                            'type'    => 'integer',
                        ),
                        'per_page' => array(
                            'default' => 20,
                            'type'    => 'integer',
                        ),
                        'is_bot'   => array(
                            'type' => 'boolean',
                        ),
                    ),
                ),
            )
        );

        // Get session details endpoint
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

        // Get alerts endpoint
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
                            'type'    => 'integer',
                        ),
                        'per_page' => array(
                            'default' => 20,
                            'type'    => 'integer',
                        ),
                        'is_read'  => array(
                            'type' => 'boolean',
                        ),
                    ),
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

        // Get known bots endpoint
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

        // Update known bot endpoint
        register_rest_route(
            $this->namespace,
            '/known-bots/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_known_bot' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                ),
            )
        );

        // Get settings endpoint
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
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_settings' ),
                    'permission_callback' => array( $this, 'check_admin_permission' ),
                ),
            )
        );
    }

    /**
     * Check permission for tracking endpoints
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function check_permission( WP_REST_Request $request ) {
        // Check if tracking is enabled
        if ( ! get_option( 'allmt_enabled', true ) ) {
            return new WP_Error(
                'tracking_disabled',
                __( 'Tracking is disabled.', 'advanced-llm-tracker' ),
                array( 'status' => 403 )
            );
        }

        // Check consent
        if ( get_option( 'allmt_enable_consent', true ) && ! ALLMT_Consent::has_consent() ) {
            return new WP_Error(
                'consent_required',
                __( 'Consent is required.', 'advanced-llm-tracker' ),
                array( 'status' => 403 )
            );
        }

        // Verify nonce
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid nonce.', 'advanced-llm-tracker' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Check admin permission
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function check_admin_permission( WP_REST_Request $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error(
                'unauthorized',
                __( 'You do not have permission to access this resource.', 'advanced-llm-tracker' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Track event
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function track_event( WP_REST_Request $request ): WP_REST_Response {
        $session_id = $request->get_param( 'sessionId' );
        $event_type = $request->get_param( 'eventType' );
        $event_data = $request->get_param( 'eventData' ) ?? array();

        // Create or update session
        $session = ALLMT_Session::get_or_create( $session_id );

        if ( ! $session ) {
            return new WP_REST_Response(
                array( 'error' => 'Failed to create session' ),
                500
            );
        }

        // Insert event
        $event_id = ALLMT_Events::insert( $session_id, $event_type, $event_data );

        if ( ! $event_id ) {
            return new WP_REST_Response(
                array( 'error' => 'Failed to insert event' ),
                500
            );
        }

        // Trigger classification if needed
        $event_count = ALLMT_Events::count_by_session( $session_id );
        if ( $event_count >= 10 && $event_count % 10 === 0 ) {
            $classifier = new ALLMT_Classifier();
            $classifier->classify_session( $session_id );
        }

        return new WP_REST_Response(
            array(
                'success'   => true,
                'event_id'  => $event_id,
                'session_id'=> $session_id,
            ),
            201
        );
    }

    /**
     * Track batch of events
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function track_batch( WP_REST_Request $request ): WP_REST_Response {
        $session_id = $request->get_param( 'sessionId' );
        $events     = $request->get_param( 'events' );

        // Create or update session
        $session = ALLMT_Session::get_or_create( $session_id );

        if ( ! $session ) {
            return new WP_REST_Response(
                array( 'error' => 'Failed to create session' ),
                500
            );
        }

        // Insert events
        $inserted = ALLMT_Events::batch_insert( $session_id, $events );

        // Trigger classification
        $classifier = new ALLMT_Classifier();
        $classifier->classify_session( $session_id );

        return new WP_REST_Response(
            array(
                'success'    => true,
                'inserted'   => $inserted,
                'session_id' => $session_id,
            ),
            201
        );
    }

    /**
     * Get statistics
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_stats( WP_REST_Request $request ): WP_REST_Response {
        $period = $request->get_param( 'period' );

        $stats = ALLMT_Stats::get_dashboard_stats( $period );

        return new WP_REST_Response( $stats );
    }

    /**
     * Get sessions
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_sessions( WP_REST_Request $request ): WP_REST_Response {
        $args = array(
            'page'     => $request->get_param( 'page' ),
            'per_page' => $request->get_param( 'per_page' ),
        );

        if ( null !== $request->get_param( 'is_bot' ) ) {
            $args['is_bot'] = $request->get_param( 'is_bot' );
        }

        $sessions = ALLMT_Session::get_sessions( $args );
        $total    = ALLMT_Session::count( $args );

        return new WP_REST_Response(
            array(
                'sessions'   => $sessions,
                'total'      => $total,
                'page'       => $args['page'],
                'per_page'   => $args['per_page'],
                'total_pages'=> ceil( $total / $args['per_page'] ),
            )
        );
    }

    /**
     * Get session details
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_session( WP_REST_Request $request ): WP_REST_Response {
        $session_id = $request->get_param( 'id' );

        $session = ALLMT_Session::get( $session_id );

        if ( ! $session ) {
            return new WP_REST_Response(
                array( 'error' => 'Session not found' ),
                404
            );
        }

        $session['events'] = ALLMT_Events::get_by_session( $session_id );

        return new WP_REST_Response( $session );
    }

    /**
     * Get alerts
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_alerts( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $page     = $request->get_param( 'page' );
        $per_page = $request->get_param( 'per_page' );
        $offset   = ( $page - 1 ) * $per_page;

        $alerts_table = $wpdb->prefix . 'allmt_alerts';

        $where = '';
        if ( null !== $request->get_param( 'is_read' ) ) {
            $where = $wpdb->prepare( ' WHERE is_read = %d', $request->get_param( 'is_read' ) ? 1 : 0 );
        }

        $alerts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$alerts_table}`{$where} ORDER BY created_at DESC LIMIT %d, %d",
                $offset,
                $per_page
            ),
            ARRAY_A
        );

        $total = $wpdb->get_var( "SELECT COUNT(*) FROM `{$alerts_table}`{$where}" );

        return new WP_REST_Response(
            array(
                'alerts'     => $alerts,
                'total'      => intval( $total ),
                'page'       => $page,
                'per_page'   => $per_page,
                'total_pages'=> ceil( $total / $per_page ),
            )
        );
    }

    /**
     * Mark alert as read
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function mark_alert_read( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $alert_id = $request->get_param( 'id' );
        $alerts_table = $wpdb->prefix . 'allmt_alerts';

        $wpdb->update(
            $alerts_table,
            array(
                'is_read'  => 1,
                'read_by'  => get_current_user_id(),
                'read_at'  => current_time( 'mysql' ),
            ),
            array( 'id' => $alert_id )
        );

        return new WP_REST_Response( array( 'success' => true ) );
    }

    /**
     * Get known bots
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_known_bots( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $known_bots_table = $wpdb->prefix . 'allmt_known_bots';
        $bots             = $wpdb->get_results( "SELECT * FROM `{$known_bots_table}` ORDER BY bot_name", ARRAY_A );

        return new WP_REST_Response( $bots );
    }

    /**
     * Update known bot
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function update_known_bot( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $bot_id       = $request->get_param( 'id' );
        $known_bots_table = $wpdb->prefix . 'allmt_known_bots';

        $data = array(
            'is_active'   => $request->get_param( 'is_active' ),
            'is_trusted'  => $request->get_param( 'is_trusted' ),
            'rate_limit'  => $request->get_param( 'rate_limit' ),
            'updated_at'  => current_time( 'mysql' ),
        );

        $wpdb->update( $known_bots_table, $data, array( 'id' => $bot_id ) );

        return new WP_REST_Response( array( 'success' => true ) );
    }

    /**
     * Get settings
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_settings( WP_REST_Request $request ): WP_REST_Response {
        $settings = ALLMT_Settings::get_all();

        // Remove sensitive data
        unset( $settings['ml_api_key'] );
        unset( $settings['ga4_api_secret'] );
        unset( $settings['slack_webhook'] );

        return new WP_REST_Response( $settings );
    }

    /**
     * Update settings
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function update_settings( WP_REST_Request $request ): WP_REST_Response {
        $params = $request->get_json_params();

        foreach ( $params as $key => $value ) {
            ALLMT_Settings::set( $key, $value );
        }

        return new WP_REST_Response( array( 'success' => true ) );
    }
}
