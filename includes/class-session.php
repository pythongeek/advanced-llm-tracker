<?php
/**
 * Session handler for Advanced LLM Tracker
 *
 * @package Advanced_LLM_Tracker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ALLMT_Session
 *
 * Handles session-related operations and data access.
 */
class ALLMT_Session {

    /**
     * Get a session by ID
     *
     * @param string $session_id Session ID.
     * @return array|null Session data or null if not found.
     */
    public static function get( string $session_id ): ?array {
        global $wpdb;

        $sessions_table = $wpdb->prefix . 'allmt_sessions';

        $session = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$sessions_table}` WHERE session_id = %s",
                $session_id
            ),
            ARRAY_A
        );

        return $session ?: null;
    }

    /**
     * Get or create a session
     *
     * @param string $session_id Session ID.
     * @return array|null Session data or null on failure.
     */
    public static function get_or_create( string $session_id ): ?array {
        $session = self::get( $session_id );

        if ( $session ) {
            return $session;
        }

        return self::create( $session_id );
    }

    /**
     * Create a new session
     *
     * @param string $session_id Session ID.
     * @param array  $data Optional session data.
     * @return array|null Session data or null on failure.
     */
    public static function create( string $session_id, array $data = array() ): ?array {
        global $wpdb;

        $sessions_table = $wpdb->prefix . 'allmt_sessions';

        $ip_address = self::get_client_ip();
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

        // Anonymize IP if enabled
        $ip_hash = hash( 'sha256', $ip_address . wp_salt() );

        $default_data = array(
            'session_id'      => $session_id,
            'ip_address'      => get_option( 'allmt_anonymize_ip', true ) ? '' : $ip_address,
            'ip_hash'         => $ip_hash,
            'user_agent'      => $user_agent,
            'user_agent_hash' => hash( 'sha256', $user_agent ),
            'session_start'   => current_time( 'mysql' ),
            'created_at'      => current_time( 'mysql' ),
            'updated_at'      => current_time( 'mysql' ),
        );

        $session_data = wp_parse_args( $data, $default_data );

        $result = $wpdb->insert( $sessions_table, $session_data );

        if ( $result === false ) {
            return null;
        }

        return $session_data;
    }

    /**
     * Update session data
     *
     * @param string $session_id Session ID.
     * @param array  $data Data to update.
     * @return bool True on success, false on failure.
     */
    public static function update( string $session_id, array $data ): bool {
        global $wpdb;

        $sessions_table = $wpdb->prefix . 'allmt_sessions';

        $data['updated_at'] = current_time( 'mysql' );

        $result = $wpdb->update(
            $sessions_table,
            $data,
            array( 'session_id' => $session_id )
        );

        return $result !== false;
    }

    /**
     * Delete a session
     *
     * @param string $session_id Session ID.
     * @return bool True on success, false on failure.
     */
    public static function delete( string $session_id ): bool {
        global $wpdb;

        $sessions_table = $wpdb->prefix . 'allmt_sessions';

        $result = $wpdb->delete(
            $sessions_table,
            array( 'session_id' => $session_id )
        );

        return $result !== false;
    }

    /**
     * Get sessions list
     *
     * @param array $args Query arguments.
     * @return array Array of sessions.
     */
    public static function get_sessions( array $args = array() ): array {
        global $wpdb;

        $defaults = array(
            'page'     => 1,
            'per_page' => 20,
            'is_bot'   => null,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        );

        $args = wp_parse_args( $args, $defaults );

        $sessions_table = $wpdb->prefix . 'allmt_sessions';
        $offset         = ( $args['page'] - 1 ) * $args['per_page'];

        $where = '';
        if ( null !== $args['is_bot'] ) {
            $where = $wpdb->prepare( ' WHERE is_bot = %d', intval( $args['is_bot'] ) );
        }

        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] ) ?: 'created_at DESC';

        $sessions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$sessions_table}`{$where} ORDER BY {$orderby} LIMIT %d, %d",
                $offset,
                $args['per_page']
            ),
            ARRAY_A
        );

        return $sessions ?: array();
    }

    /**
     * Count sessions
     *
     * @param array $args Query arguments.
     * @return int Number of sessions.
     */
    public static function count( array $args = array() ): int {
        global $wpdb;

        $sessions_table = $wpdb->prefix . 'allmt_sessions';

        $where = '';
        if ( isset( $args['is_bot'] ) ) {
            $where = $wpdb->prepare( ' WHERE is_bot = %d', intval( $args['is_bot'] ) );
        }

        if ( isset( $args['since'] ) ) {
            $where .= empty( $where ) ? ' WHERE' : ' AND';
            $where .= $wpdb->prepare( ' created_at >= %s', $args['since'] );
        }

        $count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$sessions_table}`{$where}" );

        return intval( $count );
    }

    /**
     * Increment page views for a session
     *
     * @param string $session_id Session ID.
     * @return bool True on success, false on failure.
     */
    public static function increment_page_views( string $session_id ): bool {
        global $wpdb;

        $sessions_table = $wpdb->prefix . 'allmt_sessions';

        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE `{$sessions_table}` SET page_views = page_views + 1, request_count = request_count + 1 WHERE session_id = %s",
                $session_id
            )
        );

        return $result !== false;
    }

    /**
     * Set session as having mouse data
     *
     * @param string $session_id Session ID.
     * @return bool True on success, false on failure.
     */
    public static function set_has_mouse_data( string $session_id ): bool {
        return self::update( $session_id, array( 'has_mouse_data' => 1 ) );
    }

    /**
     * Set session as having scroll data
     *
     * @param string $session_id Session ID.
     * @return bool True on success, false on failure.
     */
    public static function set_has_scroll_data( string $session_id ): bool {
        return self::update( $session_id, array( 'has_scroll_data' => 1 ) );
    }

    /**
     * Update session classification
     *
     * @param string $session_id Session ID.
     * @param bool   $is_bot Whether session is a bot.
     * @param string $bot_type Bot type/category.
     * @param float  $confidence Confidence score.
     * @return bool True on success, false on failure.
     */
    public static function update_classification( string $session_id, bool $is_bot, string $bot_type = '', float $confidence = 0 ): bool {
        return self::update( $session_id, array(
            'is_bot'         => $is_bot ? 1 : 0,
            'bot_type'       => $bot_type,
            'bot_confidence' => $confidence,
        ) );
    }

    /**
     * Get active sessions count
     *
     * @param int $minutes Minutes to consider a session active.
     * @return int Number of active sessions.
     */
    public static function get_active_count( int $minutes = 5 ): int {
        global $wpdb;

        $sessions_table = $wpdb->prefix . 'allmt_sessions';
        $cutoff_time    = gmdate( 'Y-m-d H:i:s', strtotime( "-{$minutes} minutes" ) );

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$sessions_table}` WHERE updated_at >= %s",
                $cutoff_time
            )
        );

        return intval( $count );
    }

    /**
     * Get client IP address
     *
     * @return string IP address.
     */
    private static function get_client_ip(): string {
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
