<?php
/**
 * Statistics handler for Advanced LLM Tracker
 *
 * @package Advanced_LLM_Tracker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ALLMT_Stats
 *
 * Handles statistics aggregation and reporting.
 */
class ALLMT_Stats {

    /**
     * Get dashboard statistics
     *
     * @param string $period Time period (24h, 7d, 30d, 90d).
     * @return array Statistics data.
     */
    public static function get_dashboard_stats( string $period = '24h' ): array {
        $time_ranges = array(
            '24h' => '-1 day',
            '7d'  => '-7 days',
            '30d' => '-30 days',
            '90d' => '-90 days',
        );

        $since = isset( $time_ranges[ $period ] ) ? $time_ranges[ $period ] : '-1 day';
        $since_date = gmdate( 'Y-m-d H:i:s', strtotime( $since ) );

        return array(
            'sessions'    => self::get_session_stats( $since_date ),
            'events'      => self::get_event_stats( $since_date ),
            'bots'        => self::get_bot_stats( $since_date ),
            'alerts'      => self::get_alert_stats( $since_date ),
            'period'      => $period,
            'since'       => $since_date,
        );
    }

    /**
     * Get session statistics
     *
     * @param string $since_date Date to start from.
     * @return array Session statistics.
     */
    public static function get_session_stats( string $since_date ): array {
        global $wpdb;

        $sessions_table = $wpdb->prefix . 'allmt_sessions';

        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$sessions_table}` WHERE created_at >= %s",
                $since_date
            )
        );

        $bots = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$sessions_table}` WHERE is_bot = 1 AND created_at >= %s",
                $since_date
            )
        );

        $blocked = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$sessions_table}` WHERE blocked = 1 AND created_at >= %s",
                $since_date
            )
        );

        $humans = intval( $total ) - intval( $bots );

        return array(
            'total'       => intval( $total ),
            'bots'        => intval( $bots ),
            'humans'      => $humans,
            'blocked'     => intval( $blocked ),
            'bot_rate'    => $total > 0 ? round( ( $bots / $total ) * 100, 2 ) : 0,
        );
    }

    /**
     * Get event statistics
     *
     * @param string $since_date Date to start from.
     * @return array Event statistics.
     */
    public static function get_event_stats( string $since_date ): array {
        global $wpdb;

        $events_table = $wpdb->prefix . 'allmt_events';

        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$events_table}` WHERE created_at >= %s",
                $since_date
            )
        );

        $avg_per_session = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT AVG(event_count) FROM (
                    SELECT COUNT(*) as event_count 
                    FROM `{$events_table}` 
                    WHERE created_at >= %s 
                    GROUP BY session_id
                ) as session_events",
                $since_date
            )
        );

        return array(
            'total'           => intval( $total ),
            'avg_per_session' => round( floatval( $avg_per_session ), 2 ),
        );
    }

    /**
     * Get bot statistics
     *
     * @param string $since_date Date to start from.
     * @return array Bot statistics.
     */
    public static function get_bot_stats( string $since_date ): array {
        global $wpdb;

        $sessions_table = $wpdb->prefix . 'allmt_sessions';

        // Bot categories
        $categories = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT bot_type, COUNT(*) as count 
                 FROM `{$sessions_table}` 
                 WHERE is_bot = 1 AND created_at >= %s AND bot_type IS NOT NULL
                 GROUP BY bot_type",
                $since_date
            ),
            ARRAY_A
        );

        $by_category = array();
        foreach ( $categories as $row ) {
            $by_category[ $row['bot_type'] ] = intval( $row['count'] );
        }

        // Known vs unknown bots
        $known = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$sessions_table}` WHERE is_bot = 1 AND is_known_bot = 1 AND created_at >= %s",
                $since_date
            )
        );

        $unknown = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$sessions_table}` WHERE is_bot = 1 AND is_known_bot = 0 AND created_at >= %s",
                $since_date
            )
        );

        return array(
            'by_category' => $by_category,
            'known'       => intval( $known ),
            'unknown'     => intval( $unknown ),
        );
    }

    /**
     * Get alert statistics
     *
     * @param string $since_date Date to start from.
     * @return array Alert statistics.
     */
    public static function get_alert_stats( string $since_date ): array {
        global $wpdb;

        $alerts_table = $wpdb->prefix . 'allmt_alerts';

        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$alerts_table}` WHERE created_at >= %s",
                $since_date
            )
        );

        $unread = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$alerts_table}` WHERE is_read = 0 AND created_at >= %s",
                $since_date
            )
        );

        $by_severity = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT severity, COUNT(*) as count 
                 FROM `{$alerts_table}` 
                 WHERE created_at >= %s 
                 GROUP BY severity",
                $since_date
            ),
            ARRAY_A
        );

        $severity_counts = array(
            'info'     => 0,
            'warning'  => 0,
            'high'     => 0,
            'critical' => 0,
        );

        foreach ( $by_severity as $row ) {
            $severity_counts[ $row['severity'] ] = intval( $row['count'] );
        }

        return array(
            'total'       => intval( $total ),
            'unread'      => intval( $unread ),
            'by_severity' => $severity_counts,
        );
    }

    /**
     * Get traffic timeline
     *
     * @param string $period Time period.
     * @return array Timeline data.
     */
    public static function get_traffic_timeline( string $period = '24h' ): array {
        global $wpdb;

        $time_ranges = array(
            '24h' => array( 'interval' => 'HOUR', 'since' => '-1 day', 'format' => '%Y-%m-%d %H:00:00' ),
            '7d'  => array( 'interval' => 'DAY', 'since' => '-7 days', 'format' => '%Y-%m-%d' ),
            '30d' => array( 'interval' => 'DAY', 'since' => '-30 days', 'format' => '%Y-%m-%d' ),
            '90d' => array( 'interval' => 'WEEK', 'since' => '-90 days', 'format' => '%Y-%u' ),
        );

        $config = isset( $time_ranges[ $period ] ) ? $time_ranges[ $period ] : $time_ranges['24h'];
        $since_date = gmdate( 'Y-m-d H:i:s', strtotime( $config['since'] ) );

        $sessions_table = $wpdb->prefix . 'allmt_sessions';

        $timeline = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    DATE_FORMAT(created_at, %s) as time_period,
                    COUNT(*) as total,
                    SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) as bots,
                    SUM(CASE WHEN is_bot = 0 THEN 1 ELSE 0 END) as humans
                 FROM `{$sessions_table}` 
                 WHERE created_at >= %s 
                 GROUP BY time_period 
                 ORDER BY time_period ASC",
                $config['format'],
                $since_date
            ),
            ARRAY_A
        );

        return $timeline ?: array();
    }

    /**
     * Get top bot user agents
     *
     * @param int    $limit Number of results.
     * @param string $since_date Date to start from.
     * @return array Top bot user agents.
     */
    public static function get_top_bot_agents( int $limit = 10, string $since_date = '' ): array {
        global $wpdb;

        if ( empty( $since_date ) ) {
            $since_date = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
        }

        $sessions_table = $wpdb->prefix . 'allmt_sessions';

        $agents = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_agent, COUNT(*) as count 
                 FROM `{$sessions_table}` 
                 WHERE is_bot = 1 AND created_at >= %s AND user_agent IS NOT NULL AND user_agent != ''
                 GROUP BY user_agent 
                 ORDER BY count DESC 
                 LIMIT %d",
                $since_date,
                $limit
            ),
            ARRAY_A
        );

        return $agents ?: array();
    }

    /**
     * Get top IP addresses
     *
     * @param int    $limit Number of results.
     * @param string $since_date Date to start from.
     * @return array Top IP addresses.
     */
    public static function get_top_ips( int $limit = 10, string $since_date = '' ): array {
        global $wpdb;

        if ( empty( $since_date ) ) {
            $since_date = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
        }

        $sessions_table = $wpdb->prefix . 'allmt_sessions';

        $ips = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ip_hash, COUNT(*) as count, SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) as bot_count
                 FROM `{$sessions_table}` 
                 WHERE created_at >= %s AND ip_hash IS NOT NULL AND ip_hash != ''
                 GROUP BY ip_hash 
                 ORDER BY count DESC 
                 LIMIT %d",
                $since_date,
                $limit
            ),
            ARRAY_A
        );

        return $ips ?: array();
    }

    /**
     * Aggregate hourly stats
     *
     * @return void
     */
    public static function aggregate_hourly_stats(): void {
        global $wpdb;

        $hour = gmdate( 'Y-m-d H:00:00', strtotime( '-1 hour' ) );

        $sessions_table = $wpdb->prefix . 'allmt_sessions';

        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_sessions,
                    SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) as bot_sessions,
                    SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as blocked_sessions
                 FROM `{$sessions_table}` 
                 WHERE DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') = %s",
                $hour
            )
        );

        // Store aggregated stats (could be stored in a separate table)
        do_action( 'allmt_hourly_stats_aggregated', $hour, $stats );
    }

    /**
     * Get comparison stats
     *
     * @param string $current_period Current period.
     * @param string $previous_period Previous period for comparison.
     * @return array Comparison data.
     */
    public static function get_comparison( string $current_period = '24h', string $previous_period = '48h' ): array {
        $current_since = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $current_period ) );
        $previous_since = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $previous_period ) );
        $previous_end = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $current_period ) );

        $current_stats = self::get_session_stats( $current_since );

        global $wpdb;
        $sessions_table = $wpdb->prefix . 'allmt_sessions';

        $previous_total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$sessions_table}` WHERE created_at >= %s AND created_at < %s",
                $previous_since,
                $previous_end
            )
        );

        $previous_bots = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$sessions_table}` WHERE is_bot = 1 AND created_at >= %s AND created_at < %s",
                $previous_since,
                $previous_end
            )
        );

        $previous_stats = array(
            'total' => intval( $previous_total ),
            'bots'  => intval( $previous_bots ),
        );

        // Calculate percentage changes
        $total_change = $previous_stats['total'] > 0 
            ? round( ( ( $current_stats['total'] - $previous_stats['total'] ) / $previous_stats['total'] ) * 100, 2 )
            : 0;

        $bot_change = $previous_stats['bots'] > 0 
            ? round( ( ( $current_stats['bots'] - $previous_stats['bots'] ) / $previous_stats['bots'] ) * 100, 2 )
            : 0;

        return array(
            'current'       => $current_stats,
            'previous'      => $previous_stats,
            'total_change'  => $total_change,
            'bot_change'    => $bot_change,
        );
    }
}
