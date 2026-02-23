<?php
/**
 * Classifier for Advanced LLM Tracker
 *
 * Implements rule-based heuristic scoring and provides hooks for ML classification.
 *
 * @package Advanced_LLM_Tracker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ALLMT_Classifier
 *
 * Classifies sessions as bot or human using multiple methods.
 */
class ALLMT_Classifier {

    /**
     * Classification methods
     */
    const METHOD_HEURISTIC = 'heuristic';
    const METHOD_ML_LOCAL  = 'ml_local';
    const METHOD_ML_CLOUD  = 'ml_cloud';
    const METHOD_ENSEMBLE  = 'ensemble';

    /**
     * Bot categories
     */
    const CATEGORY_TRAINING_HARVESTER = 'training_harvester';
    const CATEGORY_SEARCH_INDEXER     = 'search_indexer';
    const CATEGORY_RESEARCH_AGGREGATOR = 'research_aggregator';
    const CATEGORY_MALICIOUS_SCRAPER  = 'malicious_scraper';
    const CATEGORY_UNKNOWN_BOT        = 'unknown_bot';
    const CATEGORY_HUMAN              = 'human';

    /**
     * Classify a session
     *
     * @param string $session_id Session ID.
     * @return array Classification result.
     */
    public function classify_session( string $session_id ): array {
        // First check if it's a known bot
        $known_bot_check = $this->check_known_bot( $session_id );
        if ( $known_bot_check['is_known_bot'] ) {
            return $this->store_classification( $session_id, $known_bot_check );
        }

        // Extract features
        $feature_extractor = new ALLMT_Feature_Extractor( $session_id );
        $features          = $feature_extractor->extract_all_features();
        $feature_vector    = $feature_extractor->get_feature_vector();

        // Run heuristic classification (always available)
        $heuristic_result = $this->heuristic_classification( $features, $feature_vector );

        // Check if cloud ML is enabled and should be used
        $use_cloud_ml = get_option( 'allmt_ml_enabled', false ) && 
                        ! empty( get_option( 'allmt_ml_cloud_endpoint', '' ) );

        if ( $use_cloud_ml && $heuristic_result['confidence'] < 0.85 ) {
            // Use cloud ML for uncertain cases
            $cloud_result = $this->classify_with_cloud_ml( $session_id, $feature_vector );
            
            // Combine results
            $final_result = $this->combine_classifications( $heuristic_result, $cloud_result );
        } else {
            $final_result = $heuristic_result;
        }

        // Store classification
        return $this->store_classification( $session_id, $final_result );
    }

    /**
     * Check if session matches a known bot
     *
     * @param string $session_id Session ID.
     * @return array
     */
    private function check_known_bot( string $session_id ): array {
        global $wpdb;

        $sessions_table   = $wpdb->prefix . 'allmt_sessions';
        $known_bots_table = $wpdb->prefix . 'allmt_known_bots';

        $session = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$sessions_table}` WHERE session_id = %s",
                $session_id
            ),
            ARRAY_A
        );

        if ( ! $session ) {
            return array( 'is_known_bot' => false );
        }

        // Check user agent against known bots
        $user_agent = $session['user_agent'] ?? '';
        $known_bots = $wpdb->get_results( "SELECT * FROM `{$known_bots_table}` WHERE is_active = 1", ARRAY_A );

        foreach ( $known_bots as $bot ) {
            $patterns = json_decode( $bot['user_agent_patterns'] ?? '[]', true );
            
            foreach ( $patterns as $pattern ) {
                if ( stripos( $user_agent, $pattern ) !== false ) {
                    return array(
                        'is_bot'      => true,
                        'is_known_bot'=> true,
                        'bot_name'    => $bot['bot_name'],
                        'bot_type'    => $bot['bot_type'],
                        'confidence'  => 0.99,
                        'method'      => self::METHOD_HEURISTIC,
                        'category'    => $bot['bot_type'],
                    );
                }
            }
        }

        return array( 'is_known_bot' => false );
    }

    /**
     * Heuristic classification based on rules and scoring
     *
     * @param array $features Extracted features.
     * @param array $feature_vector Normalized feature vector.
     * @return array
     */
    private function heuristic_classification( array $features, array $feature_vector ): array {
        $bot_score    = 0;
        $human_score  = 0;
        $indicators   = array();

        // === BOT INDICATORS ===

        // 1. No client-side interaction (strong bot signal)
        if ( empty( $features['has_mouse_data'] ) && empty( $features['has_scroll_data'] ) ) {
            $bot_score += 30;
            $indicators[] = 'no_client_interaction';
        }

        // 2. Very high request rate
        $request_rate = $features['request_rate'] ?? 0;
        if ( $request_rate > 60 ) {
            $bot_score += 25;
            $indicators[] = 'high_request_rate';
        } elseif ( $request_rate > 30 ) {
            $bot_score += 15;
            $indicators[] = 'elevated_request_rate';
        }

        // 3. Suspicious mouse patterns
        if ( ! empty( $features['suspicious_mouse_pattern'] ) ) {
            $bot_score += 20;
            $indicators[] = 'suspicious_mouse';
        }

        // 4. Suspicious scroll patterns
        if ( ! empty( $features['suspicious_scroll_pattern'] ) ) {
            $bot_score += 15;
            $indicators[] = 'suspicious_scroll';
        }

        // 5. Bot in user agent
        if ( ! empty( $features['has_bot_ua'] ) ) {
            $bot_score += 25;
            $indicators[] = 'bot_user_agent';
        }

        // 6. Very low engagement
        $engagement = $features['engagement_score'] ?? 0;
        if ( $engagement < 5 ) {
            $bot_score += 20;
            $indicators[] = 'very_low_engagement';
        } elseif ( $engagement < 15 ) {
            $bot_score += 10;
            $indicators[] = 'low_engagement';
        }

        // 7. Perfect path efficiency (too efficient)
        $path_efficiency = $features['path_efficiency'] ?? 0;
        if ( $path_efficiency > 0.95 ) {
            $bot_score += 10;
            $indicators[] = 'perfect_path_efficiency';
        }

        // 8. No referrer
        if ( empty( $features['has_referrer'] ) ) {
            $bot_score += 5;
            $indicators[] = 'no_referrer';
        }

        // === HUMAN INDICATORS ===

        // 1. Has mouse data
        if ( ! empty( $features['has_mouse_data'] ) ) {
            $human_score += 15;
            $indicators[] = 'has_mouse_data';
        }

        // 2. Has scroll data
        if ( ! empty( $features['has_scroll_data'] ) ) {
            $human_score += 15;
            $indicators[] = 'has_scroll_data';
        }

        // 3. Natural mouse movement (direction changes)
        $mouse_direction_changes = $features['mouse_direction_changes'] ?? 0;
        if ( $mouse_direction_changes > 10 ) {
            $human_score += 15;
            $indicators[] = 'natural_mouse_movement';
        }

        // 4. Good scroll depth
        $max_scroll_depth = $features['max_scroll_depth'] ?? 0;
        if ( $max_scroll_depth > 75 ) {
            $human_score += 10;
            $indicators[] = 'deep_scroll';
        }

        // 5. Has clicks
        $click_count = $features['click_count'] ?? 0;
        if ( $click_count > 0 ) {
            $human_score += 10;
            $indicators[] = 'has_clicks';
        }

        // 6. Form interactions
        $form_count = $features['form_interaction_count'] ?? 0;
        if ( $form_count > 0 ) {
            $human_score += 10;
            $indicators[] = 'form_interaction';
        }

        // 7. Reasonable request rate
        if ( $request_rate > 0 && $request_rate < 20 ) {
            $human_score += 10;
            $indicators[] = 'normal_request_rate';
        }

        // 8. High engagement score
        if ( $engagement > 60 ) {
            $human_score += 15;
            $indicators[] = 'high_engagement';
        }

        // 9. Logged in user (strong signal)
        if ( ! empty( $features['is_logged_in'] ) ) {
            $human_score += 25;
            $indicators[] = 'logged_in_user';
        }

        // 10. Event time variance (humans are irregular)
        $time_variance = $features['event_time_variance'] ?? 0;
        if ( $time_variance > 100 ) {
            $human_score += 10;
            $indicators[] = 'irregular_timing';
        }

        // Calculate final scores
        $total_score    = $bot_score + $human_score;
        $bot_probability = $total_score > 0 ? $bot_score / $total_score : 0.5;
        
        // Adjust based on indicator balance
        $bot_indicators_count    = $features['bot_indicators'] ?? 0;
        $human_indicators_count  = $features['human_indicators'] ?? 0;
        
        if ( $human_indicators_count > $bot_indicators_count + 3 ) {
            $bot_probability = max( 0, $bot_probability - 0.2 );
        } elseif ( $bot_indicators_count > $human_indicators_count + 2 ) {
            $bot_probability = min( 1, $bot_probability + 0.2 );
        }

        // Determine category
        $category = $this->determine_category( $features, $bot_probability );

        // Calculate confidence
        $confidence = $this->calculate_confidence( $bot_probability, $features );

        return array(
            'is_bot'            => $bot_probability >= 0.75,
            'bot_probability'   => round( $bot_probability, 4 ),
            'human_probability' => round( 1 - $bot_probability, 4 ),
            'confidence'        => $confidence,
            'method'            => self::METHOD_HEURISTIC,
            'category'          => $category,
            'bot_score'         => $bot_score,
            'human_score'       => $human_score,
            'indicators'        => $indicators,
            'features_used'     => array_keys( $features ),
        );
    }

    /**
     * Classify with cloud ML service
     *
     * @param string $session_id Session ID.
     * @param array  $feature_vector Feature vector.
     * @return array
     */
    public function classify_with_cloud_ml( string $session_id, array $feature_vector = array() ): array {
        $endpoint = get_option( 'allmt_ml_cloud_endpoint', '' );
        $api_key  = get_option( 'allmt_ml_api_key', '' );

        if ( empty( $endpoint ) ) {
            return array(
                'is_bot'     => false,
                'confidence' => 0,
                'method'     => self::METHOD_ML_CLOUD,
                'error'      => 'Cloud ML endpoint not configured',
            );
        }

        // If no feature vector provided, extract it
        if ( empty( $feature_vector ) ) {
            $feature_extractor = new ALLMT_Feature_Extractor( $session_id );
            $feature_vector    = $feature_extractor->get_feature_vector();
        }

        $payload = array(
            'session_id'     => $session_id,
            'feature_vector' => $feature_vector,
            'timestamp'      => time(),
        );

        $args = array(
            'method'  => 'POST',
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'X-ALLMT-Version' => ALLMT_VERSION,
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 5,
        );

        $response = wp_remote_post( $endpoint, $args );

        if ( is_wp_error( $response ) ) {
            return array(
                'is_bot'     => false,
                'confidence' => 0,
                'method'     => self::METHOD_ML_CLOUD,
                'error'      => $response->get_error_message(),
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! $data || isset( $data['error'] ) ) {
            return array(
                'is_bot'     => false,
                'confidence' => 0,
                'method'     => self::METHOD_ML_CLOUD,
                'error'      => $data['error'] ?? 'Invalid response from ML service',
            );
        }

        return array(
            'is_bot'            => $data['is_bot'] ?? false,
            'bot_probability'   => $data['bot_probability'] ?? 0,
            'human_probability' => $data['human_probability'] ?? 0,
            'confidence'        => $data['confidence'] ?? 0.5,
            'method'            => self::METHOD_ML_CLOUD,
            'category'          => $data['category'] ?? self::CATEGORY_UNKNOWN_BOT,
            'model_version'     => $data['model_version'] ?? 'unknown',
        );
    }

    /**
     * Combine heuristic and ML classifications
     *
     * @param array $heuristic Heuristic result.
     * @param array $ml ML result.
     * @return array
     */
    private function combine_classifications( array $heuristic, array $ml ): array {
        // Weight based on confidence
        $heuristic_weight = $heuristic['confidence'];
        $ml_weight        = $ml['confidence'];

        // Normalize weights
        $total_weight = $heuristic_weight + $ml_weight;
        if ( $total_weight > 0 ) {
            $heuristic_weight /= $total_weight;
            $ml_weight /= $total_weight;
        } else {
            $heuristic_weight = 0.5;
            $ml_weight = 0.5;
        }

        // Weighted average of probabilities
        $bot_probability = ( $heuristic['bot_probability'] * $heuristic_weight ) + 
                           ( $ml['bot_probability'] * $ml_weight );

        // Use higher confidence
        $confidence = max( $heuristic['confidence'], $ml['confidence'] );

        // Determine final category
        if ( $bot_probability >= 0.75 ) {
            $category = $ml['category'] ?? $heuristic['category'] ?? self::CATEGORY_UNKNOWN_BOT;
        } else {
            $category = self::CATEGORY_HUMAN;
        }

        return array(
            'is_bot'            => $bot_probability >= 0.75,
            'bot_probability'   => round( $bot_probability, 4 ),
            'human_probability' => round( 1 - $bot_probability, 4 ),
            'confidence'        => $confidence,
            'method'            => self::METHOD_ENSEMBLE,
            'category'          => $category,
            'heuristic_score'   => $heuristic['bot_probability'],
            'ml_score'          => $ml['bot_probability'],
            'indicators'        => $heuristic['indicators'] ?? array(),
        );
    }

    /**
     * Determine bot category based on features
     *
     * @param array $features Features.
     * @param float $bot_probability Bot probability.
     * @return string
     */
    private function determine_category( array $features, float $bot_probability ): string {
        if ( $bot_probability < 0.75 ) {
            return self::CATEGORY_HUMAN;
        }

        $request_rate = $features['request_rate'] ?? 0;
        $engagement   = $features['engagement_score'] ?? 0;
        $path_efficiency = $features['path_efficiency'] ?? 0;

        // Training harvester: systematic, polite, comprehensive
        if ( $request_rate < 30 && $path_efficiency > 0.8 && $engagement < 20 ) {
            return self::CATEGORY_TRAINING_HARVESTER;
        }

        // Search indexer: selective, fast, structured data focused
        if ( $request_rate > 20 && $request_rate < 100 && $engagement < 30 ) {
            return self::CATEGORY_SEARCH_INDEXER;
        }

        // Malicious scraper: aggressive, evasive, targeted
        if ( $request_rate > 100 || ( empty( $features['has_mouse_data'] ) && empty( $features['has_scroll_data'] ) && $request_rate > 50 ) ) {
            return self::CATEGORY_MALICIOUS_SCRAPER;
        }

        // Research aggregator: selective, human-like pacing
        if ( $engagement > 20 && $engagement < 50 && ! empty( $features['has_scroll_data'] ) ) {
            return self::CATEGORY_RESEARCH_AGGREGATOR;
        }

        return self::CATEGORY_UNKNOWN_BOT;
    }

    /**
     * Calculate confidence score
     *
     * @param float $bot_probability Bot probability.
     * @param array $features Features.
     * @return float
     */
    private function calculate_confidence( float $bot_probability, array $features ): float {
        // Base confidence on how far from 0.5
        $distance_from_center = abs( $bot_probability - 0.5 ) * 2;
        
        // Adjust based on data quality
        $data_quality = 0;
        
        // More events = higher confidence
        $event_count = ( $features['mouse_event_count'] ?? 0 ) + 
                       ( $features['scroll_event_count'] ?? 0 ) + 
                       ( $features['click_count'] ?? 0 );
        
        if ( $event_count > 50 ) {
            $data_quality += 0.2;
        } elseif ( $event_count > 20 ) {
            $data_quality += 0.1;
        }

        // Has both mouse and scroll = higher confidence
        if ( ! empty( $features['has_mouse_data'] ) && ! empty( $features['has_scroll_data'] ) ) {
            $data_quality += 0.1;
        }

        // Known bot = very high confidence
        if ( ! empty( $features['is_known_bot'] ) ) {
            $data_quality += 0.3;
        }

        $confidence = min( 0.99, $distance_from_center + $data_quality );

        return round( $confidence, 4 );
    }

    /**
     * Store classification result
     *
     * @param string $session_id Session ID.
     * @param array  $result Classification result.
     * @return array
     */
    private function store_classification( string $session_id, array $result ): array {
        global $wpdb;

        $classifications_table = $wpdb->prefix . 'allmt_classifications';

        // Check if classification already exists
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `{$classifications_table}` WHERE session_id = %s",
                $session_id
            )
        );

        $data = array(
            'session_id'           => $session_id,
            'classification_time'  => current_time( 'mysql' ),
            'is_bot'               => $result['is_bot'] ? 1 : 0,
            'bot_probability'      => $result['bot_probability'] ?? 0,
            'human_probability'    => $result['human_probability'] ?? 0,
            'uncertainty'          => 1 - ( $result['confidence'] ?? 0.5 ),
            'bot_category'         => $result['category'] ?? self::CATEGORY_UNKNOWN_BOT,
            'classification_method'=> $result['method'] ?? self::METHOD_HEURISTIC,
            'model_version'        => $result['model_version'] ?? ALLMT_VERSION,
            'features_used'        => isset( $result['features_used'] ) ? wp_json_encode( $result['features_used'] ) : null,
            'feature_vector'       => isset( $result['feature_vector'] ) ? wp_json_encode( $result['feature_vector'] ) : null,
            'confidence_score'     => $result['confidence'] ?? 0.5,
            'requires_review'      => ( $result['confidence'] ?? 0 ) < 0.75 ? 1 : 0,
        );

        if ( $existing ) {
            $wpdb->update( $classifications_table, $data, array( 'id' => $existing ) );
        } else {
            $wpdb->insert( $classifications_table, $data );
        }

        // Update session with classification info
        $sessions_table = $wpdb->prefix . 'allmt_sessions';
        $wpdb->update(
            $sessions_table,
            array(
                'is_bot'         => $result['is_bot'] ? 1 : 0,
                'bot_type'       => $result['category'] ?? null,
                'bot_confidence' => $result['confidence'] ?? 0,
            ),
            array( 'session_id' => $session_id )
        );

        // Trigger response if bot detected
        if ( $result['is_bot'] && ( $result['confidence'] ?? 0 ) >= get_option( 'allmt_auto_block_threshold', 0.95 ) ) {
            do_action( 'allmt_high_confidence_bot_detected', $session_id, $result );
        }

        return $result;
    }
}
