<?php
/**
 * Admin handler for Advanced LLM Tracker
 *
 * @package Advanced_LLM_Tracker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ALLMT_Admin
 *
 * Handles admin dashboard and settings.
 */
class ALLMT_Admin {

    /**
     * Plugin slug
     *
     * @var string
     */
    private $plugin_slug = 'advanced-llm-tracker';

    /**
     * Settings tabs
     *
     * @var array
     */
    private $settings_tabs = array();

    /**
     * Initialize admin
     *
     * @return void
     */
    public function init(): void {
        // Add admin menu
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        // Register settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Enqueue admin assets
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Add settings link to plugins page
        add_filter( 'plugin_action_links_' . ALLMT_BASENAME, array( $this, 'add_settings_link' ) );

        // Register default settings tabs
        $this->register_default_tabs();
    }

    /**
     * Register default settings tabs
     *
     * @return void
     */
    private function register_default_tabs(): void {
        $this->settings_tabs = array(
            'general'    => array(
                'title'    => __( 'General', 'advanced-llm-tracker' ),
                'callback' => array( $this, 'render_general_settings' ),
            ),
            'detection'  => array(
                'title'    => __( 'Detection', 'advanced-llm-tracker' ),
                'callback' => array( $this, 'render_detection_settings' ),
            ),
            'responses'  => array(
                'title'    => __( 'Responses', 'advanced-llm-tracker' ),
                'callback' => array( $this, 'render_responses_settings' ),
            ),
            'alerts'     => array(
                'title'    => __( 'Alerts', 'advanced-llm-tracker' ),
                'callback' => array( $this, 'render_alerts_settings' ),
            ),
            'integrations' => array(
                'title'    => __( 'Integrations', 'advanced-llm-tracker' ),
                'callback' => array( $this, 'render_integrations_settings' ),
            ),
            'advanced'   => array(
                'title'    => __( 'Advanced', 'advanced-llm-tracker' ),
                'callback' => array( $this, 'render_advanced_settings' ),
            ),
        );

        // Allow other components to add tabs
        $this->settings_tabs = apply_filters( 'allmt_settings_tabs', $this->settings_tabs );
    }

    /**
     * Add admin menu
     *
     * @return void
     */
    public function add_admin_menu(): void {
        // Main menu
        add_menu_page(
            __( 'Advanced LLM Tracker', 'advanced-llm-tracker' ),
            __( 'LLM Tracker', 'advanced-llm-tracker' ),
            'manage_options',
            $this->plugin_slug,
            array( $this, 'render_dashboard_page' ),
            'dashicons-shield-alt',
            30
        );

        // Dashboard submenu
        add_submenu_page(
            $this->plugin_slug,
            __( 'Dashboard', 'advanced-llm-tracker' ),
            __( 'Dashboard', 'advanced-llm-tracker' ),
            'manage_options',
            $this->plugin_slug,
            array( $this, 'render_dashboard_page' )
        );

        // Live Traffic submenu
        add_submenu_page(
            $this->plugin_slug,
            __( 'Live Traffic', 'advanced-llm-tracker' ),
            __( 'Live Traffic', 'advanced-llm-tracker' ),
            'manage_options',
            $this->plugin_slug . '-traffic',
            array( $this, 'render_traffic_page' )
        );

        // Sessions submenu
        add_submenu_page(
            $this->plugin_slug,
            __( 'Sessions', 'advanced-llm-tracker' ),
            __( 'Sessions', 'advanced-llm-tracker' ),
            'manage_options',
            $this->plugin_slug . '-sessions',
            array( $this, 'render_sessions_page' )
        );

        // Alerts submenu
        add_submenu_page(
            $this->plugin_slug,
            __( 'Alerts', 'advanced-llm-tracker' ),
            __( 'Alerts', 'advanced-llm-tracker' ),
            'manage_options',
            $this->plugin_slug . '-alerts',
            array( $this, 'render_alerts_page' )
        );

        // Settings submenu
        add_submenu_page(
            $this->plugin_slug,
            __( 'Settings', 'advanced-llm-tracker' ),
            __( 'Settings', 'advanced-llm-tracker' ),
            'manage_options',
            $this->plugin_slug . '-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings
     *
     * @return void
     */
    public function register_settings(): void {
        $settings = array(
            'allmt_enabled',
            'allmt_tracking_mode',
            'allmt_sampling_rate',
            'allmt_detailed_tracking_rate',
            'allmt_detection_sensitivity',
            'allmt_min_confidence_threshold',
            'allmt_auto_block_threshold',
            'allmt_challenge_threshold',
            'allmt_enable_consent',
            'allmt_consent_banner_type',
            'allmt_anonymize_ip',
            'allmt_data_retention_days',
            'allmt_enable_differential_privacy',
            'allmt_dp_epsilon',
            'allmt_enable_caching',
            'allmt_cache_ttl',
            'allmt_batch_size',
            'allmt_batch_interval',
            'allmt_max_events_per_session',
            'allmt_enable_alerts',
            'allmt_alert_email',
            'allmt_alert_on_critical',
            'allmt_alert_on_high',
            'allmt_alert_on_medium',
            'allmt_default_response',
            'allmt_enable_rate_limiting',
            'allmt_rate_limit_requests',
            'allmt_rate_limit_window',
            'allmt_enable_js_challenge',
            'allmt_enable_captcha',
            'allmt_ml_enabled',
            'allmt_ml_cloud_endpoint',
            'allmt_ml_api_key',
            'allmt_ml_local_enabled',
            'allmt_ga4_enabled',
            'allmt_ga4_measurement_id',
            'allmt_ga4_api_secret',
            'allmt_slack_enabled',
            'allmt_slack_webhook',
        );

        foreach ( $settings as $setting ) {
            register_setting( 'allmt_settings', $setting );
        }
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page.
     * @return void
     */
    public function enqueue_admin_assets( string $hook ): void {
        // Only load on our pages
        if ( strpos( $hook, $this->plugin_slug ) === false ) {
            return;
        }

        // Enqueue WordPress components
        wp_enqueue_style( 'wp-components' );
        wp_enqueue_script( 'wp-components' );
        wp_enqueue_script( 'wp-element' );
        wp_enqueue_script( 'wp-i18n' );
        wp_enqueue_script( 'wp-api-fetch' );

        // Enqueue Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            array(),
            '4.4.1',
            true
        );

        // Enqueue admin CSS
        wp_enqueue_style(
            'allmt-admin',
            ALLMT_URL . 'assets/css/admin.css',
            array(),
            ALLMT_VERSION
        );

        // Enqueue admin JS
        wp_enqueue_script(
            'allmt-admin',
            ALLMT_URL . 'assets/js/admin.js',
            array( 'wp-element', 'wp-i18n', 'wp-api-fetch', 'chartjs' ),
            ALLMT_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'allmt-admin',
            'ALLMT_ADMIN',
            array(
                'rest_url'   => rest_url( 'allmt/v1/' ),
                'nonce'      => wp_create_nonce( 'wp_rest' ),
                'strings'    => array(
                    'loading'   => __( 'Loading...', 'advanced-llm-tracker' ),
                    'error'     => __( 'Error loading data', 'advanced-llm-tracker' ),
                    'noData'    => __( 'No data available', 'advanced-llm-tracker' ),
                    'save'      => __( 'Save Settings', 'advanced-llm-tracker' ),
                    'saving'    => __( 'Saving...', 'advanced-llm-tracker' ),
                    'saved'     => __( 'Settings saved', 'advanced-llm-tracker' ),
                ),
            )
        );
    }

    /**
     * Add settings link to plugins page
     *
     * @param array $links Plugin action links.
     * @return array
     */
    public function add_settings_link( array $links ): array {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'admin.php?page=' . $this->plugin_slug . '-settings' ),
            __( 'Settings', 'advanced-llm-tracker' )
        );
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Render dashboard page
     *
     * @return void
     */
    public function render_dashboard_page(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <div id="allmt-dashboard-root"></div>
        </div>
        <?php
    }

    /**
     * Render traffic page
     *
     * @return void
     */
    public function render_traffic_page(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <div id="allmt-traffic-root"></div>
        </div>
        <?php
    }

    /**
     * Render sessions page
     *
     * @return void
     */
    public function render_sessions_page(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <div id="allmt-sessions-root"></div>
        </div>
        <?php
    }

    /**
     * Render alerts page
     *
     * @return void
     */
    public function render_alerts_page(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <div id="allmt-alerts-root"></div>
        </div>
        <?php
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function render_settings_page(): void {
        $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <?php foreach ( $this->settings_tabs as $tab_id => $tab ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->plugin_slug . '-settings&tab=' . $tab_id ) ); ?>" 
                       class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $tab['title'] ); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'allmt_settings' );
                
                if ( isset( $this->settings_tabs[ $current_tab ] ) && is_callable( $this->settings_tabs[ $current_tab ]['callback'] ) ) {
                    call_user_func( $this->settings_tabs[ $current_tab ]['callback'] );
                }

                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render general settings
     *
     * @return void
     */
    public function render_general_settings(): void {
        ?>
        <h2><?php esc_html_e( 'General Settings', 'advanced-llm-tracker' ); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="allmt_enabled"><?php esc_html_e( 'Enable Tracking', 'advanced-llm-tracker' ); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="allmt_enabled" name="allmt_enabled" value="1" 
                        <?php checked( get_option( 'allmt_enabled', true ) ); ?>>
                    <p class="description">
                        <?php esc_html_e( 'Enable AI bot detection and tracking.', 'advanced-llm-tracker' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="allmt_tracking_mode"><?php esc_html_e( 'Tracking Mode', 'advanced-llm-tracker' ); ?></label>
                </th>
                <td>
                    <select id="allmt_tracking_mode" name="allmt_tracking_mode">
                        <option value="minimal" <?php selected( get_option( 'allmt_tracking_mode', 'standard' ), 'minimal' ); ?>>
                            <?php esc_html_e( 'Minimal (Server-side only)', 'advanced-llm-tracker' ); ?>
                        </option>
                        <option value="standard" <?php selected( get_option( 'allmt_tracking_mode', 'standard' ), 'standard' ); ?>>
                            <?php esc_html_e( 'Standard (Server + Client)', 'advanced-llm-tracker' ); ?>
                        </option>
                        <option value="comprehensive" <?php selected( get_option( 'allmt_tracking_mode', 'standard' ), 'comprehensive' ); ?>>
                            <?php esc_html_e( 'Comprehensive (Full behavioral)', 'advanced-llm-tracker' ); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="allmt_sampling_rate"><?php esc_html_e( 'Sampling Rate (%)', 'advanced-llm-tracker' ); ?></label>
                </th>
                <td>
                    <input type="number" id="allmt_sampling_rate" name="allmt_sampling_rate" 
                        value="<?php echo esc_attr( get_option( 'allmt_sampling_rate', 100 ) ); ?>" 
                        min="1" max="100" class="small-text">
                    <p class="description">
                        <?php esc_html_e( 'Percentage of visitors to track.', 'advanced-llm-tracker' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render detection settings
     *
     * @return void
     */
    public function render_detection_settings(): void {
        ?>
        <h2><?php esc_html_e( 'Detection Settings', 'advanced-llm-tracker' ); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="allmt_detection_sensitivity"><?php esc_html_e( 'Detection Sensitivity', 'advanced-llm-tracker' ); ?></label>
                </th>
                <td>
                    <select id="allmt_detection_sensitivity" name="allmt_detection_sensitivity">
                        <option value="low" <?php selected( get_option( 'allmt_detection_sensitivity', 'medium' ), 'low' ); ?>>
                            <?php esc_html_e( 'Low (Fewer false positives)', 'advanced-llm-tracker' ); ?>
                        </option>
                        <option value="medium" <?php selected( get_option( 'allmt_detection_sensitivity', 'medium' ), 'medium' ); ?>>
                            <?php esc_html_e( 'Medium (Balanced)', 'advanced-llm-tracker' ); ?>
                        </option>
                        <option value="high" <?php selected( get_option( 'allmt_detection_sensitivity', 'medium' ), 'high' ); ?>>
                            <?php esc_html_e( 'High (More aggressive)', 'advanced-llm-tracker' ); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="allmt_min_confidence_threshold">
                        <?php esc_html_e( 'Minimum Confidence Threshold', 'advanced-llm-tracker' ); ?>
                    </label>
                </th>
                <td>
                    <input type="number" id="allmt_min_confidence_threshold" name="allmt_min_confidence_threshold" 
                        value="<?php echo esc_attr( get_option( 'allmt_min_confidence_threshold', 0.75 ) ); ?>" 
                        min="0" max="1" step="0.05" class="small-text">
                    <p class="description">
                        <?php esc_html_e( 'Minimum confidence level to classify as bot.', 'advanced-llm-tracker' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render responses settings
     *
     * @return void
     */
    public function render_responses_settings(): void {
        ?>
        <h2><?php esc_html_e( 'Response Settings', 'advanced-llm-tracker' ); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="allmt_default_response"><?php esc_html_e( 'Default Response', 'advanced-llm-tracker' ); ?></label>
                </th>
                <td>
                    <select id="allmt_default_response" name="allmt_default_response">
                        <option value="allow" <?php selected( get_option( 'allmt_default_response', 'allow' ), 'allow' ); ?>>
                            <?php esc_html_e( 'Allow', 'advanced-llm-tracker' ); ?>
                        </option>
                        <option value="monitor" <?php selected( get_option( 'allmt_default_response', 'allow' ), 'monitor' ); ?>>
                            <?php esc_html_e( 'Monitor Only', 'advanced-llm-tracker' ); ?>
                        </option>
                        <option value="challenge" <?php selected( get_option( 'allmt_default_response', 'allow' ), 'challenge' ); ?>>
                            <?php esc_html_e( 'JS Challenge', 'advanced-llm-tracker' ); ?>
                        </option>
                        <option value="block" <?php selected( get_option( 'allmt_default_response', 'allow' ), 'block' ); ?>>
                            <?php esc_html_e( 'Block', 'advanced-llm-tracker' ); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="allmt_auto_block_threshold">
                        <?php esc_html_e( 'Auto-Block Threshold', 'advanced-llm-tracker' ); ?>
                    </label>
                </th>
                <td>
                    <input type="number" id="allmt_auto_block_threshold" name="allmt_auto_block_threshold" 
                        value="<?php echo esc_attr( get_option( 'allmt_auto_block_threshold', 0.95 ) ); ?>" 
                        min="0" max="1" step="0.05" class="small-text">
                    <p class="description">
                        <?php esc_html_e( 'Confidence level required for automatic blocking.', 'advanced-llm-tracker' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="allmt_enable_rate_limiting"><?php esc_html_e( 'Enable Rate Limiting', 'advanced-llm-tracker' ); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="allmt_enable_rate_limiting" name="allmt_enable_rate_limiting" value="1" 
                        <?php checked( get_option( 'allmt_enable_rate_limiting', true ) ); ?>>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render alerts settings
     *
     * @return void
     */
    public function render_alerts_settings(): void {
        ?>
        <h2><?php esc_html_e( 'Alert Settings', 'advanced-llm-tracker' ); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="allmt_enable_alerts"><?php esc_html_e( 'Enable Alerts', 'advanced-llm-tracker' ); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="allmt_enable_alerts" name="allmt_enable_alerts" value="1" 
                        <?php checked( get_option( 'allmt_enable_alerts', true ) ); ?>>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="allmt_alert_email"><?php esc_html_e( 'Alert Email', 'advanced-llm-tracker' ); ?></label>
                </th>
                <td>
                    <input type="email" id="allmt_alert_email" name="allmt_alert_email" 
                        value="<?php echo esc_attr( get_option( 'allmt_alert_email', get_option( 'admin_email' ) ) ); ?>" 
                        class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Alert On', 'advanced-llm-tracker' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="allmt_alert_on_critical" value="1" 
                            <?php checked( get_option( 'allmt_alert_on_critical', true ) ); ?>>
                        <?php esc_html_e( 'Critical events', 'advanced-llm-tracker' ); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" name="allmt_alert_on_high" value="1" 
                            <?php checked( get_option( 'allmt_alert_on_high', true ) ); ?>>
                        <?php esc_html_e( 'High severity', 'advanced-llm-tracker' ); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" name="allmt_alert_on_medium" value="1" 
                            <?php checked( get_option( 'allmt_alert_on_medium', false ) ); ?>>
                        <?php esc_html_e( 'Medium severity', 'advanced-llm-tracker' ); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render integrations settings
     *
     * @return void
     */
    public function render_integrations_settings(): void {
        ?>
        <h2><?php esc_html_e( 'Integration Settings', 'advanced-llm-tracker' ); ?></h2>
        
        <h3><?php esc_html_e( 'Google Analytics 4', 'advanced-llm-tracker' ); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="allmt_ga4_enabled"><?php esc_html_e( 'Enable GA4 Integration', 'advanced-llm-tracker' ); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="allmt_ga4_enabled" name="allmt_ga4_enabled" value="1" 
                        <?php checked( get_option( 'allmt_ga4_enabled', false ) ); ?>>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="allmt_ga4_measurement_id"><?php esc_html_e( 'Measurement ID', 'advanced-llm-tracker' ); ?></label>
                </th>
                <td>
                    <input type="text" id="allmt_ga4_measurement_id" name="allmt_ga4_measurement_id" 
                        value="<?php echo esc_attr( get_option( 'allmt_ga4_measurement_id', '' ) ); ?>" 
                        class="regular-text" placeholder="G-XXXXXXXXXX">
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e( 'Slack', 'advanced-llm-tracker' ); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="allmt_slack_enabled"><?php esc_html_e( 'Enable Slack Alerts', 'advanced-llm-tracker' ); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="allmt_slack_enabled" name="allmt_slack_enabled" value="1" 
                        <?php checked( get_option( 'allmt_slack_enabled', false ) ); ?>>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="allmt_slack_webhook"><?php esc_html_e( 'Webhook URL', 'advanced-llm-tracker' ); ?></label>
                </th>
                <td>
                    <input type="url" id="allmt_slack_webhook" name="allmt_slack_webhook" 
                        value="<?php echo esc_attr( get_option( 'allmt_slack_webhook', '' ) ); ?>" 
                        class="regular-text">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render advanced settings
     *
     * @return void
     */
    public function render_advanced_settings(): void {
        ?>
        <h2><?php esc_html_e( 'Advanced Settings', 'advanced-llm-tracker' ); ?></h2>
        
        <h3><?php esc_html_e( 'Machine Learning', 'advanced-llm-tracker' ); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="allmt_ml_enabled"><?php esc_html_e( 'Enable Cloud ML', 'advanced-llm-tracker' ); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="allmt_ml_enabled" name="allmt_ml_enabled" value="1" 
                        <?php checked( get_option( 'allmt_ml_enabled', false ) ); ?>>
                    <p class="description">
                        <?php esc_html_e( 'Use external ML service for classification (Pro feature).', 'advanced-llm-tracker' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="allmt_ml_cloud_endpoint"><?php esc_html_e( 'ML Endpoint URL', 'advanced-llm-tracker' ); ?></label>
                </th>
                <td>
                    <input type="url" id="allmt_ml_cloud_endpoint" name="allmt_ml_cloud_endpoint" 
                        value="<?php echo esc_attr( get_option( 'allmt_ml_cloud_endpoint', '' ) ); ?>" 
                        class="regular-text">
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e( 'Performance', 'advanced-llm-tracker' ); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="allmt_enable_caching"><?php esc_html_e( 'Enable Caching', 'advanced-llm-tracker' ); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="allmt_enable_caching" name="allmt_enable_caching" value="1" 
                        <?php checked( get_option( 'allmt_enable_caching', true ) ); ?>>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="allmt_batch_size"><?php esc_html_e( 'Event Batch Size', 'advanced-llm-tracker' ); ?></label>
                </th>
                <td>
                    <input type="number" id="allmt_batch_size" name="allmt_batch_size" 
                        value="<?php echo esc_attr( get_option( 'allmt_batch_size', 100 ) ); ?>" 
                        min="10" max="500" class="small-text">
                </td>
            </tr>
        </table>
        <?php
    }
}
