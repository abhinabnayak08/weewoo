<?php
/**
 * Secure Login Page Template — Premium Glass-Morphism Edition
 *
 * Flow:
 *   1. viewMain   – Email/WhatsApp identifier → Login / Register tabs
 *   2. viewMethod – "How do you want to sign in?" (biometric / email OTP / WhatsApp OTP)
 *   3. viewOTP    – 4-digit code entry (+ magic-link in email)
 *   4. Biometric  – dismissible post-login setup modal (remembered forever once set up or skipped)
 *
 * Fully self-contained — no external CSS/JS enqueue to keep theme isolation.
 *
 * @package WeeWoo_Auth_Pro
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$branding = WW_Frontend::get_branding();
$methods  = WW_Frontend::get_auth_methods();
$turnstile = WW_Turnstile::instance();
$redirect_to = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : '';
$is_mobile = wp_is_mobile();
$company  = get_option('ww_auth_company_name', '') ?: get_bloginfo('name');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a0f1c">
    <title><?php echo esc_html($branding['page_title']); ?> — <?php echo esc_html($company); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: <?php echo esc_attr($branding['primary_color'] ?: '#10B981'); ?>;
            --primary-2: #059669;
            --primary-soft: rgba(16,185,129,0.18);
            --bg-1: #060912;
            --bg-2: #0a0f1c;
            --bg-3: #0e1629;
            --glass: rgba(255,255,255,0.08);
            --glass-strong: rgba(255,255,255,0.14);
            --glass-border: rgba(255,255,255,0.14);
            --glass-border-strong: rgba(255,255,255,0.22);
            --card: rgba(255,255,255,0.96);
            --card-line: rgba(15,23,42,0.08);
            --ink: #0f172a;
            --ink-soft: #475569;
            --ink-muted: #94a3b8;
            --danger: #ef4444;
            --success: #10B981;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { height: 100%; width: 100%; }

        body.ww-page {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            min-height: 100svh;
            background:
                radial-gradient(1200px 600px at 20% -10%, rgba(16,185,129,0.22), transparent 60%),
                radial-gradient(900px 500px at 110% 10%, rgba(139,92,246,0.18), transparent 55%),
                radial-gradient(800px 500px at 50% 110%, rgba(6,182,212,0.16), transparent 60%),
                linear-gradient(180deg, var(--bg-1) 0%, var(--bg-2) 60%, var(--bg-3) 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px calc(24px + env(safe-area-inset-bottom));
            position: relative;
            overflow: hidden;
        }

        /* Ambient shapes */
        .ww-orbs { position: fixed; inset: 0; overflow: hidden; pointer-events: none; z-index: 0; }
        .ww-orb {
            position: absolute; border-radius: 50%;
            filter: blur(90px); opacity: 0.55;
            animation: floaty 22s ease-in-out infinite;
        }
        .ww-orb.a { width: 520px; height: 520px; background: var(--primary); top: -180px; right: -160px; }
        .ww-orb.b { width: 420px; height: 420px; background: #8B5CF6; bottom: -160px; left: -140px; animation-delay: -7s; }
        .ww-orb.c { width: 360px; height: 360px; background: #06b6d4; top: 50%; left: 50%; transform: translate(-50%,-50%); animation-delay: -12s; }
        @keyframes floaty {
            0%,100% { transform: translate(0,0) scale(1); }
            33%     { transform: translate(28px,-24px) scale(1.06); }
            66%     { transform: translate(-22px,18px) scale(0.96); }
        }

        /* Container */
        .ww-stage { width: 100%; max-width: 440px; position: relative; z-index: 10; }

        /* Brand bar (no shield — just bold company name) */
        .ww-brand {
            text-align: center; margin-bottom: 20px;
            color: #fff; letter-spacing: -0.5px;
            font-weight: 800; font-size: 20px;
            text-shadow: 0 2px 24px rgba(0,0,0,0.35);
        }
        .ww-brand img { max-height: 40px; width: auto; display: inline-block; }
        .ww-brand .dot { display: inline-block; width: 8px; height: 8px; background: var(--primary); border-radius: 50%; margin-right: 10px; vertical-align: middle; box-shadow: 0 0 20px var(--primary); }

        /* Glass card */
        .ww-card {
            position: relative;
            background: var(--card);
            border-radius: 28px;
            padding: 32px 28px;
            box-shadow:
                0 30px 60px -20px rgba(0,0,0,0.55),
                0 0 0 1px rgba(255,255,255,0.08),
                inset 0 1px 0 rgba(255,255,255,0.7);
            animation: cardIn 0.55s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .ww-card::before {
            content: ""; position: absolute; inset: 0;
            border-radius: inherit; pointer-events: none;
            background: linear-gradient(180deg, rgba(255,255,255,0.5), transparent 40%);
            mix-blend-mode: overlay; opacity: 0.8;
        }
        @keyframes cardIn { from { opacity:0; transform: translateY(18px) scale(0.98); } to { opacity:1; transform: translateY(0) scale(1); } }

        /* Header */
        .ww-h { text-align: center; margin-bottom: 22px; }
        .ww-h h1 { color: var(--ink); font-size: 24px; font-weight: 800; letter-spacing: -0.6px; }
        .ww-h p  { color: var(--ink-soft); font-size: 14px; margin-top: 6px; }

        /* Pill tabs (Rotta-style) */
        .ww-pills {
            position: relative;
            background: #f1f5f9;
            border-radius: 999px;
            padding: 6px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            margin-bottom: 22px;
            box-shadow: inset 0 1px 2px rgba(15,23,42,0.06);
        }
        .ww-pill-ind {
            position: absolute; top: 6px; bottom: 6px; left: 6px;
            width: calc(50% - 6px);
            background: #fff;
            border-radius: 999px;
            box-shadow: 0 4px 14px -4px rgba(15,23,42,0.18), 0 0 0 1px rgba(15,23,42,0.04);
            transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .ww-pill {
            position: relative; z-index: 2;
            padding: 12px 18px;
            background: transparent; border: 0;
            font: inherit; font-weight: 700; font-size: 14px;
            color: var(--ink-soft); cursor: pointer;
            border-radius: 999px;
            transition: color 0.25s ease;
        }
        .ww-pill.active { color: var(--ink); }
        .ww-pills[data-active="register"] .ww-pill-ind { transform: translateX(100%); }

        /* Views & Panels */
        .ww-view { display: none; animation: slideIn 0.35s ease both; }
        .ww-view.active { display: block; }
        .ww-panel { display: none; animation: slideIn 0.3s ease both; }
        .ww-panel.active { display: block; }
        @keyframes slideIn { from { opacity:0; transform: translateY(6px);} to { opacity:1; transform: translateY(0);} }

        /* Field */
        .ww-field { margin-bottom: 16px; }
        .ww-field label { display:block; font-size: 12.5px; font-weight: 700; color: var(--ink); margin-bottom: 8px; letter-spacing: 0.02em; text-transform: uppercase; }
        .ww-input-wrap { position: relative; }
        .ww-input-icon {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            width: 20px; height: 20px; pointer-events: none; color: var(--ink-muted);
            transition: color 0.2s ease, transform 0.2s ease;
        }
        .ww-input-icon svg { width: 20px; height: 20px; fill:none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .ww-input-icon.is-phone { color: #25D366; }
        .ww-input-icon.is-phone svg { fill: #25D366; stroke: #25D366; }
        .ww-input-icon.is-email { color: #6366F1; }

        .ww-input {
            width: 100%;
            min-height: 52px;
            padding: 14px 16px 14px 48px;
            font: 500 15px/1.4 inherit;
            color: var(--ink);
            background: #f8fafc;
            border: 1.5px solid var(--card-line);
            border-radius: 14px;
            outline: none;
            transition: all 0.25s ease;
        }
        .ww-input::placeholder { color: #9ca3af; }
        .ww-input:focus {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px var(--primary-soft);
        }
        .ww-input.error { border-color: var(--danger); animation: shake 0.35s ease; }
        @keyframes shake { 0%,100%{transform:translateX(0);} 25%{transform:translateX(-5px);} 75%{transform:translateX(5px);} }

        /* Phone row (MOBILE FIX — proper grid, no overflow) */
        .ww-phone-row { display: grid; grid-template-columns: 78px 1fr; gap: 10px; }
        .ww-phone-row .ww-cc {
            min-height: 52px; text-align:center; font-weight: 700; font-size: 15px;
            background: #f8fafc; color: var(--ink);
            border: 1.5px solid var(--card-line); border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
        }
        .ww-phone-row .ww-input { padding-left: 16px; }

        /* Hint */
        .ww-hint { font-size: 12px; color: var(--ink-muted); margin-top: 6px; display: none; align-items: center; gap: 6px; }
        .ww-hint.show { display: flex; }
        .ww-hint.valid { color: var(--success); }
        .ww-hint.invalid { color: var(--danger); }

        /* Buttons */
        .ww-btn {
            display: inline-flex; align-items: center; justify-content: center;
            gap: 10px; width: 100%;
            min-height: 52px;
            padding: 14px 20px;
            font: 700 15px/1 inherit;
            border: 0; border-radius: 14px;
            cursor: pointer; position: relative; overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.25s ease, background 0.25s ease;
        }
        .ww-btn:disabled { opacity: 0.55; cursor: not-allowed; transform: none !important; }
        .ww-btn-primary {
            color:#fff;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-2) 100%);
            box-shadow: 0 10px 24px -10px rgba(16,185,129,0.6), inset 0 1px 0 rgba(255,255,255,0.25);
        }
        .ww-btn-primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 14px 30px -10px rgba(16,185,129,0.7); }
        .ww-btn-primary .arrow { transition: transform 0.25s ease; }
        .ww-btn-primary:hover:not(:disabled) .arrow { transform: translateX(4px); }

        .ww-btn-ghost { color: var(--ink); background: #fff; border: 1.5px solid var(--card-line); }
        .ww-btn-ghost:hover:not(:disabled) { background: #f8fafc; border-color: #cbd5e1; }

        /* Method choice cards (screen 2) */
        .ww-choice {
            display: flex; align-items: center; gap: 14px;
            width: 100%;
            padding: 14px 16px;
            min-height: 64px;
            background: #fff;
            border: 1.5px solid var(--card-line);
            border-radius: 18px;
            cursor: pointer; text-align:left;
            font: 600 14px/1.3 inherit; color: var(--ink);
            transition: all 0.25s ease;
        }
        .ww-choice:hover { transform: translateY(-1px); border-color: #cbd5e1; box-shadow: 0 10px 24px -14px rgba(15,23,42,0.18); }
        .ww-choice .icon {
            flex: 0 0 44px; width: 44px; height: 44px; border-radius: 12px;
            display:flex; align-items:center; justify-content:center;
        }
        .ww-choice .icon svg { width:22px; height:22px; stroke: currentColor; fill: none; stroke-width:2; }
        .ww-choice .lines { flex: 1; min-width: 0; }
        .ww-choice .lines b { display:block; font-size: 15px; font-weight: 800; letter-spacing: -0.2px; }
        .ww-choice .lines span { display:block; font-size: 12.5px; color: var(--ink-soft); margin-top: 2px; }
        .ww-choice .chev { color: var(--ink-muted); transition: transform 0.2s ease; }
        .ww-choice:hover .chev { transform: translateX(3px); color: var(--ink); }

        .ww-choice.primary {
            background: linear-gradient(135deg, rgba(16,185,129,0.08) 0%, rgba(6,182,212,0.05) 100%);
            border-color: rgba(16,185,129,0.35);
        }
        .ww-choice.primary .icon { background: linear-gradient(135deg, var(--primary), #06b6d4); color:#fff; }
        .ww-choice.email .icon    { background: #EEF2FF; color: #6366F1; }
        .ww-choice.whatsapp .icon { background: #DCFCE7; color: #25D366; }

        .ww-badge {
            display: inline-block; font-size: 10.5px; font-weight: 800; letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--primary-2);
            background: var(--primary-soft);
            padding: 3px 8px; border-radius: 999px; margin-left: 6px;
        }

        /* OTP */
        .ww-otp-meta { text-align:center; margin: 4px 0 16px; }
        .ww-otp-meta small { color: var(--ink-muted); font-size: 13px; }
        .ww-otp-meta b { color: var(--ink); font-weight: 700; font-size: 15px; display:block; margin-top:4px; }
        .ww-otp-digits { display:grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 18px 0 20px; }
        .ww-otp-digits input {
            width: 100%; aspect-ratio: 1 / 1.15;
            text-align:center; font: 800 26px/1 inherit; color: var(--ink);
            background: #f8fafc; border: 1.5px solid var(--card-line); border-radius: 14px; outline:none;
            transition: all 0.2s ease;
        }
        .ww-otp-digits input:focus { border-color: var(--primary); background:#fff; box-shadow: 0 0 0 4px var(--primary-soft); transform: scale(1.03); }
        .ww-otp-digits input.filled { border-color: var(--primary); background: linear-gradient(135deg, rgba(16,185,129,0.12), rgba(6,182,212,0.08)); }

        /* Back / link */
        .ww-back {
            display:inline-flex; align-items:center; gap:6px;
            background: none; border: 0; color: var(--ink-soft); font: 600 13px inherit; cursor:pointer;
            padding: 0; margin-bottom: 14px;
        }
        .ww-back:hover { color: var(--ink); }
        .ww-back svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2.2; }

        /* Msg bar */
        .ww-msg {
            display: none; align-items: center; gap: 10px;
            padding: 12px 14px; border-radius: 12px;
            font: 600 13.5px inherit; margin-bottom: 14px;
        }
        .ww-msg.show { display:flex; }
        .ww-msg.error  { color:#991b1b; background: #FEF2F2; border: 1px solid #FECACA; }
        .ww-msg.success{ color:#065F46; background: #ECFDF5; border: 1px solid #A7F3D0; }

        /* Spinner */
        .ww-spin { width: 18px; height: 18px; border: 2px solid transparent; border-top-color: currentColor; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Divider */
        .ww-div { display:flex; align-items:center; gap: 14px; margin: 18px 0; color: var(--ink-muted); font-size: 12px; font-weight: 600; }
        .ww-div::before, .ww-div::after { content:""; flex:1; height:1px; background: var(--card-line); }

        /* Resend */
        .ww-resend { text-align:center; font-size: 13px; color: var(--ink-muted); margin-top: 14px; }
        .ww-resend button { background:none; border:0; color: var(--primary-2); font: 700 13px inherit; cursor:pointer; }
        .ww-resend button:hover { text-decoration: underline; }

        /* QR */
        .ww-qr-wrap { background: linear-gradient(135deg, #f8fafc, #eef2f7); border-radius: 20px; padding: 22px; text-align:center; }
        .ww-qr-wrap .box { background:#fff; padding: 14px; border-radius: 16px; display:inline-block; box-shadow: 0 4px 14px -4px rgba(15,23,42,0.1); }
        .ww-qr-wrap img { width: 180px; height: 180px; display:block; }
        .ww-qr-status { margin-top: 14px; font-size: 13px; color: var(--ink-soft); }

        /* Biometric Modal (premium) */
        .ww-modal-bg {
            position: fixed; inset: 0; z-index: 1000;
            background: rgba(2,6,23,0.65);
            backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
            display:none; align-items:center; justify-content:center;
            padding: 20px;
        }
        .ww-modal-bg.show { display:flex; animation: fade 0.25s ease; }
        @keyframes fade { from { opacity:0; } to { opacity:1; } }

        .ww-modal {
            width: 100%; max-width: 380px;
            background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(255,255,255,0.94));
            border-radius: 28px;
            padding: 28px 24px 24px;
            text-align:center;
            box-shadow: 0 40px 80px -20px rgba(2,6,23,0.7);
            animation: modalIn 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            position:relative; overflow:hidden;
        }
        @keyframes modalIn { from { opacity:0; transform: scale(0.9) translateY(10px);} to { opacity:1; transform: scale(1) translateY(0);} }
        .ww-modal::after {
            content:""; position:absolute; left:-40%; top:-60%; width:200%; height:60%;
            background: radial-gradient(ellipse at center, rgba(16,185,129,0.14), transparent 60%);
            pointer-events:none;
        }
        .ww-modal-icon {
            width: 84px; height: 84px; margin: 0 auto 18px;
            border-radius: 24px;
            background: linear-gradient(135deg, var(--primary), #06b6d4);
            display:flex; align-items:center; justify-content:center;
            box-shadow: 0 20px 40px -15px rgba(16,185,129,0.55), inset 0 1px 0 rgba(255,255,255,0.4);
            animation: pulse 2.6s ease-in-out infinite;
        }
        @keyframes pulse {
            0%,100% { box-shadow: 0 20px 40px -15px rgba(16,185,129,0.55), inset 0 1px 0 rgba(255,255,255,0.4); }
            50%     { box-shadow: 0 25px 55px -12px rgba(16,185,129,0.75), inset 0 1px 0 rgba(255,255,255,0.5); }
        }
        .ww-modal-icon svg { width: 40px; height: 40px; stroke:#fff; fill:none; stroke-width:2; }
        .ww-modal h2 { color: var(--ink); font-size: 22px; font-weight: 800; letter-spacing: -0.4px; }
        .ww-modal p  { color: var(--ink-soft); font-size: 14px; line-height: 1.55; margin: 8px 0 20px; }
        .ww-modal .perks { text-align:left; background:#f8fafc; border-radius: 14px; padding: 12px 14px; margin-bottom: 18px; border: 1px solid var(--card-line); }
        .ww-modal .perk { display:flex; align-items:center; gap:10px; font-size: 13px; color: var(--ink); font-weight:600; }
        .ww-modal .perk + .perk { margin-top: 8px; }
        .ww-modal .perk svg { width: 18px; height: 18px; color: var(--primary-2); flex-shrink:0; }
        .ww-modal .ww-btn { margin-bottom: 10px; }
        .ww-modal .skip { background:none; border:0; color: var(--ink-muted); font: 600 13.5px inherit; cursor:pointer; padding: 8px 10px; }
        .ww-modal .skip:hover { color: var(--ink); }
        .ww-modal label.never { display:inline-flex; align-items:center; gap:6px; font-size: 12px; color: var(--ink-muted); margin-top: 4px; cursor:pointer; user-select:none; }
        .ww-modal label.never input { margin: 0; }

        /* Footer / return link */
        .ww-foot { text-align:center; margin-top: 18px; }
        .ww-foot a { color: rgba(255,255,255,0.7); font-size: 13px; font-weight: 600; text-decoration:none; }
        .ww-foot a:hover { color: #fff; }

        /* Mobile */
        @media (max-width: 480px) {
            .ww-card { padding: 26px 20px; border-radius: 24px; }
            .ww-otp-digits { gap: 8px; }
            .ww-orb.a, .ww-orb.b { filter: blur(70px); opacity: 0.35; }
            .ww-orb.c { display:none; }
        }

        /* QR hidden on mobile */
        @media (max-width: 640px) { .ww-qr-hide-mobile { display:none !important; } }
    </style>

    <?php if ($turnstile->is_enabled()): ?>
    <script src="<?php echo esc_url($turnstile->get_script_url()); ?>" async defer></script>
    <?php endif; ?>
</head>
<body class="ww-page">
    <div class="ww-orbs">
        <div class="ww-orb a"></div>
        <div class="ww-orb b"></div>
        <div class="ww-orb c"></div>
    </div>

    <div class="ww-stage">
        <div class="ww-brand">
            <?php if (!empty($branding['logo_url'])): ?>
                <img src="<?php echo esc_url($branding['logo_url']); ?>" alt="<?php echo esc_attr($company); ?>">
            <?php else: ?>
                <span class="dot"></span><?php echo esc_html($company); ?>
            <?php endif; ?>
        </div>

        <div class="ww-card">
            <!-- MAIN VIEW -->
            <div class="ww-view active" id="viewMain">
                <div class="ww-h">
                    <h1 id="hTitle"><?php echo esc_html($branding['welcome_text'] ?: 'Welcome back'); ?></h1>
                    <p id="hSub">Sign in to continue to <?php echo esc_html($company); ?></p>
                </div>

                <div class="ww-pills" data-active="login">
                    <span class="ww-pill-ind"></span>
                    <button type="button" class="ww-pill active" data-panel="panelLogin">Login</button>
                    <button type="button" class="ww-pill" data-panel="panelRegister">Register</button>
                </div>

                <div id="msg" class="ww-msg"></div>

                <!-- LOGIN PANEL -->
                <div class="ww-panel active" id="panelLogin">
                    <form id="formLogin" novalidate>
                        <div class="ww-field">
                            <label>Email or WhatsApp Number</label>
                            <div class="ww-input-wrap">
                                <span class="ww-input-icon" id="loginIcon">
                                    <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                </span>
                                <input type="text" id="loginInput" class="ww-input" placeholder="you@example.com or 98765 43210" required autocomplete="username" inputmode="email">
                            </div>
                            <div class="ww-hint" id="loginHint"></div>
                        </div>
                        <button type="submit" class="ww-btn ww-btn-primary" id="btnContinue">
                            Continue
                            <span class="arrow">→</span>
                        </button>
                    </form>

                    <?php if ($methods['qr'] && !$is_mobile): ?>
                    <div class="ww-qr-hide-mobile">
                        <div class="ww-div">or scan QR</div>
                        <button type="button" class="ww-btn ww-btn-ghost" id="btnShowQR">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                            Scan QR Code to Login
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- REGISTER PANEL -->
                <div class="ww-panel" id="panelRegister">
                    <form id="formRegister" novalidate>
                        <div class="ww-field">
                            <label>Full Name</label>
                            <div class="ww-input-wrap">
                                <span class="ww-input-icon">
                                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                </span>
                                <input type="text" id="regName" class="ww-input" placeholder="John Doe" required autocomplete="name">
                            </div>
                        </div>
                        <div class="ww-field">
                            <label>Email</label>
                            <div class="ww-input-wrap">
                                <span class="ww-input-icon is-email">
                                    <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                </span>
                                <input type="email" id="regEmail" class="ww-input" placeholder="you@example.com" required autocomplete="email" inputmode="email">
                            </div>
                            <div class="ww-hint" id="regEmailHint"></div>
                        </div>
                        <div class="ww-field">
                            <label>WhatsApp Number</label>
                            <div class="ww-phone-row">
                                <div class="ww-cc">+91</div>
                                <input type="tel" id="regPhone" class="ww-input" placeholder="98765 43210" maxlength="12" required autocomplete="tel-national" inputmode="numeric">
                            </div>
                            <div class="ww-hint" id="regPhoneHint"></div>
                        </div>
                        <button type="submit" class="ww-btn ww-btn-primary" id="btnRegister">
                            Create Account
                            <span class="arrow">→</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- METHOD VIEW (step 2) -->
            <div class="ww-view" id="viewMethod">
                <button type="button" class="ww-back" data-goto="viewMain">
                    <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    Back
                </button>

                <div class="ww-h">
                    <h1>How do you want to sign in?</h1>
                    <p>Welcome back, <b id="mGreet">friend</b>. Choose your method:</p>
                </div>

                <div id="msg2" class="ww-msg"></div>

                <div id="choices" style="display:flex; flex-direction:column; gap: 10px;">
                    <!-- filled dynamically -->
                </div>

                <div class="ww-resend" style="margin-top: 16px;">
                    <button type="button" id="btnSwitchUser">Not you? Use a different account</button>
                </div>
            </div>

            <!-- OTP VIEW -->
            <div class="ww-view" id="viewOTP">
                <button type="button" class="ww-back" data-goto="back-otp">
                    <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    Back
                </button>

                <div class="ww-h">
                    <h1>Verify your code</h1>
                </div>
                <div class="ww-otp-meta">
                    <small id="otpChanLbl">We sent a 4-digit code to</small>
                    <b id="otpDest"></b>
                </div>

                <div id="msgOtp" class="ww-msg"></div>

                <form id="formOTP">
                    <div class="ww-otp-digits">
                        <input type="text" class="ww-otp-d" maxlength="1" inputmode="numeric" pattern="[0-9]">
                        <input type="text" class="ww-otp-d" maxlength="1" inputmode="numeric" pattern="[0-9]">
                        <input type="text" class="ww-otp-d" maxlength="1" inputmode="numeric" pattern="[0-9]">
                        <input type="text" class="ww-otp-d" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    </div>
                    <button type="submit" class="ww-btn ww-btn-primary" id="btnVerify" disabled>
                        Verify &amp; Sign In
                        <span class="arrow">→</span>
                    </button>
                </form>

                <div class="ww-resend">
                    <span id="resendCountdown">Resend in <strong>60s</strong></span>
                    <button type="button" id="btnResend" style="display:none;">Resend Code</button>
                </div>
            </div>

            <!-- QR VIEW -->
            <div class="ww-view" id="viewQR">
                <button type="button" class="ww-back" data-goto="viewMain" data-stop-qr="1">
                    <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    Back
                </button>
                <div class="ww-h">
                    <h1>Scan with your phone</h1>
                    <p>Open your camera and point it at the code.</p>
                </div>
                <div class="ww-qr-wrap" id="qrBox">
                    <div class="ww-spin" style="margin:40px auto; color: var(--ink-soft);"></div>
                </div>
                <div class="ww-qr-status" id="qrStatus">Waiting for authorization…</div>
                <div style="height: 14px;"></div>
                <button type="button" class="ww-btn ww-btn-ghost" id="btnRefreshQR">Refresh QR Code</button>
            </div>
        </div>

        <div class="ww-foot">
            <a href="<?php echo esc_url(home_url('/')); ?>">← Return to <?php echo esc_html($company); ?></a>
        </div>
    </div>

    <!-- BIOMETRIC SETUP MODAL -->
    <div class="ww-modal-bg" id="bioModal">
        <div class="ww-modal">
            <div class="ww-modal-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 11v3"/><path d="M12 19c-3 0-5-2-5-5 0-4 2-7 5-7s5 3 5 7"/>
                    <path d="M6 9a6 6 0 0 1 12 0"/><path d="M3 12c0-5 4-9 9-9"/>
                </svg>
            </div>
            <h2>Skip codes next time</h2>
            <p>Enable biometric login and sign in to <?php echo esc_html($company); ?> instantly with Face ID, Touch ID, or your device passkey — no more OTPs to type.</p>

            <div class="perks">
                <div class="perk">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20,6 9,17 4,12"/></svg>
                    One-tap sign in with Face ID / Touch ID
                </div>
                <div class="perk">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20,6 9,17 4,12"/></svg>
                    No more waiting for OTP codes
                </div>
                <div class="perk">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20,6 9,17 4,12"/></svg>
                    End-to-end encrypted, device-bound
                </div>
            </div>

            <button type="button" class="ww-btn ww-btn-primary" id="bioEnable">
                Enable Biometric Login
                <span class="arrow">→</span>
            </button>
            <button type="button" class="skip" id="bioSkip">Skip for now</button>
            <label class="never">
                <input type="checkbox" id="bioNever">
                <span>Don't ask me again</span>
            </label>
        </div>
    </div>

    <script>
    (function(){
        'use strict';

        var API      = '<?php echo esc_url_raw(rest_url('ww-auth/v1/')); ?>';
        var NONCE    = '<?php echo wp_create_nonce('wp_rest'); ?>';
        var REDIRECT = '<?php echo esc_js($redirect_to); ?>' || '<?php echo esc_js(home_url('/my-account/')); ?>';
        var CFG = {
            whatsapp: <?php echo $methods['whatsapp'] ? 'true' : 'false'; ?>,
            email:    <?php echo $methods['email']    ? 'true' : 'false'; ?>,
            passkeys: <?php echo $methods['passkeys'] ? 'true' : 'false'; ?>
        };

        /* ---------- helpers ---------- */
        var qs  = function(s, el){ return (el||document).querySelector(s); };
        var qsa = function(s, el){ return Array.prototype.slice.call((el||document).querySelectorAll(s)); };

        function api(path, body, method){
            var opts = { method: method || 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE } };
            if (method !== 'GET') opts.body = JSON.stringify(body || {});
            return fetch(API + path, opts).then(function(r){ return r.json(); });
        }

        function show(id, msg, kind){
            var el = qs('#' + id);
            if (!el) return;
            el.textContent = msg || '';
            el.className = 'ww-msg' + (msg ? (' show ' + (kind || 'error')) : '');
        }
        function clearMsgs(){ ['msg','msg2','msgOtp'].forEach(function(id){ show(id, ''); }); }

        function setLoading(btn, on){
            if (on) {
                btn.dataset._label = btn.innerHTML;
                btn.innerHTML = '<span class="ww-spin"></span> Please wait…';
                btn.disabled = true;
            } else {
                btn.innerHTML = btn.dataset._label || btn.innerHTML;
                btn.disabled = false;
            }
        }

        function showView(id){
            qsa('.ww-view').forEach(function(v){ v.classList.remove('active'); });
            var target = qs('#' + id);
            if (target) target.classList.add('active');
            clearMsgs();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        /* ---------- pill tabs ---------- */
        var pills = qs('.ww-pills');
        qsa('.ww-pill').forEach(function(btn){
            btn.addEventListener('click', function(){
                qsa('.ww-pill').forEach(function(p){ p.classList.remove('active'); });
                qsa('.ww-panel').forEach(function(p){ p.classList.remove('active'); });
                btn.classList.add('active');
                var panelId = btn.getAttribute('data-panel');
                qs('#' + panelId).classList.add('active');
                pills.setAttribute('data-active', panelId === 'panelRegister' ? 'register' : 'login');

                if (panelId === 'panelRegister') {
                    qs('#hTitle').textContent = 'Create your account';
                    qs('#hSub').textContent   = 'Set up secure access in seconds';
                } else {
                    qs('#hTitle').textContent = '<?php echo esc_js($branding['welcome_text'] ?: 'Welcome back'); ?>';
                    qs('#hSub').textContent   = 'Sign in to continue to <?php echo esc_js($company); ?>';
                }
                clearMsgs();
            });
        });

        /* ---------- back buttons ---------- */
        qsa('[data-goto]').forEach(function(el){
            el.addEventListener('click', function(){
                var go = el.getAttribute('data-goto');
                if (el.getAttribute('data-stop-qr')) stopQR();
                if (go === 'back-otp') { go = lookupState.userId ? 'viewMethod' : 'viewMain'; }
                showView(go);
            });
        });

        /* ---------- input type detect + dynamic icon ---------- */
        var loginInput = qs('#loginInput');
        var loginIcon  = qs('#loginIcon');

        var MAIL_SVG = '<svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>';
        var WA_SVG   = '<svg viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';

        function detectType(val){
            val = (val || '').trim();
            if (!val) return 'empty';
            if (val.indexOf('@') > -1) return 'email';
            // First character is digit or '+' => phone context
            if (/^[+]?\d/.test(val)) return 'phone';
            return 'text';
        }

        function updateLoginIcon(){
            var t = detectType(loginInput.value);
            loginIcon.classList.remove('is-phone','is-email');
            if (t === 'phone') { loginIcon.innerHTML = WA_SVG; loginIcon.classList.add('is-phone'); }
            else if (t === 'email') { loginIcon.innerHTML = MAIL_SVG; loginIcon.classList.add('is-email'); }
            else { loginIcon.innerHTML = MAIL_SVG; } /* default neutral */
        }
        loginInput.addEventListener('input', updateLoginIcon);
        updateLoginIcon();

        /* ---------- validators ---------- */
        function isEmail(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
        function isPhone10(v){ return /^\d{10}$/.test(String(v).replace(/\D/g,'')); }

        /* ---------- register field hints ---------- */
        qs('#regEmail').addEventListener('input', function(){
            var h = qs('#regEmailHint'); var v = this.value.trim();
            if (!v) { h.className = 'ww-hint'; h.textContent=''; return; }
            if (isEmail(v)) { h.className = 'ww-hint show valid'; h.textContent='✓ Looks good'; }
            else { h.className = 'ww-hint show invalid'; h.textContent='✗ Enter a valid email'; }
        });
        qs('#regPhone').addEventListener('input', function(){
            this.value = this.value.replace(/\D/g,'').slice(0,10);
            var h = qs('#regPhoneHint'); var d = this.value;
            if (!d) { h.className = 'ww-hint'; h.textContent=''; return; }
            if (d.length === 10) { h.className = 'ww-hint show valid'; h.textContent='✓ Valid 10-digit number'; }
            else { h.className = 'ww-hint show invalid'; h.textContent='Enter 10 digits (' + d.length + '/10)'; }
        });

        /* ---------- lookup state ---------- */
        var lookupState = { identifier: '', type: '', userId: 0, email: '', phone: '', hasPk: false };

        /* ---------- login continue → lookup → method ---------- */
        qs('#formLogin').addEventListener('submit', function(e){
            e.preventDefault();
            var raw = loginInput.value.trim();
            var type = detectType(raw);
            var btn = qs('#btnContinue');

            if (type === 'email') {
                if (!isEmail(raw)) { show('msg','Please enter a valid email'); return; }
            } else if (type === 'phone') {
                var digits = raw.replace(/\D/g,'');
                if (digits.length < 10) { show('msg','Enter at least a 10-digit phone'); return; }
            } else {
                show('msg','Enter an email or WhatsApp number'); return;
            }

            setLoading(btn, true);
            clearMsgs();

            api('lookup', { identifier: raw, type: type }).then(function(r){
                setLoading(btn, false);
                if (!r || !r.exists) {
                    // Switch to register, pre-fill
                    show('msg','We couldn\'t find an account. Please register.', 'error');
                    qs('[data-panel="panelRegister"]').click();
                    if (type === 'email') qs('#regEmail').value = raw;
                    else qs('#regPhone').value = raw.replace(/\D/g,'').slice(-10);
                    return;
                }
                lookupState = {
                    identifier: raw, type: type,
                    userId: r.user_id,
                    maskedEmail: r.masked_email || '',
                    maskedPhone: r.masked_phone || '',
                    canEmail: !!r.can_email,
                    canWhatsApp: !!r.can_whatsapp,
                    hasPk: !!r.has_passkey,
                    name: r.display_name || 'friend'
                };
                qs('#mGreet').textContent = lookupState.name;
                renderChoices();
                showView('viewMethod');
            }).catch(function(){
                setLoading(btn, false);
                show('msg','Network error. Try again.');
            });
        });

        /* ---------- render method choices ---------- */
        function renderChoices(){
            var c = qs('#choices'); c.innerHTML = '';

            // Biometric (only if user has passkey + platform supports WebAuthn)
            if (CFG.passkeys && lookupState.hasPk && window.PublicKeyCredential) {
                c.appendChild(choiceEl('primary',
                    '<svg viewBox="0 0 24 24"><path d="M12 11v3"/><path d="M12 19c-3 0-5-2-5-5 0-4 2-7 5-7s5 3 5 7"/><path d="M6 9a6 6 0 0 1 12 0"/><path d="M3 12c0-5 4-9 9-9"/></svg>',
                    '<b>Biometric Login <span class="ww-badge">Fastest</span></b><span>Use Face ID / Touch ID / Passkey</span>',
                    function(){ startPasskeyLogin(); }
                ));
            }

            if (lookupState.canEmail && lookupState.maskedEmail) {
                c.appendChild(choiceEl('email',
                    '<svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
                    '<b>Get sign-in code on Email</b><span>OTP sent to ' + lookupState.maskedEmail + '</span>',
                    function(){ sendEmailCode(); }
                ));
            }

            if (lookupState.canWhatsApp && lookupState.maskedPhone) {
                c.appendChild(choiceEl('whatsapp',
                    '<svg viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>',
                    '<b>Get sign-in code on WhatsApp</b><span>OTP sent to ' + lookupState.maskedPhone + '</span>',
                    function(){ sendWhatsAppCode(); }
                ));
            }

            if (!c.children.length) {
                var warn = document.createElement('div');
                warn.className = 'ww-msg show error';
                warn.textContent = 'No sign-in method is currently available. Please contact support.';
                c.appendChild(warn);
            }
        }

        function choiceEl(kind, iconSVG, inner, onClick){
            var b = document.createElement('button');
            b.type='button';
            b.className='ww-choice ' + kind;
            b.innerHTML = '<span class="icon">' + iconSVG + '</span><span class="lines">' + inner + '</span>'
                       + '<span class="chev"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>';
            b.addEventListener('click', onClick);
            return b;
        }

        /* ---------- register ---------- */
        qs('#formRegister').addEventListener('submit', function(e){
            e.preventDefault();
            var name = qs('#regName').value.trim();
            var email = qs('#regEmail').value.trim();
            var phone = qs('#regPhone').value.replace(/\D/g,'');
            var btn = qs('#btnRegister');

            if (!name) { show('msg','Please enter your name'); return; }
            if (!isEmail(email)) { show('msg','Enter a valid email'); return; }
            if (!isPhone10(phone)) { show('msg','Enter a valid 10-digit WhatsApp number'); return; }

            setLoading(btn, true); clearMsgs();
            api('email/send', { email: email, name: name, phone: '+91' + phone }).then(function(r){
                setLoading(btn, false);
                if (!r || !r.success) { show('msg', (r && r.error) || 'Failed to send code'); return; }
                otpContext = { kind: 'email', dest: email, destLabel: email, userId: 0, isNew: true };
                enterOTPView();
            }).catch(function(){ setLoading(btn, false); show('msg','Network error'); });
        });

        /* ---------- switch user ---------- */
        qs('#btnSwitchUser').addEventListener('click', function(){
            loginInput.value = ''; updateLoginIcon();
            showView('viewMain');
        });

        /* ---------- OTP context + transitions ---------- */
        var otpContext = { kind:'email', dest:'', destLabel:'', userId:0, isNew:false };
        var resendInt = null;

        function enterOTPView(){
            qs('#otpDest').textContent = otpContext.destLabel || otpContext.dest;
            qs('#otpChanLbl').textContent = otpContext.kind === 'whatsapp'
                ? 'We sent a 4-digit code on WhatsApp to'
                : 'We sent a 4-digit code by email to';
            qsa('.ww-otp-d').forEach(function(d){ d.value=''; d.classList.remove('filled'); });
            qs('#btnVerify').disabled = true;
            showView('viewOTP');
            startResend();
            setTimeout(function(){ qs('.ww-otp-d').focus(); }, 150);
        }

        function sendEmailCode(){
            clearMsgs();
            api('email/send', { user_id: lookupState.userId }).then(function(r){
                if (!r || !r.success) { show('msg2', (r && r.error) || 'Failed to send'); return; }
                otpContext = { kind:'email', dest: lookupState.maskedEmail, destLabel: lookupState.maskedEmail, userId: lookupState.userId, isNew:false };
                enterOTPView();
            }).catch(function(){ show('msg2','Network error'); });
        }

        function sendWhatsAppCode(){
            clearMsgs();
            api('whatsapp/send', { phone: lookupState.identifier }).then(function(r){
                if (!r || !r.success) { show('msg2', (r && r.error) || 'Failed to send'); return; }
                otpContext = { kind:'whatsapp', dest: lookupState.identifier, destLabel: lookupState.maskedPhone, userId: lookupState.userId, isNew:false };
                enterOTPView();
            }).catch(function(){ show('msg2','Network error'); });
        }

        /* ---------- OTP digit UX ---------- */
        var otpInputs = qsa('.ww-otp-d');
        otpInputs.forEach(function(inp, i){
            inp.addEventListener('input', function(){
                this.value = this.value.replace(/\D/g,'');
                if (this.value) {
                    this.classList.add('filled');
                    if (i < otpInputs.length - 1) otpInputs[i+1].focus();
                } else { this.classList.remove('filled'); }
                qs('#btnVerify').disabled = otpInputs.map(function(x){return x.value;}).join('').length !== 4;
            });
            inp.addEventListener('keydown', function(e){
                if (e.key === 'Backspace' && !this.value && i > 0) otpInputs[i-1].focus();
            });
            inp.addEventListener('paste', function(e){
                e.preventDefault();
                var t = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'').slice(0,4);
                t.split('').forEach(function(d, idx){ if (otpInputs[idx]) { otpInputs[idx].value = d; otpInputs[idx].classList.add('filled'); } });
                qs('#btnVerify').disabled = t.length !== 4;
                if (t.length === 4) otpInputs[3].focus();
            });
        });

        qs('#formOTP').addEventListener('submit', function(e){
            e.preventDefault();
            var code = otpInputs.map(function(x){return x.value;}).join('');
            var btn = qs('#btnVerify');
            setLoading(btn, true); clearMsgs();

            var path, body;
            if (otpContext.kind === 'whatsapp') {
                path = 'whatsapp/verify'; body = { phone: otpContext.dest, otp: code };
            } else {
                // For existing users (userId known), server will still resolve via email.
                // 'dest' here is either the original user-typed email (new signup) or masked email —
                // the email/verify endpoint needs the REAL email. Use real email from identifier if known.
                var realEmail = otpContext.isNew ? otpContext.dest : (lookupState.type === 'email' ? lookupState.identifier : '');
                if (!realEmail && otpContext.userId) {
                    // Fallback: ask server using user_id — but our endpoint only accepts email.
                    // Simple path: include user_id so server can resolve.
                }
                body = { email: realEmail, otp: code, user_id: otpContext.userId || 0 };
                path = 'email/verify';
            }

            api(path, body).then(function(r){
                setLoading(btn, false);
                if (!r || !r.success) {
                    show('msgOtp', (r && r.error) || 'Invalid code');
                    otpInputs.forEach(function(x){ x.value=''; x.classList.remove('filled'); });
                    qs('#btnVerify').disabled = true;
                    otpInputs[0].focus();
                    return;
                }
                handlePostLogin(r);
            }).catch(function(){ setLoading(btn,false); show('msgOtp','Network error'); });
        });

        function startResend(){
            var n = 60;
            var lbl = qs('#resendCountdown'); var btn = qs('#btnResend');
            lbl.style.display = 'inline'; btn.style.display = 'none';
            if (resendInt) clearInterval(resendInt);
            resendInt = setInterval(function(){
                n--;
                lbl.innerHTML = 'Resend in <strong>' + n + 's</strong>';
                if (n <= 0) { clearInterval(resendInt); lbl.style.display='none'; btn.style.display='inline'; }
            }, 1000);
        }

        qs('#btnResend').addEventListener('click', function(){
            this.disabled = true;
            var path = otpContext.kind === 'whatsapp' ? 'whatsapp/send' : 'email/send';
            var body = otpContext.kind === 'whatsapp' ? { phone: otpContext.dest } : (otpContext.userId ? { user_id: otpContext.userId } : { email: otpContext.dest });
            var self = this;
            api(path, body).then(function(r){
                if (r && r.success) { show('msgOtp','Code resent!', 'success'); startResend(); }
                else show('msgOtp', (r && r.error) || 'Failed to resend');
                self.disabled = false;
            });
        });

        /* ---------- QR ---------- */
        var qrInt = null; var qrSess = null;
        qs('#btnShowQR')?.addEventListener('click', function(){ showView('viewQR'); genQR(); });
        qs('#btnRefreshQR')?.addEventListener('click', function(){ stopQR(); genQR(); });
        function genQR(){
            var box = qs('#qrBox');
            box.innerHTML = '<div class="ww-spin" style="margin:40px auto; color: var(--ink-soft);"></div>';
            qs('#qrStatus').textContent = 'Generating QR code…';
            api('qr/generate', {}).then(function(r){
                if (!r || !r.success) { box.innerHTML = '<p style="color:#b91c1c;">Failed to generate QR</p>'; return; }
                qrSess = r.session_id;
                var url = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(r.qr_data);
                box.innerHTML = '<div class="box"><img src="'+ url +'" alt="QR"></div>';
                qs('#qrStatus').textContent = 'Waiting for authorization on your phone…';
                if (qrInt) clearInterval(qrInt);
                qrInt = setInterval(pollQR, 3000);
            });
        }
        function pollQR(){
            fetch(API + 'qr/poll?session_id=' + qrSess, { headers: { 'X-WP-Nonce': NONCE } })
                .then(function(r){ return r.json(); })
                .then(function(r){
                    if (r.status === 'authorized') { stopQR(); qs('#qrStatus').textContent = 'Authorized! Redirecting…'; setTimeout(function(){ window.location.href = r.redirect || REDIRECT; }, 400); }
                    else if (r.status === 'expired') { stopQR(); qs('#qrStatus').textContent = 'QR expired. Please refresh.'; }
                });
        }
        function stopQR(){ if (qrInt) { clearInterval(qrInt); qrInt = null; } }
        window.stopQR = stopQR;

        /* ---------- Passkey login (post-lookup) ---------- */
        function b64urlToBuf(s){
            s = s.replace(/-/g,'+').replace(/_/g,'/'); while (s.length % 4) s += '=';
            var bin = atob(s); var bytes = new Uint8Array(bin.length);
            for (var i=0;i<bin.length;i++) bytes[i] = bin.charCodeAt(i);
            return bytes;
        }
        function bufToB64url(buf){
            var bytes = new Uint8Array(buf); var s = '';
            for (var i=0;i<bytes.length;i++) s += String.fromCharCode(bytes[i]);
            return btoa(s).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');
        }

        function startPasskeyLogin(){
            clearMsgs();
            if (!window.PublicKeyCredential) { show('msg2','Biometrics not supported on this browser.'); return; }
            api('passkeys/login/options', { user_id: lookupState.userId }).then(function(r){
                if (!r || !r.success) { show('msg2', (r && r.error) || 'Unable to start biometric login.'); return; }
                var o = r.options;
                var publicKey = {
                    challenge: b64urlToBuf(o.challenge),
                    timeout: o.timeout,
                    rpId: o.rpId,
                    userVerification: o.userVerification
                };
                if (o.allowCredentials && o.allowCredentials.length) {
                    publicKey.allowCredentials = o.allowCredentials.map(function(c){ return { type: c.type, id: b64urlToBuf(c.id), transports: c.transports }; });
                }
                return navigator.credentials.get({ publicKey: publicKey }).then(function(cred){
                    return api('passkeys/login/verify', {
                        id: cred.id,
                        rawId: bufToB64url(cred.rawId),
                        type: cred.type,
                        session_id: o.session_id,
                        response: {
                            clientDataJSON: bufToB64url(cred.response.clientDataJSON),
                            authenticatorData: bufToB64url(cred.response.authenticatorData),
                            signature: bufToB64url(cred.response.signature)
                        }
                    });
                }).then(function(v){
                    if (!v || !v.success) { show('msg2', (v && v.error) || 'Biometric verification failed.'); return; }
                    // successful — no need to show setup modal again
                    try { localStorage.setItem('ww_auth_bio_done', '1'); } catch(e){}
                    window.location.href = v.redirect || REDIRECT;
                });
            }).catch(function(e){
                if (e && e.name === 'NotAllowedError') show('msg2','Biometric cancelled.');
                else show('msg2','Biometric login failed. Use an OTP instead.');
            });
        }

        /* ---------- Post-login: maybe show biometric setup modal ---------- */
        function handlePostLogin(r){
            var user = r.user || {};
            var offer = CFG.passkeys && window.PublicKeyCredential && !user.has_passkey;
            var dismissed = false;
            try { dismissed = localStorage.getItem('ww_auth_bio_never') === '1' || localStorage.getItem('ww_auth_bio_done') === '1'; } catch(e){}

            if (offer && !dismissed) {
                // Stash redirect + open modal
                window._wwRedirect = r.redirect || REDIRECT;
                qs('#bioModal').classList.add('show');
                return;
            }
            window.location.href = r.redirect || REDIRECT;
        }

        /* ---------- Biometric setup (post-login) ---------- */
        qs('#bioSkip').addEventListener('click', function(){
            try { if (qs('#bioNever').checked) localStorage.setItem('ww_auth_bio_never', '1'); } catch(e){}
            qs('#bioModal').classList.remove('show');
            window.location.href = window._wwRedirect || REDIRECT;
        });

        qs('#bioEnable').addEventListener('click', function(){
            var btn = this; setLoading(btn, true);
            api('passkeys/register/options', {}).then(function(r){
                if (!r || !r.success) { setLoading(btn,false); show('msg','Biometric setup failed: ' + ((r && r.error) || 'unknown')); return; }
                var o = r.options;
                var publicKey = {
                    challenge: b64urlToBuf(o.challenge),
                    rp: o.rp,
                    user: {
                        id: b64urlToBuf(o.user.id),
                        name: o.user.name,
                        displayName: o.user.displayName
                    },
                    pubKeyCredParams: o.pubKeyCredParams,
                    timeout: o.timeout,
                    authenticatorSelection: o.authenticatorSelection,
                    attestation: o.attestation,
                    excludeCredentials: (o.excludeCredentials || []).map(function(c){ return { type: c.type, id: b64urlToBuf(c.id), transports: c.transports }; })
                };
                return navigator.credentials.create({ publicKey: publicKey }).then(function(cred){
                    return api('passkeys/register/verify', {
                        id: cred.id,
                        rawId: bufToB64url(cred.rawId),
                        type: cred.type,
                        response: {
                            clientDataJSON: bufToB64url(cred.response.clientDataJSON),
                            attestationObject: bufToB64url(cred.response.attestationObject)
                        }
                    });
                }).then(function(v){
                    setLoading(btn, false);
                    if (!v || !v.success) {
                        alert('Setup failed: ' + ((v && v.error) || 'unknown'));
                        return;
                    }
                    try { localStorage.setItem('ww_auth_bio_done', '1'); } catch(e){}
                    qs('#bioModal').classList.remove('show');
                    window.location.href = window._wwRedirect || REDIRECT;
                });
            }).catch(function(e){
                setLoading(btn, false);
                if (e && e.name === 'NotAllowedError') alert('Biometric setup was cancelled.');
                else alert('Biometric setup failed. You can try again later from your account.');
            });
        });

        /* ---------- Magic-link auto-redeem ---------- */
        (function(){
            try {
                var p = new URLSearchParams(window.location.search);
                var m = p.get('ww_magic'); var em = p.get('email');
                if (!m || !em) return;
                show('msg','Signing you in securely…', 'success');
                fetch(API + 'magic-link?token=' + encodeURIComponent(m) + '&email=' + encodeURIComponent(em), { headers: { 'X-WP-Nonce': NONCE } })
                    .then(function(r){ return r.json(); })
                    .then(function(r){
                        if (r && r.success) { show('msg','Signed in! Redirecting…','success'); setTimeout(function(){ window.location.href = r.redirect || REDIRECT; }, 400); }
                        else show('msg', (r && r.error) || 'Magic link expired or invalid.');
                    });
            } catch(e){}
        })();
    })();
    </script>
</body>
</html>
