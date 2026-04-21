<?php
/**
 * Secure Login Page Template — Dark / Neon Edition (v1.3.0)
 *
 * Flow (simplified to single-input auto-detect):
 *   1. viewMain     — Single input. Icon morphs email <-> WhatsApp as the user types.
 *                     On submit we call /lookup → straight to OTP (email or WhatsApp).
 *   2. viewRegister — For unknown identifiers; collects name + email + WhatsApp.
 *   3. viewOTP      — 4 circular boxes. Resend timer. Continue.
 *   4. view2FA      — Admin 2FA (when coming from wp-login password auth).
 *   5. bioModal     — Post-login biometric setup. Uses a short-lived ww_token
 *                     instead of cookie-based auth so hosts with SameSite / REST
 *                     quirks still work 100%.
 *
 * If the user is already logged in (detected via /me), we short-circuit to the
 * redirect target rather than showing the form again.
 *
 * @package WeeWoo_Auth_Pro
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$branding    = WW_Frontend::get_branding();
$methods     = WW_Frontend::get_auth_methods();
$turnstile   = WW_Turnstile::instance();
$redirect_to = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : '';
$is_mobile   = wp_is_mobile();
$company     = get_option('ww_auth_company_name', '') ?: get_bloginfo('name');
$primary     = $branding['primary_color'] ?: '#1DD589';
$welcome     = $branding['welcome_text'] ?: 'Welcome back';
$ww_2fa      = isset($_GET['ww_2fa']) ? sanitize_text_field((string) $_GET['ww_2fa']) : '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#050608">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="Referrer-Policy" content="same-origin">
    <title><?php echo esc_html($branding['page_title']); ?> — <?php echo esc_html($company); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: <?php echo esc_attr($primary); ?>;
            --primary-ink: #001210;
            --bg-0: #050608;
            --bg-1: #0b0d12;
            --bg-2: #141922;
            --line: rgba(255,255,255,0.08);
            --line-2: rgba(255,255,255,0.14);
            --ink: #ffffff;
            --ink-2: #d1d5db;
            --ink-3: #9aa3b2;
            --ink-4: #5b6473;
            --danger: #ff5a5a;
            --chip: #141922;
            --chip-2: #1c2230;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { height: 100%; width: 100%; }
        button, input, select, textarea { font-family: inherit; }
        img { max-width: 100%; height: auto; display: block; }

        body.ww-page {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-weight: 500;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            min-height: 100vh;
            min-height: 100svh;
            color: var(--ink);
            background: var(--bg-0);
            background-image:
                radial-gradient(1200px 520px at 50% -10%, rgba(29,213,137,0.14), transparent 60%),
                radial-gradient(900px 460px at 50% 110%, rgba(29,213,137,0.10), transparent 55%),
                linear-gradient(180deg, var(--bg-0) 0%, var(--bg-1) 55%, var(--bg-0) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 36px 20px calc(36px + env(safe-area-inset-bottom));
            position: relative;
            overflow-x: hidden;
        }
        body.ww-page::before {
            content: "";
            position: fixed; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
            background-size: 48px 48px;
            pointer-events: none;
            z-index: 0;
            mask-image: radial-gradient(ellipse at center, black 30%, transparent 80%);
            -webkit-mask-image: radial-gradient(ellipse at center, black 30%, transparent 80%);
        }

        .ww-stage {
            width: 100%;
            max-width: 440px;
            position: relative;
            z-index: 1;
            margin: auto;
        }

        /* Centered brand header */
        .ww-brand {
            text-align: center;
            margin-bottom: 30px;
            font-weight: 900;
            font-size: 22px;
            letter-spacing: -0.025em;
            color: var(--ink);
        }
        .ww-brand .pill {
            display: inline-block;
            padding: 3px 10px;
            margin-left: 6px;
            background: var(--primary);
            color: var(--primary-ink);
            border-radius: 6px;
            font-weight: 900;
            letter-spacing: -0.01em;
            vertical-align: 2px;
        }
        .ww-brand img { max-height: 36px; width: auto; margin: 0 auto; }

        /* Heading */
        .ww-h { margin-bottom: 24px; text-align: center; }
        .ww-h h1 {
            font-size: 34px;
            font-weight: 800;
            letter-spacing: -0.025em;
            line-height: 1.08;
        }
        .ww-h h1 .ac { color: var(--primary); }
        .ww-h p {
            margin: 14px auto 0;
            max-width: 36ch;
            font-size: 14.5px;
            color: var(--ink-3);
            line-height: 1.55;
            font-weight: 500;
        }

        /* Views */
        .ww-view { display: none; animation: slideIn 0.35s ease both; }
        .ww-view.active { display: block; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        /* Field */
        .ww-field { margin-bottom: 18px; }
        .ww-field > label {
            display: block;
            font-size: 12.5px;
            font-weight: 800;
            color: var(--ink-2);
            margin-bottom: 8px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .ww-input-wrap {
            position: relative;
            display: flex; align-items: center;
            padding: 0 16px;
            background: var(--chip);
            border: 1.5px solid var(--line);
            border-radius: 14px;
            min-height: 58px;
            transition: border-color .2s ease, background-color .2s ease;
        }
        .ww-input-wrap:focus-within { border-color: var(--primary); background: var(--chip-2); }
        .ww-input-wrap .icn {
            width: 20px; height: 20px; flex-shrink: 0;
            color: var(--ink-3);
            margin-right: 12px;
            transition: color .2s ease, transform .18s ease;
        }
        .ww-input-wrap .icn svg { width: 100%; height: 100%; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .ww-input-wrap.is-email .icn { color: var(--primary); }
        .ww-input-wrap.is-phone .icn { color: #25D366; }
        .ww-input-wrap.is-phone .icn svg { fill: #25D366; stroke: #25D366; }

        .ww-input {
            flex: 1; min-width: 0;
            width: 100%;
            border: 0; outline: none; background: transparent;
            color: var(--ink);
            font-size: 15px;
            font-weight: 600;
            letter-spacing: -0.01em;
            padding: 17px 0;
        }
        .ww-input::placeholder { color: var(--ink-4); font-weight: 500; }

        /* Phone row — grid prevents mobile overflow */
        .ww-phone-row { display: grid; grid-template-columns: 78px 1fr; gap: 10px; }
        .ww-cc {
            height: 58px;
            display: flex; align-items: center; justify-content: center;
            background: var(--chip);
            border: 1.5px solid var(--line);
            border-radius: 14px;
            color: var(--ink);
            font-weight: 800;
            font-size: 15px;
        }

        .ww-hint {
            display: none;
            margin-top: 8px;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--ink-3);
        }
        .ww-hint.show { display: block; }
        .ww-hint.valid { color: var(--primary); }
        .ww-hint.invalid { color: var(--danger); }

        /* Buttons */
        .ww-btn {
            display: inline-flex; align-items: center; justify-content: center;
            gap: 10px;
            width: 100%;
            min-height: 58px;
            padding: 18px 24px;
            border: 0;
            border-radius: 999px;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -0.01em;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .25s ease, background-color .2s ease;
            margin-top: 6px;
        }
        .ww-btn:disabled { opacity: .55; cursor: not-allowed; transform: none !important; }
        .ww-btn-primary {
            background: var(--primary);
            color: var(--primary-ink);
            box-shadow: 0 14px 30px -12px rgba(29,213,137,0.45);
        }
        .ww-btn-primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 18px 36px -12px rgba(29,213,137,0.6); }
        .ww-btn-primary .arrow { transition: transform .2s ease; }
        .ww-btn-primary:hover:not(:disabled) .arrow { transform: translateX(4px); }

        .ww-btn-ghost {
            background: transparent;
            color: var(--ink);
            border: 1.5px solid var(--line-2);
        }
        .ww-btn-ghost:hover:not(:disabled) { background: var(--chip); }

        /* Link row */
        .ww-linkrow {
            margin-top: 22px;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            color: var(--ink-3);
        }
        .ww-linkrow a, .ww-linkrow button {
            background: none; border: 0; padding: 0;
            color: var(--primary);
            font: inherit; font-weight: 800;
            text-decoration: none; cursor: pointer;
        }
        .ww-linkrow a:hover, .ww-linkrow button:hover { text-decoration: underline; }

        /* Back */
        .ww-back {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--chip);
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 10px 14px;
            color: var(--ink-2);
            font-size: 13px; font-weight: 700;
            cursor: pointer;
            margin-bottom: 18px;
        }
        .ww-back:hover { background: var(--chip-2); color: var(--ink); }
        .ww-back svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }

        /* Message */
        .ww-msg {
            display: none; align-items: center; gap: 10px;
            padding: 13px 14px;
            border-radius: 12px;
            font-size: 13.5px;
            font-weight: 700;
            margin-bottom: 16px;
        }
        .ww-msg.show { display: flex; }
        .ww-msg.error   { background: rgba(255,90,90,0.1); color: #ffbcbc; border: 1px solid rgba(255,90,90,0.25); }
        .ww-msg.success { background: rgba(29,213,137,0.1); color: var(--primary); border: 1px solid rgba(29,213,137,0.3); }

        /* Spinner */
        .ww-spin { width: 18px; height: 18px; border: 2px solid currentColor; border-top-color: transparent; border-radius: 50%; animation: spin .85s linear infinite; display: inline-block; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* OTP — circular boxes */
        .ww-otp-dest { text-align: center; color: var(--ink-3); margin: 4px 0 22px; font-size: 14px; line-height: 1.6; font-weight: 500; }
        .ww-otp-dest b { color: var(--ink); font-weight: 800; }
        .ww-otp-circles {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin: 24px 0;
            padding: 0 8px;
        }
        .ww-otp-circles input {
            width: 100%;
            aspect-ratio: 1 / 1;
            max-width: 72px;
            justify-self: center;
            text-align: center;
            border-radius: 50%;
            background: transparent;
            color: var(--ink);
            font-size: 26px;
            font-weight: 900;
            letter-spacing: -0.01em;
            border: 1.5px solid var(--line-2);
            outline: none;
            transition: border-color .2s ease, transform .2s ease, background-color .2s ease;
        }
        .ww-otp-circles input:focus { border-color: var(--primary); background: rgba(29,213,137,0.08); transform: scale(1.04); }
        .ww-otp-circles input.filled { border-color: var(--primary); color: var(--primary); }

        .ww-resend { text-align: center; margin: 4px 0 22px; font-size: 13.5px; font-weight: 700; }
        .ww-resend #rcText { color: var(--ink-3); font-weight: 600; }
        .ww-resend button { background: none; border: 0; padding: 0; color: var(--primary); font: inherit; font-weight: 800; cursor: pointer; }
        .ww-resend button:hover { text-decoration: underline; }

        /* Signed-in banner */
        .ww-signed {
            display: flex; align-items: center; gap: 14px;
            padding: 16px;
            background: var(--chip);
            border: 1px solid var(--line-2);
            border-radius: 18px;
            margin-bottom: 16px;
        }
        .ww-signed .av {
            width: 46px; height: 46px;
            border-radius: 50%;
            background: var(--primary);
            color: var(--primary-ink);
            display: flex; align-items: center; justify-content: center;
            font-weight: 900; font-size: 18px;
        }
        .ww-signed .meta { flex: 1; min-width: 0; }
        .ww-signed .meta b { display: block; font-weight: 800; font-size: 15px; }
        .ww-signed .meta span { display: block; color: var(--ink-3); font-size: 13px; font-weight: 500; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* 2FA channel selector */
        .ww-channels { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; }
        .ww-chan {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            min-height: 50px;
            background: var(--chip);
            border: 1.5px solid var(--line);
            border-radius: 14px;
            color: var(--ink);
            font: 700 14px inherit;
            cursor: pointer;
        }
        .ww-chan:hover { background: var(--chip-2); }
        .ww-chan.active { border-color: var(--primary); color: var(--primary); }
        .ww-chan svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2; }

        /* Biometric modal */
        .ww-modal-bg {
            position: fixed; inset: 0; z-index: 1000;
            background: rgba(0,0,0,0.78);
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            display: none; align-items: center; justify-content: center;
            padding: 20px;
        }
        .ww-modal-bg.show { display: flex; animation: fade .25s ease; }
        @keyframes fade { from { opacity: 0; } to { opacity: 1; } }
        .ww-modal {
            width: 100%; max-width: 380px;
            background: var(--bg-2);
            border: 1px solid var(--line-2);
            border-radius: 28px;
            padding: 28px 24px 22px;
            text-align: center;
            box-shadow: 0 40px 80px -20px rgba(0,0,0,0.8);
        }
        .ww-modal .m-icon {
            width: 64px; height: 64px; margin: 0 auto 16px;
            border-radius: 18px;
            background: var(--primary);
            color: var(--primary-ink);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 18px 36px -12px rgba(29,213,137,0.5);
        }
        .ww-modal .m-icon svg { width: 30px; height: 30px; stroke: currentColor; fill: none; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
        .ww-modal h2 { font-size: 20px; font-weight: 900; letter-spacing: -0.02em; }
        .ww-modal p { font-size: 13.5px; color: var(--ink-3); font-weight: 500; margin: 6px 0 16px; line-height: 1.5; }
        .ww-modal .m-err {
            display: none;
            background: rgba(255,90,90,0.12); color: #ffbcbc;
            border: 1px solid rgba(255,90,90,0.3); border-radius: 12px;
            padding: 10px 12px; margin-bottom: 12px;
            font-size: 12.5px; font-weight: 700; text-align: left;
        }
        .ww-modal .m-err.show { display: block; }
        .ww-modal .skip {
            display: block; width: 100%; margin-top: 10px;
            background: none; border: 0;
            color: var(--ink-3); font: 700 14px inherit; cursor: pointer;
            padding: 10px;
        }
        .ww-modal .skip:hover { color: var(--ink); }

        .ww-foot { margin-top: 20px; text-align: center; font-size: 13px; color: var(--ink-4); }
        .ww-foot a { color: var(--ink-3); text-decoration: none; font-weight: 700; }
        .ww-foot a:hover { color: var(--ink); }

        @media (max-width: 420px) {
            body.ww-page { padding: 26px 16px calc(26px + env(safe-area-inset-bottom)); align-items: center; }
            .ww-h h1 { font-size: 28px; }
            .ww-otp-circles { gap: 10px; padding: 0 2px; }
            .ww-otp-circles input { font-size: 22px; }
            .ww-phone-row { grid-template-columns: 72px 1fr; }
            .ww-input-wrap { min-height: 54px; }
            .ww-cc { height: 54px; }
        }
    </style>

    <?php if ($turnstile->is_enabled()): ?>
    <script src="<?php echo esc_url($turnstile->get_script_url()); ?>" async defer></script>
    <?php endif; ?>
</head>
<body class="ww-page">
    <div class="ww-stage">
        <div class="ww-brand">
            <?php if (!empty($branding['logo_url'])): ?>
                <img src="<?php echo esc_url($branding['logo_url']); ?>" alt="<?php echo esc_attr($company); ?>">
            <?php else:
                $parts = preg_split('/\s+/', trim($company), 2);
                $first = $parts[0] ?? $company;
                $rest  = $parts[1] ?? '';
                ?>
                <span><?php echo esc_html($first); ?></span>
                <?php if ($rest): ?><span class="pill"><?php echo esc_html($rest); ?></span><?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Already-signed-in short-circuit -->
        <div class="ww-view" id="viewSignedIn">
            <div class="ww-h">
                <h1>You're already <span class="ac">signed in</span>.</h1>
                <p>Welcome back. Continue to your account.</p>
            </div>
            <div class="ww-signed" id="signedBox"></div>
            <button type="button" class="ww-btn ww-btn-primary" id="btnContinueIn">Continue <span class="arrow">→</span></button>
            <div class="ww-linkrow">
                <a href="<?php echo esc_url(wp_logout_url(home_url('/secure-login/'))); ?>">Sign out</a>
            </div>
        </div>

        <!-- MAIN -->
        <div class="ww-view<?php echo $ww_2fa ? '' : ' active'; ?>" id="viewMain">
            <div class="ww-h">
                <h1 id="hTitle"><?php echo esc_html($welcome); ?><span class="ac">.</span></h1>
                <p id="hSub">Sign up for a new account or log in to your existing one to seamlessly browse &amp; order with <?php echo esc_html($company); ?>.</p>
            </div>

            <div id="msg" class="ww-msg"></div>

            <form id="formLogin" novalidate>
                <div class="ww-field">
                    <label id="mainLabel" for="loginInput">Email or WhatsApp Number</label>
                    <div class="ww-input-wrap is-email" id="loginWrap">
                        <span class="icn" id="loginIcn">
                            <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </span>
                        <input type="text" id="loginInput" class="ww-input" placeholder="you@example.com or 98765 43210" autocomplete="username" inputmode="email" required>
                    </div>
                </div>

                <button type="submit" class="ww-btn ww-btn-primary" id="btnContinue">
                    Continue <span class="arrow">→</span>
                </button>
            </form>

            <div class="ww-linkrow">
                Don't have an account? <button type="button" id="btnGotoRegister">Sign up</button>
            </div>
        </div>

        <!-- REGISTER -->
        <div class="ww-view" id="viewRegister">
            <button type="button" class="ww-back" data-goto="viewMain">
                <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Back
            </button>
            <div class="ww-h">
                <h1>Create <span class="ac">account.</span></h1>
                <p>Set up your <?php echo esc_html($company); ?> account in under a minute.</p>
            </div>
            <div id="msgReg" class="ww-msg"></div>
            <form id="formRegister" novalidate>
                <div class="ww-field">
                    <label>Full Name</label>
                    <div class="ww-input-wrap">
                        <span class="icn">
                            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </span>
                        <input type="text" id="regName" class="ww-input" placeholder="John Doe" autocomplete="name" required>
                    </div>
                </div>
                <div class="ww-field">
                    <label>Email</label>
                    <div class="ww-input-wrap is-email">
                        <span class="icn">
                            <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </span>
                        <input type="email" id="regEmail" class="ww-input" placeholder="you@example.com" autocomplete="email" required>
                    </div>
                    <div class="ww-hint" id="regEmailHint"></div>
                </div>
                <div class="ww-field">
                    <label>WhatsApp Number</label>
                    <div class="ww-phone-row">
                        <div class="ww-cc">+91</div>
                        <div class="ww-input-wrap is-phone">
                            <span class="icn">
                                <svg viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51"/></svg>
                            </span>
                            <input type="tel" id="regPhone" class="ww-input" placeholder="98765 43210" maxlength="10" autocomplete="tel-national" inputmode="numeric" required>
                        </div>
                    </div>
                    <div class="ww-hint" id="regPhoneHint"></div>
                </div>
                <button type="submit" class="ww-btn ww-btn-primary" id="btnRegister">
                    Send Verification Code <span class="arrow">→</span>
                </button>
            </form>
            <div class="ww-linkrow">
                Already have an account? <button type="button" data-goto="viewMain">Log in</button>
            </div>
        </div>

        <!-- OTP -->
        <div class="ww-view" id="viewOTP">
            <button type="button" class="ww-back" data-goto="back-otp">
                <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Back
            </button>
            <div class="ww-h">
                <h1>Enter OTP to <span class="ac">Verify</span><br>Your Identity 🔒</h1>
            </div>
            <p class="ww-otp-dest">
                A one-time password has been sent<br>
                to <b id="otpDest">your inbox</b>.
            </p>
            <div id="msgOtp" class="ww-msg"></div>
            <form id="formOTP">
                <div class="ww-otp-circles">
                    <input type="text" class="ww-otp-d" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input type="text" class="ww-otp-d" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input type="text" class="ww-otp-d" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input type="text" class="ww-otp-d" maxlength="1" inputmode="numeric" pattern="[0-9]">
                </div>
                <div class="ww-resend">
                    <span id="rcText">Resend code in <strong id="rcNum">00:30</strong></span>
                    <button type="button" id="btnResend" style="display:none;">Resend Code</button>
                </div>
                <button type="submit" class="ww-btn ww-btn-primary" id="btnVerify" disabled>
                    Continue <span class="arrow">→</span>
                </button>
            </form>
        </div>

        <!-- ADMIN 2FA -->
        <div class="ww-view<?php echo $ww_2fa ? ' active' : ''; ?>" id="view2FA">
            <div class="ww-h">
                <h1>Admin 2-Step <span class="ac">Verification</span></h1>
                <p>You've entered a valid password. For security we need to verify it's really you.</p>
            </div>
            <div id="msg2fa" class="ww-msg"></div>

            <div class="ww-channels" id="chanPick">
                <button type="button" class="ww-chan active" data-chan="email">
                    <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    Email
                </button>
                <button type="button" class="ww-chan" data-chan="whatsapp">
                    <svg viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    WhatsApp
                </button>
            </div>

            <button type="button" class="ww-btn ww-btn-primary" id="btnSend2fa">
                Send Code <span class="arrow">→</span>
            </button>

            <div id="otp2faBox" style="display:none; margin-top: 20px;">
                <p class="ww-otp-dest">Code sent to <b id="dest2fa"></b></p>
                <form id="form2fa">
                    <div class="ww-otp-circles">
                        <input type="text" class="ww-otp-2 ww-otp-d" maxlength="1" inputmode="numeric" pattern="[0-9]">
                        <input type="text" class="ww-otp-2 ww-otp-d" maxlength="1" inputmode="numeric" pattern="[0-9]">
                        <input type="text" class="ww-otp-2 ww-otp-d" maxlength="1" inputmode="numeric" pattern="[0-9]">
                        <input type="text" class="ww-otp-2 ww-otp-d" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    </div>
                    <button type="submit" class="ww-btn ww-btn-primary" id="btnVerify2fa" disabled>
                        Verify &amp; Enter Admin <span class="arrow">→</span>
                    </button>
                </form>
            </div>
        </div>

        <div class="ww-foot">
            <a href="<?php echo esc_url(home_url('/')); ?>">← Return to <?php echo esc_html($company); ?></a>
        </div>
    </div>

    <!-- BIOMETRIC MODAL -->
    <div class="ww-modal-bg" id="bioModal">
        <div class="ww-modal">
            <div class="m-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M12 11v3"/><path d="M12 19c-3 0-5-2-5-5 0-4 2-7 5-7s5 3 5 7"/>
                    <path d="M6 9a6 6 0 0 1 12 0"/><path d="M3 12c0-5 4-9 9-9"/>
                </svg>
            </div>
            <h2>Enable biometric to skip OTPs</h2>
            <p>Sign in instantly next time with Face ID, Touch ID, or your device passkey.</p>
            <div class="m-err" id="bioErr"></div>
            <button type="button" class="ww-btn ww-btn-primary" id="bioEnable">
                Enable Biometric <span class="arrow">→</span>
            </button>
            <button type="button" class="skip" id="bioSkip">Skip for now</button>
        </div>
    </div>

    <script>
    (function(){
        'use strict';

        var API      = '<?php echo esc_url_raw(rest_url('ww-auth/v1/')); ?>';
        var NONCE    = '<?php echo wp_create_nonce('wp_rest'); ?>';
        var REDIRECT = '<?php echo esc_js($redirect_to); ?>' || '<?php echo esc_js(home_url('/my-account/')); ?>';
        var WW_2FA   = '<?php echo esc_js($ww_2fa); ?>';
        var WW_TOKEN = '';
        var CFG = {
            whatsapp: <?php echo $methods['whatsapp'] ? 'true' : 'false'; ?>,
            email:    <?php echo $methods['email']    ? 'true' : 'false'; ?>,
            passkeys: <?php echo $methods['passkeys'] ? 'true' : 'false'; ?>
        };

        /* ---------- helpers ---------- */
        function qs(s, el){ return (el||document).querySelector(s); }
        function qsa(s, el){ return Array.prototype.slice.call((el||document).querySelectorAll(s)); }

        function api(path, body, method){
            var headers = { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE };
            if (WW_TOKEN) headers['X-WW-Auth-Token'] = WW_TOKEN;
            var opts = { method: method || 'POST', headers: headers, credentials: 'same-origin' };
            if (method !== 'GET') opts.body = JSON.stringify(body || {});
            return fetch(API + path, opts).then(function(r){
                return r.json().catch(function(){ return { success:false, error:'Bad server response (HTTP ' + r.status + ')' }; });
            });
        }
        function show(id, msg, kind){
            var el = qs('#' + id); if (!el) return;
            el.textContent = msg || '';
            el.className = 'ww-msg' + (msg ? (' show ' + (kind || 'error')) : '');
        }
        function clearMsgs(){ ['msg','msg2','msgOtp','msgReg','msg2fa'].forEach(function(id){ show(id, ''); }); }
        function setLoading(btn, on){
            if (on) { btn.dataset._lbl = btn.innerHTML; btn.innerHTML = '<span class="ww-spin"></span> Please wait…'; btn.disabled = true; }
            else   { btn.innerHTML = btn.dataset._lbl || btn.innerHTML; btn.disabled = false; }
        }
        function showView(id){
            qsa('.ww-view').forEach(function(v){ v.classList.remove('active'); });
            var t = qs('#' + id); if (t) t.classList.add('active');
            clearMsgs();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        function initials(name){ return (name || '?').split(' ').map(function(w){return w[0];}).slice(0,2).join('').toUpperCase(); }

        /* ---------- back buttons ---------- */
        qsa('[data-goto]').forEach(function(el){
            el.addEventListener('click', function(){
                var go = el.getAttribute('data-goto');
                if (go === 'back-otp') go = 'viewMain';
                showView(go);
            });
        });

        /* ---------- detect email/phone + morph icon ---------- */
        var loginInput = qs('#loginInput');
        var loginWrap  = qs('#loginWrap');
        var loginIcn   = qs('#loginIcn');
        var mainLabel  = qs('#mainLabel');

        var MAIL_SVG = '<svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>';
        var WA_SVG   = '<svg viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';

        function detect(v){
            v = String(v || '').trim();
            if (!v) return 'empty';
            if (v.indexOf('@') > -1) return 'email';
            if (/^[+]?\d/.test(v)) return 'phone';
            return 'text';
        }
        function applyType(){
            var t = detect(loginInput.value);
            loginWrap.classList.remove('is-email','is-phone');
            if (t === 'phone') {
                loginIcn.innerHTML = WA_SVG;
                loginWrap.classList.add('is-phone');
                mainLabel.textContent = 'WhatsApp Number';
                loginInput.setAttribute('inputmode','numeric');
            } else {
                loginIcn.innerHTML = MAIL_SVG;
                if (t === 'email') loginWrap.classList.add('is-email');
                mainLabel.textContent = 'Email or WhatsApp Number';
                loginInput.setAttribute('inputmode','email');
            }
        }
        loginInput.addEventListener('input', applyType);
        applyType();

        function isEmail(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
        function isPhone10(v){ return /^\d{10}$/.test(String(v).replace(/\D/g,'')); }

        /* ---------- register hints ---------- */
        qs('#regPhone').addEventListener('input', function(){
            this.value = this.value.replace(/\D/g,'').slice(0,10);
            var h = qs('#regPhoneHint'), d = this.value;
            if (!d) { h.className='ww-hint'; h.textContent=''; return; }
            if (d.length === 10) { h.className='ww-hint show valid'; h.textContent='✓ 10 digits'; }
            else { h.className='ww-hint show invalid'; h.textContent='Enter 10 digits (' + d.length + '/10)'; }
        });
        qs('#regEmail').addEventListener('input', function(){
            var h = qs('#regEmailHint'), v = this.value.trim();
            if (!v) { h.className='ww-hint'; h.textContent=''; return; }
            if (isEmail(v)) { h.className='ww-hint show valid'; h.textContent='✓ Looks good'; }
            else { h.className='ww-hint show invalid'; h.textContent='✗ Enter a valid email'; }
        });

        /* ---------- already-logged-in check ---------- */
        fetch(API + 'me', { headers: { 'X-WP-Nonce': NONCE }, credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(r){
                if (r && r.logged_in && !WW_2FA) {
                    qs('#signedBox').innerHTML =
                        '<div class="av">' + escHtml(initials(r.user.display_name)) + '</div>'
                      + '<div class="meta"><b>' + escHtml(r.user.display_name) + '</b><span>' + escHtml(r.user.email) + '</span></div>';
                    showView('viewSignedIn');
                }
            }).catch(function(){});

        qs('#btnContinueIn').addEventListener('click', function(){ window.location.href = REDIRECT; });

        /* ---------- lookup state ---------- */
        var lookupState = { identifier:'', type:'', userId:0, maskedEmail:'', maskedPhone:'', canEmail:false, canWhatsApp:false, hasPk:false, name:'' };

        /* ---------- Continue → lookup + auto-send OTP ---------- */
        qs('#formLogin').addEventListener('submit', function(e){
            e.preventDefault();
            var btn = qs('#btnContinue');
            var raw = loginInput.value.trim();
            var t = detect(raw);

            var identifier;
            if (t === 'email') {
                if (!isEmail(raw)) return show('msg','Enter a valid email');
                identifier = raw;
            } else if (t === 'phone') {
                var d = raw.replace(/\D/g,'');
                if (d.length < 10) return show('msg','Enter a 10-digit phone number');
                identifier = '+91' + d.slice(-10);
            } else {
                return show('msg','Enter an email or WhatsApp number');
            }

            setLoading(btn, true); clearMsgs();
            api('lookup', { identifier: identifier, type: (t === 'email' ? 'email' : 'phone') }).then(function(r){
                if (!r || !r.exists) {
                    setLoading(btn, false);
                    show('msg', "We couldn't find that account. Let's create one.");
                    showView('viewRegister');
                    if (t === 'email') qs('#regEmail').value = identifier;
                    else qs('#regPhone').value = identifier.replace(/\D/g,'').slice(-10);
                    return;
                }
                lookupState = {
                    identifier: identifier, type: (t === 'email' ? 'email' : 'phone'),
                    userId: r.user_id,
                    maskedEmail: r.masked_email || '',
                    maskedPhone: r.masked_phone || '',
                    canEmail: !!r.can_email, canWhatsApp: !!r.can_whatsapp,
                    hasPk: !!r.has_passkey,
                    name: r.display_name || 'friend'
                };

                // Auto-decide channel:
                //   - email input → email OTP
                //   - phone input + WhatsApp enabled → WA OTP
                //   - phone input + WhatsApp disabled → email OTP to user's registered email
                if (t === 'email') {
                    sendEmailCode(btn);
                } else if (t === 'phone' && lookupState.canWhatsApp) {
                    sendWhatsAppCode(btn);
                } else if (lookupState.canEmail) {
                    sendEmailCode(btn);
                } else {
                    setLoading(btn, false);
                    show('msg', 'No sign-in method available. Please contact support.');
                }
            }).catch(function(){ setLoading(btn, false); show('msg','Network error. Try again.'); });
        });

        function sendEmailCode(btn){
            api('email/send', { user_id: lookupState.userId, email: (lookupState.type === 'email' ? lookupState.identifier : '') }).then(function(r){
                if (btn) setLoading(btn, false);
                if (!r || !r.success) return show('msg', (r && r.error) || 'Failed to send');
                otpCtx = { kind:'email', destLabel: lookupState.maskedEmail || lookupState.identifier, userId: lookupState.userId, isNew: false, realEmail: (lookupState.type === 'email' ? lookupState.identifier : '') };
                enterOTP();
            });
        }
        function sendWhatsAppCode(btn){
            api('whatsapp/send', { phone: lookupState.identifier }).then(function(r){
                if (btn) setLoading(btn, false);
                if (!r || !r.success) return show('msg', (r && r.error) || 'Failed to send');
                otpCtx = { kind:'whatsapp', destLabel: lookupState.maskedPhone, userId: lookupState.userId, isNew: false, realEmail: '' };
                enterOTP();
            });
        }

        /* ---------- register ---------- */
        qs('#btnGotoRegister').addEventListener('click', function(){ showView('viewRegister'); });
        qs('#formRegister').addEventListener('submit', function(e){
            e.preventDefault();
            var btn = qs('#btnRegister');
            var name = qs('#regName').value.trim();
            var email = qs('#regEmail').value.trim();
            var phone = qs('#regPhone').value.replace(/\D/g,'');
            if (!name) return show('msgReg','Please enter your name');
            if (!isEmail(email)) return show('msgReg','Enter a valid email');
            if (!isPhone10(phone)) return show('msgReg','Enter a valid 10-digit WhatsApp number');

            setLoading(btn, true); clearMsgs();
            // Safety: check not already registered
            api('lookup', { identifier: email, type: 'email' }).then(function(r){
                if (r && r.exists) {
                    setLoading(btn, false);
                    show('msgReg', "An account with that email already exists. Please log in instead.");
                    showView('viewMain');
                    qs('#loginInput').value = email; applyType();
                    return;
                }
                // Check phone too
                api('lookup', { identifier: '+91' + phone, type: 'phone' }).then(function(rp){
                    if (rp && rp.exists) {
                        setLoading(btn, false);
                        show('msgReg', "An account with that WhatsApp number already exists. Please log in instead.");
                        showView('viewMain');
                        qs('#loginInput').value = phone; applyType();
                        return;
                    }
                    // Send signup OTP via email
                    api('email/send', { email: email, name: name, phone: '+91' + phone }).then(function(rs){
                        setLoading(btn, false);
                        if (!rs || !rs.success) return show('msgReg', (rs && rs.error) || 'Failed to send code');
                        otpCtx = { kind:'email', destLabel: email, userId: 0, isNew: true, realEmail: email };
                        enterOTP();
                    });
                });
            });
        });

        /* ---------- OTP UI ---------- */
        var otpCtx = { kind:'email', destLabel:'', userId:0, isNew:false, realEmail:'' };
        var resendInt = null;

        function enterOTP(){
            qs('#otpDest').textContent = otpCtx.destLabel;
            qsa('.ww-otp-d').forEach(function(d){ d.value=''; d.classList.remove('filled'); });
            qs('#btnVerify').disabled = true;
            showView('viewOTP');
            startResend();
            setTimeout(function(){ var f = qs('#viewOTP .ww-otp-d'); f && f.focus(); }, 140);
        }

        var otpInputs = qsa('#viewOTP .ww-otp-d');
        bindOTP(otpInputs, '#btnVerify');
        bindOTP(qsa('#view2FA .ww-otp-d'), '#btnVerify2fa');

        function bindOTP(inputs, btnSel){
            inputs.forEach(function(inp, i){
                inp.addEventListener('input', function(){
                    this.value = this.value.replace(/\D/g,'');
                    if (this.value) { this.classList.add('filled'); if (i < inputs.length - 1) inputs[i+1].focus(); }
                    else this.classList.remove('filled');
                    qs(btnSel).disabled = inputs.map(function(x){return x.value;}).join('').length !== 4;
                });
                inp.addEventListener('keydown', function(e){ if (e.key==='Backspace' && !this.value && i>0) inputs[i-1].focus(); });
                inp.addEventListener('paste', function(e){
                    e.preventDefault();
                    var t = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'').slice(0,4);
                    t.split('').forEach(function(d,idx){ if (inputs[idx]) { inputs[idx].value=d; inputs[idx].classList.add('filled'); } });
                    qs(btnSel).disabled = t.length !== 4;
                    if (t.length === 4) inputs[3].focus();
                });
            });
        }

        qs('#formOTP').addEventListener('submit', function(e){
            e.preventDefault();
            var code = otpInputs.map(function(x){return x.value;}).join('');
            var btn = qs('#btnVerify');
            setLoading(btn, true); clearMsgs();

            var path, body;
            if (otpCtx.kind === 'whatsapp') {
                path='whatsapp/verify'; body={ phone: lookupState.identifier, otp: code };
            } else {
                path='email/verify';
                body={ otp: code, user_id: otpCtx.userId || 0 };
                if (otpCtx.realEmail) body.email = otpCtx.realEmail;
            }
            api(path, body).then(function(r){
                setLoading(btn, false);
                if (!r || !r.success) {
                    show('msgOtp', (r && r.error) || 'Invalid code');
                    otpInputs.forEach(function(x){ x.value=''; x.classList.remove('filled'); });
                    qs('#btnVerify').disabled = true; otpInputs[0].focus(); return;
                }
                if (r.nonce) NONCE = r.nonce;
                if (r.ww_token) WW_TOKEN = r.ww_token;
                handlePostLogin(r);
            });
        });

        function startResend(){
            var n = 30;
            var lbl = qs('#rcText'), btn = qs('#btnResend'), num = qs('#rcNum');
            lbl.style.display='inline'; btn.style.display='none';
            num.textContent = '00:' + (n < 10 ? '0'+n : n);
            if (resendInt) clearInterval(resendInt);
            resendInt = setInterval(function(){
                n--;
                num.textContent = '00:' + (n < 10 ? '0'+n : n);
                if (n <= 0) { clearInterval(resendInt); lbl.style.display='none'; btn.style.display='inline'; }
            }, 1000);
        }
        qs('#btnResend').addEventListener('click', function(){
            var self = this; self.disabled = true;
            var path = otpCtx.kind === 'whatsapp' ? 'whatsapp/send' : 'email/send';
            var body = otpCtx.kind === 'whatsapp' ? { phone: lookupState.identifier } : (otpCtx.userId ? { user_id: otpCtx.userId } : { email: otpCtx.realEmail });
            api(path, body).then(function(r){
                if (r && r.success) { show('msgOtp','Code resent!', 'success'); startResend(); }
                else show('msgOtp', (r && r.error) || 'Failed to resend');
                self.disabled = false;
            });
        });

        /* ---------- Admin 2FA flow ---------- */
        var chan2fa = 'email';
        qsa('#chanPick .ww-chan').forEach(function(b){
            b.addEventListener('click', function(){
                qsa('#chanPick .ww-chan').forEach(function(x){ x.classList.remove('active'); });
                b.classList.add('active');
                chan2fa = b.getAttribute('data-chan');
            });
        });
        qs('#btnSend2fa').addEventListener('click', function(){
            var btn = this; setLoading(btn, true); clearMsgs();
            api('admin-2fa/send', { token: WW_2FA, channel: chan2fa }).then(function(r){
                setLoading(btn, false);
                if (!r || !r.success) return show('msg2fa', (r && r.error) || 'Could not send code');
                qs('#dest2fa').textContent = r.dest || '';
                qs('#otp2faBox').style.display = 'block';
                show('msg2fa', 'Code sent successfully.', 'success');
            });
        });
        qs('#form2fa').addEventListener('submit', function(e){
            e.preventDefault();
            var inputs = qsa('#view2FA .ww-otp-d');
            var code = inputs.map(function(x){return x.value;}).join('');
            var btn = qs('#btnVerify2fa');
            setLoading(btn, true); clearMsgs();
            api('admin-2fa/verify', { token: WW_2FA, otp: code }).then(function(r){
                setLoading(btn, false);
                if (!r || !r.success) {
                    show('msg2fa', (r && r.error) || 'Incorrect code');
                    inputs.forEach(function(x){ x.value=''; x.classList.remove('filled'); });
                    qs('#btnVerify2fa').disabled = true; inputs[0].focus(); return;
                }
                window.location.href = r.redirect || '/wp-admin/';
            });
        });

        /* ---------- B64URL ---------- */
        function b64uToBuf(s){ s = String(s).replace(/-/g,'+').replace(/_/g,'/'); while (s.length % 4) s += '='; var bin = atob(s); var b = new Uint8Array(bin.length); for (var i=0;i<bin.length;i++) b[i]=bin.charCodeAt(i); return b; }
        function bufToB64u(buf){ var b = new Uint8Array(buf); var s=''; for (var i=0;i<b.length;i++) s += String.fromCharCode(b[i]); return btoa(s).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,''); }

        /* ---------- Passkey login ---------- */
        function startPasskeyLogin(){
            clearMsgs();
            if (!window.isSecureContext) return show('msg','Biometrics require HTTPS. Ask your host to enable SSL.');
            if (!window.PublicKeyCredential) return show('msg','Biometrics not supported on this browser.');
            api('passkeys/login/options', { user_id: lookupState.userId }).then(function(r){
                if (!r || !r.success) return show('msg', (r && r.error) || 'Biometric login unavailable');
                var o = r.options;
                var publicKey = { challenge: b64uToBuf(o.challenge), timeout: o.timeout, rpId: o.rpId, userVerification: o.userVerification };
                if (o.allowCredentials && o.allowCredentials.length) {
                    publicKey.allowCredentials = o.allowCredentials.map(function(c){ return { type:c.type, id: b64uToBuf(c.id), transports: c.transports }; });
                }
                return navigator.credentials.get({ publicKey: publicKey }).then(function(cred){
                    return api('passkeys/login/verify', {
                        id: cred.id,
                        rawId: bufToB64u(cred.rawId),
                        type: cred.type,
                        session_id: o.session_id,
                        response: {
                            clientDataJSON: bufToB64u(cred.response.clientDataJSON),
                            authenticatorData: bufToB64u(cred.response.authenticatorData),
                            signature: bufToB64u(cred.response.signature)
                        }
                    });
                }).then(function(v){
                    if (!v || !v.success) return show('msg', (v && v.error) || 'Biometric verification failed');
                    if (v.nonce) NONCE = v.nonce;
                    if (v.ww_token) WW_TOKEN = v.ww_token;
                    try { localStorage.setItem('ww_auth_bio_done','1'); } catch(e){}
                    window.location.href = v.redirect || REDIRECT;
                });
            }).catch(function(e){
                if (e && e.name === 'NotAllowedError') show('msg','Biometric cancelled.');
                else show('msg','Biometric failed: ' + ((e && e.message) || 'unknown'));
            });
        }

        /* ---------- Post-login: show biometric modal if applicable ---------- */
        function handlePostLogin(r){
            var user = r.user || {};
            var alreadyDone = false;
            try { alreadyDone = localStorage.getItem('ww_auth_bio_done') === '1'; } catch(e){}
            var canOffer = CFG.passkeys && window.isSecureContext && window.PublicKeyCredential && !user.has_passkey && !alreadyDone;
            if (canOffer) { window._wwRedirect = r.redirect || REDIRECT; qs('#bioModal').classList.add('show'); return; }
            window.location.href = r.redirect || REDIRECT;
        }

        /* ---------- Biometric setup ---------- */
        function bioError(msg){ var e = qs('#bioErr'); e.textContent = msg; e.classList.add('show'); }
        function bioClear(){ qs('#bioErr').classList.remove('show'); qs('#bioErr').textContent = ''; }

        qs('#bioSkip').addEventListener('click', function(){
            try { localStorage.setItem('ww_auth_bio_done','1'); } catch(e){}
            qs('#bioModal').classList.remove('show');
            window.location.href = window._wwRedirect || REDIRECT;
        });

        qs('#bioEnable').addEventListener('click', function(){
            bioClear(); var btn = this;
            if (!window.isSecureContext) return bioError('Biometric setup requires HTTPS. Please enable SSL on your site first.');
            if (!window.PublicKeyCredential) return bioError('Biometrics not supported on this browser.');

            setLoading(btn, true);
            api('passkeys/register/options', {}).then(function(r){
                if (!r || !r.success) {
                    setLoading(btn, false);
                    var code = (r && r.code) ? (' [' + r.code + ']') : '';
                    var real = (r && (r.message || r.error)) ? (r.message || r.error) : 'Could not initialise biometric setup';
                    bioError(real + code + ' — confirm the Passkeys toggle is ON in WeeWoo Auth → General.');
                    return;
                }
                var o = r.options;
                var publicKey = {
                    challenge: b64uToBuf(o.challenge),
                    rp: o.rp,
                    user: { id: b64uToBuf(o.user.id), name: o.user.name, displayName: o.user.displayName },
                    pubKeyCredParams: o.pubKeyCredParams,
                    timeout: o.timeout,
                    authenticatorSelection: o.authenticatorSelection,
                    attestation: o.attestation,
                    excludeCredentials: (o.excludeCredentials || []).map(function(c){ return { type:c.type, id: b64uToBuf(c.id), transports: c.transports }; })
                };
                return navigator.credentials.create({ publicKey: publicKey }).then(function(cred){
                    return api('passkeys/register/verify', {
                        id: cred.id,
                        rawId: bufToB64u(cred.rawId),
                        type: cred.type,
                        response: {
                            clientDataJSON: bufToB64u(cred.response.clientDataJSON),
                            attestationObject: bufToB64u(cred.response.attestationObject)
                        }
                    });
                }).then(function(v){
                    setLoading(btn, false);
                    if (!v || !v.success) return bioError((v && (v.error || v.message)) || 'Setup failed on server.');
                    try { localStorage.setItem('ww_auth_bio_done','1'); } catch(e){}
                    qs('#bioModal').classList.remove('show');
                    window.location.href = window._wwRedirect || REDIRECT;
                });
            }).catch(function(e){
                setLoading(btn, false);
                if (e && e.name === 'NotAllowedError') bioError('Setup was cancelled or timed out. Tap Enable to try again.');
                else if (e && e.name === 'InvalidStateError') bioError('This device is already registered.');
                else if (e && e.name === 'SecurityError') bioError('Domain mismatch. Passkeys need the RP ID to match your site host.');
                else bioError('Biometric setup failed: ' + ((e && e.message) || e && e.name || 'unknown error'));
            });
        });

        /* ---------- Magic-link auto-redeem ---------- */
        (function(){
            try {
                var p = new URLSearchParams(window.location.search);
                var m = p.get('ww_magic'), em = p.get('email');
                if (!m || !em) return;
                show('msg','Signing you in securely…', 'success');
                fetch(API + 'magic-link?token=' + encodeURIComponent(m) + '&email=' + encodeURIComponent(em), { headers: { 'X-WP-Nonce': NONCE }, credentials: 'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(r){
                        if (r && r.success) {
                            if (r.nonce) NONCE = r.nonce;
                            if (r.ww_token) WW_TOKEN = r.ww_token;
                            show('msg','Signed in! Redirecting…','success');
                            setTimeout(function(){ window.location.href = r.redirect || REDIRECT; }, 400);
                        } else show('msg', (r && r.error) || 'Magic link expired or invalid.');
                    });
            } catch(e){}
        })();

        function escHtml(s){ return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
    })();
    </script>
</body>
</html>
