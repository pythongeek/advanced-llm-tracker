<?php
/**
 * ML Engine for Advanced LLM Tracker
 *
 * @package Advanced_LLM_Tracker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ALLMT_ML_Engine
 *
 * Handles machine learning operations.
 */
class ALLMT_ML_Engine {

    /**
     * Model version
     *
     * @var string
     */
    private $model_version = '1.0.0';

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize ML engine
    }

    /**
     * Get model version
     *
     * @return string
     */
    public function get_model_version(): string {
        return $this->model_version;
    }

    /**
     * Predict bot probability using local heuristic model
     *
     * @param array $features Feature vector.
     * @return array Prediction result.
     */
    public function predict_local( array $features ): array {
        // This is a simplified heuristic model
        // In production, this would use a trained model (e.g., XGBoost exported to PHP)

        $bot_score   = 0;
        $human_score = 0;

        // Weights for different features
        $weights = array(
            'has_mouse_data'      => array( 'bot' => -10, 'human' => 15 ),
            'has_scroll_data'     => array( 'bot' => -10, 'human' => 15 ),
            'request_rate'        => array( 'bot' => 20, 'human' => -5 ),
            'engagement_score'    => array( 'bot' => -15, 'human' => 20 ),
            'bot_indicators'      => array( 'bot' => 15, 'human' => -10 ),
            'human_indicators'    => array( 'bot' => -15, 'human' => 15 ),
            'has_bot_ua'          => array( 'bot' => 25, 'human' => 0 ),
            'is_logged_in'        => array( 'bot' => 0, 'human' => 25 ),
        );

        foreach ( $weights as $feature => $scores ) {
            $value = $features[ $feature ] ?? 0;
            
            if ( is_bool( $value ) || $value === 0 || $value === 1 ) {
                // Binary feature
                if ( $value ) {
                    $bot_score   += $scores['bot'];
                    $human_score += $scores['human'];
                }
            } else {
                // Continuous feature - normalize to 0-1
                $normalized = min( 1, max( 0, $value ) );
                $bot_score   += $scores['bot'] * $normalized;
                $human_score += $scores['human'] * $normalized;
            }
        }

        $total_score = abs( $bot_score ) + abs( $human_score );
        $bot_probability = $total_score > 0 ? max( 0, min( 1, 0.5 + ( $bot_score / $total_score ) ) ) : 0.5;

        return array(
            'bot_probability'   => round( $bot_probability, 4 ),
            'human_probability' => round( 1 - $bot_probability, 4 ),
            'confidence'        => $this->calculate_confidence( $features ),
            'model_version'     => $this->model_version,
        );
    }

    /**
     * Calculate prediction confidence
     *
     * @param array $features Feature vector.
     * @return float
     */
    private function calculate_confidence( array $features ): float {
        $confidence = 0.5;

        // More data = higher confidence
        $event_count = ( $features['mouse_event_count'] ?? 0 ) + 
                       ( $features['scroll_event_count'] ?? 0 );
        
        if ( $event_count > 50 ) {
            $confidence += 0.2;
        } elseif ( $event_count > 20 ) {
            $confidence += 0.1;
        }

        // Clear indicators = higher confidence
        if ( ! empty( $features['has_bot_ua'] ) || ! empty( $features['is_logged_in'] ) ) {
            $confidence += 0.2;
        }

        return min( 0.95, $confidence );
    }

    /**
     * Check if cloud ML is available
     *
     * @return bool
     */
    public function is_cloud_ml_available(): bool {
        return get_option( 'allmt_ml_enabled', false ) && 
               ! empty( get_option( 'allmt_ml_cloud_endpoint', '' ) );
    }
}
