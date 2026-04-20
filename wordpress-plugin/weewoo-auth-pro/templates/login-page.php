<?php
/**
 * Secure Login Page Template
 * 
 * Premium Fintech UI Design
 *
 * @package WeeWoo_Auth_Pro
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$branding = WW_Frontend::get_branding();
$methods = WW_Frontend::get_auth_methods();
$turnstile = WW_Turnstile::instance();
$qr_session = isset($_GET['qr_session']) ? sanitize_text_field($_GET['qr_session']) : '';
$is_qr_auth = isset($_GET['action']) && $_GET['action'] === 'ww_qr_auth' && is_user_logged_in();
$redirect_to = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($branding['page_title']); ?> - <?php bloginfo('name'); ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <?php wp_head(); ?>
    
    <style>
        :root {
            --ww-primary: <?php echo esc_attr($branding['primary_color'] ?: '#10B981'); ?>;
            --ww-secondary: <?php echo esc_attr($branding['secondary_color'] ?: '#111827'); ?>;
            --ww-bg: #0a0a0a;
            --ww-card: #1a1a1a;
            --ww-card-hover: #222222;
            --ww-border: #2a2a2a;
            --ww-text: #ffffff;
            --ww-text-muted: #9ca3af;
            --ww-input-bg: #0f0f0f;
            --ww-success: #10B981;
            --ww-error: #ef4444;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--ww-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--ww-text);
            line-height: 1.5;
        }
        
        /* Main Container */
        .ww-container {
            width: 100%;
            max-width: 400px;
        }
        
        /* Card */
        .ww-card {
            background: var(--ww-card);
            border-radius: 24px;
            padding: 32px;
            border: 1px solid var(--ww-border);
            animation: slideUp 0.4s ease-out;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }
        
        /* Header */
        .ww-header {
            text-align: center;
            margin-bottom: 28px;
        }
        
        .ww-logo {
            margin-bottom: 20px;
        }
        
        .ww-logo img {
            max-height: 40px;
            width: auto;
        }
        
        .ww-logo-icon {
            width: 48px;
            height: 48px;
            background: var(--ww-primary);
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .ww-logo-icon svg {
            width: 24px;
            height: 24px;
            color: white;
        }
        
        .ww-title {
            font-size: 22px;
            font-weight: 600;
            color: var(--ww-text);
            margin-bottom: 6px;
        }
        
        .ww-subtitle {
            font-size: 14px;
            color: var(--ww-text-muted);
        }
        
        /* Tabs */
        .ww-tabs {
            display: flex;
            background: var(--ww-input-bg);
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 24px;
        }
        
        .ww-tab {
            flex: 1;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 500;
            color: var(--ww-text-muted);
            background: transparent;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .ww-tab:hover {
            color: var(--ww-text);
        }
        
        .ww-tab.active {
            background: var(--ww-card-hover);
            color: var(--ww-text);
        }
        
        /* Views */
        .ww-view {
            display: none;
            animation: fadeIn 0.3s ease-out;
        }
        
        .ww-view.active {
            display: block;
        }
        
        /* Form Groups */
        .ww-form-group {
            margin-bottom: 16px;
        }
        
        .ww-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--ww-text);
            margin-bottom: 8px;
        }
        
        .ww-input-wrapper {
            position: relative;
        }
        
        .ww-input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--ww-text-muted);
            pointer-events: none;
        }
        
        .ww-input-icon svg {
            width: 18px;
            height: 18px;
        }
        
        .ww-input {
            width: 100%;
            padding: 14px 14px 14px 44px;
            font-size: 15px;
            font-family: inherit;
            color: var(--ww-text);
            background: var(--ww-input-bg);
            border: 1px solid var(--ww-border);
            border-radius: 12px;
            outline: none;
            transition: all 0.2s ease;
        }
        
        .ww-input::placeholder {
            color: var(--ww-text-muted);
        }
        
        .ww-input:focus {
            border-color: var(--ww-primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }
        
        .ww-input.error {
            border-color: var(--ww-error);
            animation: shake 0.3s ease;
        }
        
        /* Password Toggle */
        .ww-password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--ww-text-muted);
            cursor: pointer;
            padding: 4px;
        }
        
        .ww-password-toggle:hover {
            color: var(--ww-text);
        }
        
        .ww-password-toggle svg {
            width: 18px;
            height: 18px;
        }
        
        /* Checkbox & Remember */
        .ww-options-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 13px;
        }
        
        .ww-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: var(--ww-text-muted);
        }
        
        .ww-checkbox input {
            width: 16px;
            height: 16px;
            accent-color: var(--ww-primary);
            cursor: pointer;
        }
        
        .ww-link {
            color: var(--ww-primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .ww-link:hover {
            text-decoration: underline;
        }
        
        /* Buttons */
        .ww-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px 20px;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .ww-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .ww-btn-primary {
            background: var(--ww-primary);
            color: white;
        }
        
        .ww-btn-primary:hover:not(:disabled) {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }
        
        .ww-btn-primary:active:not(:disabled) {
            transform: translateY(0);
        }
        
        .ww-btn-outline {
            background: transparent;
            color: var(--ww-text);
            border: 1px solid var(--ww-border);
        }
        
        .ww-btn-outline:hover:not(:disabled) {
            background: var(--ww-card-hover);
            border-color: var(--ww-text-muted);
        }
        
        .ww-btn-icon {
            width: 20px;
            height: 20px;
        }
        
        /* Spinner */
        .ww-spinner {
            width: 18px;
            height: 18px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Divider */
        .ww-divider {
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 24px 0;
            color: var(--ww-text-muted);
            font-size: 13px;
        }
        
        .ww-divider::before,
        .ww-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--ww-border);
        }
        
        /* Auth Methods */
        .ww-methods {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .ww-method-btn {
            display: flex;
            align-items: center;
            gap: 14px;
            width: 100%;
            padding: 14px 16px;
            background: var(--ww-input-bg);
            border: 1px solid var(--ww-border);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: left;
        }
        
        .ww-method-btn:hover {
            background: var(--ww-card-hover);
            border-color: var(--ww-text-muted);
        }
        
        .ww-method-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .ww-method-icon.whatsapp { background: #25D366; }
        .ww-method-icon.email { background: #6366F1; }
        .ww-method-icon.passkey { background: #3B82F6; }
        .ww-method-icon.qr { background: var(--ww-primary); }
        
        .ww-method-icon svg {
            width: 20px;
            height: 20px;
            color: white;
        }
        
        .ww-method-info strong {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--ww-text);
            margin-bottom: 2px;
        }
        
        .ww-method-info span {
            font-size: 12px;
            color: var(--ww-text-muted);
        }
        
        /* OTP Input */
        .ww-otp-container {
            text-align: center;
            margin: 24px 0;
        }
        
        .ww-otp-label {
            font-size: 14px;
            color: var(--ww-text-muted);
            margin-bottom: 8px;
        }
        
        .ww-otp-destination {
            font-weight: 600;
            color: var(--ww-text);
        }
        
        .ww-otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 24px 0;
        }
        
        .ww-otp-input {
            width: 52px;
            height: 60px;
            text-align: center;
            font-size: 22px;
            font-weight: 600;
            font-family: inherit;
            color: var(--ww-text);
            background: var(--ww-input-bg);
            border: 1px solid var(--ww-border);
            border-radius: 12px;
            outline: none;
            transition: all 0.2s ease;
        }
        
        .ww-otp-input:focus {
            border-color: var(--ww-primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }
        
        .ww-otp-input.filled {
            border-color: var(--ww-primary);
            background: rgba(16, 185, 129, 0.1);
        }
        
        /* Resend */
        .ww-resend {
            text-align: center;
            font-size: 13px;
            color: var(--ww-text-muted);
            margin-top: 16px;
        }
        
        .ww-resend-btn {
            background: none;
            border: none;
            color: var(--ww-primary);
            font-weight: 500;
            cursor: pointer;
            font-size: 13px;
            font-family: inherit;
        }
        
        .ww-resend-btn:hover {
            text-decoration: underline;
        }
        
        /* Back Button */
        .ww-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: none;
            border: none;
            color: var(--ww-text-muted);
            font-size: 14px;
            font-family: inherit;
            cursor: pointer;
            margin-bottom: 20px;
            padding: 0;
        }
        
        .ww-back:hover {
            color: var(--ww-text);
        }
        
        .ww-back svg {
            width: 16px;
            height: 16px;
        }
        
        /* Message */
        .ww-message {
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 16px;
            display: none;
        }
        
        .ww-message.show {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .ww-message.error {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .ww-message.success {
            background: rgba(16, 185, 129, 0.15);
            color: #6ee7b7;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        /* QR Code */
        .ww-qr-container {
            text-align: center;
            padding: 20px 0;
        }
        
        .ww-qr-wrapper {
            background: white;
            padding: 16px;
            border-radius: 16px;
            display: inline-block;
            margin-bottom: 16px;
        }
        
        .ww-qr-image {
            width: 180px;
            height: 180px;
            display: block;
        }
        
        .ww-qr-hint {
            font-size: 14px;
            color: var(--ww-text-muted);
        }
        
        .ww-qr-status {
            margin-top: 12px;
            padding: 10px 14px;
            background: var(--ww-input-bg);
            border-radius: 10px;
            font-size: 13px;
            color: var(--ww-text-muted);
        }
        
        /* Biometric */
        .ww-biometric-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px;
            background: var(--ww-input-bg);
            border: 1px solid var(--ww-border);
            border-radius: 12px;
            color: var(--ww-text);
            font-size: 14px;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            margin-top: 12px;
            transition: all 0.2s ease;
        }
        
        .ww-biometric-btn:hover {
            background: var(--ww-card-hover);
            border-color: var(--ww-text-muted);
        }
        
        .ww-biometric-btn svg {
            width: 20px;
            height: 20px;
        }
        
        /* Footer */
        .ww-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--ww-border);
        }
        
        .ww-footer a {
            color: var(--ww-text-muted);
            font-size: 13px;
            text-decoration: none;
        }
        
        .ww-footer a:hover {
            color: var(--ww-primary);
        }
        
        /* Turnstile */
        .ww-turnstile {
            display: flex;
            justify-content: center;
            margin-bottom: 16px;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .ww-card {
                padding: 24px 20px;
                border-radius: 20px;
            }
            
            .ww-otp-input {
                width: 46px;
                height: 54px;
                font-size: 20px;
            }
            
            .ww-otp-inputs {
                gap: 8px;
            }
        }
    </style>
    
    <?php if ($turnstile->is_enabled()): ?>
    <script src="<?php echo esc_url($turnstile->get_script_url()); ?>" async defer></script>
    <?php endif; ?>
</head>
<body>
    <div class="ww-container">
        <div class="ww-card">
            <!-- Header -->
            <div class="ww-header">
                <div class="ww-logo">
                    <?php if (!empty($branding['logo_url'])): ?>
                        <img src="<?php echo esc_url($branding['logo_url']); ?>" alt="<?php bloginfo('name'); ?>">
                    <?php else: ?>
                        <div class="ww-logo-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                </div>
                <h1 class="ww-title" id="main-title"><?php echo esc_html($branding['welcome_text'] ?: 'Go ahead and set up your account'); ?></h1>
                <p class="ww-subtitle" id="main-subtitle">Sign in to continue to your account</p>
            </div>
            
            <!-- Message -->
            <div id="ww-message" class="ww-message"></div>
            
            <!-- Main View: Login/Register Tabs -->
            <div class="ww-view active" id="view-main">
                <!-- Tabs -->
                <div class="ww-tabs">
                    <button type="button" class="ww-tab active" data-tab="login">Login</button>
                    <button type="button" class="ww-tab" data-tab="register">Register</button>
                </div>
                
                <!-- Login Tab Content -->
                <div class="ww-tab-content active" id="tab-login">
                    <form id="form-login">
                        <div class="ww-form-group">
                            <label class="ww-label">Email Address</label>
                            <div class="ww-input-wrapper">
                                <span class="ww-input-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                        <polyline points="22,6 12,13 2,6"/>
                                    </svg>
                                </span>
                                <input type="email" id="login-email" class="ww-input" placeholder="you@example.com" required autocomplete="email">
                            </div>
                        </div>
                        
                        <div class="ww-form-group">
                            <label class="ww-label">Password</label>
                            <div class="ww-input-wrapper">
                                <span class="ww-input-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                    </svg>
                                </span>
                                <input type="password" id="login-password" class="ww-input" style="padding-right: 44px;" placeholder="Enter your password" required autocomplete="current-password">
                                <button type="button" class="ww-password-toggle" onclick="togglePassword('login-password', this)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="eye-icon">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <div class="ww-options-row">
                            <label class="ww-checkbox">
                                <input type="checkbox" id="remember-me">
                                <span>Remember me</span>
                            </label>
                            <a href="#" class="ww-link" onclick="showForgotPassword(); return false;">Forgot Password?</a>
                        </div>
                        
                        <?php if ($turnstile->is_enabled()): ?>
                        <div class="ww-turnstile">
                            <?php echo $turnstile->render_widget(); ?>
                        </div>
                        <?php endif; ?>
                        
                        <button type="submit" class="ww-btn ww-btn-primary" id="btn-login">
                            Login
                        </button>
                    </form>
                    
                    <div class="ww-divider">Or continue with</div>
                    
                    <div class="ww-methods">
                        <?php if ($methods['whatsapp']): ?>
                        <button type="button" class="ww-method-btn" data-method="whatsapp">
                            <div class="ww-method-icon whatsapp">
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                </svg>
                            </div>
                            <div class="ww-method-info">
                                <strong>WhatsApp OTP</strong>
                                <span>Get code on WhatsApp</span>
                            </div>
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($methods['passkeys']): ?>
                        <button type="button" class="ww-method-btn" data-method="passkey">
                            <div class="ww-method-icon passkey">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/>
                                </svg>
                            </div>
                            <div class="ww-method-info">
                                <strong>Biometric Verification</strong>
                                <span>Use Face ID or Touch ID</span>
                            </div>
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($methods['qr']): ?>
                        <button type="button" class="ww-method-btn" data-method="qr">
                            <div class="ww-method-icon qr">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="7" height="7"/>
                                    <rect x="14" y="3" width="7" height="7"/>
                                    <rect x="14" y="14" width="7" height="7"/>
                                    <rect x="3" y="14" width="7" height="7"/>
                                </svg>
                            </div>
                            <div class="ww-method-info">
                                <strong>QR Code</strong>
                                <span>Scan with your phone</span>
                            </div>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Register Tab Content -->
                <div class="ww-tab-content" id="tab-register">
                    <form id="form-register">
                        <div class="ww-form-group">
                            <label class="ww-label">Full Name</label>
                            <div class="ww-input-wrapper">
                                <span class="ww-input-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                        <circle cx="12" cy="7" r="4"/>
                                    </svg>
                                </span>
                                <input type="text" id="register-name" class="ww-input" placeholder="John Doe" required>
                            </div>
                        </div>
                        
                        <div class="ww-form-group">
                            <label class="ww-label">Email Address</label>
                            <div class="ww-input-wrapper">
                                <span class="ww-input-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                        <polyline points="22,6 12,13 2,6"/>
                                    </svg>
                                </span>
                                <input type="email" id="register-email" class="ww-input" placeholder="you@example.com" required autocomplete="email">
                            </div>
                        </div>
                        
                        <div class="ww-form-group">
                            <label class="ww-label">Phone Number</label>
                            <div class="ww-input-wrapper">
                                <span class="ww-input-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                    </svg>
                                </span>
                                <input type="tel" id="register-phone" class="ww-input" placeholder="+1 234 567 8900" autocomplete="tel">
                            </div>
                        </div>
                        
                        <div class="ww-form-group">
                            <label class="ww-label">Password</label>
                            <div class="ww-input-wrapper">
                                <span class="ww-input-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                    </svg>
                                </span>
                                <input type="password" id="register-password" class="ww-input" style="padding-right: 44px;" placeholder="Create a password" required autocomplete="new-password">
                                <button type="button" class="ww-password-toggle" onclick="togglePassword('register-password', this)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="eye-icon">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <?php if ($turnstile->is_enabled()): ?>
                        <div class="ww-turnstile">
                            <?php echo $turnstile->render_widget(); ?>
                        </div>
                        <?php endif; ?>
                        
                        <button type="submit" class="ww-btn ww-btn-primary" id="btn-register">
                            Create Account
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- OTP Verification View -->
            <div class="ww-view" id="view-otp">
                <button type="button" class="ww-back" onclick="showView('main')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back
                </button>
                
                <div class="ww-header" style="margin-bottom: 16px;">
                    <div class="ww-logo">
                        <div class="ww-logo-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                    </div>
                    <h1 class="ww-title">Verify your account</h1>
                </div>
                
                <div class="ww-otp-container">
                    <p class="ww-otp-label">We sent a verification code to</p>
                    <p class="ww-otp-destination" id="otp-destination"></p>
                </div>
                
                <form id="form-otp">
                    <div class="ww-otp-inputs">
                        <input type="text" class="ww-otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                        <input type="text" class="ww-otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                        <input type="text" class="ww-otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                        <input type="text" class="ww-otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                    </div>
                    
                    <button type="submit" class="ww-btn ww-btn-primary" id="btn-verify" disabled>
                        Verify
                    </button>
                </form>
                
                <?php if ($methods['passkeys']): ?>
                <button type="button" class="ww-biometric-btn" id="btn-biometric">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/>
                    </svg>
                    Biometric Verification
                </button>
                <?php endif; ?>
                
                <div class="ww-resend">
                    <span id="resend-timer">Resend code in <strong>60s</strong></span>
                    <button type="button" class="ww-resend-btn" id="btn-resend" style="display: none;">
                        Resend Code
                    </button>
                </div>
            </div>
            
            <!-- QR View -->
            <div class="ww-view" id="view-qr">
                <button type="button" class="ww-back" onclick="showView('main'); stopQRPolling();">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back
                </button>
                
                <div class="ww-qr-container" id="qr-container">
                    <div class="ww-spinner" style="margin: 40px auto;"></div>
                </div>
                
                <div class="ww-qr-status" id="qr-status">
                    Waiting for authorization...
                </div>
                
                <button type="button" class="ww-btn ww-btn-outline" style="margin-top: 16px;" id="btn-refresh-qr">
                    Refresh QR Code
                </button>
            </div>
            
            <!-- WhatsApp View -->
            <div class="ww-view" id="view-whatsapp">
                <button type="button" class="ww-back" onclick="showView('main')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back
                </button>
                
                <form id="form-whatsapp">
                    <div class="ww-form-group">
                        <label class="ww-label">WhatsApp Number</label>
                        <div class="ww-input-wrapper">
                            <span class="ww-input-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                </svg>
                            </span>
                            <input type="tel" id="whatsapp-phone" class="ww-input" placeholder="+1 234 567 8900" required autocomplete="tel">
                        </div>
                    </div>
                    
                    <?php if ($turnstile->is_enabled()): ?>
                    <div class="ww-turnstile">
                        <?php echo $turnstile->render_widget(); ?>
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="ww-btn ww-btn-primary" id="btn-send-whatsapp">
                        Send Code
                    </button>
                </form>
            </div>
            
            <!-- Footer -->
            <div class="ww-footer">
                <a href="<?php echo esc_url(home_url('/')); ?>">Return to <?php bloginfo('name'); ?></a>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        'use strict';
        
        const API_URL = '<?php echo esc_url_raw(rest_url('ww-auth/v1/')); ?>';
        const NONCE = '<?php echo wp_create_nonce('wp_rest'); ?>';
        const REDIRECT_TO = '<?php echo esc_js($redirect_to); ?>';
        
        let currentMethod = 'email';
        let otpDestination = '';
        let resendTimer = null;
        let qrPollInterval = null;
        let qrSessionId = null;
        
        // Tab switching
        document.querySelectorAll('.ww-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.ww-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.ww-tab-content').forEach(c => c.classList.remove('active'));
                
                this.classList.add('active');
                const tabId = this.dataset.tab;
                document.getElementById('tab-' + tabId).classList.add('active');
                
                // Update title
                const title = document.getElementById('main-title');
                const subtitle = document.getElementById('main-subtitle');
                if (tabId === 'register') {
                    title.textContent = 'Create your account';
                    subtitle.textContent = 'Get started with a free account';
                } else {
                    title.textContent = '<?php echo esc_js($branding['welcome_text'] ?: 'Go ahead and set up your account'); ?>';
                    subtitle.textContent = 'Sign in to continue to your account';
                }
            });
        });
        
        // View switching
        window.showView = function(viewId) {
            document.querySelectorAll('.ww-view').forEach(v => v.classList.remove('active'));
            document.getElementById('view-' + viewId).classList.add('active');
            hideMessage();
        };
        
        // Method selection
        document.querySelectorAll('.ww-method-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const method = this.dataset.method;
                currentMethod = method;
                
                if (method === 'qr') {
                    showView('qr');
                    generateQRCode();
                } else if (method === 'whatsapp') {
                    showView('whatsapp');
                } else if (method === 'passkey') {
                    startPasskeyAuth();
                }
            });
        });
        
        // Password toggle
        window.togglePassword = function(inputId, btn) {
            const input = document.getElementById(inputId);
            const type = input.type === 'password' ? 'text' : 'password';
            input.type = type;
        };
        
        // Message functions
        function showMessage(text, type = 'error') {
            const msg = document.getElementById('ww-message');
            msg.textContent = text;
            msg.className = 'ww-message show ' + type;
        }
        
        function hideMessage() {
            document.getElementById('ww-message').className = 'ww-message';
        }
        
        // Loading state
        function setLoading(btn, loading) {
            if (loading) {
                btn.disabled = true;
                btn.dataset.text = btn.innerHTML;
                btn.innerHTML = '<span class="ww-spinner"></span>';
            } else {
                btn.disabled = false;
                btn.innerHTML = btn.dataset.text || btn.innerHTML;
            }
        }
        
        // API call helper
        async function apiCall(endpoint, data = {}, method = 'POST') {
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': NONCE
                }
            };
            if (method === 'POST') {
                options.body = JSON.stringify(data);
            }
            const response = await fetch(API_URL + endpoint, options);
            return response.json();
        }
        
        function getTurnstileToken() {
            if (typeof turnstile !== 'undefined') {
                return turnstile.getResponse() || '';
            }
            return '';
        }
        
        // Login form
        document.getElementById('form-login')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-login');
            const email = document.getElementById('login-email').value;
            
            setLoading(btn, true);
            hideMessage();
            
            try {
                // Send email OTP
                const result = await apiCall('email/send', {
                    email: email,
                    turnstile_token: getTurnstileToken()
                });
                
                if (result.success) {
                    otpDestination = email;
                    currentMethod = 'email';
                    document.getElementById('otp-destination').textContent = email;
                    showView('otp');
                    startResendTimer();
                    focusFirstOTP();
                } else {
                    showMessage(result.error || 'Failed to send code');
                }
            } catch (err) {
                showMessage('Network error. Please try again.');
            }
            
            setLoading(btn, false);
        });
        
        // Register form
        document.getElementById('form-register')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-register');
            const email = document.getElementById('register-email').value;
            
            setLoading(btn, true);
            hideMessage();
            
            try {
                const result = await apiCall('email/send', {
                    email: email,
                    turnstile_token: getTurnstileToken()
                });
                
                if (result.success) {
                    otpDestination = email;
                    currentMethod = 'email';
                    document.getElementById('otp-destination').textContent = email;
                    showView('otp');
                    startResendTimer();
                    focusFirstOTP();
                } else {
                    showMessage(result.error || 'Failed to send code');
                }
            } catch (err) {
                showMessage('Network error. Please try again.');
            }
            
            setLoading(btn, false);
        });
        
        // WhatsApp form
        document.getElementById('form-whatsapp')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-send-whatsapp');
            const phone = document.getElementById('whatsapp-phone').value;
            
            setLoading(btn, true);
            hideMessage();
            
            try {
                const result = await apiCall('whatsapp/send', {
                    phone: phone,
                    turnstile_token: getTurnstileToken()
                });
                
                if (result.success) {
                    otpDestination = phone;
                    currentMethod = 'whatsapp';
                    document.getElementById('otp-destination').textContent = phone;
                    showView('otp');
                    startResendTimer();
                    focusFirstOTP();
                } else {
                    showMessage(result.error || 'Failed to send code');
                }
            } catch (err) {
                showMessage('Network error. Please try again.');
            }
            
            setLoading(btn, false);
        });
        
        // OTP inputs
        const otpInputs = document.querySelectorAll('.ww-otp-input');
        
        function focusFirstOTP() {
            otpInputs[0]?.focus();
        }
        
        function getOTP() {
            return Array.from(otpInputs).map(i => i.value).join('');
        }
        
        function clearOTP() {
            otpInputs.forEach(i => {
                i.value = '';
                i.classList.remove('filled');
            });
            document.getElementById('btn-verify').disabled = true;
        }
        
        function checkOTPComplete() {
            const otp = getOTP();
            document.getElementById('btn-verify').disabled = otp.length !== 4;
        }
        
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                
                if (this.value) {
                    this.classList.add('filled');
                    if (index < otpInputs.length - 1) {
                        otpInputs[index + 1].focus();
                    }
                } else {
                    this.classList.remove('filled');
                }
                checkOTPComplete();
            });
            
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && !this.value && index > 0) {
                    otpInputs[index - 1].focus();
                }
            });
            
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text');
                const digits = paste.replace(/[^0-9]/g, '').slice(0, 4);
                digits.split('').forEach((d, i) => {
                    if (otpInputs[i]) {
                        otpInputs[i].value = d;
                        otpInputs[i].classList.add('filled');
                    }
                });
                checkOTPComplete();
            });
        });
        
        // OTP verification
        document.getElementById('form-otp')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-verify');
            const otp = getOTP();
            
            setLoading(btn, true);
            hideMessage();
            
            const endpoint = currentMethod === 'whatsapp' ? 'whatsapp/verify' : 'email/verify';
            const data = currentMethod === 'whatsapp' 
                ? { phone: otpDestination, otp: otp }
                : { email: otpDestination, otp: otp };
            
            try {
                const result = await apiCall(endpoint, data);
                
                if (result.success) {
                    showMessage('Login successful! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = result.redirect || REDIRECT_TO || '/';
                    }, 500);
                } else {
                    showMessage(result.error || 'Invalid code');
                    clearOTP();
                    focusFirstOTP();
                }
            } catch (err) {
                showMessage('Network error. Please try again.');
            }
            
            setLoading(btn, false);
        });
        
        // Resend timer
        function startResendTimer() {
            let seconds = 60;
            const timerEl = document.getElementById('resend-timer');
            const resendBtn = document.getElementById('btn-resend');
            
            timerEl.style.display = 'inline';
            resendBtn.style.display = 'none';
            
            if (resendTimer) clearInterval(resendTimer);
            
            resendTimer = setInterval(() => {
                seconds--;
                timerEl.innerHTML = `Resend code in <strong>${seconds}s</strong>`;
                
                if (seconds <= 0) {
                    clearInterval(resendTimer);
                    timerEl.style.display = 'none';
                    resendBtn.style.display = 'inline';
                }
            }, 1000);
        }
        
        document.getElementById('btn-resend')?.addEventListener('click', async function() {
            this.disabled = true;
            
            try {
                const endpoint = currentMethod === 'whatsapp' ? 'whatsapp/send' : 'email/send';
                const data = currentMethod === 'whatsapp' 
                    ? { phone: otpDestination } 
                    : { email: otpDestination };
                
                const result = await apiCall(endpoint, data);
                
                if (result.success) {
                    showMessage('Code resent!', 'success');
                    startResendTimer();
                    clearOTP();
                    focusFirstOTP();
                } else {
                    showMessage(result.error || 'Failed to resend');
                }
            } catch (err) {
                showMessage('Network error');
            }
            
            this.disabled = false;
        });
        
        // QR Code
        async function generateQRCode() {
            const container = document.getElementById('qr-container');
            container.innerHTML = '<div class="ww-spinner" style="margin: 40px auto;"></div>';
            document.getElementById('qr-status').textContent = 'Generating QR code...';
            
            try {
                const result = await apiCall('qr/generate', {});
                
                if (result.success) {
                    qrSessionId = result.session_id;
                    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(result.qr_data)}`;
                    
                    container.innerHTML = `
                        <div class="ww-qr-wrapper">
                            <img src="${qrUrl}" alt="QR Code" class="ww-qr-image">
                        </div>
                        <p class="ww-qr-hint">Scan with your phone to log in</p>
                    `;
                    
                    document.getElementById('qr-status').textContent = 'Waiting for authorization...';
                    startQRPolling();
                } else {
                    container.innerHTML = '<p style="color: var(--ww-error);">Failed to generate QR code</p>';
                }
            } catch (err) {
                container.innerHTML = '<p style="color: var(--ww-error);">Network error</p>';
            }
        }
        
        function startQRPolling() {
            if (qrPollInterval) clearInterval(qrPollInterval);
            
            qrPollInterval = setInterval(async () => {
                try {
                    const response = await fetch(API_URL + `qr/poll?session_id=${qrSessionId}`, {
                        headers: { 'X-WP-Nonce': NONCE }
                    });
                    const result = await response.json();
                    
                    if (result.status === 'authorized') {
                        stopQRPolling();
                        document.getElementById('qr-status').textContent = 'Authorized! Redirecting...';
                        showMessage('Login successful!', 'success');
                        setTimeout(() => {
                            window.location.href = result.redirect || REDIRECT_TO || '/';
                        }, 500);
                    } else if (result.status === 'expired') {
                        stopQRPolling();
                        document.getElementById('qr-status').textContent = 'QR code expired. Please refresh.';
                    }
                } catch (err) {
                    // Silent fail
                }
            }, 3000);
        }
        
        window.stopQRPolling = function() {
            if (qrPollInterval) {
                clearInterval(qrPollInterval);
                qrPollInterval = null;
            }
        };
        
        document.getElementById('btn-refresh-qr')?.addEventListener('click', function() {
            stopQRPolling();
            generateQRCode();
        });
        
        // Passkey authentication
        async function startPasskeyAuth() {
            hideMessage();
            
            try {
                const optionsRes = await apiCall('passkeys/login/options', {});
                
                if (!optionsRes.success) {
                    showMessage(optionsRes.error || 'Failed to start authentication');
                    return;
                }
                
                const options = optionsRes.options;
                
                const publicKeyCredentialRequestOptions = {
                    challenge: Uint8Array.from(atob(options.challenge.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0)),
                    timeout: options.timeout,
                    rpId: options.rpId,
                    userVerification: options.userVerification
                };
                
                const credential = await navigator.credentials.get({
                    publicKey: publicKeyCredentialRequestOptions
                });
                
                const credentialResponse = {
                    id: credential.id,
                    rawId: btoa(String.fromCharCode(...new Uint8Array(credential.rawId))),
                    response: {
                        clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(credential.response.clientDataJSON))),
                        authenticatorData: btoa(String.fromCharCode(...new Uint8Array(credential.response.authenticatorData))),
                        signature: btoa(String.fromCharCode(...new Uint8Array(credential.response.signature)))
                    },
                    type: credential.type,
                    session_id: options.session_id
                };
                
                const verifyRes = await apiCall('passkeys/login/verify', credentialResponse);
                
                if (verifyRes.success) {
                    showMessage('Login successful! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = verifyRes.redirect || REDIRECT_TO || '/';
                    }, 500);
                } else {
                    showMessage(verifyRes.error || 'Authentication failed');
                }
            } catch (err) {
                if (err.name === 'NotAllowedError') {
                    showMessage('Authentication was cancelled');
                } else {
                    showMessage('Passkey authentication failed');
                }
            }
        }
        
        // Biometric button in OTP view
        document.getElementById('btn-biometric')?.addEventListener('click', startPasskeyAuth);
        
        // Forgot password
        window.showForgotPassword = function() {
            // For now, show message - can be extended
            showMessage('Password reset link will be sent to your email.');
        };
        
    })();
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>
