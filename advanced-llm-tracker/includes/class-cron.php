<?php
/**
 * Cron handler for Advanced LLM Tracker
 *
 * @package Advanced_LLM_Tracker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ALLMT_Cron
 *
 * Handles scheduled tasks.
 */
class ALLMT_Cron {

    /**
     * Constructor
     */
    public function __construct() {
        // Cron hooks are registered in the main plugin class
    }

    /**
     * Run all scheduled tasks
     *
     * @return void
     */
    public static function run_tasks(): void {
        self::cleanup_old_data();
        self::aggregate_stats();
        self::cleanup_blocklist();
    }

    /**
     * Cleanup old data
     *
     * @return void
     */
    public static function cleanup_old_data(): void {
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
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM `{$table_name}` WHERE created_at < %s",
                $cutoff_date
            ) );
        }

        do_action( 'allmt_data_cleanup_completed', $retention_days );
    }

    /**
     * Aggregate statistics
     *
     * @return void
     */
    public static function aggregate_stats(): void {
        ALLMT_Stats::aggregate_hourly_stats();
    }

    /**
     * Cleanup expired blocklist entries
     *
     * @return void
     */
    public static function cleanup_blocklist(): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'allmt_blocklist';

        $wpdb->query( 
            "DELETE FROM `{$table_name}` WHERE expires_at < NOW() AND is_active = 1"
        );
    }

    /**
     * Update known bots list
     *
     * @return void
     */
    public static function update_known_bots(): void {
        // Fetch latest known bot signatures from remote source
        $remote_url = 'https://api.advanced-llm-tracker.com/v1/known-bots';
        
        $response = wp_remote_get( $remote_url, array( 'timeout' => 30 ) );
        
        if ( is_wp_error( $response ) ) {
            return;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! $data || ! is_array( $data ) ) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'allmt_known_bots';

        foreach ( $data as $bot ) {
            // Check if bot already exists
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM `{$table_name}` WHERE bot_name = %s",
                    $bot['bot_name']
                )
            );

            if ( $existing ) {
                // Update existing
                $wpdb->update(
                    $table_name,
                    array(
                        'user_agent_patterns' => wp_json_encode( $bot['user_agent_patterns'] ?? array() ),
                        'ip_ranges'           => wp_json_encode( $bot['ip_ranges'] ?? array() ),
                        'updated_at'          => current_time( 'mysql' ),
                    ),
                    array( 'id' => $existing )
                );
            } else {
                // Insert new
                $wpdb->insert(
                    $table_name,
                    array(
                        'bot_name'            => $bot['bot_name'],
                        'bot_type'            => $bot['bot_type'] ?? 'unknown',
                        'user_agent_patterns' => wp_json_encode( $bot['user_agent_patterns'] ?? array() ),
                        'ip_ranges'           => wp_json_encode( $bot['ip_ranges'] ?? array() ),
                        'is_trusted'          => $bot['is_trusted'] ?? 0,
                        'description'         => $bot['description'] ?? '',
                        'official_url'        => $bot['official_url'] ?? '',
                        'created_at'          => current_time( 'mysql' ),
                        'updated_at'          => current_time( 'mysql' ),
                    )
                );
            }
        }

        do_action( 'allmt_known_bots_updated' );
    }
}
