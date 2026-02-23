<?php
/**
 * Alerts handler for Advanced LLM Tracker
 *
 * @package Advanced_LLM_Tracker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ALLMT_Alerts
 *
 * Handles alert notifications.
 */
class ALLMT_Alerts {

    /**
     * Create an alert
     *
     * @param string $type Alert type.
     * @param string $severity Alert severity.
     * @param string $title Alert title.
     * @param string $message Alert message.
     * @param string|null $session_id Session ID.
     * @param string|null $ip_address IP address.
     * @return int Alert ID.
     */
    public function create_alert( string $type, string $severity, string $title, string $message, ?string $session_id = null, ?string $ip_address = null ): int {
        global $wpdb;

        if ( ! get_option( 'allmt_enable_alerts', true ) ) {
            return 0;
        }

        // Check severity threshold
        $threshold = get_option( 'allmt_alert_on_critical', true ) ? 'critical' : 
                    ( get_option( 'allmt_alert_on_high', true ) ? 'high' : 'medium' );

        $severity_levels = array( 'info' => 0, 'warning' => 1, 'high' => 2, 'critical' => 3 );
        
        if ( ( $severity_levels[ $severity ] ?? 0 ) < ( $severity_levels[ $threshold ] ?? 0 ) ) {
            return 0;
        }

        $alert_data = array(
            'alert_type'   => $type,
            'severity'     => $severity,
            'title'        => $title,
            'message'      => $message,
            'session_id'   => $session_id,
            'ip_address'   => $ip_address,
            'created_at'   => current_time( 'mysql' ),
        );

        $wpdb->insert( $wpdb->prefix . 'allmt_alerts', $alert_data );
        $alert_id = $wpdb->insert_id;

        // Send notifications
        $this->send_notifications( $alert_data );

        return $alert_id;
    }

    /**
     * Send alert notifications
     *
     * @param array $alert Alert data.
     * @return void
     */
    private function send_notifications( array $alert ): void {
        // Email notification
        $this->send_email_notification( $alert );

        // Slack notification
        if ( get_option( 'allmt_slack_enabled', false ) ) {
            $this->send_slack_notification( $alert );
        }

        do_action( 'allmt_alert_notification_sent', $alert );
    }

    /**
     * Send email notification
     *
     * @param array $alert Alert data.
     * @return void
     */
    private function send_email_notification( array $alert ): void {
        $to      = get_option( 'allmt_alert_email', get_option( 'admin_email' ) );
        $subject = sprintf( '[%s] %s: %s', get_bloginfo( 'name' ), strtoupper( $alert['severity'] ), $alert['title'] );
        
        $message = sprintf(
            "Alert Type: %s\nSeverity: %s\nTime: %s\n\n%s\n\nSession ID: %s\nIP Address: %s\n\nView in dashboard: %s",
            $alert['alert_type'],
            $alert['severity'],
            $alert['created_at'],
            $alert['message'],
            $alert['session_id'] ?? 'N/A',
            $alert['ip_address'] ?? 'N/A',
            admin_url( 'admin.php?page=advanced-llm-tracker-alerts' )
        );

        wp_mail( $to, $subject, $message );
    }

    /**
     * Send Slack notification
     *
     * @param array $alert Alert data.
     * @return void
     */
    private function send_slack_notification( array $alert ): void {
        $webhook = get_option( 'allmt_slack_webhook', '' );
        if ( empty( $webhook ) ) {
            return;
        }

        $colors = array(
            'info'     => '#36a64f',
            'warning'  => '#daa520',
            'high'     => '#ff8c00',
            'critical' => '#dc143c',
        );

        $payload = array(
            'attachments' => array(
                array(
                    'color'  => $colors[ $alert['severity'] ] ?? '#808080',
                    'title'  => $alert['title'],
                    'text'   => $alert['message'],
                    'fields' => array(
                        array(
                            'title' => 'Type',
                            'value' => $alert['alert_type'],
                            'short' => true,
                        ),
                        array(
                            'title' => 'Severity',
                            'value' => $alert['severity'],
                            'short' => true,
                        ),
                        array(
                            'title' => 'Session ID',
                            'value' => $alert['session_id'] ?? 'N/A',
                            'short' => true,
                        ),
                        array(
                            'title' => 'Time',
                            'value' => $alert['created_at'],
                            'short' => true,
                        ),
                    ),
                ),
            ),
        );

        wp_remote_post( $webhook, array(
            'body'    => wp_json_encode( $payload ),
            'headers' => array( 'Content-Type' => 'application/json' ),
        ) );
    }
}
