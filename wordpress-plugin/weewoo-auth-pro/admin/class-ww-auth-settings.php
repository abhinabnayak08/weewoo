<?php
/**
 * Admin Settings Class - Premium Edition
 *
 * @package WeeWoo_Auth_Pro
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings page with 4 tabs: General, Meta API, Security, Branding
 */
final class WW_Auth_Settings
{
    private static ?WW_Auth_Settings $instance = null;
    private string $page_slug = 'ww-auth-settings';

    public static function instance(): WW_Auth_Settings
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_notices', [$this, 'admin_notices']);
    }

    /**
     * Add menu item
     */
    public function add_admin_menu(): void
    {
        add_menu_page(
            __('WeeWoo Auth Pro', 'weewoo-auth-pro'),
            __('WeeWoo Auth', 'weewoo-auth-pro'),
            'manage_options',
            $this->page_slug,
            [$this, 'render_settings_page'],
            'dashicons-shield-alt',
            80
        );
    }

    /**
     * Admin notices
     */
    public function admin_notices(): void
    {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] && isset($_GET['page']) && $_GET['page'] === $this->page_slug) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Settings saved successfully!', 'weewoo-auth-pro'); ?></strong></p>
            </div>
            <?php
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets(string $hook): void
    {
        if (strpos($hook, $this->page_slug) === false) {
            return;
        }

        wp_enqueue_style(
            'ww-auth-admin',
            WW_AUTH_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WW_AUTH_VERSION
        );
    }

    /**
     * Register all settings
     */
    public function register_settings(): void
    {
        // General Settings
        register_setting('ww_auth_general', 'ww_auth_whatsapp_enabled', [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
        register_setting('ww_auth_general', 'ww_auth_email_enabled', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
        register_setting('ww_auth_general', 'ww_auth_passkeys_enabled', [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
        register_setting('ww_auth_general', 'ww_auth_qr_enabled', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
        register_setting('ww_auth_general', 'ww_auth_admin_2fa_enabled', [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
        
        // Per-form settings
        register_setting('ww_auth_general', 'ww_auth_login_whatsapp', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
        register_setting('ww_auth_general', 'ww_auth_login_email', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
        register_setting('ww_auth_general', 'ww_auth_register_whatsapp', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
        register_setting('ww_auth_general', 'ww_auth_register_email', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);

        // Meta API Settings
        register_setting('ww_auth_meta', 'ww_auth_meta_phone_id', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('ww_auth_meta', 'ww_auth_meta_access_token', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('ww_auth_meta', 'ww_auth_meta_template_name', [
            'type' => 'string',
            'default' => 'authentication_otp',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('ww_auth_meta', 'ww_auth_meta_template_lang', [
            'type' => 'string',
            'default' => 'en',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        // Security Settings
        register_setting('ww_auth_security', 'ww_auth_turnstile_enabled', [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
        register_setting('ww_auth_security', 'ww_auth_turnstile_site_key', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('ww_auth_security', 'ww_auth_turnstile_secret_key', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('ww_auth_security', 'ww_auth_rate_limit_attempts', [
            'type' => 'integer',
            'default' => 5,
            'sanitize_callback' => 'absint',
        ]);
        register_setting('ww_auth_security', 'ww_auth_rate_limit_duration', [
            'type' => 'integer',
            'default' => 60,
            'sanitize_callback' => 'absint',
        ]);

        // Branding Settings
        register_setting('ww_auth_branding', 'ww_auth_logo_url', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'esc_url_raw',
        ]);
        register_setting('ww_auth_branding', 'ww_auth_primary_color', [
            'type' => 'string',
            'default' => '#10B981',
            'sanitize_callback' => 'sanitize_hex_color',
        ]);
        register_setting('ww_auth_branding', 'ww_auth_secondary_color', [
            'type' => 'string',
            'default' => '#111827',
            'sanitize_callback' => 'sanitize_hex_color',
        ]);
        register_setting('ww_auth_branding', 'ww_auth_page_title', [
            'type' => 'string',
            'default' => 'Secure Login',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('ww_auth_branding', 'ww_auth_welcome_text', [
            'type' => 'string',
            'default' => 'Welcome back',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('ww_auth_branding', 'ww_auth_company_name', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('ww_auth_branding', 'ww_auth_email_company_name', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
    }

    /**
     * Render the settings page
     */
    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        ?>
        <div class="ww-auth-admin-wrap">
            <div class="ww-auth-admin-header">
                <h1>
                    <span class="ww-auth-logo">🔐</span>
                    <?php esc_html_e('WeeWoo Auth Pro', 'weewoo-auth-pro'); ?>
                </h1>
                <p class="ww-auth-version">v<?php echo esc_html(WW_AUTH_VERSION); ?></p>
            </div>

            <nav class="ww-auth-tabs">
                <a href="?page=<?php echo esc_attr($this->page_slug); ?>&tab=general" 
                   class="ww-auth-tab <?php echo $active_tab === 'general' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e('General', 'weewoo-auth-pro'); ?>
                </a>
                <a href="?page=<?php echo esc_attr($this->page_slug); ?>&tab=meta" 
                   class="ww-auth-tab <?php echo $active_tab === 'meta' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-whatsapp"></span>
                    <?php esc_html_e('Meta API', 'weewoo-auth-pro'); ?>
                </a>
                <a href="?page=<?php echo esc_attr($this->page_slug); ?>&tab=security" 
                   class="ww-auth-tab <?php echo $active_tab === 'security' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-shield"></span>
                    <?php esc_html_e('Security', 'weewoo-auth-pro'); ?>
                </a>
                <a href="?page=<?php echo esc_attr($this->page_slug); ?>&tab=branding" 
                   class="ww-auth-tab <?php echo $active_tab === 'branding' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-art"></span>
                    <?php esc_html_e('Branding', 'weewoo-auth-pro'); ?>
                </a>
            </nav>

            <div class="ww-auth-tab-content">
                <?php
                switch ($active_tab) {
                    case 'meta':
                        $this->render_meta_tab();
                        break;
                    case 'security':
                        $this->render_security_tab();
                        break;
                    case 'branding':
                        $this->render_branding_tab();
                        break;
                    default:
                        $this->render_general_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render General Tab
     */
    private function render_general_tab(): void
    {
        ?>
        <form method="post" action="options.php">
            <?php 
            settings_fields('ww_auth_general');
            ?>
            
            <div class="ww-auth-card">
                <h2><?php esc_html_e('Authentication Methods', 'weewoo-auth-pro'); ?></h2>
                <p class="ww-auth-card-desc"><?php esc_html_e('Enable or disable authentication methods globally.', 'weewoo-auth-pro'); ?></p>

                <div class="ww-auth-toggle-group">
                    <div class="ww-auth-toggle-row">
                        <div class="ww-auth-toggle-info">
                            <strong><?php esc_html_e('WhatsApp OTP', 'weewoo-auth-pro'); ?></strong>
                            <span><?php esc_html_e('Send one-time passwords via WhatsApp (requires Meta API setup)', 'weewoo-auth-pro'); ?></span>
                        </div>
                        <label class="ww-auth-switch">
                            <input type="checkbox" name="ww_auth_whatsapp_enabled" value="1" 
                                   <?php checked(get_option('ww_auth_whatsapp_enabled', false)); ?>>
                            <span class="ww-auth-slider"></span>
                        </label>
                    </div>

                    <div class="ww-auth-toggle-row">
                        <div class="ww-auth-toggle-info">
                            <strong><?php esc_html_e('Email OTP', 'weewoo-auth-pro'); ?></strong>
                            <span><?php esc_html_e('Send one-time passwords via Email', 'weewoo-auth-pro'); ?></span>
                        </div>
                        <label class="ww-auth-switch">
                            <input type="checkbox" name="ww_auth_email_enabled" value="1" 
                                   <?php checked(get_option('ww_auth_email_enabled', true)); ?>>
                            <span class="ww-auth-slider"></span>
                        </label>
                    </div>

                    <div class="ww-auth-toggle-row">
                        <div class="ww-auth-toggle-info">
                            <strong><?php esc_html_e('Passkeys (WebAuthn)', 'weewoo-auth-pro'); ?></strong>
                            <span><?php esc_html_e('Enable biometric/hardware key authentication', 'weewoo-auth-pro'); ?></span>
                        </div>
                        <label class="ww-auth-switch">
                            <input type="checkbox" name="ww_auth_passkeys_enabled" value="1" 
                                   <?php checked(get_option('ww_auth_passkeys_enabled', false)); ?>>
                            <span class="ww-auth-slider"></span>
                        </label>
                    </div>

                    <div class="ww-auth-toggle-row">
                        <div class="ww-auth-toggle-info">
                            <strong><?php esc_html_e('QR Code Login', 'weewoo-auth-pro'); ?></strong>
                            <span><?php esc_html_e('Desktop-to-mobile QR handshake (desktop only)', 'weewoo-auth-pro'); ?></span>
                        </div>
                        <label class="ww-auth-switch">
                            <input type="checkbox" name="ww_auth_qr_enabled" value="1" 
                                   <?php checked(get_option('ww_auth_qr_enabled', true)); ?>>
                            <span class="ww-auth-slider"></span>
                        </label>
                    </div>

                    <div class="ww-auth-toggle-row">
                        <div class="ww-auth-toggle-info">
                            <strong><?php esc_html_e('Admin 2FA', 'weewoo-auth-pro'); ?></strong>
                            <span><?php esc_html_e('Require 2FA for administrator accounts', 'weewoo-auth-pro'); ?></span>
                        </div>
                        <label class="ww-auth-switch">
                            <input type="checkbox" name="ww_auth_admin_2fa_enabled" value="1" 
                                   <?php checked(get_option('ww_auth_admin_2fa_enabled', false)); ?>>
                            <span class="ww-auth-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="ww-auth-card">
                <h2><?php esc_html_e('Per-Form Method Control', 'weewoo-auth-pro'); ?></h2>
                <p class="ww-auth-card-desc"><?php esc_html_e('Choose which methods are available on Login vs Register forms.', 'weewoo-auth-pro'); ?></p>

                <table class="ww-auth-form-table" style="margin-top: 16px;">
                    <tr>
                        <th style="width: 200px;"></th>
                        <th style="text-align: center; padding: 8px;">Login Form</th>
                        <th style="text-align: center; padding: 8px;">Register Form</th>
                    </tr>
                    <tr>
                        <td><strong>WhatsApp OTP</strong></td>
                        <td style="text-align: center;">
                            <input type="checkbox" name="ww_auth_login_whatsapp" value="1" 
                                   <?php checked(get_option('ww_auth_login_whatsapp', true)); ?>>
                        </td>
                        <td style="text-align: center;">
                            <input type="checkbox" name="ww_auth_register_whatsapp" value="1" 
                                   <?php checked(get_option('ww_auth_register_whatsapp', true)); ?>>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Email OTP</strong></td>
                        <td style="text-align: center;">
                            <input type="checkbox" name="ww_auth_login_email" value="1" 
                                   <?php checked(get_option('ww_auth_login_email', true)); ?>>
                        </td>
                        <td style="text-align: center;">
                            <input type="checkbox" name="ww_auth_register_email" value="1" 
                                   <?php checked(get_option('ww_auth_register_email', true)); ?>>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="ww-auth-card">
                <h2><?php esc_html_e('Login Page', 'weewoo-auth-pro'); ?></h2>
                <div class="ww-auth-info-box">
                    <strong><?php esc_html_e('Secure Login URL:', 'weewoo-auth-pro'); ?></strong>
                    <code><?php echo esc_url(home_url('/secure-login/')); ?></code>
                    <a href="<?php echo esc_url(home_url('/secure-login/')); ?>" target="_blank" style="margin-left: 12px;">
                        <?php esc_html_e('Preview', 'weewoo-auth-pro'); ?> →
                    </a>
                </div>
            </div>

            <?php submit_button(__('Save Settings', 'weewoo-auth-pro'), 'ww-auth-btn-primary'); ?>
        </form>
        <?php
    }

    /**
     * Render Meta API Tab
     */
    private function render_meta_tab(): void
    {
        $phone_id = get_option('ww_auth_meta_phone_id', '');
        $access_token = get_option('ww_auth_meta_access_token', '');
        $is_configured = !empty($phone_id) && !empty($access_token);
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('ww_auth_meta'); ?>
            
            <div class="ww-auth-card">
                <h2><?php esc_html_e('Meta Cloud API Configuration', 'weewoo-auth-pro'); ?></h2>
                <p class="ww-auth-card-desc">
                    <?php esc_html_e('Configure your Meta Business API credentials for WhatsApp OTP authentication.', 'weewoo-auth-pro'); ?>
                    <a href="https://developers.facebook.com/docs/whatsapp/cloud-api/" target="_blank">
                        <?php esc_html_e('Learn more', 'weewoo-auth-pro'); ?> →
                    </a>
                </p>

                <?php if ($is_configured): ?>
                <div class="ww-auth-info-box" style="background: #F0FDF4; border-color: #BBF7D0; margin-bottom: 20px;">
                    <span style="color: #166534;">✓ <?php esc_html_e('Meta API is configured', 'weewoo-auth-pro'); ?></span>
                </div>
                <?php else: ?>
                <div class="ww-auth-info-box" style="background: #FEF3C7; border-color: #FCD34D; margin-bottom: 20px;">
                    <span style="color: #92400E;">⚠ <?php esc_html_e('Meta API not configured - WhatsApp OTP will not work', 'weewoo-auth-pro'); ?></span>
                </div>
                <?php endif; ?>

                <table class="form-table ww-auth-form-table">
                    <tr>
                        <th scope="row">
                            <label for="ww_auth_meta_phone_id"><?php esc_html_e('Phone Number ID', 'weewoo-auth-pro'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="ww_auth_meta_phone_id" 
                                   name="ww_auth_meta_phone_id" 
                                   value="<?php echo esc_attr($phone_id); ?>" 
                                   class="regular-text"
                                   placeholder="Enter your Phone Number ID">
                            <p class="description"><?php esc_html_e('Found in Meta Business Suite under WhatsApp > Phone Numbers.', 'weewoo-auth-pro'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ww_auth_meta_access_token"><?php esc_html_e('Access Token', 'weewoo-auth-pro'); ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="ww_auth_meta_access_token" 
                                   name="ww_auth_meta_access_token" 
                                   value="<?php echo esc_attr($access_token); ?>" 
                                   class="regular-text"
                                   placeholder="Enter your Access Token">
                            <p class="description"><?php esc_html_e('Permanent token from System User in Meta Business Suite.', 'weewoo-auth-pro'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ww_auth_meta_template_name"><?php esc_html_e('Template Name', 'weewoo-auth-pro'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="ww_auth_meta_template_name" 
                                   name="ww_auth_meta_template_name" 
                                   value="<?php echo esc_attr(get_option('ww_auth_meta_template_name', 'authentication_otp')); ?>" 
                                   class="regular-text"
                                   placeholder="authentication_otp">
                            <p class="description"><?php esc_html_e('Name of your approved Authentication template.', 'weewoo-auth-pro'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ww_auth_meta_template_lang"><?php esc_html_e('Template Language', 'weewoo-auth-pro'); ?></label>
                        </th>
                        <td>
                            <select id="ww_auth_meta_template_lang" name="ww_auth_meta_template_lang" class="regular-text">
                                <?php
                                $languages = ['en' => 'English', 'en_US' => 'English (US)', 'hi' => 'Hindi', 'es' => 'Spanish', 'pt_BR' => 'Portuguese (BR)', 'ar' => 'Arabic'];
                                $current = get_option('ww_auth_meta_template_lang', 'en');
                                foreach ($languages as $code => $name) {
                                    printf('<option value="%s" %s>%s</option>', esc_attr($code), selected($current, $code, false), esc_html($name));
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button(__('Save Meta Settings', 'weewoo-auth-pro'), 'ww-auth-btn-primary'); ?>
        </form>
        <?php
    }

    /**
     * Render Security Tab
     */
    private function render_security_tab(): void
    {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('ww_auth_security'); ?>
            
            <div class="ww-auth-card">
                <h2><?php esc_html_e('Cloudflare Turnstile', 'weewoo-auth-pro'); ?></h2>
                <p class="ww-auth-card-desc">
                    <?php esc_html_e('Protect your login form with Cloudflare Turnstile CAPTCHA.', 'weewoo-auth-pro'); ?>
                    <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank">
                        <?php esc_html_e('Get your keys', 'weewoo-auth-pro'); ?> →
                    </a>
                </p>

                <div class="ww-auth-toggle-row" style="margin-bottom: 20px;">
                    <div class="ww-auth-toggle-info">
                        <strong><?php esc_html_e('Enable Turnstile', 'weewoo-auth-pro'); ?></strong>
                        <span><?php esc_html_e('Require Turnstile verification on login', 'weewoo-auth-pro'); ?></span>
                    </div>
                    <label class="ww-auth-switch">
                        <input type="checkbox" name="ww_auth_turnstile_enabled" value="1" 
                               <?php checked(get_option('ww_auth_turnstile_enabled', false)); ?>>
                        <span class="ww-auth-slider"></span>
                    </label>
                </div>

                <table class="form-table ww-auth-form-table">
                    <tr>
                        <th scope="row">
                            <label for="ww_auth_turnstile_site_key"><?php esc_html_e('Site Key', 'weewoo-auth-pro'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="ww_auth_turnstile_site_key" 
                                   name="ww_auth_turnstile_site_key" 
                                   value="<?php echo esc_attr(get_option('ww_auth_turnstile_site_key')); ?>" 
                                   class="regular-text"
                                   placeholder="0x4XXXXXXXXXXXXXXXXX">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ww_auth_turnstile_secret_key"><?php esc_html_e('Secret Key', 'weewoo-auth-pro'); ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="ww_auth_turnstile_secret_key" 
                                   name="ww_auth_turnstile_secret_key" 
                                   value="<?php echo esc_attr(get_option('ww_auth_turnstile_secret_key')); ?>" 
                                   class="regular-text"
                                   placeholder="0x4XXXXXXXXXXXXXXXXX">
                        </td>
                    </tr>
                </table>
            </div>

            <div class="ww-auth-card">
                <h2><?php esc_html_e('Rate Limiting', 'weewoo-auth-pro'); ?></h2>
                <p class="ww-auth-card-desc"><?php esc_html_e('Protect against brute force attacks.', 'weewoo-auth-pro'); ?></p>

                <table class="form-table ww-auth-form-table">
                    <tr>
                        <th scope="row">
                            <label for="ww_auth_rate_limit_attempts"><?php esc_html_e('Max Failed Attempts', 'weewoo-auth-pro'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="ww_auth_rate_limit_attempts" 
                                   name="ww_auth_rate_limit_attempts" 
                                   value="<?php echo esc_attr(get_option('ww_auth_rate_limit_attempts', 5)); ?>" 
                                   class="small-text"
                                   min="1" max="20">
                            <p class="description"><?php esc_html_e('Number of failed attempts before blocking.', 'weewoo-auth-pro'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ww_auth_rate_limit_duration"><?php esc_html_e('Block Duration (minutes)', 'weewoo-auth-pro'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="ww_auth_rate_limit_duration" 
                                   name="ww_auth_rate_limit_duration" 
                                   value="<?php echo esc_attr(get_option('ww_auth_rate_limit_duration', 60)); ?>" 
                                   class="small-text"
                                   min="1" max="1440">
                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button(__('Save Security Settings', 'weewoo-auth-pro'), 'ww-auth-btn-primary'); ?>
        </form>
        <?php
    }

    /**
     * Render Branding Tab
     */
    private function render_branding_tab(): void
    {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('ww_auth_branding'); ?>
            
            <div class="ww-auth-card">
                <h2><?php esc_html_e('Visual Branding', 'weewoo-auth-pro'); ?></h2>
                <p class="ww-auth-card-desc"><?php esc_html_e('Customize the appearance of your secure login page.', 'weewoo-auth-pro'); ?></p>

                <table class="form-table ww-auth-form-table">
                    <tr>
                        <th scope="row">
                            <label for="ww_auth_logo_url"><?php esc_html_e('Logo URL', 'weewoo-auth-pro'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="ww_auth_logo_url" 
                                   name="ww_auth_logo_url" 
                                   value="<?php echo esc_attr(get_option('ww_auth_logo_url')); ?>" 
                                   class="large-text"
                                   placeholder="https://yoursite.com/logo.png">
                            <p class="description"><?php esc_html_e('Full URL to your logo (recommended: 200x60px).', 'weewoo-auth-pro'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ww_auth_company_name"><?php esc_html_e('Company Name', 'weewoo-auth-pro'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="ww_auth_company_name" 
                                   name="ww_auth_company_name" 
                                   value="<?php echo esc_attr(get_option('ww_auth_company_name')); ?>" 
                                   class="regular-text"
                                   placeholder="Your Company">
                            <p class="description"><?php esc_html_e('Shown in email footer and page.', 'weewoo-auth-pro'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ww_auth_email_company_name"><?php esc_html_e('Email Header Name (Bold)', 'weewoo-auth-pro'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="ww_auth_email_company_name"
                                   name="ww_auth_email_company_name"
                                   value="<?php echo esc_attr(get_option('ww_auth_email_company_name')); ?>"
                                   class="regular-text"
                                   placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">
                            <p class="description"><?php esc_html_e('Big bold text at the top of the OTP email instead of a logo image. Defaults to your site name.', 'weewoo-auth-pro'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ww_auth_primary_color"><?php esc_html_e('Primary Color', 'weewoo-auth-pro'); ?></label>
                        </th>
                        <td>
                            <input type="color" 
                                   id="ww_auth_primary_color" 
                                   name="ww_auth_primary_color" 
                                   value="<?php echo esc_attr(get_option('ww_auth_primary_color', '#10B981')); ?>">
                            <code><?php echo esc_html(get_option('ww_auth_primary_color', '#10B981')); ?></code>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ww_auth_secondary_color"><?php esc_html_e('Secondary Color', 'weewoo-auth-pro'); ?></label>
                        </th>
                        <td>
                            <input type="color" 
                                   id="ww_auth_secondary_color" 
                                   name="ww_auth_secondary_color" 
                                   value="<?php echo esc_attr(get_option('ww_auth_secondary_color', '#111827')); ?>">
                            <code><?php echo esc_html(get_option('ww_auth_secondary_color', '#111827')); ?></code>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ww_auth_page_title"><?php esc_html_e('Page Title', 'weewoo-auth-pro'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="ww_auth_page_title" 
                                   name="ww_auth_page_title" 
                                   value="<?php echo esc_attr(get_option('ww_auth_page_title', 'Secure Login')); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ww_auth_welcome_text"><?php esc_html_e('Welcome Text', 'weewoo-auth-pro'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="ww_auth_welcome_text" 
                                   name="ww_auth_welcome_text" 
                                   value="<?php echo esc_attr(get_option('ww_auth_welcome_text', 'Welcome back')); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button(__('Save Branding', 'weewoo-auth-pro'), 'ww-auth-btn-primary'); ?>
        </form>
        <?php
    }
}
