<?php
/**
 * Secure Login Page Template
 * 
 * Studio White UI - Dribbble-style Fintech Design
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($branding['page_title']); ?> - <?php bloginfo('name'); ?></title>
    
    <?php wp_head(); ?>
    
    <style>
        <?php echo WW_Frontend::get_css_variables(); ?>
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', Roboto, sans-serif;
            background: #F4F9F5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.5;
        }
        
        .ww-login-wrapper {
            width: 100%;
            max-width: 420px;
        }
        
        .ww-login-card {
            background: white;
            border-radius: 24px;
            padding: 40px 32px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
            animation: slideUp 0.4s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .ww-logo {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .ww-logo img {
            max-height: 48px;
            width: auto;
        }
        
        .ww-logo-placeholder {
            width: 56px;
            height: 56px;
            background: var(--ww-primary);
            border-radius: 16px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .ww-logo-placeholder svg {
            width: 28px;
            height: 28px;
            color: white;
        }
        
        .ww-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .ww-header h1 {
            font-size: 24px;
            font-weight: 600;
            color: var(--ww-secondary);
            margin-bottom: 8px;
        }
        
        .ww-header p {
            color: #6B7280;
            font-size: 15px;
        }
        
        /* Steps/Views */
        .ww-step {
            display: none;
            animation: fadeIn 0.3s ease-out;
        }
        
        .ww-step.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Method Selection */
        .ww-methods {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .ww-method-btn {
            display: flex;
            align-items: center;
            gap: 16px;
            width: 100%;
            padding: 16px 20px;
            background: #F9FAFB;
            border: 2px solid transparent;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: left;
        }
        
        .ww-method-btn:hover {
            background: #F3F4F6;
            border-color: var(--ww-primary);
        }
        
        .ww-method-btn:active {
            transform: scale(0.98);
        }
        
        .ww-method-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .ww-method-icon.whatsapp { background: #25D366; }
        .ww-method-icon.email { background: #6366F1; }
        .ww-method-icon.passkey { background: var(--ww-secondary); }
        .ww-method-icon.qr { background: var(--ww-primary); }
        
        .ww-method-icon svg {
            width: 22px;
            height: 22px;
            color: white;
        }
        
        .ww-method-info {
            flex: 1;
        }
        
        .ww-method-info strong {
            display: block;
            font-size: 15px;
            font-weight: 600;
            color: var(--ww-secondary);
            margin-bottom: 2px;
        }
        
        .ww-method-info span {
            font-size: 13px;
            color: #6B7280;
        }
        
        /* Form Inputs */
        .ww-form-group {
            margin-bottom: 20px;
        }
        
        .ww-form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--ww-secondary);
            margin-bottom: 8px;
        }
        
        .ww-input-wrapper {
            position: relative;
        }
        
        .ww-input {
            width: 100%;
            padding: 14px 16px;
            font-size: 16px;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .ww-input:focus {
            border-color: var(--ww-primary);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }
        
        .ww-input.error {
            border-color: #EF4444;
        }
        
        /* OTP Input */
        .ww-otp-inputs {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        
        .ww-otp-input {
            width: 56px;
            height: 64px;
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            outline: none;
            transition: all 0.2s;
        }
        
        .ww-otp-input:focus {
            border-color: var(--ww-primary);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }
        
        .ww-otp-input.filled {
            border-color: var(--ww-primary);
            background: rgba(16, 185, 129, 0.05);
        }
        
        /* Buttons */
        .ww-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 16px 24px;
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 100px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .ww-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .ww-btn-primary {
            background: var(--ww-secondary);
            color: white;
        }
        
        .ww-btn-primary:hover:not(:disabled) {
            background: var(--ww-secondary-hover);
            transform: translateY(-1px);
        }
        
        .ww-btn-primary:active:not(:disabled) {
            transform: translateY(0);
        }
        
        .ww-btn-outline {
            background: transparent;
            color: var(--ww-secondary);
            border: 2px solid #E5E7EB;
        }
        
        .ww-btn-outline:hover:not(:disabled) {
            background: #F9FAFB;
            border-color: var(--ww-secondary);
        }
        
        .ww-btn-link {
            background: none;
            border: none;
            color: var(--ww-primary);
            font-weight: 500;
            cursor: pointer;
            font-size: 14px;
        }
        
        .ww-btn-link:hover {
            text-decoration: underline;
        }
        
        /* Loading Spinner */
        .ww-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Messages */
        .ww-message {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .ww-message.error {
            background: #FEF2F2;
            color: #DC2626;
        }
        
        .ww-message.success {
            background: #ECFDF5;
            color: #059669;
        }
        
        /* Back button */
        .ww-back {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6B7280;
            font-size: 14px;
            margin-bottom: 24px;
            cursor: pointer;
            background: none;
            border: none;
        }
        
        .ww-back:hover {
            color: var(--ww-secondary);
        }
        
        /* QR Code */
        .ww-qr-container {
            text-align: center;
            padding: 20px 0;
        }
        
        .ww-qr-image {
            width: 200px;
            height: 200px;
            border-radius: 16px;
            margin-bottom: 16px;
        }
        
        .ww-qr-hint {
            color: #6B7280;
            font-size: 14px;
        }
        
        .ww-qr-status {
            margin-top: 16px;
            padding: 12px;
            background: #F9FAFB;
            border-radius: 12px;
            font-size: 14px;
            color: #6B7280;
        }
        
        /* Resend Timer */
        .ww-resend {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #6B7280;
        }
        
        /* Turnstile */
        .ww-turnstile {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        /* Footer */
        .ww-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #E5E7EB;
        }
        
        .ww-footer a {
            color: #6B7280;
            font-size: 13px;
            text-decoration: none;
        }
        
        .ww-footer a:hover {
            color: var(--ww-primary);
        }
        
        /* QR Auth Confirmation */
        .ww-qr-auth-confirm {
            text-align: center;
            padding: 20px 0;
        }
        
        .ww-qr-auth-icon {
            width: 80px;
            height: 80px;
            background: var(--ww-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        
        .ww-qr-auth-icon svg {
            width: 40px;
            height: 40px;
            color: white;
        }
        
        /* Passkey prompt */
        .ww-passkey-prompt {
            text-align: center;
            padding: 40px 0;
        }
        
        .ww-passkey-icon {
            width: 80px;
            height: 80px;
            background: var(--ww-secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .ww-login-card {
                padding: 32px 24px;
                border-radius: 20px;
            }
            
            .ww-otp-input {
                width: 48px;
                height: 56px;
                font-size: 20px;
            }
        }
    </style>
    
    <?php if ($turnstile->is_enabled()): ?>
    <script src="<?php echo esc_url($turnstile->get_script_url()); ?>" async defer></script>
    <?php endif; ?>
</head>
<body>
    <div class="ww-login-wrapper">
        <div class="ww-login-card">
            <!-- Logo -->
            <div class="ww-logo">
                <?php if (!empty($branding['logo_url'])): ?>
                    <img src="<?php echo esc_url($branding['logo_url']); ?>" alt="<?php bloginfo('name'); ?>">
                <?php else: ?>
                    <div class="ww-logo-placeholder">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Header -->
            <div class="ww-header">
                <h1><?php echo esc_html($branding['welcome_text']); ?></h1>
                <p>Sign in to continue to your account</p>
            </div>
            
            <!-- Message Container -->
            <div id="ww-message" class="ww-message" style="display: none;"></div>
            
            <?php if ($is_qr_auth): ?>
            <!-- QR Authorization Confirmation (for mobile users) -->
            <div class="ww-step active" id="step-qr-confirm">
                <div class="ww-qr-auth-confirm">
                    <div class="ww-qr-auth-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 12l2 2 4-4"/>
                            <circle cx="12" cy="12" r="10"/>
                        </svg>
                    </div>
                    <h3 style="color: var(--ww-secondary); margin-bottom: 8px;">Authorize Login</h3>
                    <p style="color: #6B7280; margin-bottom: 24px;">
                        Do you want to sign in to <?php bloginfo('name'); ?> on another device?
                    </p>
                    <button type="button" class="ww-btn ww-btn-primary" id="btn-authorize-qr">
                        Authorize Login
                    </button>
                    <button type="button" class="ww-btn ww-btn-outline" style="margin-top: 12px;" onclick="window.close();">
                        Cancel
                    </button>
                </div>
            </div>
            
            <?php else: ?>
            
            <!-- Step 1: Method Selection -->
            <div class="ww-step active" id="step-methods">
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
                    
                    <?php if ($methods['email']): ?>
                    <button type="button" class="ww-method-btn" data-method="email">
                        <div class="ww-method-icon email">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </div>
                        <div class="ww-method-info">
                            <strong>Email OTP</strong>
                            <span>Get code via email</span>
                        </div>
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($methods['passkeys']): ?>
                    <button type="button" class="ww-method-btn" data-method="passkey">
                        <div class="ww-method-icon passkey">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </div>
                        <div class="ww-method-info">
                            <strong>Passkey</strong>
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
            
            <!-- Step 2: WhatsApp Phone Input -->
            <div class="ww-step" id="step-whatsapp">
                <button type="button" class="ww-back" onclick="showStep('methods')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back
                </button>
                
                <form id="form-whatsapp">
                    <div class="ww-form-group">
                        <label for="phone">WhatsApp Number</label>
                        <input type="tel" id="phone" name="phone" class="ww-input" 
                               placeholder="+1 234 567 8900" required
                               pattern="[\+]?[0-9\s\-\(\)]+"
                               autocomplete="tel">
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
            
            <!-- Step 2: Email Input -->
            <div class="ww-step" id="step-email">
                <button type="button" class="ww-back" onclick="showStep('methods')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back
                </button>
                
                <form id="form-email">
                    <div class="ww-form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="ww-input" 
                               placeholder="you@example.com" required
                               autocomplete="email">
                    </div>
                    
                    <?php if ($turnstile->is_enabled()): ?>
                    <div class="ww-turnstile">
                        <?php echo $turnstile->render_widget(); ?>
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="ww-btn ww-btn-primary" id="btn-send-email">
                        Send Code
                    </button>
                </form>
            </div>
            
            <!-- Step 3: OTP Verification -->
            <div class="ww-step" id="step-otp">
                <button type="button" class="ww-back" onclick="goBackFromOTP()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back
                </button>
                
                <div style="text-align: center; margin-bottom: 24px;">
                    <p style="color: #6B7280;">Enter the 4-digit code sent to</p>
                    <strong id="otp-destination" style="color: var(--ww-secondary);"></strong>
                </div>
                
                <form id="form-otp">
                    <div class="ww-otp-inputs">
                        <input type="text" class="ww-otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                        <input type="text" class="ww-otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                        <input type="text" class="ww-otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                        <input type="text" class="ww-otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                    </div>
                    
                    <button type="submit" class="ww-btn ww-btn-primary" style="margin-top: 24px;" id="btn-verify-otp" disabled>
                        Verify
                    </button>
                </form>
                
                <div class="ww-resend">
                    <span id="resend-timer">Resend code in <strong>60s</strong></span>
                    <button type="button" class="ww-btn-link" id="btn-resend" style="display: none;">
                        Resend Code
                    </button>
                </div>
            </div>
            
            <!-- Step: QR Code -->
            <div class="ww-step" id="step-qr">
                <button type="button" class="ww-back" onclick="showStep('methods'); stopQRPolling();">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
                
                <button type="button" class="ww-btn ww-btn-outline" style="margin-top: 20px;" id="btn-refresh-qr">
                    Refresh QR Code
                </button>
            </div>
            
            <!-- Step: Passkey -->
            <div class="ww-step" id="step-passkey">
                <button type="button" class="ww-back" onclick="showStep('methods')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back
                </button>
                
                <div class="ww-passkey-prompt">
                    <div class="ww-passkey-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/>
                        </svg>
                    </div>
                    <h3 style="color: var(--ww-secondary); margin-bottom: 8px;">Use Your Passkey</h3>
                    <p style="color: #6B7280; margin-bottom: 24px;">
                        Authenticate using Face ID, Touch ID, or your security key
                    </p>
                    <button type="button" class="ww-btn ww-btn-primary" id="btn-start-passkey">
                        Continue with Passkey
                    </button>
                </div>
            </div>
            
            <?php endif; ?>
            
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
        const QR_SESSION = '<?php echo esc_js($qr_session); ?>';
        
        let currentMethod = null;
        let otpDestination = null;
        let resendTimer = null;
        let qrPollInterval = null;
        let qrSessionId = null;
        
        // Helper functions
        function showStep(stepId) {
            document.querySelectorAll('.ww-step').forEach(step => {
                step.classList.remove('active');
            });
            document.getElementById('step-' + stepId)?.classList.add('active');
            hideMessage();
        }
        
        function showMessage(text, type = 'error') {
            const msg = document.getElementById('ww-message');
            msg.textContent = text;
            msg.className = 'ww-message ' + type;
            msg.style.display = 'flex';
        }
        
        function hideMessage() {
            document.getElementById('ww-message').style.display = 'none';
        }
        
        function setLoading(button, loading) {
            if (loading) {
                button.disabled = true;
                button.dataset.originalText = button.innerHTML;
                button.innerHTML = '<span class="ww-spinner"></span> Please wait...';
            } else {
                button.disabled = false;
                button.innerHTML = button.dataset.originalText || 'Submit';
            }
        }
        
        async function apiCall(endpoint, data = {}) {
            const response = await fetch(API_URL + endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': NONCE
                },
                body: JSON.stringify(data)
            });
            return response.json();
        }
        
        function getTurnstileToken() {
            if (typeof turnstile !== 'undefined') {
                const widget = document.querySelector('.cf-turnstile iframe');
                if (widget) {
                    return turnstile.getResponse();
                }
            }
            return '';
        }
        
        // Method selection
        document.querySelectorAll('.ww-method-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const method = this.dataset.method;
                currentMethod = method;
                
                if (method === 'qr') {
                    showStep('qr');
                    generateQRCode();
                } else if (method === 'passkey') {
                    showStep('passkey');
                } else {
                    showStep(method);
                }
            });
        });
        
        // WhatsApp form
        document.getElementById('form-whatsapp')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-send-whatsapp');
            const phone = document.getElementById('phone').value;
            
            setLoading(btn, true);
            hideMessage();
            
            try {
                const result = await apiCall('whatsapp/send', {
                    phone: phone,
                    turnstile_token: getTurnstileToken()
                });
                
                if (result.success) {
                    otpDestination = phone;
                    document.getElementById('otp-destination').textContent = phone;
                    showStep('otp');
                    startResendTimer();
                    focusFirstOTPInput();
                } else {
                    showMessage(result.error || 'Failed to send code');
                }
            } catch (err) {
                showMessage('Network error. Please try again.');
            }
            
            setLoading(btn, false);
        });
        
        // Email form
        document.getElementById('form-email')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-send-email');
            const email = document.getElementById('email').value;
            
            setLoading(btn, true);
            hideMessage();
            
            try {
                const result = await apiCall('email/send', {
                    email: email,
                    turnstile_token: getTurnstileToken()
                });
                
                if (result.success) {
                    otpDestination = email;
                    document.getElementById('otp-destination').textContent = email;
                    showStep('otp');
                    startResendTimer();
                    focusFirstOTPInput();
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
        
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
                
                if (this.value.length === 1) {
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
                
                digits.split('').forEach((digit, i) => {
                    if (otpInputs[i]) {
                        otpInputs[i].value = digit;
                        otpInputs[i].classList.add('filled');
                    }
                });
                
                checkOTPComplete();
            });
        });
        
        function focusFirstOTPInput() {
            otpInputs[0]?.focus();
        }
        
        function checkOTPComplete() {
            const otp = Array.from(otpInputs).map(i => i.value).join('');
            document.getElementById('btn-verify-otp').disabled = otp.length !== 4;
        }
        
        function getOTP() {
            return Array.from(otpInputs).map(i => i.value).join('');
        }
        
        function clearOTP() {
            otpInputs.forEach(input => {
                input.value = '';
                input.classList.remove('filled');
            });
            document.getElementById('btn-verify-otp').disabled = true;
        }
        
        // OTP verification
        document.getElementById('form-otp')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-verify-otp');
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
                    focusFirstOTPInput();
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
        
        document.getElementById('btn-resend')?.addEventListener('click', function() {
            if (currentMethod === 'whatsapp') {
                document.getElementById('form-whatsapp').dispatchEvent(new Event('submit'));
            } else if (currentMethod === 'email') {
                document.getElementById('form-email').dispatchEvent(new Event('submit'));
            }
        });
        
        function goBackFromOTP() {
            if (resendTimer) clearInterval(resendTimer);
            clearOTP();
            showStep(currentMethod);
        }
        
        // QR Code
        async function generateQRCode() {
            const container = document.getElementById('qr-container');
            container.innerHTML = '<div class="ww-spinner" style="margin: 40px auto;"></div>';
            document.getElementById('qr-status').textContent = 'Generating QR code...';
            
            try {
                const response = await fetch(API_URL + 'qr/generate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': NONCE
                    }
                });
                const result = await response.json();
                
                if (result.success) {
                    qrSessionId = result.session_id;
                    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(result.qr_data)}`;
                    
                    container.innerHTML = `
                        <img src="${qrUrl}" alt="QR Code" class="ww-qr-image" />
                        <p class="ww-qr-hint">Scan with your phone to log in</p>
                    `;
                    
                    document.getElementById('qr-status').textContent = 'Waiting for authorization...';
                    startQRPolling();
                } else {
                    container.innerHTML = '<p style="color: #EF4444;">Failed to generate QR code</p>';
                }
            } catch (err) {
                container.innerHTML = '<p style="color: #EF4444;">Network error</p>';
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
                    // Silent fail for polling
                }
            }, 3000);
        }
        
        function stopQRPolling() {
            if (qrPollInterval) {
                clearInterval(qrPollInterval);
                qrPollInterval = null;
            }
        }
        
        window.stopQRPolling = stopQRPolling;
        window.showStep = showStep;
        window.goBackFromOTP = goBackFromOTP;
        
        document.getElementById('btn-refresh-qr')?.addEventListener('click', function() {
            stopQRPolling();
            generateQRCode();
        });
        
        // Passkey
        document.getElementById('btn-start-passkey')?.addEventListener('click', async function() {
            const btn = this;
            setLoading(btn, true);
            hideMessage();
            
            try {
                // Get authentication options
                const optionsRes = await apiCall('passkeys/login/options', {});
                
                if (!optionsRes.success) {
                    showMessage(optionsRes.error || 'Failed to start authentication');
                    setLoading(btn, false);
                    return;
                }
                
                const options = optionsRes.options;
                
                // Prepare for WebAuthn
                const publicKeyCredentialRequestOptions = {
                    challenge: Uint8Array.from(atob(options.challenge.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0)),
                    timeout: options.timeout,
                    rpId: options.rpId,
                    userVerification: options.userVerification
                };
                
                // Get credential
                const credential = await navigator.credentials.get({
                    publicKey: publicKeyCredentialRequestOptions
                });
                
                // Prepare response
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
                
                // Verify
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
            
            setLoading(btn, false);
        });
        
        // QR Authorization (mobile)
        document.getElementById('btn-authorize-qr')?.addEventListener('click', async function() {
            const btn = this;
            const sessionId = new URLSearchParams(window.location.search).get('session');
            
            if (!sessionId) {
                showMessage('Invalid session');
                return;
            }
            
            setLoading(btn, true);
            
            try {
                const result = await apiCall('qr/authorize', { session_id: sessionId });
                
                if (result.success) {
                    showMessage('Login authorized! You can close this window.', 'success');
                    btn.style.display = 'none';
                } else {
                    showMessage(result.error || 'Authorization failed');
                }
            } catch (err) {
                showMessage('Network error');
            }
            
            setLoading(btn, false);
        });
        
        // Handle QR session from URL
        if (QR_SESSION) {
            // User scanned QR and needs to log in first
            // After login, they'll be redirected back to authorize
        }
        
    })();
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>
