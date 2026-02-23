<?php
/**
 * Feature Extractor for Advanced LLM Tracker
 *
 * Extracts behavioral features from sessions for ML classification.
 *
 * @package Advanced_LLM_Tracker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ALLMT_Feature_Extractor
 *
 * Extracts behavioral features from session data for bot detection.
 */
class ALLMT_Feature_Extractor {

    /**
     * Session ID
     *
     * @var string
     */
    private $session_id;

    /**
     * Session data
     *
     * @var array|null
     */
    private $session_data;

    /**
     * Events data
     *
     * @var array
     */
    private $events;

    /**
     * Extracted features
     *
     * @var array
     */
    private $features = array();

    /**
     * Constructor
     *
     * @param string $session_id Session ID.
     */
    public function __construct( string $session_id ) {
        $this->session_id = $session_id;
        $this->load_session_data();
    }

    /**
     * Load session data from database
     *
     * @return void
     */
    private function load_session_data(): void {
        global $wpdb;

        $sessions_table = $wpdb->prefix . 'allmt_sessions';
        $events_table   = $wpdb->prefix . 'allmt_events';

        $this->session_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$sessions_table}` WHERE session_id = %s",
                $this->session_id
            ),
            ARRAY_A
        );

        if ( $this->session_data ) {
            $this->events = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM `{$events_table}` WHERE session_id = %s ORDER BY timestamp ASC",
                    $this->session_id
                ),
                ARRAY_A
            );
        } else {
            $this->events = array();
        }
    }

    /**
     * Extract all features
     *
     * @return array
     */
    public function extract_all_features(): array {
        $this->features = array();

        // Request-level features
        $this->extract_request_features();

        // Session-level features
        $this->extract_session_features();

        // Client-side interaction features
        $this->extract_client_side_features();

        // Temporal features
        $this->extract_temporal_features();

        // Behavioral pattern features
        $this->extract_behavioral_patterns();

        return $this->features;
    }

    /**
     * Extract request-level features
     *
     * @return void
     */
    private function extract_request_features(): void {
        if ( ! $this->session_data ) {
            return;
        }

        // Request rate (requests per minute)
        $session_duration = max( 1, intval( $this->session_data['session_duration'] ?? 60 ) );
        $request_count    = intval( $this->session_data['request_count'] ?? 0 );
        $request_rate     = ( $request_count / $session_duration ) * 60;

        $this->features['request_rate'] = round( $request_rate, 2 );
        $this->features['request_count'] = $request_count;

        // User agent analysis
        $user_agent = $this->session_data['user_agent'] ?? '';
        $this->features['ua_length'] = strlen( $user_agent );
        $this->features['has_js_ua'] = (int) ( strpos( $user_agent, 'JavaScript' ) !== false );
        $this->features['has_bot_ua'] = (int) preg_match( '/bot|crawler|spider|scraper/i', $user_agent );

        // Check for known bot signatures
        $this->features['is_known_bot'] = intval( $this->session_data['is_known_bot'] ?? 0 );

        // TLS fingerprint presence
        $this->features['has_tls_fp'] = (int) ! empty( $this->session_data['ja3_fingerprint'] );

        // Cookie handling (if we have session, cookies are working)
        $this->features['accepts_cookies'] = 1;
    }

    /**
     * Extract session-level features
     *
     * @return void
     */
    private function extract_session_features(): void {
        if ( ! $this->session_data ) {
            return;
        }

        $page_views = intval( $this->session_data['page_views'] ?? 0 );
        $request_count = intval( $this->session_data['request_count'] ?? 1 );

        // Path efficiency (unique pages / total requests)
        $this->features['path_efficiency'] = round( $page_views / $request_count, 4 );

        // Session duration in seconds
        $session_duration = intval( $this->session_data['session_duration'] ?? 0 );
        $this->features['session_duration'] = $session_duration;

        // Pages per minute
        $this->features['pages_per_minute'] = $session_duration > 0 
            ? round( ( $page_views / $session_duration ) * 60, 2 ) 
            : 0;

        // Has WordPress user
        $this->features['is_logged_in'] = intval( $this->session_data['wp_user_id'] ?? 0 ) > 0 ? 1 : 0;

        // Referrer presence
        $this->features['has_referrer'] = (int) ! empty( $this->session_data['referrer'] );
    }

    /**
     * Extract client-side interaction features
     *
     * @return void
     */
    private function extract_client_side_features(): void {
        $has_mouse_data = intval( $this->session_data['has_mouse_data'] ?? 0 );
        $has_scroll_data = intval( $this->session_data['has_scroll_data'] ?? 0 );

        $this->features['has_mouse_data'] = $has_mouse_data;
        $this->features['has_scroll_data'] = $has_scroll_data;

        // Mouse event analysis
        $mouse_events = array_filter( $this->events, function( $event ) {
            return in_array( $event['event_type'], array( 'mouse_trajectory', 'mousemove' ), true );
        } );

        if ( ! empty( $mouse_events ) ) {
            $this->analyze_mouse_events( $mouse_events );
        } else {
            $this->features['mouse_event_count'] = 0;
            $this->features['avg_mouse_velocity'] = 0;
            $this->features['mouse_direction_changes'] = 0;
        }

        // Scroll event analysis
        $scroll_events = array_filter( $this->events, function( $event ) {
            return in_array( $event['event_type'], array( 'scroll_behavior', 'scroll', 'scroll_milestone' ), true );
        } );

        if ( ! empty( $scroll_events ) ) {
            $this->analyze_scroll_events( $scroll_events );
        } else {
            $this->features['scroll_event_count'] = 0;
            $this->features['avg_scroll_velocity'] = 0;
            $this->features['max_scroll_depth'] = 0;
        }

        // Click analysis
        $click_events = array_filter( $this->events, function( $event ) {
            return $event['event_type'] === 'click';
        } );

        $this->features['click_count'] = count( $click_events );

        // Form interaction analysis
        $form_events = array_filter( $this->events, function( $event ) {
            return $event['event_type'] === 'form_interaction';
        } );

        $this->features['form_interaction_count'] = count( $form_events );

        // Element visibility events
        $visibility_events = array_filter( $this->events, function( $event ) {
            return $event['event_type'] === 'element_visible';
        } );

        $this->features['element_visibility_count'] = count( $visibility_events );
    }

    /**
     * Analyze mouse events
     *
     * @param array $mouse_events Mouse events.
     * @return void
     */
    private function analyze_mouse_events( array $mouse_events ): void {
        $this->features['mouse_event_count'] = count( $mouse_events );

        $velocities = array();
        $direction_changes = array();
        $distances = array();

        foreach ( $mouse_events as $event ) {
            $data = json_decode( $event['event_data'] ?? '{}', true );

            if ( isset( $data['avgVelocity'] ) ) {
                $velocities[] = floatval( $data['avgVelocity'] );
            }
            if ( isset( $data['directionChanges'] ) ) {
                $direction_changes[] = intval( $data['directionChanges'] );
            }
            if ( isset( $data['distance'] ) ) {
                $distances[] = intval( $data['distance'] );
            }
        }

        $this->features['avg_mouse_velocity'] = ! empty( $velocities ) 
            ? round( array_sum( $velocities ) / count( $velocities ), 2 ) 
            : 0;

        $this->features['mouse_direction_changes'] = ! empty( $direction_changes ) 
            ? array_sum( $direction_changes ) 
            : 0;

        $this->features['total_mouse_distance'] = ! empty( $distances ) 
            ? array_sum( $distances ) 
            : 0;

        // Detect suspicious patterns
        $this->features['suspicious_mouse_pattern'] = $this->detect_suspicious_mouse_pattern( $velocities );
    }

    /**
     * Analyze scroll events
     *
     * @param array $scroll_events Scroll events.
     * @return void
     */
    private function analyze_scroll_events( array $scroll_events ): void {
        $this->features['scroll_event_count'] = count( $scroll_events );

        $velocities = array();
        $max_depth = 0;
        $direction_changes = 0;

        foreach ( $scroll_events as $event ) {
            $data = json_decode( $event['event_data'] ?? '{}', true );

            if ( isset( $data['avgVelocity'] ) ) {
                $velocities[] = floatval( $data['avgVelocity'] );
            }
            if ( isset( $data['maxVelocity'] ) ) {
                $velocities[] = floatval( $data['maxVelocity'] );
            }
            if ( isset( $data['finalDepth'] ) ) {
                $max_depth = max( $max_depth, intval( $data['finalDepth'] ) );
            }
            if ( isset( $data['depth'] ) ) {
                $max_depth = max( $max_depth, intval( $data['depth'] ) );
            }
            if ( isset( $data['directionChanges'] ) ) {
                $direction_changes += intval( $data['directionChanges'] );
            }
        }

        $this->features['avg_scroll_velocity'] = ! empty( $velocities ) 
            ? round( array_sum( $velocities ) / count( $velocities ), 2 ) 
            : 0;

        $this->features['max_scroll_velocity'] = ! empty( $velocities ) 
            ? max( $velocities ) 
            : 0;

        $this->features['max_scroll_depth'] = $max_depth;
        $this->features['scroll_direction_changes'] = $direction_changes;

        // Detect suspicious patterns
        $this->features['suspicious_scroll_pattern'] = $this->detect_suspicious_scroll_pattern( $velocities );
    }

    /**
     * Extract temporal features
     *
     * @return void
     */
    private function extract_temporal_features(): void {
        if ( empty( $this->events ) ) {
            $this->features['event_time_variance'] = 0;
            $this->features['avg_time_between_events'] = 0;
            return;
        }

        $timestamps = array_map( function( $event ) {
            return floatval( $event['timestamp'] );
        }, $this->events );

        sort( $timestamps );

        // Calculate time differences between consecutive events
        $time_diffs = array();
        for ( $i = 1; $i < count( $timestamps ); $i++ ) {
            $time_diffs[] = $timestamps[ $i ] - $timestamps[ $i - 1 ];
        }

        if ( ! empty( $time_diffs ) ) {
            $this->features['avg_time_between_events'] = round( array_sum( $time_diffs ) / count( $time_diffs ), 2 );
            $this->features['event_time_variance'] = $this->calculate_variance( $time_diffs );
            $this->features['event_time_std'] = round( sqrt( $this->features['event_time_variance'] ), 2 );
        } else {
            $this->features['avg_time_between_events'] = 0;
            $this->features['event_time_variance'] = 0;
            $this->features['event_time_std'] = 0;
        }

        // Hour of day (0-23)
        $this->features['hour_of_day'] = intval( gmdate( 'G', strtotime( $this->session_data['session_start'] ?? 'now' ) ) );

        // Day of week (0-6, Sunday = 0)
        $this->features['day_of_week'] = intval( gmdate( 'w', strtotime( $this->session_data['session_start'] ?? 'now' ) ) );
    }

    /**
     * Extract behavioral pattern features
     *
     * @return void
     */
    private function extract_behavioral_patterns(): void {
        // Interaction depth ratio (events / page views)
        $page_views = intval( $this->session_data['page_views'] ?? 1 );
        $event_count = count( $this->events );
        $this->features['interaction_depth_ratio'] = round( $event_count / $page_views, 2 );

        // Mouse to scroll ratio
        $mouse_count = $this->features['mouse_event_count'] ?? 0;
        $scroll_count = $this->features['scroll_event_count'] ?? 0;
        $this->features['mouse_scroll_ratio'] = $scroll_count > 0 
            ? round( $mouse_count / $scroll_count, 2 ) 
            : 0;

        // Engagement score (composite metric)
        $this->features['engagement_score'] = $this->calculate_engagement_score();

        // Bot likelihood indicators
        $this->features['bot_indicators'] = $this->count_bot_indicators();

        // Human likelihood indicators
        $this->features['human_indicators'] = $this->count_human_indicators();
    }

    /**
     * Calculate engagement score
     *
     * @return float
     */
    private function calculate_engagement_score(): float {
        $score = 0;

        // Mouse activity (0-30 points)
        $mouse_count = $this->features['mouse_event_count'] ?? 0;
        $score += min( 30, $mouse_count * 2 );

        // Scroll activity (0-25 points)
        $scroll_count = $this->features['scroll_event_count'] ?? 0;
        $score += min( 25, $scroll_count * 3 );

        // Click activity (0-20 points)
        $click_count = $this->features['click_count'] ?? 0;
        $score += min( 20, $click_count * 5 );

        // Form interactions (0-15 points)
        $form_count = $this->features['form_interaction_count'] ?? 0;
        $score += min( 15, $form_count * 5 );

        // Element visibility (0-10 points)
        $visibility_count = $this->features['element_visibility_count'] ?? 0;
        $score += min( 10, $visibility_count );

        return round( $score, 2 );
    }

    /**
     * Count bot indicators
     *
     * @return int
     */
    private function count_bot_indicators(): int {
        $indicators = 0;

        // No mouse data
        if ( empty( $this->features['mouse_event_count'] ) || $this->features['mouse_event_count'] === 0 ) {
            $indicators++;
        }

        // No scroll data
        if ( empty( $this->features['scroll_event_count'] ) || $this->features['scroll_event_count'] === 0 ) {
            $indicators++;
        }

        // Very high request rate
        if ( ( $this->features['request_rate'] ?? 0 ) > 100 ) {
            $indicators++;
        }

        // Suspicious mouse pattern
        if ( ! empty( $this->features['suspicious_mouse_pattern'] ) ) {
            $indicators++;
        }

        // Suspicious scroll pattern
        if ( ! empty( $this->features['suspicious_scroll_pattern'] ) ) {
            $indicators++;
        }

        // Known bot user agent
        if ( ! empty( $this->features['has_bot_ua'] ) ) {
            $indicators += 2;
        }

        // Very low engagement
        if ( ( $this->features['engagement_score'] ?? 0 ) < 10 ) {
            $indicators++;
        }

        return $indicators;
    }

    /**
     * Count human indicators
     *
     * @return int
     */
    private function count_human_indicators(): int {
        $indicators = 0;

        // Has mouse data
        if ( ! empty( $this->features['mouse_event_count'] ) && $this->features['mouse_event_count'] > 0 ) {
            $indicators++;
        }

        // Has scroll data
        if ( ! empty( $this->features['scroll_event_count'] ) && $this->features['scroll_event_count'] > 0 ) {
            $indicators++;
        }

        // Reasonable request rate
        $request_rate = $this->features['request_rate'] ?? 0;
        if ( $request_rate > 0 && $request_rate < 30 ) {
            $indicators++;
        }

        // Natural mouse movement
        if ( ! empty( $this->features['mouse_direction_changes'] ) && $this->features['mouse_direction_changes'] > 5 ) {
            $indicators++;
        }

        // Good scroll depth
        if ( ( $this->features['max_scroll_depth'] ?? 0 ) > 50 ) {
            $indicators++;
        }

        // Has clicks
        if ( ! empty( $this->features['click_count'] ) && $this->features['click_count'] > 0 ) {
            $indicators++;
        }

        // Form interactions
        if ( ! empty( $this->features['form_interaction_count'] ) && $this->features['form_interaction_count'] > 0 ) {
            $indicators++;
        }

        // High engagement score
        if ( ( $this->features['engagement_score'] ?? 0 ) > 50 ) {
            $indicators++;
        }

        // Logged in user
        if ( ! empty( $this->features['is_logged_in'] ) ) {
            $indicators += 2;
        }

        return $indicators;
    }

    /**
     * Detect suspicious mouse patterns
     *
     * @param array $velocities Mouse velocities.
     * @return int
     */
    private function detect_suspicious_mouse_pattern( array $velocities ): int {
        if ( empty( $velocities ) ) {
            return 0;
        }

        // Check for constant velocity (indicative of automation)
        $variance = $this->calculate_variance( $velocities );
        if ( $variance < 0.1 && count( $velocities ) > 5 ) {
            return 1; // Suspicious: too consistent
        }

        // Check for unrealistic speeds
        $max_velocity = max( $velocities );
        if ( $max_velocity > 5000 ) {
            return 1; // Suspicious: too fast
        }

        return 0;
    }

    /**
     * Detect suspicious scroll patterns
     *
     * @param array $velocities Scroll velocities.
     * @return int
     */
    private function detect_suspicious_scroll_pattern( array $velocities ): int {
        if ( empty( $velocities ) ) {
            return 0;
        }

        // Check for constant scroll velocity
        $variance = $this->calculate_variance( $velocities );
        if ( $variance < 0.5 && count( $velocities ) > 3 ) {
            return 1; // Suspicious: too consistent
        }

        // Check for unrealistic scroll speeds
        $max_velocity = max( $velocities );
        if ( $max_velocity > 10000 ) {
            return 1; // Suspicious: too fast
        }

        return 0;
    }

    /**
     * Calculate variance of an array
     *
     * @param array $values Values array.
     * @return float
     */
    private function calculate_variance( array $values ): float {
        if ( count( $values ) < 2 ) {
            return 0;
        }

        $mean = array_sum( $values ) / count( $values );
        $squared_diffs = array_map( function( $value ) use ( $mean ) {
            return pow( $value - $mean, 2 );
        }, $values );

        return array_sum( $squared_diffs ) / count( $squared_diffs );
    }

    /**
     * Get feature vector for ML models
     *
     * @return array
     */
    public function get_feature_vector(): array {
        if ( empty( $this->features ) ) {
            $this->extract_all_features();
        }

        // Return normalized feature vector
        return array(
            'request_rate'           => $this->normalize_value( $this->features['request_rate'] ?? 0, 0, 100 ),
            'path_efficiency'        => $this->features['path_efficiency'] ?? 0,
            'session_duration'       => $this->normalize_value( $this->features['session_duration'] ?? 0, 0, 3600 ),
            'has_mouse_data'         => $this->features['has_mouse_data'] ?? 0,
            'has_scroll_data'        => $this->features['has_scroll_data'] ?? 0,
            'mouse_event_count'      => $this->normalize_value( $this->features['mouse_event_count'] ?? 0, 0, 100 ),
            'scroll_event_count'     => $this->normalize_value( $this->features['scroll_event_count'] ?? 0, 0, 50 ),
            'click_count'            => $this->normalize_value( $this->features['click_count'] ?? 0, 0, 20 ),
            'avg_mouse_velocity'     => $this->normalize_value( $this->features['avg_mouse_velocity'] ?? 0, 0, 1000 ),
            'avg_scroll_velocity'    => $this->normalize_value( $this->features['avg_scroll_velocity'] ?? 0, 0, 500 ),
            'max_scroll_depth'       => $this->normalize_value( $this->features['max_scroll_depth'] ?? 0, 0, 100 ),
            'engagement_score'       => $this->normalize_value( $this->features['engagement_score'] ?? 0, 0, 100 ),
            'bot_indicators'         => $this->normalize_value( $this->features['bot_indicators'] ?? 0, 0, 10 ),
            'human_indicators'       => $this->normalize_value( $this->features['human_indicators'] ?? 0, 0, 10 ),
            'event_time_variance'    => $this->normalize_value( $this->features['event_time_variance'] ?? 0, 0, 10000 ),
            'has_referrer'           => $this->features['has_referrer'] ?? 0,
            'is_logged_in'           => $this->features['is_logged_in'] ?? 0,
            'has_bot_ua'             => $this->features['has_bot_ua'] ?? 0,
            'suspicious_mouse'       => $this->features['suspicious_mouse_pattern'] ?? 0,
            'suspicious_scroll'      => $this->features['suspicious_scroll_pattern'] ?? 0,
        );
    }

    /**
     * Normalize a value to 0-1 range
     *
     * @param float $value Value to normalize.
     * @param float $min Minimum expected value.
     * @param float $max Maximum expected value.
     * @return float
     */
    private function normalize_value( float $value, float $min, float $max ): float {
        if ( $max <= $min ) {
            return 0;
        }
        $normalized = ( $value - $min ) / ( $max - $min );
        return max( 0, min( 1, $normalized ) );
    }
}
