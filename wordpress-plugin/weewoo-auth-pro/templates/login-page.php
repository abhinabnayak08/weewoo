<?php
/**
 * Secure Login Page Template
 * 
 * Premium Fintech UI Design - Fixed Version
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
    
    <style>
        /* Reset & Base */
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            width: 100%;
        }
        
        body.ww-auth-page {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0a0a0a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #ffffff;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Container */
        .ww-auth-container {
            width: 100%;
            max-width: 420px;
        }
        
        /* Card */
        .ww-auth-card {
            background: #1a1a1a;
            border-radius: 24px;
            padding: 36px 32px;
            border: 1px solid #2a2a2a;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        
        /* Logo */
        .ww-auth-logo {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .ww-auth-logo img {
            max-height: 44px;
            width: auto;
        }
        
        .ww-auth-logo-icon {
            width: 56px;
            height: 56px;
            background: <?php echo esc_attr($branding['primary_color'] ?: '#10B981'); ?>;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .ww-auth-logo-icon svg {
            width: 28px;
            height: 28px;
            fill: none;
            stroke: white;
            stroke-width: 2;
        }
        
        /* Header */
        .ww-auth-header {
            text-align: center;
            margin-bottom: 28px;
        }
        
        .ww-auth-title {
            font-size: 24px;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 8px;
        }
        
        .ww-auth-subtitle {
            font-size: 14px;
            color: #9ca3af;
        }
        
        /* Tabs */
        .ww-auth-tabs {
            display: flex;
            background: #0f0f0f;
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 24px;
        }
        
        .ww-auth-tab {
            flex: 1;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 500;
            color: #9ca3af;
            background: transparent;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
        }
        
        .ww-auth-tab:hover {
            color: #ffffff;
        }
        
        .ww-auth-tab.active {
            background: #2a2a2a;
            color: #ffffff;
        }
        
        /* Tab Panels - CRITICAL: Hide inactive */
        .ww-auth-panel {
            display: none;
        }
        
        .ww-auth-panel.active {
            display: block;
        }
        
        /* Views */
        .ww-auth-view {
            display: none;
        }
        
        .ww-auth-view.active {
            display: block;
        }
        
        /* Form Groups */
        .ww-auth-field {
            margin-bottom: 18px;
        }
        
        .ww-auth-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #e5e5e5;
            margin-bottom: 8px;
        }
        
        .ww-auth-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .ww-auth-input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
        }
        
        .ww-auth-input-icon svg {
            width: 18px;
            height: 18px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        
        .ww-auth-input {
            width: 100%;
            padding: 14px 14px 14px 46px;
            font-size: 15px;
            font-family: inherit;
            color: #ffffff;
            background: #0f0f0f;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            outline: none;
            transition: all 0.2s ease;
        }
        
        .ww-auth-input::placeholder {
            color: #6b7280;
        }
        
        .ww-auth-input:focus {
            border-color: <?php echo esc_attr($branding['primary_color'] ?: '#10B981'); ?>;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }
        
        .ww-auth-input.has-toggle {
            padding-right: 46px;
        }
        
        /* Password Toggle */
        .ww-auth-toggle-pwd {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .ww-auth-toggle-pwd:hover {
            color: #9ca3af;
        }
        
        .ww-auth-toggle-pwd svg {
            width: 18px;
            height: 18px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
        }
        
        /* Options Row */
        .ww-auth-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 13px;
        }
        
        .ww-auth-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: #9ca3af;
        }
        
        .ww-auth-checkbox input {
            width: 16px;
            height: 16px;
            accent-color: <?php echo esc_attr($branding['primary_color'] ?: '#10B981'); ?>;
            cursor: pointer;
        }
        
        .ww-auth-link {
            color: <?php echo esc_attr($branding['primary_color'] ?: '#10B981'); ?>;
            text-decoration: none;
            font-weight: 500;
        }
        
        .ww-auth-link:hover {
            text-decoration: underline;
        }
        
        /* Buttons */
        .ww-auth-btn {
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
        
        .ww-auth-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .ww-auth-btn-primary {
            background: <?php echo esc_attr($branding['primary_color'] ?: '#10B981'); ?>;
            color: white;
        }
        
        .ww-auth-btn-primary:hover:not(:disabled) {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }
        
        .ww-auth-btn-outline {
            background: transparent;
            color: #ffffff;
            border: 1px solid #2a2a2a;
        }
        
        .ww-auth-btn-outline:hover:not(:disabled) {
            background: #222222;
            border-color: #3a3a3a;
        }
        
        /* Spinner */
        .ww-auth-spinner {
            width: 18px;
            height: 18px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: ww-spin 0.8s linear infinite;
        }
        
        @keyframes ww-spin {
            to { transform: rotate(360deg); }
        }
        
        /* Divider */
        .ww-auth-divider {
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 24px 0;
            color: #6b7280;
            font-size: 13px;
        }
        
        .ww-auth-divider::before,
        .ww-auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #2a2a2a;
        }
        
        /* Method Buttons */
        .ww-auth-methods {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .ww-auth-method {
            display: flex;
            align-items: center;
            gap: 14px;
            width: 100%;
            padding: 14px 16px;
            background: #0f0f0f;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: left;
            font-family: inherit;
            color: inherit;
        }
        
        .ww-auth-method:hover {
            background: #1a1a1a;
            border-color: #3a3a3a;
        }
        
        .ww-auth-method-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .ww-auth-method-icon.whatsapp { background: #25D366; }
        .ww-auth-method-icon.email { background: #6366F1; }
        .ww-auth-method-icon.passkey { background: #3B82F6; }
        .ww-auth-method-icon.qr { background: <?php echo esc_attr($branding['primary_color'] ?: '#10B981'); ?>; }
        
        .ww-auth-method-icon svg {
            width: 20px;
            height: 20px;
            fill: white;
            stroke: white;
            stroke-width: 0;
        }
        
        .ww-auth-method-info strong {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #ffffff;
            margin-bottom: 2px;
        }
        
        .ww-auth-method-info span {
            font-size: 12px;
            color: #9ca3af;
        }
        
        /* OTP */
        .ww-auth-otp-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .ww-auth-otp-label {
            font-size: 14px;
            color: #9ca3af;
            margin-bottom: 4px;
        }
        
        .ww-auth-otp-dest {
            font-weight: 600;
            color: #ffffff;
        }
        
        .ww-auth-otp-inputs {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin: 24px 0;
        }
        
        .ww-auth-otp-digit {
            width: 54px;
            height: 62px;
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            font-family: inherit;
            color: #ffffff;
            background: #0f0f0f;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            outline: none;
            transition: all 0.2s ease;
        }
        
        .ww-auth-otp-digit:focus {
            border-color: <?php echo esc_attr($branding['primary_color'] ?: '#10B981'); ?>;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }
        
        .ww-auth-otp-digit.filled {
            border-color: <?php echo esc_attr($branding['primary_color'] ?: '#10B981'); ?>;
            background: rgba(16, 185, 129, 0.1);
        }
        
        /* Biometric */
        .ww-auth-biometric {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px;
            background: #0f0f0f;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            color: #ffffff;
            font-size: 14px;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            margin-top: 12px;
            transition: all 0.2s ease;
        }
        
        .ww-auth-biometric:hover {
            background: #1a1a1a;
            border-color: #3a3a3a;
        }
        
        .ww-auth-biometric svg {
            width: 20px;
            height: 20px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
        }
        
        /* Resend */
        .ww-auth-resend {
            text-align: center;
            font-size: 13px;
            color: #9ca3af;
            margin-top: 16px;
        }
        
        .ww-auth-resend-btn {
            background: none;
            border: none;
            color: <?php echo esc_attr($branding['primary_color'] ?: '#10B981'); ?>;
            font-weight: 500;
            cursor: pointer;
            font-size: 13px;
            font-family: inherit;
        }
        
        .ww-auth-resend-btn:hover {
            text-decoration: underline;
        }
        
        /* Back */
        .ww-auth-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: none;
            border: none;
            color: #9ca3af;
            font-size: 14px;
            font-family: inherit;
            cursor: pointer;
            margin-bottom: 20px;
            padding: 0;
        }
        
        .ww-auth-back:hover {
            color: #ffffff;
        }
        
        .ww-auth-back svg {
            width: 16px;
            height: 16px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
        }
        
        /* Message */
        .ww-auth-message {
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 16px;
            display: none;
        }
        
        .ww-auth-message.show {
            display: block;
        }
        
        .ww-auth-message.error {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .ww-auth-message.success {
            background: rgba(16, 185, 129, 0.15);
            color: #6ee7b7;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        /* QR */
        .ww-auth-qr {
            text-align: center;
            padding: 20px 0;
        }
        
        .ww-auth-qr-wrap {
            background: white;
            padding: 16px;
            border-radius: 16px;
            display: inline-block;
            margin-bottom: 16px;
        }
        
        .ww-auth-qr-img {
            width: 180px;
            height: 180px;
            display: block;
        }
        
        .ww-auth-qr-hint {
            font-size: 14px;
            color: #9ca3af;
        }
        
        .ww-auth-qr-status {
            margin-top: 12px;
            padding: 10px 14px;
            background: #0f0f0f;
            border-radius: 10px;
            font-size: 13px;
            color: #9ca3af;
        }
        
        /* Footer */
        .ww-auth-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #2a2a2a;
        }
        
        .ww-auth-footer a {
            color: #9ca3af;
            font-size: 13px;
            text-decoration: none;
        }
        
        .ww-auth-footer a:hover {
            color: <?php echo esc_attr($branding['primary_color'] ?: '#10B981'); ?>;
        }
        
        /* Turnstile */
        .ww-auth-turnstile {
            display: flex;
            justify-content: center;
            margin-bottom: 16px;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .ww-auth-card {
                padding: 28px 20px;
                border-radius: 20px;
            }
            
            .ww-auth-otp-digit {
                width: 48px;
                height: 56px;
                font-size: 20px;
            }
            
            .ww-auth-otp-inputs {
                gap: 8px;
            }
        }
    </style>
    
    <?php if ($turnstile->is_enabled()): ?>
    <script src="<?php echo esc_url($turnstile->get_script_url()); ?>" async defer></script>
    <?php endif; ?>
</head>
<body class="ww-auth-page">
    <div class="ww-auth-container">
        <div class="ww-auth-card">
            <!-- Logo -->
            <div class="ww-auth-logo">
                <?php if (!empty($branding['logo_url'])): ?>
                    <img src="<?php echo esc_url($branding['logo_url']); ?>" alt="<?php bloginfo('name'); ?>">
                <?php else: ?>
                    <div class="ww-auth-logo-icon">
                        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Header -->
            <div class="ww-auth-header">
                <h1 class="ww-auth-title" id="pageTitle"><?php echo esc_html($branding['welcome_text'] ?: 'Welcome back'); ?></h1>
                <p class="ww-auth-subtitle" id="pageSubtitle">Sign in to continue to your account</p>
            </div>
            
            <!-- Message -->
            <div id="authMessage" class="ww-auth-message"></div>
            
            <!-- View: Main (Login/Register) -->
            <div class="ww-auth-view active" id="viewMain">
                <!-- Tabs -->
                <div class="ww-auth-tabs">
                    <button type="button" class="ww-auth-tab active" data-panel="panelLogin">Login</button>
                    <button type="button" class="ww-auth-tab" data-panel="panelRegister">Register</button>
                </div>
                
                <!-- Panel: Login -->
                <div class="ww-auth-panel active" id="panelLogin">
                    <form id="formLogin">
                        <div class="ww-auth-field">
                            <label class="ww-auth-label">Email Address</label>
                            <div class="ww-auth-input-wrap">
                                <span class="ww-auth-input-icon">
                                    <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                </span>
                                <input type="email" id="loginEmail" class="ww-auth-input" placeholder="you@example.com" required autocomplete="email">
                            </div>
                        </div>
                        
                        <div class="ww-auth-field">
                            <label class="ww-auth-label">Password</label>
                            <div class="ww-auth-input-wrap">
                                <span class="ww-auth-input-icon">
                                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                </span>
                                <input type="password" id="loginPassword" class="ww-auth-input has-toggle" placeholder="Enter your password" required autocomplete="current-password">
                                <button type="button" class="ww-auth-toggle-pwd" onclick="togglePwd('loginPassword')">
                                    <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                        </div>
                        
                        <div class="ww-auth-options">
                            <label class="ww-auth-checkbox">
                                <input type="checkbox" id="rememberMe">
                                <span>Remember me</span>
                            </label>
                            <a href="#" class="ww-auth-link" onclick="alert('Password reset link will be sent to your email.'); return false;">Forgot Password?</a>
                        </div>
                        
                        <?php if ($turnstile->is_enabled()): ?>
                        <div class="ww-auth-turnstile"><?php echo $turnstile->render_widget(); ?></div>
                        <?php endif; ?>
                        
                        <button type="submit" class="ww-auth-btn ww-auth-btn-primary" id="btnLogin">Login</button>
                    </form>
                    
                    <div class="ww-auth-divider">Or continue with</div>
                    
                    <div class="ww-auth-methods">
                        <?php if ($methods['whatsapp']): ?>
                        <button type="button" class="ww-auth-method" data-method="whatsapp">
                            <div class="ww-auth-method-icon whatsapp">
                                <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            </div>
                            <div class="ww-auth-method-info">
                                <strong>WhatsApp OTP</strong>
                                <span>Get code on WhatsApp</span>
                            </div>
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($methods['passkeys']): ?>
                        <button type="button" class="ww-auth-method" data-method="passkey">
                            <div class="ww-auth-method-icon passkey">
                                <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/></svg>
                            </div>
                            <div class="ww-auth-method-info">
                                <strong>Biometric Verification</strong>
                                <span>Use Face ID or Touch ID</span>
                            </div>
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($methods['qr']): ?>
                        <button type="button" class="ww-auth-method" data-method="qr">
                            <div class="ww-auth-method-icon qr">
                                <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                            </div>
                            <div class="ww-auth-method-info">
                                <strong>QR Code</strong>
                                <span>Scan with your phone</span>
                            </div>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Panel: Register -->
                <div class="ww-auth-panel" id="panelRegister">
                    <form id="formRegister">
                        <div class="ww-auth-field">
                            <label class="ww-auth-label">Full Name</label>
                            <div class="ww-auth-input-wrap">
                                <span class="ww-auth-input-icon">
                                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                </span>
                                <input type="text" id="registerName" class="ww-auth-input" placeholder="John Doe" required>
                            </div>
                        </div>
                        
                        <div class="ww-auth-field">
                            <label class="ww-auth-label">Email Address</label>
                            <div class="ww-auth-input-wrap">
                                <span class="ww-auth-input-icon">
                                    <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                </span>
                                <input type="email" id="registerEmail" class="ww-auth-input" placeholder="you@example.com" required autocomplete="email">
                            </div>
                        </div>
                        
                        <div class="ww-auth-field">
                            <label class="ww-auth-label">Phone Number</label>
                            <div class="ww-auth-input-wrap">
                                <span class="ww-auth-input-icon">
                                    <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                </span>
                                <input type="tel" id="registerPhone" class="ww-auth-input" placeholder="+1 234 567 8900" autocomplete="tel">
                            </div>
                        </div>
                        
                        <div class="ww-auth-field">
                            <label class="ww-auth-label">Password</label>
                            <div class="ww-auth-input-wrap">
                                <span class="ww-auth-input-icon">
                                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                </span>
                                <input type="password" id="registerPassword" class="ww-auth-input has-toggle" placeholder="Create a password" required autocomplete="new-password">
                                <button type="button" class="ww-auth-toggle-pwd" onclick="togglePwd('registerPassword')">
                                    <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                        </div>
                        
                        <?php if ($turnstile->is_enabled()): ?>
                        <div class="ww-auth-turnstile"><?php echo $turnstile->render_widget(); ?></div>
                        <?php endif; ?>
                        
                        <button type="submit" class="ww-auth-btn ww-auth-btn-primary" id="btnRegister">Create Account</button>
                    </form>
                </div>
            </div>
            
            <!-- View: OTP -->
            <div class="ww-auth-view" id="viewOTP">
                <button type="button" class="ww-auth-back" onclick="showView('viewMain')">
                    <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    Back
                </button>
                
                <div class="ww-auth-logo" style="margin-bottom: 16px;">
                    <div class="ww-auth-logo-icon">
                        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                </div>
                
                <div class="ww-auth-header" style="margin-bottom: 8px;">
                    <h1 class="ww-auth-title">Verify your account</h1>
                </div>
                
                <div class="ww-auth-otp-header">
                    <p class="ww-auth-otp-label">We sent a verification code to</p>
                    <p class="ww-auth-otp-dest" id="otpDest"></p>
                </div>
                
                <form id="formOTP">
                    <div class="ww-auth-otp-inputs">
                        <input type="text" class="ww-auth-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                        <input type="text" class="ww-auth-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                        <input type="text" class="ww-auth-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                        <input type="text" class="ww-auth-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                    </div>
                    
                    <button type="submit" class="ww-auth-btn ww-auth-btn-primary" id="btnVerify" disabled>Verify</button>
                </form>
                
                <?php if ($methods['passkeys']): ?>
                <button type="button" class="ww-auth-biometric" id="btnBiometric">
                    <svg viewBox="0 0 24 24"><path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/></svg>
                    Biometric Verification
                </button>
                <?php endif; ?>
                
                <div class="ww-auth-resend">
                    <span id="resendTimer">Resend code in <strong>60s</strong></span>
                    <button type="button" class="ww-auth-resend-btn" id="btnResend" style="display: none;">Resend Code</button>
                </div>
            </div>
            
            <!-- View: QR -->
            <div class="ww-auth-view" id="viewQR">
                <button type="button" class="ww-auth-back" onclick="showView('viewMain'); stopQR();">
                    <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    Back
                </button>
                
                <div class="ww-auth-qr" id="qrContainer">
                    <div class="ww-auth-spinner" style="margin: 40px auto;"></div>
                </div>
                
                <div class="ww-auth-qr-status" id="qrStatus">Waiting for authorization...</div>
                
                <button type="button" class="ww-auth-btn ww-auth-btn-outline" style="margin-top: 16px;" id="btnRefreshQR">Refresh QR Code</button>
            </div>
            
            <!-- View: WhatsApp -->
            <div class="ww-auth-view" id="viewWhatsApp">
                <button type="button" class="ww-auth-back" onclick="showView('viewMain')">
                    <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    Back
                </button>
                
                <form id="formWhatsApp">
                    <div class="ww-auth-field">
                        <label class="ww-auth-label">WhatsApp Number</label>
                        <div class="ww-auth-input-wrap">
                            <span class="ww-auth-input-icon">
                                <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            </span>
                            <input type="tel" id="whatsappPhone" class="ww-auth-input" placeholder="+1 234 567 8900" required autocomplete="tel">
                        </div>
                    </div>
                    
                    <?php if ($turnstile->is_enabled()): ?>
                    <div class="ww-auth-turnstile"><?php echo $turnstile->render_widget(); ?></div>
                    <?php endif; ?>
                    
                    <button type="submit" class="ww-auth-btn ww-auth-btn-primary" id="btnSendWhatsApp">Send Code</button>
                </form>
            </div>
            
            <!-- Footer -->
            <div class="ww-auth-footer">
                <a href="<?php echo esc_url(home_url('/')); ?>">Return to <?php bloginfo('name'); ?></a>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        'use strict';
        
        var API = '<?php echo esc_url_raw(rest_url('ww-auth/v1/')); ?>';
        var NONCE = '<?php echo wp_create_nonce('wp_rest'); ?>';
        var REDIRECT = '<?php echo esc_js($redirect_to); ?>' || '/';
        
        var currentMethod = 'email';
        var otpDest = '';
        var resendInt = null;
        var qrInt = null;
        var qrSession = null;
        
        // Tab switching
        document.querySelectorAll('.ww-auth-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                var panelId = this.getAttribute('data-panel');
                
                document.querySelectorAll('.ww-auth-tab').forEach(function(t) { t.classList.remove('active'); });
                document.querySelectorAll('.ww-auth-panel').forEach(function(p) { p.classList.remove('active'); });
                
                this.classList.add('active');
                document.getElementById(panelId).classList.add('active');
                
                var title = document.getElementById('pageTitle');
                var subtitle = document.getElementById('pageSubtitle');
                if (panelId === 'panelRegister') {
                    title.textContent = 'Create your account';
                    subtitle.textContent = 'Get started with a free account';
                } else {
                    title.textContent = '<?php echo esc_js($branding['welcome_text'] ?: 'Welcome back'); ?>';
                    subtitle.textContent = 'Sign in to continue to your account';
                }
            });
        });
        
        // View switching
        window.showView = function(id) {
            document.querySelectorAll('.ww-auth-view').forEach(function(v) { v.classList.remove('active'); });
            document.getElementById(id).classList.add('active');
            hideMsg();
        };
        
        // Password toggle
        window.togglePwd = function(id) {
            var inp = document.getElementById(id);
            inp.type = inp.type === 'password' ? 'text' : 'password';
        };
        
        // Messages
        function showMsg(txt, type) {
            var m = document.getElementById('authMessage');
            m.textContent = txt;
            m.className = 'ww-auth-message show ' + (type || 'error');
        }
        function hideMsg() {
            document.getElementById('authMessage').className = 'ww-auth-message';
        }
        
        // Loading
        function setLoad(btn, on) {
            if (on) {
                btn.disabled = true;
                btn.setAttribute('data-txt', btn.innerHTML);
                btn.innerHTML = '<span class="ww-auth-spinner"></span>';
            } else {
                btn.disabled = false;
                btn.innerHTML = btn.getAttribute('data-txt') || btn.innerHTML;
            }
        }
        
        // API
        function api(endpoint, data, method) {
            var opts = {
                method: method || 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE }
            };
            if (method !== 'GET') opts.body = JSON.stringify(data || {});
            return fetch(API + endpoint, opts).then(function(r) { return r.json(); });
        }
        
        function getTurnstile() {
            if (typeof turnstile !== 'undefined') return turnstile.getResponse() || '';
            return '';
        }
        
        // Method buttons
        document.querySelectorAll('.ww-auth-method').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var m = this.getAttribute('data-method');
                currentMethod = m;
                if (m === 'qr') { showView('viewQR'); genQR(); }
                else if (m === 'whatsapp') { showView('viewWhatsApp'); }
                else if (m === 'passkey') { startPasskey(); }
            });
        });
        
        // Login form
        document.getElementById('formLogin').addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('btnLogin');
            var email = document.getElementById('loginEmail').value;
            setLoad(btn, true);
            hideMsg();
            
            api('email/send', { email: email, turnstile_token: getTurnstile() }).then(function(r) {
                if (r.success) {
                    otpDest = email;
                    currentMethod = 'email';
                    document.getElementById('otpDest').textContent = email;
                    showView('viewOTP');
                    startTimer();
                    focusOTP();
                } else {
                    showMsg(r.error || 'Failed to send code');
                }
                setLoad(btn, false);
            }).catch(function() { showMsg('Network error'); setLoad(btn, false); });
        });
        
        // Register form
        document.getElementById('formRegister').addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('btnRegister');
            var email = document.getElementById('registerEmail').value;
            setLoad(btn, true);
            hideMsg();
            
            api('email/send', { email: email, turnstile_token: getTurnstile() }).then(function(r) {
                if (r.success) {
                    otpDest = email;
                    currentMethod = 'email';
                    document.getElementById('otpDest').textContent = email;
                    showView('viewOTP');
                    startTimer();
                    focusOTP();
                } else {
                    showMsg(r.error || 'Failed to send code');
                }
                setLoad(btn, false);
            }).catch(function() { showMsg('Network error'); setLoad(btn, false); });
        });
        
        // WhatsApp form
        document.getElementById('formWhatsApp').addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('btnSendWhatsApp');
            var phone = document.getElementById('whatsappPhone').value;
            setLoad(btn, true);
            hideMsg();
            
            api('whatsapp/send', { phone: phone, turnstile_token: getTurnstile() }).then(function(r) {
                if (r.success) {
                    otpDest = phone;
                    currentMethod = 'whatsapp';
                    document.getElementById('otpDest').textContent = phone;
                    showView('viewOTP');
                    startTimer();
                    focusOTP();
                } else {
                    showMsg(r.error || 'Failed to send code');
                }
                setLoad(btn, false);
            }).catch(function() { showMsg('Network error'); setLoad(btn, false); });
        });
        
        // OTP inputs
        var otpInputs = document.querySelectorAll('.ww-auth-otp-digit');
        
        function focusOTP() { otpInputs[0].focus(); }
        function getOTP() { return Array.from(otpInputs).map(function(i) { return i.value; }).join(''); }
        function clearOTP() { otpInputs.forEach(function(i) { i.value = ''; i.classList.remove('filled'); }); document.getElementById('btnVerify').disabled = true; }
        function checkOTP() { document.getElementById('btnVerify').disabled = getOTP().length !== 4; }
        
        otpInputs.forEach(function(inp, idx) {
            inp.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value) {
                    this.classList.add('filled');
                    if (idx < otpInputs.length - 1) otpInputs[idx + 1].focus();
                } else {
                    this.classList.remove('filled');
                }
                checkOTP();
            });
            inp.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && !this.value && idx > 0) otpInputs[idx - 1].focus();
            });
            inp.addEventListener('paste', function(e) {
                e.preventDefault();
                var txt = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '').slice(0, 4);
                txt.split('').forEach(function(d, i) {
                    if (otpInputs[i]) { otpInputs[i].value = d; otpInputs[i].classList.add('filled'); }
                });
                checkOTP();
            });
        });
        
        // OTP verify
        document.getElementById('formOTP').addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('btnVerify');
            var otp = getOTP();
            setLoad(btn, true);
            hideMsg();
            
            var endpoint = currentMethod === 'whatsapp' ? 'whatsapp/verify' : 'email/verify';
            var data = currentMethod === 'whatsapp' ? { phone: otpDest, otp: otp } : { email: otpDest, otp: otp };
            
            api(endpoint, data).then(function(r) {
                if (r.success) {
                    showMsg('Login successful! Redirecting...', 'success');
                    setTimeout(function() { window.location.href = r.redirect || REDIRECT; }, 500);
                } else {
                    showMsg(r.error || 'Invalid code');
                    clearOTP();
                    focusOTP();
                }
                setLoad(btn, false);
            }).catch(function() { showMsg('Network error'); setLoad(btn, false); });
        });
        
        // Resend timer
        function startTimer() {
            var sec = 60;
            var el = document.getElementById('resendTimer');
            var btn = document.getElementById('btnResend');
            el.style.display = 'inline';
            btn.style.display = 'none';
            if (resendInt) clearInterval(resendInt);
            resendInt = setInterval(function() {
                sec--;
                el.innerHTML = 'Resend code in <strong>' + sec + 's</strong>';
                if (sec <= 0) {
                    clearInterval(resendInt);
                    el.style.display = 'none';
                    btn.style.display = 'inline';
                }
            }, 1000);
        }
        
        document.getElementById('btnResend').addEventListener('click', function() {
            this.disabled = true;
            var endpoint = currentMethod === 'whatsapp' ? 'whatsapp/send' : 'email/send';
            var data = currentMethod === 'whatsapp' ? { phone: otpDest } : { email: otpDest };
            api(endpoint, data).then(function(r) {
                if (r.success) {
                    showMsg('Code resent!', 'success');
                    startTimer();
                    clearOTP();
                    focusOTP();
                } else {
                    showMsg(r.error || 'Failed');
                }
                document.getElementById('btnResend').disabled = false;
            }).catch(function() { showMsg('Network error'); document.getElementById('btnResend').disabled = false; });
        });
        
        // QR
        function genQR() {
            var c = document.getElementById('qrContainer');
            c.innerHTML = '<div class="ww-auth-spinner" style="margin:40px auto;"></div>';
            document.getElementById('qrStatus').textContent = 'Generating QR code...';
            
            api('qr/generate', {}).then(function(r) {
                if (r.success) {
                    qrSession = r.session_id;
                    var url = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(r.qr_data);
                    c.innerHTML = '<div class="ww-auth-qr-wrap"><img src="' + url + '" class="ww-auth-qr-img" alt="QR"></div><p class="ww-auth-qr-hint">Scan with your phone to log in</p>';
                    document.getElementById('qrStatus').textContent = 'Waiting for authorization...';
                    startQR();
                } else {
                    c.innerHTML = '<p style="color:#ef4444;">Failed to generate QR</p>';
                }
            }).catch(function() { c.innerHTML = '<p style="color:#ef4444;">Network error</p>'; });
        }
        
        function startQR() {
            if (qrInt) clearInterval(qrInt);
            qrInt = setInterval(function() {
                fetch(API + 'qr/poll?session_id=' + qrSession, { headers: { 'X-WP-Nonce': NONCE } })
                    .then(function(r) { return r.json(); })
                    .then(function(r) {
                        if (r.status === 'authorized') {
                            stopQR();
                            document.getElementById('qrStatus').textContent = 'Authorized! Redirecting...';
                            showMsg('Login successful!', 'success');
                            setTimeout(function() { window.location.href = r.redirect || REDIRECT; }, 500);
                        } else if (r.status === 'expired') {
                            stopQR();
                            document.getElementById('qrStatus').textContent = 'QR expired. Please refresh.';
                        }
                    }).catch(function() {});
            }, 3000);
        }
        
        window.stopQR = function() { if (qrInt) { clearInterval(qrInt); qrInt = null; } };
        
        document.getElementById('btnRefreshQR').addEventListener('click', function() { stopQR(); genQR(); });
        
        // Passkey
        function startPasskey() {
            hideMsg();
            api('passkeys/login/options', {}).then(function(r) {
                if (!r.success) { showMsg(r.error || 'Failed'); return; }
                var opts = r.options;
                var pkOpts = {
                    challenge: Uint8Array.from(atob(opts.challenge.replace(/-/g, '+').replace(/_/g, '/')), function(c) { return c.charCodeAt(0); }),
                    timeout: opts.timeout,
                    rpId: opts.rpId,
                    userVerification: opts.userVerification
                };
                navigator.credentials.get({ publicKey: pkOpts }).then(function(cred) {
                    var resp = {
                        id: cred.id,
                        rawId: btoa(String.fromCharCode.apply(null, new Uint8Array(cred.rawId))),
                        response: {
                            clientDataJSON: btoa(String.fromCharCode.apply(null, new Uint8Array(cred.response.clientDataJSON))),
                            authenticatorData: btoa(String.fromCharCode.apply(null, new Uint8Array(cred.response.authenticatorData))),
                            signature: btoa(String.fromCharCode.apply(null, new Uint8Array(cred.response.signature)))
                        },
                        type: cred.type,
                        session_id: opts.session_id
                    };
                    api('passkeys/login/verify', resp).then(function(v) {
                        if (v.success) {
                            showMsg('Login successful!', 'success');
                            setTimeout(function() { window.location.href = v.redirect || REDIRECT; }, 500);
                        } else {
                            showMsg(v.error || 'Auth failed');
                        }
                    }).catch(function() { showMsg('Network error'); });
                }).catch(function(e) {
                    if (e.name === 'NotAllowedError') showMsg('Authentication cancelled');
                    else showMsg('Passkey failed');
                });
            }).catch(function() { showMsg('Network error'); });
        }
        
        document.getElementById('btnBiometric')?.addEventListener('click', startPasskey);
        
    })();
    </script>
</body>
</html>
