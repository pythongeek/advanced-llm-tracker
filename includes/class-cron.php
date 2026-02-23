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
 * Handles cron jobs.
 */
class ALLMT_Cron {

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize cron handlers
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

        $tables = array( 'sessions', 'events', 'classifications' );

        foreach ( $tables as $table ) {
            $table_name = $wpdb->prefix . 'allmt_' . $table;
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE created_at < %s", $cutoff_date ) );
        }

        do_action( 'allmt_cron_cleanup_completed' );
    }

    /**
     * Aggregate statistics
     *
     * @return void
     */
    public static function aggregate_stats(): void {
        // Implementation for stats aggregation
        do_action( 'allmt_cron_stats_aggregated' );
    }
}
