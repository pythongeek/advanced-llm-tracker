<?php
/**
 * Installer for Advanced LLM Tracker
 *
 * @package Advanced_LLM_Tracker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ALLMT_Installer
 *
 * Handles plugin installation, activation, deactivation, and uninstallation.
 */
class ALLMT_Installer {

    /**
     * Database schema version
     *
     * @var string
     */
    private static $db_version = '1.0.0';

    /**
     * Plugin activation
     *
     * @return void
     */
    public static function activate(): void {
        global $wpdb;

        // Check capabilities
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        // Create database tables
        self::create_tables();

        // Set default options
        self::set_default_options();

        // Schedule cron jobs
        self::schedule_cron_jobs();

        // Create required directories
        self::create_directories();

        // Log activation
        do_action( 'allmt_plugin_activated' );

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     *
     * @return void
     */
    public static function deactivate(): void {
        // Clear scheduled cron jobs
        self::clear_cron_jobs();

        // Flush rewrite rules
        flush_rewrite_rules();

        do_action( 'allmt_plugin_deactivated' );
    }

    /**
     * Plugin uninstall
     *
     * @return void
     */
    public static function uninstall(): void {
        // Check if data should be preserved
        $preserve_data = get_option( 'allmt_preserve_data_on_uninstall', false );

        if ( ! $preserve_data ) {
            self::drop_tables();
            self::delete_options();
        }

        do_action( 'allmt_plugin_uninstalled' );
    }

    /**
     * Create database tables
     *
     * @return void
     */
    private static function create_tables(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix    = $wpdb->prefix . 'allmt_';

        // Sessions table - stores session-level data
        $sql_sessions = "CREATE TABLE IF NOT EXISTS {$table_prefix}sessions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            ip_address varchar(45) NOT NULL,
            ip_hash varchar(64) NOT NULL,
            user_agent text NOT NULL,
            user_agent_hash varchar(64) NOT NULL,
            ja3_fingerprint varchar(64) DEFAULT NULL,
            tls_fingerprint varchar(64) DEFAULT NULL,
            country_code varchar(2) DEFAULT NULL,
            asn varchar(20) DEFAULT NULL,
            organization varchar(100) DEFAULT NULL,
            is_bot tinyint(1) DEFAULT 0,
            bot_type varchar(50) DEFAULT NULL,
            bot_confidence decimal(5,4) DEFAULT 0.0000,
            classification_method varchar(20) DEFAULT 'heuristic',
            session_start datetime NOT NULL,
            session_end datetime DEFAULT NULL,
            page_views int(11) DEFAULT 0,
            request_count int(11) DEFAULT 0,
            avg_request_rate decimal(8,2) DEFAULT 0.00,
            path_efficiency decimal(5,4) DEFAULT 0.0000,
            has_mouse_data tinyint(1) DEFAULT 0,
            has_scroll_data tinyint(1) DEFAULT 0,
            consent_given tinyint(1) DEFAULT 0,
            consent_timestamp datetime DEFAULT NULL,
            wp_user_id bigint(20) unsigned DEFAULT 0,
            referrer varchar(500) DEFAULT NULL,
            landing_page varchar(500) DEFAULT NULL,
            exit_page varchar(500) DEFAULT NULL,
            session_duration int(11) DEFAULT 0,
            device_type varchar(20) DEFAULT NULL,
            browser varchar(50) DEFAULT NULL,
            os varchar(50) DEFAULT NULL,
            screen_resolution varchar(20) DEFAULT NULL,
            viewport_size varchar(20) DEFAULT NULL,
            is_known_bot tinyint(1) DEFAULT 0,
            known_bot_name varchar(100) DEFAULT NULL,
            response_action varchar(20) DEFAULT 'allow',
            blocked tinyint(1) DEFAULT 0,
            block_reason varchar(100) DEFAULT NULL,
            block_timestamp datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY ip_hash (ip_hash),
            KEY user_agent_hash (user_agent_hash),
            KEY is_bot (is_bot),
            KEY bot_confidence (bot_confidence),
            KEY session_start (session_start),
            KEY ja3_fingerprint (ja3_fingerprint),
            KEY country_code (country_code),
            KEY created_at (created_at)
        ) {$charset_collate};";

        // Events table - stores granular behavioral events
        $sql_events = "CREATE TABLE IF NOT EXISTS {$table_prefix}events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            event_type varchar(50) NOT NULL,
            event_data longtext DEFAULT NULL,
            page_url varchar(500) DEFAULT NULL,
            page_path varchar(500) DEFAULT NULL,
            timestamp decimal(16,4) NOT NULL,
            viewport_x int(11) DEFAULT NULL,
            viewport_y int(11) DEFAULT NULL,
            scroll_depth int(11) DEFAULT NULL,
            scroll_velocity decimal(8,2) DEFAULT NULL,
            element_tag varchar(50) DEFAULT NULL,
            element_id varchar(100) DEFAULT NULL,
            element_class varchar(200) DEFAULT NULL,
            element_path text DEFAULT NULL,
            time_on_page int(11) DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY event_type (event_type),
            KEY timestamp (timestamp),
            KEY created_at (created_at),
            KEY page_path (page_path(191))
        ) {$charset_collate};";

        // Classifications table - stores ML classification results
        $sql_classifications = "CREATE TABLE IF NOT EXISTS {$table_prefix}classifications (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            classification_time datetime NOT NULL,
            is_bot tinyint(1) DEFAULT 0,
            bot_probability decimal(5,4) DEFAULT 0.0000,
            human_probability decimal(5,4) DEFAULT 0.0000,
            uncertainty decimal(5,4) DEFAULT 0.0000,
            bot_category varchar(50) DEFAULT NULL,
            classification_method varchar(20) DEFAULT 'heuristic',
            model_version varchar(20) DEFAULT '1.0.0',
            features_used longtext DEFAULT NULL,
            feature_vector longtext DEFAULT NULL,
            shap_values longtext DEFAULT NULL,
            confidence_score decimal(5,4) DEFAULT 0.0000,
            requires_review tinyint(1) DEFAULT 0,
            reviewed tinyint(1) DEFAULT 0,
            reviewed_by bigint(20) unsigned DEFAULT NULL,
            reviewed_at datetime DEFAULT NULL,
            review_notes text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY is_bot (is_bot),
            KEY bot_probability (bot_probability),
            KEY classification_method (classification_method),
            KEY requires_review (requires_review),
            KEY created_at (created_at)
        ) {$charset_collate};";

        // Alerts table - stores alert notifications
        $sql_alerts = "CREATE TABLE IF NOT EXISTS {$table_prefix}alerts (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            alert_type varchar(50) NOT NULL,
            severity varchar(20) NOT NULL DEFAULT 'warning',
            title varchar(200) NOT NULL,
            message text NOT NULL,
            session_id varchar(64) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            related_data longtext DEFAULT NULL,
            is_read tinyint(1) DEFAULT 0,
            read_by bigint(20) unsigned DEFAULT NULL,
            read_at datetime DEFAULT NULL,
            notified_channels longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY alert_type (alert_type),
            KEY severity (severity),
            KEY is_read (is_read),
            KEY created_at (created_at),
            KEY session_id (session_id)
        ) {$charset_collate};";

        // Settings table - stores plugin settings
        $sql_settings = "CREATE TABLE IF NOT EXISTS {$table_prefix}settings (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            setting_name varchar(100) NOT NULL,
            setting_value longtext DEFAULT NULL,
            setting_group varchar(50) DEFAULT 'general',
            is_pro tinyint(1) DEFAULT 0,
            autoload tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_name (setting_name),
            KEY setting_group (setting_group),
            KEY is_pro (is_pro)
        ) {$charset_collate};";

        // Known bots table - stores known bot signatures
        $sql_known_bots = "CREATE TABLE IF NOT EXISTS {$table_prefix}known_bots (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            bot_name varchar(100) NOT NULL,
            bot_type varchar(50) NOT NULL,
            user_agent_patterns longtext DEFAULT NULL,
            ip_ranges longtext DEFAULT NULL,
            asn_list longtext DEFAULT NULL,
            ja3_fingerprints longtext DEFAULT NULL,
            is_trusted tinyint(1) DEFAULT 0,
            rate_limit int(11) DEFAULT 0,
            description text DEFAULT NULL,
            official_url varchar(500) DEFAULT NULL,
            documentation_url varchar(500) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            last_seen datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY bot_name (bot_name),
            KEY bot_type (bot_type),
            KEY is_trusted (is_trusted),
            KEY is_active (is_active)
        ) {$charset_collate};";

        // Blocklist table - stores blocked IPs/sessions
        $sql_blocklist = "CREATE TABLE IF NOT EXISTS {$table_prefix}blocklist (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) DEFAULT NULL,
            ip_range varchar(50) DEFAULT NULL,
            session_id varchar(64) DEFAULT NULL,
            block_type varchar(20) NOT NULL DEFAULT 'temporary',
            block_reason varchar(100) NOT NULL,
            block_duration int(11) DEFAULT 3600,
            blocked_by varchar(50) NOT NULL DEFAULT 'system',
            evidence longtext DEFAULT NULL,
            expires_at datetime NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            hit_count int(11) DEFAULT 0,
            last_hit_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ip_address (ip_address),
            KEY session_id (session_id),
            KEY is_active (is_active),
            KEY expires_at (expires_at),
            KEY created_at (created_at)
        ) {$charset_collate};";

        // Audit log table - stores security audit events
        $sql_audit_log = "CREATE TABLE IF NOT EXISTS {$table_prefix}audit_log (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_action varchar(50) NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            user_login varchar(60) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            session_id varchar(64) DEFAULT NULL,
            object_type varchar(50) DEFAULT NULL,
            object_id varchar(100) DEFAULT NULL,
            old_value longtext DEFAULT NULL,
            new_value longtext DEFAULT NULL,
            description text DEFAULT NULL,
            user_agent text DEFAULT NULL,
            request_uri varchar(500) DEFAULT NULL,
            referrer varchar(500) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY event_action (event_action),
            KEY user_id (user_id),
            KEY ip_address (ip_address),
            KEY created_at (created_at),
            KEY object_type (object_type, object_id)
        ) {$charset_collate};";

        // Execute table creation
        dbDelta( $sql_sessions );
        dbDelta( $sql_events );
        dbDelta( $sql_classifications );
        dbDelta( $sql_alerts );
        dbDelta( $sql_settings );
        dbDelta( $sql_known_bots );
        dbDelta( $sql_blocklist );
        dbDelta( $sql_audit_log );

        // Update db version
        update_option( 'allmt_db_version', self::$db_version );

        // Populate known bots data
        self::populate_known_bots();
    }

    /**
     * Set default plugin options
     *
     * @return void
     */
    private static function set_default_options(): void {
        $defaults = array(
            // General settings
            'allmt_enabled'                          => true,
            'allmt_tracking_mode'                    => 'standard',
            'allmt_sampling_rate'                    => 100,
            'allmt_detailed_tracking_rate'           => 10,
            
            // Detection settings
            'allmt_detection_sensitivity'            => 'medium',
            'allmt_min_confidence_threshold'         => 0.75,
            'allmt_auto_block_threshold'             => 0.95,
            'allmt_challenge_threshold'              => 0.80,
            
            // Privacy settings
            'allmt_enable_consent'                   => true,
            'allmt_consent_banner_type'              => 'custom',
            'allmt_anonymize_ip'                     => true,
            'allmt_data_retention_days'              => 90,
            'allmt_enable_differential_privacy'      => true,
            'allmt_dp_epsilon'                       => 1.0,
            
            // Performance settings
            'allmt_enable_caching'                   => true,
            'allmt_cache_ttl'                        => 300,
            'allmt_batch_size'                       => 100,
            'allmt_batch_interval'                   => 50,
            'allmt_max_events_per_session'           => 1000,
            
            // Alert settings
            'allmt_enable_alerts'                    => true,
            'allmt_alert_email'                      => get_option( 'admin_email' ),
            'allmt_alert_on_critical'                => true,
            'allmt_alert_on_high'                    => true,
            'allmt_alert_on_medium'                  => false,
            
            // Response settings
            'allmt_default_response'                 => 'allow',
            'allmt_enable_rate_limiting'             => true,
            'allmt_rate_limit_requests'              => 60,
            'allmt_rate_limit_window'                => 60,
            'allmt_enable_js_challenge'              => true,
            'allmt_enable_captcha'                   => false,
            
            // ML settings
            'allmt_ml_enabled'                       => false,
            'allmt_ml_cloud_endpoint'                => '',
            'allmt_ml_api_key'                       => '',
            'allmt_ml_local_enabled'                 => true,
            
            // Integration settings
            'allmt_ga4_enabled'                      => false,
            'allmt_ga4_measurement_id'               => '',
            'allmt_ga4_api_secret'                   => '',
            'allmt_slack_enabled'                    => false,
            'allmt_slack_webhook'                    => '',
            
            // Pro settings (placeholder)
            'allmt_is_pro'                           => false,
            'allmt_license_key'                      => '',
            'allmt_license_status'                   => 'inactive',
        );

        foreach ( $defaults as $option_name => $option_value ) {
            if ( false === get_option( $option_name ) ) {
                add_option( $option_name, $option_value );
            }
        }
    }

    /**
     * Schedule cron jobs
     *
     * @return void
     */
    private static function schedule_cron_jobs(): void {
        // Data cleanup cron (daily)
        if ( ! wp_next_scheduled( 'allmt_cleanup_old_data' ) ) {
            wp_schedule_event( time(), 'daily', 'allmt_cleanup_old_data' );
        }

        // Stats aggregation cron (hourly)
        if ( ! wp_next_scheduled( 'allmt_aggregate_stats' ) ) {
            wp_schedule_event( time(), 'hourly', 'allmt_aggregate_stats' );
        }

        // Blocklist cleanup cron (every 15 minutes)
        if ( ! wp_next_scheduled( 'allmt_cleanup_blocklist' ) ) {
            wp_schedule_event( time(), 'allmt_15min', 'allmt_cleanup_blocklist' );
        }

        // Known bots update cron (weekly)
        if ( ! wp_next_scheduled( 'allmt_update_known_bots' ) ) {
            wp_schedule_event( time(), 'weekly', 'allmt_update_known_bots' );
        }
    }

    /**
     * Clear cron jobs
     *
     * @return void
     */
    private static function clear_cron_jobs(): void {
        wp_clear_scheduled_hook( 'allmt_cleanup_old_data' );
        wp_clear_scheduled_hook( 'allmt_aggregate_stats' );
        wp_clear_scheduled_hook( 'allmt_cleanup_blocklist' );
        wp_clear_scheduled_hook( 'allmt_update_known_bots' );
    }

    /**
     * Create required directories
     *
     * @return void
     */
    private static function create_directories(): void {
        $upload_dir = wp_upload_dir();
        $allmt_dir  = $upload_dir['basedir'] . '/advanced-llm-tracker';

        if ( ! file_exists( $allmt_dir ) ) {
            wp_mkdir_p( $allmt_dir );
        }

        // Create .htaccess to protect directory
        $htaccess_file = $allmt_dir . '/.htaccess';
        if ( ! file_exists( $htaccess_file ) ) {
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "deny from all\n";
            file_put_contents( $htaccess_file, $htaccess_content );
        }

        // Create index.php to prevent directory listing
        $index_file = $allmt_dir . '/index.php';
        if ( ! file_exists( $index_file ) ) {
            file_put_contents( $index_file, '<?php // Silence is golden' );
        }
    }

    /**
     * Drop database tables
     *
     * @return void
     */
    private static function drop_tables(): void {
        global $wpdb;

        $table_prefix = $wpdb->prefix . 'allmt_';
        $tables       = array(
            'sessions',
            'events',
            'classifications',
            'alerts',
            'settings',
            'known_bots',
            'blocklist',
            'audit_log',
        );

        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$table_prefix}{$table}" );
        }
    }

    /**
     * Delete plugin options
     *
     * @return void
     */
    private static function delete_options(): void {
        global $wpdb;

        // Delete all allmt_ prefixed options
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'allmt_%'" );
    }

    /**
     * Populate known bots data
     *
     * @return void
     */
    private static function populate_known_bots(): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'allmt_known_bots';

        // Check if data already exists
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
        if ( $count > 0 ) {
            return;
        }

        $known_bots = array(
            array(
                'bot_name'          => 'GPTBot',
                'bot_type'          => 'training_harvester',
                'user_agent_patterns' => json_encode( array( 'GPTBot', 'OpenAI' ) ),
                'ip_ranges'         => json_encode( array( '20.15.240.0/20', '20.171.206.0/24', '52.230.152.0/24' ) ),
                'is_trusted'        => 1,
                'rate_limit'        => 100,
                'description'       => 'OpenAI GPTBot for training data collection',
                'official_url'      => 'https://openai.com/gptbot',
            ),
            array(
                'bot_name'          => 'ClaudeBot',
                'bot_type'          => 'training_harvester',
                'user_agent_patterns' => json_encode( array( 'ClaudeBot', 'anthropic' ) ),
                'ip_ranges'         => json_encode( array() ),
                'is_trusted'        => 1,
                'rate_limit'        => 100,
                'description'       => 'Anthropic ClaudeBot for training data collection',
                'official_url'      => 'https://www.anthropic.com',
            ),
            array(
                'bot_name'          => 'Google-Extended',
                'bot_type'          => 'training_harvester',
                'user_agent_patterns' => json_encode( array( 'Google-Extended' ) ),
                'ip_ranges'         => json_encode( array() ),
                'is_trusted'        => 1,
                'rate_limit'        => 200,
                'description'       => 'Google Extended for Bard/AI training',
                'official_url'      => 'https://developers.google.com/search/docs/crawling-indexing/overview-google-crawlers',
            ),
            array(
                'bot_name'          => 'CCBot',
                'bot_type'          => 'training_harvester',
                'user_agent_patterns' => json_encode( array( 'CCBot', 'CommonCrawl' ) ),
                'ip_ranges'         => json_encode( array() ),
                'is_trusted'        => 1,
                'rate_limit'        => 50,
                'description'       => 'Common Crawl bot for open dataset creation',
                'official_url'      => 'https://commoncrawl.org',
            ),
            array(
                'bot_name'          => 'PerplexityBot',
                'bot_type'          => 'search_indexer',
                'user_agent_patterns' => json_encode( array( 'PerplexityBot' ) ),
                'ip_ranges'         => json_encode( array() ),
                'is_trusted'        => 1,
                'rate_limit'        => 150,
                'description'       => 'Perplexity AI search indexer',
                'official_url'      => 'https://www.perplexity.ai',
            ),
            array(
                'bot_name'          => 'OAI-SearchBot',
                'bot_type'          => 'search_indexer',
                'user_agent_patterns' => json_encode( array( 'OAI-SearchBot' ) ),
                'ip_ranges'         => json_encode( array() ),
                'is_trusted'        => 1,
                'rate_limit'        => 150,
                'description'       => 'OpenAI Search bot',
                'official_url'      => 'https://openai.com/search',
            ),
            array(
                'bot_name'          => 'ChatGPT-User',
                'bot_type'          => 'research_aggregator',
                'user_agent_patterns' => json_encode( array( 'ChatGPT-User' ) ),
                'ip_ranges'         => json_encode( array() ),
                'is_trusted'        => 1,
                'rate_limit'        => 200,
                'description'       => 'ChatGPT user agent for browsing',
                'official_url'      => 'https://openai.com/chatgpt',
            ),
            array(
                'bot_name'          => 'Meta-ExternalAgent',
                'bot_type'          => 'training_harvester',
                'user_agent_patterns' => json_encode( array( 'Meta-ExternalAgent', 'FacebookBot' ) ),
                'ip_ranges'         => json_encode( array() ),
                'is_trusted'        => 1,
                'rate_limit'        => 100,
                'description'       => 'Meta AI training data collector',
                'official_url'      => 'https://ai.meta.com',
            ),
        );

        foreach ( $known_bots as $bot ) {
            $wpdb->insert( $table_name, $bot );
        }
    }
}
