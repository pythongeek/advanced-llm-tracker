<?php
/**
 * Consent handler for Advanced LLM Tracker
 *
 * @package Advanced_LLM_Tracker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ALLMT_Consent
 *
 * Handles consent management.
 */
class ALLMT_Consent {

    /**
     * Check if user has given consent
     *
     * @return bool
     */
    public static function has_consent(): bool {
        if ( ! get_option( 'allmt_enable_consent', true ) ) {
            return true;
        }

        // Check for consent cookie
        if ( isset( $_COOKIE['allmt_consent'] ) && $_COOKIE['allmt_consent'] === 'granted' ) {
            return true;
        }

        // Check for WordPress Consent API
        if ( function_exists( 'wp_has_consent' ) && wp_has_consent( 'statistics' ) ) {
            return true;
        }

        return false;
    }

    /**
     * Grant consent
     *
     * @return void
     */
    public static function grant_consent(): void {
        setcookie(
            'allmt_consent',
            'granted',
            array(
                'expires'  => time() + YEAR_IN_SECONDS,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly' => false,
                'samesite' => 'Lax',
            )
        );
    }

    /**
     * Revoke consent
     *
     * @return void
     */
    public static function revoke_consent(): void {
        setcookie(
            'allmt_consent',
            'denied',
            array(
                'expires'  => time() + YEAR_IN_SECONDS,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly' => false,
                'samesite' => 'Lax',
            )
        );
    }
}
