<?php
/**
 * Secure Login Page Template - Premium Edition
 * 
 * Beautiful gradient design with animations
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
$redirect_to = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : '';
$is_mobile = wp_is_mobile();
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: <?php echo esc_attr($branding['primary_color'] ?: '#10B981'); ?>;
            --primary-dark: #059669;
            --primary-light: #34D399;
            --secondary: <?php echo esc_attr($branding['secondary_color'] ?: '#111827'); ?>;
            --accent: #8B5CF6;
            --bg-gradient-1: #0f172a;
            --bg-gradient-2: #1e1b4b;
            --bg-gradient-3: #134e4a;
            --card-bg: rgba(255, 255, 255, 0.95);
            --card-border: rgba(255, 255, 255, 0.2);
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            --input-bg: #f9fafb;
            --input-border: #e5e7eb;
            --success: #10B981;
            --error: #ef4444;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        html, body { height: 100%; width: 100%; }
        
        body.ww-page {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--bg-gradient-1) 0%, var(--bg-gradient-2) 50%, var(--bg-gradient-3) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        /* Animated Background */
        .ww-bg-shapes {
            position: fixed;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
            z-index: 0;
        }
        
        .ww-shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.5;
            animation: float 20s ease-in-out infinite;
        }
        
        .ww-shape-1 {
            width: 600px;
            height: 600px;
            background: var(--primary);
            top: -200px;
            right: -200px;
            animation-delay: 0s;
        }
        
        .ww-shape-2 {
            width: 500px;
            height: 500px;
            background: var(--accent);
            bottom: -150px;
            left: -150px;
            animation-delay: -5s;
        }
        
        .ww-shape-3 {
            width: 400px;
            height: 400px;
            background: #06b6d4;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation-delay: -10s;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(30px, -30px) scale(1.05); }
            50% { transform: translate(-20px, 20px) scale(0.95); }
            75% { transform: translate(20px, 30px) scale(1.02); }
        }
        
        /* Container */
        .ww-container {
            width: 100%;
            max-width: 440px;
            position: relative;
            z-index: 10;
        }
        
        /* Card */
        .ww-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-radius: 28px;
            padding: 40px 36px;
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.5);
            animation: cardEnter 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        @keyframes cardEnter {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        /* Logo */
        .ww-logo {
            text-align: center;
            margin-bottom: 28px;
        }
        
        .ww-logo img {
            max-height: 48px;
            width: auto;
        }
        
        .ww-logo-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px -5px rgba(16, 185, 129, 0.4);
            animation: logoGlow 3s ease-in-out infinite;
        }
        
        @keyframes logoGlow {
            0%, 100% { box-shadow: 0 10px 30px -5px rgba(16, 185, 129, 0.4); }
            50% { box-shadow: 0 15px 40px -5px rgba(16, 185, 129, 0.6); }
        }
        
        .ww-logo-icon svg {
            width: 32px;
            height: 32px;
            fill: none;
            stroke: white;
            stroke-width: 2;
        }
        
        /* Header */
        .ww-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .ww-title {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .ww-subtitle {
            font-size: 15px;
            color: var(--text-muted);
        }
        
        /* Tabs */
        .ww-tabs {
            display: flex;
            background: var(--input-bg);
            border-radius: 14px;
            padding: 5px;
            margin-bottom: 28px;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .ww-tab {
            flex: 1;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            background: transparent;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: inherit;
            position: relative;
        }
        
        .ww-tab:hover:not(.active) {
            color: var(--text-dark);
        }
        
        .ww-tab.active {
            background: white;
            color: var(--text-dark);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        /* Panels */
        .ww-panel { display: none; animation: fadeSlide 0.4s ease; }
        .ww-panel.active { display: block; }
        
        @keyframes fadeSlide {
            from { opacity: 0; transform: translateX(10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        /* Views */
        .ww-view { display: none; animation: fadeSlide 0.4s ease; }
        .ww-view.active { display: block; }
        
        /* Form */
        .ww-field {
            margin-bottom: 20px;
        }
        
        .ww-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        
        .ww-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .ww-input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            transition: all 0.3s ease;
        }
        
        .ww-input-icon svg {
            width: 20px;
            height: 20px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        
        .ww-input-icon.whatsapp svg {
            fill: #25D366;
            stroke: #25D366;
        }
        
        .ww-input-icon.email svg {
            stroke: #6366F1;
        }
        
        .ww-input {
            width: 100%;
            padding: 16px 16px 16px 50px;
            font-size: 15px;
            font-family: inherit;
            color: var(--text-dark);
            background: var(--input-bg);
            border: 2px solid var(--input-border);
            border-radius: 14px;
            outline: none;
            transition: all 0.3s ease;
        }
        
        .ww-input::placeholder {
            color: #9ca3af;
        }
        
        .ww-input:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }
        
        .ww-input.error {
            border-color: var(--error);
            animation: shake 0.4s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-6px); }
            40%, 80% { transform: translateX(6px); }
        }
        
        /* Country Code */
        .ww-phone-wrap {
            display: flex;
            gap: 10px;
        }
        
        .ww-country-code {
            width: 90px;
            padding: 16px 12px;
            font-size: 15px;
            font-family: inherit;
            color: var(--text-dark);
            background: var(--input-bg);
            border: 2px solid var(--input-border);
            border-radius: 14px;
            outline: none;
            font-weight: 600;
            text-align: center;
        }
        
        .ww-phone-input {
            flex: 1;
            padding: 16px;
            font-size: 15px;
            font-family: inherit;
            color: var(--text-dark);
            background: var(--input-bg);
            border: 2px solid var(--input-border);
            border-radius: 14px;
            outline: none;
            transition: all 0.3s ease;
        }
        
        .ww-phone-input:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }
        
        /* Buttons */
        .ww-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 16px 24px;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .ww-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .ww-btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 15px -3px rgba(16, 185, 129, 0.4);
        }
        
        .ww-btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -5px rgba(16, 185, 129, 0.5);
        }
        
        .ww-btn-primary:active:not(:disabled) {
            transform: translateY(0);
        }
        
        .ww-btn-secondary {
            background: white;
            color: var(--text-dark);
            border: 2px solid var(--input-border);
        }
        
        .ww-btn-secondary:hover:not(:disabled) {
            background: var(--input-bg);
            border-color: var(--text-muted);
        }
        
        /* Biometric Button - Premium */
        .ww-biometric-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            color: white;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .ww-biometric-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(139, 92, 246, 0.2) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .ww-biometric-btn:hover::before {
            opacity: 1;
        }
        
        .ww-biometric-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.3);
        }
        
        .ww-biometric-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, #06b6d4 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .ww-biometric-icon svg {
            width: 22px;
            height: 22px;
            fill: none;
            stroke: white;
            stroke-width: 2;
        }
        
        /* Spinner */
        .ww-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* Divider */
        .ww-divider {
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 24px 0;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
        }
        
        .ww-divider::before,
        .ww-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--input-border), transparent);
        }
        
        /* OTP */
        .ww-otp-wrap {
            text-align: center;
            margin: 24px 0;
        }
        
        .ww-otp-label {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        
        .ww-otp-dest {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 16px;
        }
        
        .ww-otp-inputs {
            display: flex;
            gap: 14px;
            justify-content: center;
            margin: 28px 0;
        }
        
        .ww-otp-digit {
            width: 60px;
            height: 70px;
            text-align: center;
            font-size: 28px;
            font-weight: 700;
            font-family: inherit;
            color: var(--text-dark);
            background: var(--input-bg);
            border: 2px solid var(--input-border);
            border-radius: 16px;
            outline: none;
            transition: all 0.3s ease;
        }
        
        .ww-otp-digit:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
            transform: scale(1.05);
        }
        
        .ww-otp-digit.filled {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(6, 182, 212, 0.1) 100%);
        }
        
        /* Resend */
        .ww-resend {
            text-align: center;
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 20px;
        }
        
        .ww-resend-btn {
            background: none;
            border: none;
            color: var(--primary);
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            font-family: inherit;
            transition: color 0.2s;
        }
        
        .ww-resend-btn:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        /* Back */
        .ww-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            margin-bottom: 24px;
            padding: 0;
            transition: color 0.2s;
        }
        
        .ww-back:hover {
            color: var(--text-dark);
        }
        
        .ww-back svg {
            width: 18px;
            height: 18px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
        }
        
        /* Message */
        .ww-msg {
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            gap: 10px;
        }
        
        .ww-msg.show { display: flex; }
        
        .ww-msg.error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .ww-msg.success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        /* QR - Desktop Only */
        .ww-qr-section {
            display: <?php echo $is_mobile ? 'none' : 'block'; ?>;
        }
        
        .ww-qr-box {
            text-align: center;
            padding: 24px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 20px;
            margin-bottom: 20px;
        }
        
        .ww-qr-img-wrap {
            background: white;
            padding: 16px;
            border-radius: 16px;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 12px;
        }
        
        .ww-qr-img {
            width: 160px;
            height: 160px;
            display: block;
        }
        
        .ww-qr-hint {
            font-size: 13px;
            color: var(--text-muted);
        }
        
        .ww-qr-status {
            padding: 12px 16px;
            background: var(--input-bg);
            border-radius: 10px;
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 16px;
        }
        
        /* Footer */
        .ww-footer {
            text-align: center;
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid var(--input-border);
        }
        
        .ww-footer a {
            color: var(--text-muted);
            font-size: 13px;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .ww-footer a:hover {
            color: var(--primary);
        }
        
        /* Modal */
        .ww-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }
        
        .ww-modal-overlay.show {
            display: flex;
        }
        
        .ww-modal {
            background: white;
            border-radius: 24px;
            padding: 36px;
            max-width: 400px;
            width: 100%;
            text-align: center;
            animation: modalEnter 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        @keyframes modalEnter {
            from { opacity: 0; transform: scale(0.9) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        
        .ww-modal-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary) 0%, #06b6d4 100%);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 15px 40px -10px rgba(16, 185, 129, 0.4);
        }
        
        .ww-modal-icon svg {
            width: 40px;
            height: 40px;
            fill: none;
            stroke: white;
            stroke-width: 2;
        }
        
        .ww-modal-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        
        .ww-modal-text {
            font-size: 15px;
            color: var(--text-muted);
            margin-bottom: 28px;
            line-height: 1.6;
        }
        
        .ww-modal-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .ww-modal-skip {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            font-family: inherit;
            margin-top: 8px;
        }
        
        .ww-modal-skip:hover {
            color: var(--text-dark);
        }
        
        /* Validation Hints */
        .ww-hint {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .ww-hint.valid {
            color: var(--success);
        }
        
        .ww-hint.invalid {
            color: var(--error);
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .ww-card {
                padding: 32px 24px;
                border-radius: 24px;
            }
            
            .ww-otp-digit {
                width: 52px;
                height: 60px;
                font-size: 24px;
            }
            
            .ww-otp-inputs {
                gap: 10px;
            }
            
            .ww-shape-1, .ww-shape-2, .ww-shape-3 {
                display: none;
            }
        }
    </style>
    
    <?php if ($turnstile->is_enabled()): ?>
    <script src="<?php echo esc_url($turnstile->get_script_url()); ?>" async defer></script>
    <?php endif; ?>
</head>
<body class="ww-page">
    <!-- Animated Background -->
    <div class="ww-bg-shapes">
        <div class="ww-shape ww-shape-1"></div>
        <div class="ww-shape ww-shape-2"></div>
        <div class="ww-shape ww-shape-3"></div>
    </div>
    
    <div class="ww-container">
        <div class="ww-card">
            <!-- Logo - Only show on main view -->
            <div class="ww-logo" id="mainLogo">
                <?php if (!empty($branding['logo_url'])): ?>
                    <img src="<?php echo esc_url($branding['logo_url']); ?>" alt="<?php bloginfo('name'); ?>">
                <?php else: ?>
                    <div class="ww-logo-icon">
                        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Header -->
            <div class="ww-header">
                <h1 class="ww-title" id="pageTitle"><?php echo esc_html($branding['welcome_text'] ?: 'Welcome back'); ?></h1>
                <p class="ww-subtitle" id="pageSubtitle">Sign in to continue</p>
            </div>
            
            <!-- Message -->
            <div id="msg" class="ww-msg"></div>
            
            <!-- View: Main -->
            <div class="ww-view active" id="viewMain">
                <div class="ww-tabs">
                    <button type="button" class="ww-tab active" data-panel="panelLogin">Login</button>
                    <button type="button" class="ww-tab" data-panel="panelRegister">Register</button>
                </div>
                
                <!-- Login Panel -->
                <div class="ww-panel active" id="panelLogin">
                    <form id="formLogin">
                        <div class="ww-field">
                            <label class="ww-label">Email or WhatsApp Number</label>
                            <div class="ww-input-wrap">
                                <span class="ww-input-icon" id="loginInputIcon">
                                    <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                </span>
                                <input type="text" id="loginInput" class="ww-input" placeholder="you@example.com or +91 98765 43210" required autocomplete="username">
                            </div>
                        </div>
                        
                        <button type="submit" class="ww-btn ww-btn-primary" id="btnLogin">
                            Send Code
                        </button>
                    </form>
                    
                    <?php if ($methods['passkeys']): ?>
                    <div class="ww-divider">or</div>
                    
                    <button type="button" class="ww-biometric-btn" id="btnPasskeyLogin">
                        <span class="ww-biometric-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/></svg>
                        </span>
                        <span>Login with Biometrics</span>
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($methods['qr'] && !$is_mobile): ?>
                    <div class="ww-qr-section" style="margin-top: 20px;">
                        <div class="ww-divider">or scan QR</div>
                        <button type="button" class="ww-btn ww-btn-secondary" id="btnShowQR">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                            Scan QR Code to Login
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Register Panel -->
                <div class="ww-panel" id="panelRegister">
                    <form id="formRegister">
                        <div class="ww-field">
                            <label class="ww-label">Full Name</label>
                            <div class="ww-input-wrap">
                                <span class="ww-input-icon">
                                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                </span>
                                <input type="text" id="regName" class="ww-input" placeholder="John Doe" required>
                            </div>
                        </div>
                        
                        <div class="ww-field">
                            <label class="ww-label">Email Address</label>
                            <div class="ww-input-wrap">
                                <span class="ww-input-icon">
                                    <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                </span>
                                <input type="email" id="regEmail" class="ww-input" placeholder="you@example.com" required>
                            </div>
                            <div class="ww-hint" id="emailHint"></div>
                        </div>
                        
                        <div class="ww-field">
                            <label class="ww-label">WhatsApp Number</label>
                            <div class="ww-phone-wrap">
                                <input type="text" class="ww-country-code" value="+91" readonly>
                                <input type="tel" id="regPhone" class="ww-phone-input" placeholder="98765 43210" maxlength="12" required>
                            </div>
                            <div class="ww-hint" id="phoneHint"></div>
                        </div>
                        
                        <button type="submit" class="ww-btn ww-btn-primary" id="btnRegister">
                            Create Account
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- View: OTP -->
            <div class="ww-view" id="viewOTP">
                <button type="button" class="ww-back" onclick="showView('viewMain')">
                    <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    Back
                </button>
                
                <div class="ww-otp-wrap">
                    <p class="ww-otp-label">We sent a verification code to</p>
                    <p class="ww-otp-dest" id="otpDest"></p>
                </div>
                
                <form id="formOTP">
                    <div class="ww-otp-inputs">
                        <input type="text" class="ww-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                        <input type="text" class="ww-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                        <input type="text" class="ww-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                        <input type="text" class="ww-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                    </div>
                    
                    <button type="submit" class="ww-btn ww-btn-primary" id="btnVerify" disabled>
                        Verify Code
                    </button>
                </form>
                
                <?php if ($methods['passkeys']): ?>
                <div class="ww-divider">or</div>
                <button type="button" class="ww-biometric-btn" id="btnBiometricOTP">
                    <span class="ww-biometric-icon">
                        <svg viewBox="0 0 24 24"><path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/></svg>
                    </span>
                    <span>Use Biometrics Instead</span>
                </button>
                <?php endif; ?>
                
                <div class="ww-resend">
                    <span id="resendTimer">Resend code in <strong>60s</strong></span>
                    <button type="button" class="ww-resend-btn" id="btnResend" style="display: none;">Resend Code</button>
                </div>
            </div>
            
            <!-- View: QR -->
            <div class="ww-view" id="viewQR">
                <button type="button" class="ww-back" onclick="showView('viewMain'); stopQR();">
                    <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    Back
                </button>
                
                <div class="ww-qr-box" id="qrContainer">
                    <div class="ww-spinner" style="margin: 40px auto;"></div>
                </div>
                
                <div class="ww-qr-status" id="qrStatus">Waiting for authorization...</div>
                
                <button type="button" class="ww-btn ww-btn-secondary" style="margin-top: 16px;" id="btnRefreshQR">
                    Refresh QR Code
                </button>
            </div>
            
            <!-- Footer -->
            <div class="ww-footer">
                <a href="<?php echo esc_url(home_url('/')); ?>">Return to <?php bloginfo('name'); ?></a>
            </div>
        </div>
    </div>
    
    <!-- Biometric Setup Modal -->
    <div class="ww-modal-overlay" id="modalBiometric">
        <div class="ww-modal">
            <div class="ww-modal-icon">
                <svg viewBox="0 0 24 24"><path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/></svg>
            </div>
            <h2 class="ww-modal-title">Enable Fast Login</h2>
            <p class="ww-modal-text">Set up biometric authentication for instant, secure login without OTP codes. Use Face ID, Touch ID, or your device security.</p>
            <div class="ww-modal-actions">
                <button type="button" class="ww-btn ww-btn-primary" id="btnSetupBiometric">
                    Set Up Biometrics
                </button>
                <button type="button" class="ww-modal-skip" id="btnSkipBiometric">
                    Maybe Later
                </button>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        'use strict';
        
        var API = '<?php echo esc_url_raw(rest_url('ww-auth/v1/')); ?>';
        var NONCE = '<?php echo wp_create_nonce('wp_rest'); ?>';
        var REDIRECT = '<?php echo esc_js($redirect_to); ?>' || '<?php echo esc_js(home_url('/my-account/')); ?>';
        
        var currentMode = 'login';
        var otpDest = '';
        var otpMethod = 'email';
        var resendInt = null;
        var qrInt = null;
        var qrSession = null;
        var isNewUser = false;
        var currentUserId = null;
        
        // Tab switching
        document.querySelectorAll('.ww-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                var panelId = this.getAttribute('data-panel');
                
                document.querySelectorAll('.ww-tab').forEach(function(t) { t.classList.remove('active'); });
                document.querySelectorAll('.ww-panel').forEach(function(p) { p.classList.remove('active'); });
                
                this.classList.add('active');
                document.getElementById(panelId).classList.add('active');
                
                var title = document.getElementById('pageTitle');
                var subtitle = document.getElementById('pageSubtitle');
                if (panelId === 'panelRegister') {
                    currentMode = 'register';
                    title.textContent = 'Create Account';
                    subtitle.textContent = 'Get started in seconds';
                } else {
                    currentMode = 'login';
                    title.textContent = '<?php echo esc_js($branding['welcome_text'] ?: 'Welcome back'); ?>';
                    subtitle.textContent = 'Sign in to continue';
                }
                hideMsg();
            });
        });
        
        // View switching
        window.showView = function(id) {
            document.querySelectorAll('.ww-view').forEach(function(v) { v.classList.remove('active'); });
            document.getElementById(id).classList.add('active');
            
            // Show/hide logo based on view
            var logo = document.getElementById('mainLogo');
            if (id === 'viewOTP') {
                logo.style.display = 'none';
            } else {
                logo.style.display = 'block';
            }
            hideMsg();
        };
        
        // Messages
        function showMsg(txt, type) {
            var m = document.getElementById('msg');
            m.textContent = txt;
            m.className = 'ww-msg show ' + (type || 'error');
        }
        function hideMsg() {
            document.getElementById('msg').className = 'ww-msg';
        }
        
        // Loading
        function setLoad(btn, on) {
            if (on) {
                btn.disabled = true;
                btn.setAttribute('data-txt', btn.innerHTML);
                btn.innerHTML = '<span class="ww-spinner"></span> Please wait...';
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
        
        // Detect input type
        var loginInput = document.getElementById('loginInput');
        var loginIcon = document.getElementById('loginInputIcon');
        
        function detectInputType(val) {
            val = val.trim();
            if (val.includes('@')) {
                return 'email';
            } else if (/^[\+]?[0-9\s\-]+$/.test(val) && val.replace(/\D/g, '').length >= 10) {
                return 'phone';
            }
            return 'unknown';
        }
        
        loginInput.addEventListener('input', function() {
            var type = detectInputType(this.value);
            if (type === 'phone') {
                loginIcon.innerHTML = '<svg viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';
                loginIcon.classList.add('whatsapp');
                loginIcon.classList.remove('email');
            } else {
                loginIcon.innerHTML = '<svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>';
                loginIcon.classList.remove('whatsapp');
                if (type === 'email') {
                    loginIcon.classList.add('email');
                }
            }
        });
        
        // Validation
        function validateEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }
        
        function validatePhone(phone) {
            var digits = phone.replace(/\D/g, '');
            return digits.length === 10;
        }
        
        // Register validation
        var regEmail = document.getElementById('regEmail');
        var regPhone = document.getElementById('regPhone');
        var emailHint = document.getElementById('emailHint');
        var phoneHint = document.getElementById('phoneHint');
        
        regEmail.addEventListener('input', function() {
            if (this.value) {
                if (validateEmail(this.value)) {
                    emailHint.textContent = '✓ Valid email';
                    emailHint.className = 'ww-hint valid';
                } else {
                    emailHint.textContent = '✗ Enter a valid email';
                    emailHint.className = 'ww-hint invalid';
                }
            } else {
                emailHint.textContent = '';
                emailHint.className = 'ww-hint';
            }
        });
        
        regPhone.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9\s]/g, '');
            var digits = this.value.replace(/\D/g, '');
            if (digits.length > 0) {
                if (digits.length === 10) {
                    phoneHint.textContent = '✓ Valid phone number';
                    phoneHint.className = 'ww-hint valid';
                } else {
                    phoneHint.textContent = '✗ Enter 10 digit number';
                    phoneHint.className = 'ww-hint invalid';
                }
            } else {
                phoneHint.textContent = '';
                phoneHint.className = 'ww-hint';
            }
        });
        
        // Login form
        document.getElementById('formLogin').addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('btnLogin');
            var val = loginInput.value.trim();
            var type = detectInputType(val);
            
            if (type === 'unknown') {
                showMsg('Please enter a valid email or phone number');
                return;
            }
            
            setLoad(btn, true);
            hideMsg();
            
            // Check if user exists first
            api('check-user', { identifier: val, type: type }).then(function(r) {
                if (r.exists) {
                    // User exists, send OTP
                    otpMethod = type === 'phone' ? 'whatsapp' : 'email';
                    var endpoint = otpMethod === 'whatsapp' ? 'whatsapp/send' : 'email/send';
                    var data = otpMethod === 'whatsapp' ? { phone: val } : { email: val };
                    
                    api(endpoint, data).then(function(res) {
                        if (res.success) {
                            otpDest = val;
                            isNewUser = false;
                            currentUserId = r.user_id;
                            document.getElementById('otpDest').textContent = val;
                            document.getElementById('pageTitle').textContent = 'Verify Your Identity';
                            document.getElementById('pageSubtitle').textContent = 'Enter the code we sent you';
                            showView('viewOTP');
                            startTimer();
                            focusOTP();
                        } else {
                            showMsg(res.error || 'Failed to send code');
                        }
                        setLoad(btn, false);
                    });
                } else {
                    // User doesn't exist, switch to register
                    showMsg('Account not found. Please register first.', 'error');
                    document.querySelector('[data-panel="panelRegister"]').click();
                    if (type === 'email') {
                        document.getElementById('regEmail').value = val;
                    } else {
                        document.getElementById('regPhone').value = val.replace(/\D/g, '').slice(-10);
                    }
                    setLoad(btn, false);
                }
            }).catch(function() {
                showMsg('Network error. Please try again.');
                setLoad(btn, false);
            });
        });
        
        // Register form
        document.getElementById('formRegister').addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('btnRegister');
            var name = document.getElementById('regName').value.trim();
            var email = document.getElementById('regEmail').value.trim();
            var phone = document.getElementById('regPhone').value.replace(/\D/g, '');
            
            if (!validateEmail(email)) {
                showMsg('Please enter a valid email address');
                return;
            }
            
            if (!validatePhone(phone)) {
                showMsg('Please enter a valid 10-digit phone number');
                return;
            }
            
            setLoad(btn, true);
            hideMsg();
            
            // Send email verification
            api('email/send', { email: email, name: name, phone: '+91' + phone }).then(function(r) {
                if (r.success) {
                    otpDest = email;
                    otpMethod = 'email';
                    isNewUser = true;
                    document.getElementById('otpDest').textContent = email;
                    document.getElementById('pageTitle').textContent = 'Verify Your Email';
                    document.getElementById('pageSubtitle').textContent = 'Enter the code we sent you';
                    showView('viewOTP');
                    startTimer();
                    focusOTP();
                } else {
                    showMsg(r.error || 'Failed to send code');
                }
                setLoad(btn, false);
            }).catch(function() {
                showMsg('Network error');
                setLoad(btn, false);
            });
        });
        
        // OTP inputs
        var otpInputs = document.querySelectorAll('.ww-otp-digit');
        
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
            
            var endpoint = otpMethod === 'whatsapp' ? 'whatsapp/verify' : 'email/verify';
            var data = otpMethod === 'whatsapp' ? { phone: otpDest, otp: otp } : { email: otpDest, otp: otp };
            
            api(endpoint, data).then(function(r) {
                if (r.success) {
                    currentUserId = r.user && r.user.id;
                    
                    // Check if should show biometric setup
                    if (isNewUser || (r.user && !r.user.has_passkey)) {
                        // Show biometric setup modal
                        document.getElementById('modalBiometric').classList.add('show');
                    } else {
                        // Redirect
                        showMsg('Login successful! Redirecting...', 'success');
                        setTimeout(function() { window.location.href = r.redirect || REDIRECT; }, 500);
                    }
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
            var endpoint = otpMethod === 'whatsapp' ? 'whatsapp/send' : 'email/send';
            var data = otpMethod === 'whatsapp' ? { phone: otpDest } : { email: otpDest };
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
            });
        });
        
        // QR
        document.getElementById('btnShowQR')?.addEventListener('click', function() {
            showView('viewQR');
            genQR();
        });
        
        function genQR() {
            var c = document.getElementById('qrContainer');
            c.innerHTML = '<div class="ww-spinner" style="margin:40px auto;"></div>';
            document.getElementById('qrStatus').textContent = 'Generating QR code...';
            
            api('qr/generate', {}).then(function(r) {
                if (r.success) {
                    qrSession = r.session_id;
                    var url = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' + encodeURIComponent(r.qr_data);
                    c.innerHTML = '<div class="ww-qr-img-wrap"><img src="' + url + '" class="ww-qr-img" alt="QR"></div><p class="ww-qr-hint">Scan with your phone camera</p>';
                    document.getElementById('qrStatus').textContent = 'Waiting for authorization...';
                    startQR();
                } else {
                    c.innerHTML = '<p style="color:#ef4444;">Failed to generate QR</p>';
                }
            });
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
                    });
            }, 3000);
        }
        
        window.stopQR = function() { if (qrInt) { clearInterval(qrInt); qrInt = null; } };
        
        document.getElementById('btnRefreshQR')?.addEventListener('click', function() { stopQR(); genQR(); });
        
        // Passkey login
        function startPasskey() {
            hideMsg();
            api('passkeys/login/options', {}).then(function(r) {
                if (!r.success) { showMsg(r.error || 'Biometrics not available'); return; }
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
                            showMsg(v.error || 'Authentication failed');
                        }
                    });
                }).catch(function(e) {
                    if (e.name === 'NotAllowedError') showMsg('Authentication cancelled');
                    else showMsg('Biometrics failed. Please use OTP.');
                });
            });
        }
        
        document.getElementById('btnPasskeyLogin')?.addEventListener('click', startPasskey);
        document.getElementById('btnBiometricOTP')?.addEventListener('click', startPasskey);
        
        // Biometric setup
        document.getElementById('btnSetupBiometric')?.addEventListener('click', function() {
            var btn = this;
            setLoad(btn, true);
            
            api('passkeys/register/options', {}).then(function(r) {
                if (!r.success) {
                    showMsg(r.error || 'Setup failed');
                    setLoad(btn, false);
                    return;
                }
                
                var opts = r.options;
                var pkOpts = {
                    challenge: Uint8Array.from(atob(opts.challenge.replace(/-/g, '+').replace(/_/g, '/')), function(c) { return c.charCodeAt(0); }),
                    rp: opts.rp,
                    user: {
                        id: Uint8Array.from(atob(opts.user.id), function(c) { return c.charCodeAt(0); }),
                        name: opts.user.name,
                        displayName: opts.user.displayName
                    },
                    pubKeyCredParams: opts.pubKeyCredParams,
                    timeout: opts.timeout,
                    authenticatorSelection: opts.authenticatorSelection,
                    attestation: opts.attestation
                };
                
                navigator.credentials.create({ publicKey: pkOpts }).then(function(cred) {
                    var resp = {
                        id: cred.id,
                        rawId: btoa(String.fromCharCode.apply(null, new Uint8Array(cred.rawId))),
                        response: {
                            clientDataJSON: btoa(String.fromCharCode.apply(null, new Uint8Array(cred.response.clientDataJSON))),
                            attestationObject: btoa(String.fromCharCode.apply(null, new Uint8Array(cred.response.attestationObject)))
                        },
                        type: cred.type
                    };
                    
                    api('passkeys/register/verify', resp).then(function(v) {
                        if (v.success) {
                            document.getElementById('modalBiometric').classList.remove('show');
                            showMsg('Biometrics enabled! Redirecting...', 'success');
                            setTimeout(function() { window.location.href = REDIRECT; }, 1000);
                        } else {
                            showMsg(v.error || 'Setup failed');
                        }
                        setLoad(btn, false);
                    });
                }).catch(function() {
                    showMsg('Setup cancelled');
                    setLoad(btn, false);
                });
            });
        });
        
        document.getElementById('btnSkipBiometric')?.addEventListener('click', function() {
            document.getElementById('modalBiometric').classList.remove('show');
            showMsg('Login successful! Redirecting...', 'success');
            setTimeout(function() { window.location.href = REDIRECT; }, 500);
        });
        
    })();
    </script>
</body>
</html>
