<?php
/**
 * Main Plugin Class for Advanced LLM Tracker
 *
 * @package Advanced_LLM_Tracker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ALLMT_Plugin
 *
 * Main plugin class that initializes all components.
 */
class ALLMT_Plugin {

    /**
     * Plugin instance
     *
     * @var ALLMT_Plugin|null
     */
    private static $instance = null;

    /**
     * Plugin components
     *
     * @var array
     */
    private $components = array();

    /**
     * Get plugin instance (singleton)
     *
     * @return ALLMT_Plugin
     */
    public static function get_instance(): ALLMT_Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public static function init(): void {
        $instance = self::get_instance();
        $instance->setup();
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Private constructor for singleton
    }

    /**
     * Setup plugin components
     *
     * @return void
     */
    private function setup(): void {
        // Load text domain for internationalization - at init hook
        add_action( 'init', array( $this, 'load_textdomain' ), 1 );

        // Add custom cron schedules
        add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );

        // Initialize components
        add_action( 'init', array( $this, 'init_components' ), 5 );

        // Register REST API endpoints
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

        // Enqueue frontend scripts
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );

        // Admin hooks
        if ( is_admin() ) {
            $this->setup_admin();
        }

        // Security hooks
        add_action( 'init', array( $this, 'setup_security' ), 1 );

        // Privacy hooks
        add_action( 'init', array( $this, 'setup_privacy' ), 2 );

        // Cron action hooks
        add_action( 'allmt_cleanup_old_data', array( $this, 'cleanup_old_data' ) );
        add_action( 'allmt_aggregate_stats', array( $this, 'aggregate_stats' ) );
        add_action( 'allmt_cleanup_blocklist', array( $this, 'cleanup_blocklist' ) );
        add_action( 'allmt_update_known_bots', array( $this, 'update_known_bots' ) );

        do_action( 'allmt_plugin_loaded' );
    }

    /**
     * Load plugin textdomain
     *
     * @return void
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'advanced-llm-tracker',
            false,
            dirname( ALLMT_BASENAME ) . '/languages/'
        );
    }

    /**
     * Add custom cron schedules
     *
     * @param array $schedules Existing cron schedules.
     * @return array
     */
    public function add_cron_schedules( array $schedules ): array {
        $schedules['allmt_15min'] = array(
            'interval' => 900,
            'display'  => __( 'Every 15 Minutes', 'advanced-llm-tracker' ),
        );

        $schedules['weekly'] = array(
            'interval' => 604800,
            'display'  => __( 'Once Weekly', 'advanced-llm-tracker' ),
        );

        return $schedules;
    }

    /**
     * Initialize plugin components
     *
     * @return void
     */
    public function init_components(): void {
        // Initialize settings
        $this->components['settings'] = new ALLMT_Settings();

        // Initialize security
        $this->components['security'] = new ALLMT_Security();

        // Initialize privacy/consent
        $this->components['consent'] = new ALLMT_Consent();

        // Initialize tracker
        $this->components['tracker'] = new ALLMT_Tracker();

        // NOTE: ALLMT_Feature_Extractor is instantiated per-session by the classifier
        // Do NOT instantiate it here without a session_id parameter

        // Initialize classifier
        $this->components['classifier'] = new ALLMT_Classifier();

        // Initialize response engine
        $this->components['response_engine'] = new ALLMT_Response_Engine();

        // Initialize alerts
        $this->components['alerts'] = new ALLMT_Alerts();

        // Initialize ML engine
        $this->components['ml_engine'] = new ALLMT_ML_Engine();

        // Initialize GA4 integration
        if ( get_option( 'allmt_ga4_enabled', false ) ) {
            $this->components['ga4'] = new ALLMT_GA4_Integration();
        }

        // Initialize cron handler
        $this->components['cron'] = new ALLMT_Cron();

        do_action( 'allmt_components_initialized', $this->components );
    }

    /**
     * Register REST API routes
     *
     * @return void
     */
    public function register_rest_routes(): void {
        $rest_api = new ALLMT_REST_API();
        $rest_api->register_routes();
    }

    /**
     * Enqueue frontend scripts
     *
     * @return void
     */
    public function enqueue_frontend_scripts(): void {
        // Don't enqueue for known bots
        if ( $this->is_known_bot_request() ) {
            return;
        }

        // Don't enqueue if tracking is disabled
        if ( ! get_option( 'allmt_enabled', true ) ) {
            return;
        }

        // Don't enqueue for AMP pages
        if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
            return;
        }

        // Check if user has opted out
        if ( isset( $_COOKIE['allmt_opt_out'] ) ) {
            return;
        }

        // Get session ID
        $session_id = $this->get_or_create_session_id();

        // Prepare SDK configuration
        $sdk_config = array(
            'endpoint'             => rest_url( 'allmt/v1/track' ),
            'nonce'                => wp_create_nonce( 'wp_rest' ),
            'sessionId'            => $session_id,
            'samplingRate'         => (int) get_option( 'allmt_sampling_rate', 100 ),
            'detailedTrackingRate' => (int) get_option( 'allmt_detailed_tracking_rate', 10 ),
            'batchSize'            => (int) get_option( 'allmt_batch_size', 100 ),
            'batchInterval'        => (int) get_option( 'allmt_batch_interval', 50 ),
            'enableMouseTracking'  => (bool) get_option( 'allmt_enable_mouse_tracking', true ),
            'enableScrollTracking' => (bool) get_option( 'allmt_enable_scroll_tracking', true ),
            'enableViewportTracking' => (bool) get_option( 'allmt_enable_viewport_tracking', true ),
            'enableDOMTracking'    => (bool) get_option( 'allmt_enable_dom_tracking', true ),
            'differentialPrivacy'  => (bool) get_option( 'allmt_enable_differential_privacy', true ),
            'dpEpsilon'            => (float) get_option( 'allmt_dp_epsilon', 1.0 ),
            'consentRequired'      => (bool) get_option( 'allmt_enable_consent', true ),
            'maxEventsPerSession'  => (int) get_option( 'allmt_max_events_per_session', 1000 ),
            'debug'                => (bool) get_option( 'allmt_debug_mode', false ),
        );

        // Enqueue the SDK
        wp_enqueue_script(
            'allmt-sdk',
            ALLMT_URL . 'assets/js/sdk.min.js',
            array(),
            ALLMT_VERSION,
            array(
                'strategy'  => 'defer',
                'in_footer' => true,
            )
        );

        // Localize script with configuration
        wp_localize_script(
            'allmt-sdk',
            'ALLMT_SDK_CONFIG',
            $sdk_config
        );
    }

    /**
     * Setup admin functionality
     *
     * @return void
     */
    private function setup_admin(): void {
        $admin = new ALLMT_Admin();
        $admin->init();
    }

    /**
     * Setup security features
     *
     * @return void
     */
    public function setup_security(): void {
        // Rate limiting for REST API
        add_filter( 'rest_pre_dispatch', array( $this, 'check_rate_limit' ), 10, 3 );

        // Verify nonces for sensitive operations
        add_action( 'rest_api_init', array( $this, 'require_authentication' ) );
    }

    /**
     * Setup privacy features
     *
     * @return void
     */
    public function setup_privacy(): void {
        // Register privacy exporter
        add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_privacy_exporter' ) );

        // Register privacy eraser
        add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_privacy_eraser' ) );

        // Add privacy policy content
        add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
    }

    /**
     * Check rate limit for REST API requests
     *
     * @param mixed           $result Response to replace the requested version with.
     * @param WP_REST_Server  $server Server instance.
     * @param WP_REST_Request $request Request used to generate the response.
     * @return mixed
     */
    public function check_rate_limit( $result, $server, $request ) {
        // Only check our endpoints
        if ( strpos( $request->get_route(), '/allmt/' ) !== 0 ) {
            return $result;
        }

        $ip_address = $this->get_client_ip();
        $cache_key  = 'allmt_rate_limit_' . md5( $ip_address );
        $requests   = get_transient( $cache_key );

        if ( false === $requests ) {
            $requests = array(
                'count'     => 1,
                'timestamp' => time(),
            );
            set_transient( $cache_key, $requests, MINUTE_IN_SECONDS );
        } else {
            $limit = (int) get_option( 'allmt_rate_limit_requests', 60 );

            if ( $requests['count'] >= $limit ) {
                return new WP_Error(
                    'rate_limit_exceeded',
                    __( 'Rate limit exceeded. Please try again later.', 'advanced-llm-tracker' ),
                    array( 'status' => 429 )
                );
            }

            $requests['count']++;
            set_transient( $cache_key, $requests, MINUTE_IN_SECONDS );
        }

        return $result;
    }

    /**
     * Require authentication for sensitive endpoints
     *
     * @return void
     */
    public function require_authentication(): void {
        // This is handled in the REST API class per-endpoint
    }

    /**
     * Register privacy exporter
     *
     * @param array $exporters Privacy exporters.
     * @return array
     */
    public function register_privacy_exporter( array $exporters ): array {
        $exporters['advanced-llm-tracker'] = array(
            'exporter_friendly_name' => __( 'Advanced LLM Tracker Data', 'advanced-llm-tracker' ),
            'callback'               => array( $this, 'privacy_data_exporter' ),
        );
        return $exporters;
    }

    /**
     * Register privacy eraser
     *
     * @param array $erasers Privacy erasers.
     * @return array
     */
    public function register_privacy_eraser( array $erasers ): array {
        $erasers['advanced-llm-tracker'] = array(
            'eraser_friendly_name' => __( 'Advanced LLM Tracker Data', 'advanced-llm-tracker' ),
            'callback'             => array( $this, 'privacy_data_eraser' ),
        );
        return $erasers;
    }

    /**
     * Privacy data exporter callback
     *
     * @param string $email_address Email address.
     * @param int    $page Page number.
     * @return array
     */
    public function privacy_data_exporter( string $email_address, int $page = 1 ): array {
        // Implementation for exporting user data
        return array(
            'data' => array(),
            'done' => true,
        );
    }

    /**
     * Privacy data eraser callback
     *
     * @param string $email_address Email address.
     * @param int    $page Page number.
     * @return array
     */
    public function privacy_data_eraser( string $email_address, int $page = 1 ): array {
        // Implementation for erasing user data
        return array(
            'items_removed'  => 0,
            'items_retained' => 0,
            'messages'       => array(),
            'done'           => true,
        );
    }

    /**
     * Add privacy policy content
     *
     * @return void
     */
    public function add_privacy_policy_content(): void {
        if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
            return;
        }

        $content = sprintf(
            '<h3>%s</h3><p>%s</p>',
            __( 'Advanced LLM Tracker', 'advanced-llm-tracker' ),
            __( 'This website uses Advanced LLM Tracker to detect and analyze AI bot traffic. The plugin may collect anonymized data about visitor behavior including page views, scroll patterns, and interaction data. This data is used to distinguish between human visitors and AI systems. All data is processed in accordance with GDPR and other applicable privacy regulations. IP addresses are hashed and personal data is not stored.', 'advanced-llm-tracker' )
        );

        wp_add_privacy_policy_content( 'Advanced LLM Tracker', $content );
    }

    /**
     * Check if current request is from a known bot
     *
     * @return bool
     */
    private function is_known_bot_request(): bool {
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

        $bot_patterns = array(
            '/bot/i',
            '/crawler/i',
            '/spider/i',
            '/scraper/i',
            '/curl/i',
            '/wget/i',
            '/python/i',
            '/java\//i',
        );

        foreach ( $bot_patterns as $pattern ) {
            if ( preg_match( $pattern, $user_agent ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get or create session ID
     *
     * @return string
     */
    private function get_or_create_session_id(): string {
        $cookie_name = 'allmt_session';

        if ( isset( $_COOKIE[ $cookie_name ] ) ) {
            $session_id = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
            if ( $this->validate_session_id( $session_id ) ) {
                return $session_id;
            }
        }

        // Generate new session ID
        $session_id = $this->generate_session_id();

        // Set cookie (session cookie, httponly, secure if HTTPS)
        $cookie_params = array(
            'expires'  => 0,
            'path'     => COOKIEPATH,
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        );

        setcookie( $cookie_name, $session_id, $cookie_params );

        return $session_id;
    }

    /**
     * Generate a secure session ID
     *
     * @return string
     */
    private function generate_session_id(): string {
        return bin2hex( random_bytes( 32 ) );
    }

    /**
     * Validate session ID format
     *
     * @param string $session_id Session ID to validate.
     * @return bool
     */
    private function validate_session_id( string $session_id ): bool {
        return (bool) preg_match( '/^[a-f0-9]{64}$/', $session_id );
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    public function get_client_ip(): string {
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
                // Handle multiple IPs (X-Forwarded-For can contain multiple)
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
     * Cleanup old data (cron job)
     *
     * @return void
     */
    public function cleanup_old_data(): void {
        global $wpdb;

        $retention_days = (int) get_option( 'allmt_data_retention_days', 90 );
        $cutoff_date    = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

        $tables = array(
            'sessions',
            'events',
            'classifications',
        );

        foreach ( $tables as $table ) {
            $table_name = $wpdb->prefix . 'allmt_' . $table;
            // Use $wpdb->query with properly prepared statement
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM `{$table_name}` WHERE created_at < %s",
                $cutoff_date
            ) );
        }

        // Log the cleanup
        do_action( 'allmt_data_cleanup_completed', $retention_days );
    }

    /**
     * Aggregate statistics (cron job)
     *
     * @return void
     */
    public function aggregate_stats(): void {
        // Implementation for hourly stats aggregation
        do_action( 'allmt_stats_aggregated' );
    }

    /**
     * Cleanup expired blocklist entries (cron job)
     *
     * @return void
     */
    public function cleanup_blocklist(): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'allmt_blocklist';

        // Use $wpdb->query with properly prepared statement
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM `{$table_name}` WHERE expires_at < NOW() AND is_active = 1"
        ) );
    }

    /**
     * Update known bots list (cron job)
     *
     * @return void
     */
    public function update_known_bots(): void {
        // Implementation for updating known bot signatures
        do_action( 'allmt_known_bots_updated' );
    }

    /**
     * Get a plugin component
     *
     * @param string $name Component name.
     * @return object|null
     */
    public function get_component( string $name ): ?object {
        return $this->components[ $name ] ?? null;
    }
}
