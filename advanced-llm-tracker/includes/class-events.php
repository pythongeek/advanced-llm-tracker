<?php
/**
 * Events handler for Advanced LLM Tracker
 *
 * @package Advanced_LLM_Tracker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ALLMT_Events
 *
 * Handles event-related operations and data access.
 */
class ALLMT_Events {

    /**
     * Event types
     */
    const TYPE_PAGE_VIEW        = 'page_view';
    const TYPE_MOUSE_TRAJECTORY = 'mouse_trajectory';
    const TYPE_SCROLL_BEHAVIOR  = 'scroll_behavior';
    const TYPE_CLICK            = 'click';
    const TYPE_FORM_INTERACTION = 'form_interaction';
    const TYPE_ELEMENT_VISIBLE  = 'element_visible';
    const TYPE_SESSION_START    = 'session_start';
    const TYPE_SESSION_END      = 'session_end';

    /**
     * Insert a new event
     *
     * @param string $session_id Session ID.
     * @param string $event_type Event type.
     * @param array  $data Event data.
     * @return int|false Event ID or false on failure.
     */
    public static function insert( string $session_id, string $event_type, array $data = array() ) {
        global $wpdb;

        $events_table = $wpdb->prefix . 'allmt_events';

        $event_data = array(
            'session_id' => $session_id,
            'event_type' => sanitize_text_field( $event_type ),
            'event_data' => ! empty( $data ) ? wp_json_encode( $data ) : null,
            'page_url'   => isset( $data['pageUrl'] ) ? esc_url_raw( $data['pageUrl'] ) : null,
            'page_path'  => isset( $data['pageUrl'] ) ? esc_url_raw( wp_parse_url( $data['pageUrl'], PHP_URL_PATH ) ) : null,
            'timestamp'  => microtime( true ),
            'created_at' => current_time( 'mysql' ),
        );

        // Add optional coordinate fields
        if ( isset( $data['x'] ) ) {
            $event_data['viewport_x'] = intval( $data['x'] );
        }
        if ( isset( $data['y'] ) ) {
            $event_data['viewport_y'] = intval( $data['y'] );
        }
        if ( isset( $data['scrollDepth'] ) ) {
            $event_data['scroll_depth'] = intval( $data['scrollDepth'] );
        }

        $result = $wpdb->insert( $events_table, $event_data );

        if ( $result === false ) {
            return false;
        }

        // Update session based on event type
        self::update_session_for_event( $session_id, $event_type );

        return $wpdb->insert_id;
    }

    /**
     * Get events for a session
     *
     * @param string $session_id Session ID.
     * @param array  $args Query arguments.
     * @return array Array of events.
     */
    public static function get_by_session( string $session_id, array $args = array() ): array {
        global $wpdb;

        $defaults = array(
            'limit'   => 100,
            'orderby' => 'timestamp',
            'order'   => 'ASC',
        );

        $args = wp_parse_args( $args, $defaults );

        $events_table = $wpdb->prefix . 'allmt_events';
        $orderby      = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] ) ?: 'timestamp ASC';

        $events = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$events_table}` WHERE session_id = %s ORDER BY {$orderby} LIMIT %d",
                $session_id,
                $args['limit']
            ),
            ARRAY_A
        );

        return $events ?: array();
    }

    /**
     * Get events by type
     *
     * @param string $event_type Event type.
     * @param array  $args Query arguments.
     * @return array Array of events.
     */
    public static function get_by_type( string $event_type, array $args = array() ): array {
        global $wpdb;

        $defaults = array(
            'limit'  => 100,
            'since'  => null,
            'offset' => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $events_table = $wpdb->prefix . 'allmt_events';

        $where = ' WHERE event_type = %s';
        $params = array( $event_type );

        if ( $args['since'] ) {
            $where .= ' AND created_at >= %s';
            $params[] = $args['since'];
        }

        $params[] = $args['offset'];
        $params[] = $args['limit'];

        $events = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$events_table}`{$where} ORDER BY created_at DESC LIMIT %d, %d",
                $params
            ),
            ARRAY_A
        );

        return $events ?: array();
    }

    /**
     * Count events for a session
     *
     * @param string $session_id Session ID.
     * @param string $event_type Optional event type filter.
     * @return int Number of events.
     */
    public static function count_by_session( string $session_id, string $event_type = '' ): int {
        global $wpdb;

        $events_table = $wpdb->prefix . 'allmt_events';

        $where = ' WHERE session_id = %s';
        $params = array( $session_id );

        if ( ! empty( $event_type ) ) {
            $where .= ' AND event_type = %s';
            $params[] = $event_type;
        }

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$events_table}`{$where}",
                $params
            )
        );

        return intval( $count );
    }

    /**
     * Count total events
     *
     * @param array $args Query arguments.
     * @return int Number of events.
     */
    public static function count( array $args = array() ): int {
        global $wpdb;

        $events_table = $wpdb->prefix . 'allmt_events';

        $where = '';
        $params = array();

        if ( isset( $args['since'] ) ) {
            $where = ' WHERE created_at >= %s';
            $params[] = $args['since'];
        }

        if ( isset( $args['event_type'] ) ) {
            $where .= empty( $where ) ? ' WHERE' : ' AND';
            $where .= ' event_type = %s';
            $params[] = $args['event_type'];
        }

        if ( empty( $params ) ) {
            $count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$events_table}`" );
        } else {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM `{$events_table}`{$where}",
                    $params
                )
            );
        }

        return intval( $count );
    }

    /**
     * Delete events for a session
     *
     * @param string $session_id Session ID.
     * @return bool True on success, false on failure.
     */
    public static function delete_by_session( string $session_id ): bool {
        global $wpdb;

        $events_table = $wpdb->prefix . 'allmt_events';

        $result = $wpdb->delete(
            $events_table,
            array( 'session_id' => $session_id )
        );

        return $result !== false;
    }

    /**
     * Delete old events
     *
     * @param string $cutoff_date Date cutoff (Y-m-d H:i:s format).
     * @return int Number of deleted events.
     */
    public static function delete_old( string $cutoff_date ): int {
        global $wpdb;

        $events_table = $wpdb->prefix . 'allmt_events';

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$events_table}` WHERE created_at < %s",
                $cutoff_date
            )
        );

        return $result !== false ? $wpdb->rows_affected : 0;
    }

    /**
     * Get event statistics
     *
     * @param string $period Time period (24h, 7d, 30d, 90d).
     * @return array Statistics data.
     */
    public static function get_stats( string $period = '24h' ): array {
        global $wpdb;

        $time_ranges = array(
            '24h' => '-1 day',
            '7d'  => '-7 days',
            '30d' => '-30 days',
            '90d' => '-90 days',
        );

        $since = isset( $time_ranges[ $period ] ) ? $time_ranges[ $period ] : '-1 day';
        $since_date = gmdate( 'Y-m-d H:i:s', strtotime( $since ) );

        $events_table = $wpdb->prefix . 'allmt_events';

        // Get total events
        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$events_table}` WHERE created_at >= %s",
                $since_date
            )
        );

        // Get events by type
        $by_type = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT event_type, COUNT(*) as count FROM `{$events_table}` WHERE created_at >= %s GROUP BY event_type",
                $since_date
            ),
            ARRAY_A
        );

        $type_counts = array();
        foreach ( $by_type as $row ) {
            $type_counts[ $row['event_type'] ] = intval( $row['count'] );
        }

        return array(
            'total'    => intval( $total ),
            'by_type'  => $type_counts,
            'period'   => $period,
            'since'    => $since_date,
        );
    }

    /**
     * Get events timeline
     *
     * @param string $period Time period.
     * @return array Timeline data.
     */
    public static function get_timeline( string $period = '24h' ): array {
        global $wpdb;

        $time_ranges = array(
            '24h' => array( 'interval' => 'HOUR', 'format' => '%Y-%m-%d %H:00:00' ),
            '7d'  => array( 'interval' => 'DAY', 'format' => '%Y-%m-%d' ),
            '30d' => array( 'interval' => 'DAY', 'format' => '%Y-%m-%d' ),
            '90d' => array( 'interval' => 'WEEK', 'format' => '%Y-%u' ),
        );

        $interval = isset( $time_ranges[ $period ] ) ? $time_ranges[ $period ]['interval'] : 'HOUR';
        $since    = gmdate( 'Y-m-d H:i:s', strtotime( isset( $time_ranges[ $period ] ) ? $time_ranges[ $period ]['interval'] === 'HOUR' ? '-1 day' : '-7 days' : '-1 day' ) );

        $events_table = $wpdb->prefix . 'allmt_events';

        $timeline = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE_FORMAT(created_at, %s) as time_period, COUNT(*) as count 
                 FROM `{$events_table}` 
                 WHERE created_at >= %s 
                 GROUP BY time_period 
                 ORDER BY time_period ASC",
                $interval === 'HOUR' ? '%Y-%m-%d %H:00:00' : '%Y-%m-%d',
                $since
            ),
            ARRAY_A
        );

        return $timeline ?: array();
    }

    /**
     * Update session based on event type
     *
     * @param string $session_id Session ID.
     * @param string $event_type Event type.
     * @return void
     */
    private static function update_session_for_event( string $session_id, string $event_type ): void {
        switch ( $event_type ) {
            case self::TYPE_PAGE_VIEW:
                ALLMT_Session::increment_page_views( $session_id );
                break;

            case self::TYPE_MOUSE_TRAJECTORY:
                ALLMT_Session::set_has_mouse_data( $session_id );
                break;

            case self::TYPE_SCROLL_BEHAVIOR:
                ALLMT_Session::set_has_scroll_data( $session_id );
                break;
        }
    }

    /**
     * Batch insert events
     *
     * @param string $session_id Session ID.
     * @param array  $events Array of events.
     * @return int Number of inserted events.
     */
    public static function batch_insert( string $session_id, array $events ): int {
        $inserted = 0;

        foreach ( $events as $event ) {
            if ( ! isset( $event['type'] ) ) {
                continue;
            }

            $data = isset( $event['data'] ) ? $event['data'] : array();
            if ( isset( $event['pageUrl'] ) ) {
                $data['pageUrl'] = $event['pageUrl'];
            }

            $result = self::insert( $session_id, $event['type'], $data );

            if ( $result !== false ) {
                $inserted++;
            }
        }

        return $inserted;
    }
}
