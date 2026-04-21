<?php
/**
 * Secure Login Page Template — Premium Dark / Neon Accent Edition (v1.2.0)
 *
 * Inspired by the "Uber Eats" mobile-auth look but tuned for a passwordless
 * OTP + biometric flow. No external CSS/JS enqueue; everything is inline and
 * namespaced under `.ww-page` so the host WordPress theme cannot bleed in.
 *
 * Flow:
 *   viewMain    — Email/Phone pill toggle + single input + Continue
 *   viewMethod  — For known users: Biometric (fastest) / Email OTP / WhatsApp OTP
 *   viewOTP     — Circular OTP boxes + Continue
 *   bioModal    — Tight, dismissible biometric setup prompt (not shown after setup)
 *
 * Critical bug-fixes baked in:
 *   - Fresh REST nonce returned by login endpoints is hot-swapped into the JS,
 *     so the subsequent `passkeys/register/options` POST authenticates correctly
 *     (previous NONCE was for anonymous context and WP was silently rejecting it).
 *   - `navigator.credentials.create / .get` failures are surfaced in-modal with
 *     the real error message instead of a silent loading spinner.
 *   - Secure-context guard (WebAuthn requires HTTPS or localhost).
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
$primary     = $branding['primary_color'] ?: '#A8FF35';
$welcome     = $branding['welcome_text'] ?: 'Get Started now';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#050608">
    <title><?php echo esc_html($branding['page_title']); ?> — <?php echo esc_html($company); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: <?php echo esc_attr($primary); ?>;
            --primary-ink: #0a0d00;
            --bg-0: #050608;
            --bg-1: #0b0d12;
            --bg-2: #12161c;
            --line: rgba(255,255,255,0.08);
            --line-2: rgba(255,255,255,0.14);
            --ink: #ffffff;
            --ink-2: #cbd5e1;
            --ink-3: #8b93a1;
            --ink-4: #5b6473;
            --danger: #ff5a5a;
            --success: var(--primary);
            --chip: #171c25;
            --chip-2: #1f2530;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { height: 100%; width: 100%; }
        button, input, select, textarea { font-family: inherit; }

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
                radial-gradient(1200px 500px at 20% -10%, rgba(168,255,53,0.12), transparent 60%),
                radial-gradient(1000px 480px at 110% 110%, rgba(168,255,53,0.08), transparent 55%),
                linear-gradient(180deg, var(--bg-0) 0%, var(--bg-1) 60%, var(--bg-0) 100%);
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 40px 20px calc(36px + env(safe-area-inset-bottom));
            position: relative;
            overflow-x: hidden;
        }

        /* Faint grid pattern */
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
        }

        /* Brand header */
        .ww-brand {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 32px;
            font-weight: 900;
            font-size: 22px;
            letter-spacing: -0.02em;
        }
        .ww-brand .pill {
            display: inline-flex; align-items: center;
            padding: 4px 10px;
            background: var(--primary);
            color: var(--primary-ink);
            border-radius: 6px;
            font-weight: 900;
            letter-spacing: -0.01em;
        }
        .ww-brand img { max-height: 34px; width: auto; }

        /* Heading */
        .ww-heading { margin-bottom: 28px; }
        .ww-heading h1 {
            font-size: 36px;
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.08;
            color: #fff;
        }
        .ww-heading h1 .accent { color: var(--primary); }
        .ww-heading p {
            margin-top: 14px;
            font-size: 15px;
            color: var(--ink-3);
            line-height: 1.55;
            font-weight: 500;
            max-width: 38ch;
        }

        /* Views */
        .ww-view { display: none; animation: slideIn 0.35s ease both; }
        .ww-view.active { display: block; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        /* Pill toggle (Email / Phone) */
        .ww-toggle {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 6px;
            padding: 6px;
            background: var(--chip);
            border-radius: 999px;
            margin-bottom: 24px;
            border: 1px solid var(--line);
        }
        .ww-toggle button {
            appearance: none; border: 0;
            padding: 14px 16px;
            font-size: 15px;
            font-weight: 700;
            color: var(--ink-3);
            background: transparent;
            border-radius: 999px;
            cursor: pointer;
            letter-spacing: -0.01em;
            transition: color .2s ease, background-color .2s ease, transform .15s ease;
        }
        .ww-toggle button.active {
            background: var(--primary);
            color: var(--primary-ink);
        }
        .ww-toggle button:not(.active):hover { color: var(--ink-2); }

        /* Label + input */
        .ww-field { margin-bottom: 18px; }
        .ww-field > label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: var(--ink-2);
            margin-bottom: 8px;
            letter-spacing: -0.01em;
        }

        .ww-input-wrap {
            position: relative;
            display: flex; align-items: center;
            padding: 0 16px;
            background: var(--chip);
            border: 1.5px solid var(--line);
            border-radius: 14px;
            transition: border-color .2s ease, background-color .2s ease;
            min-height: 56px;
        }
        .ww-input-wrap:focus-within {
            border-color: var(--primary);
            background: var(--chip-2);
        }
        .ww-input-wrap .icn {
            width: 20px; height: 20px; flex-shrink: 0; color: var(--ink-3);
            margin-right: 10px;
            transition: color .2s ease;
        }
        .ww-input-wrap .icn svg { width: 100%; height: 100%; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .ww-input-wrap.is-phone .icn { color: #25D366; }
        .ww-input-wrap.is-phone .icn svg { fill: #25D366; stroke: #25D366; }
        .ww-input-wrap.is-email  .icn { color: var(--primary); }

        .ww-input {
            flex: 1; min-width: 0;
            border: 0; outline: none; background: transparent;
            color: var(--ink);
            font-size: 15px;
            font-weight: 600;
            letter-spacing: -0.01em;
            padding: 16px 0;
        }
        .ww-input::placeholder { color: var(--ink-4); font-weight: 500; }

        /* Phone row (+91 + digits) */
        .ww-phone-row { display: grid; grid-template-columns: 82px 1fr; gap: 10px; }
        .ww-cc {
            display: flex; align-items: center; justify-content: center;
            height: 56px;
            background: var(--chip);
            border: 1.5px solid var(--line);
            border-radius: 14px;
            color: var(--ink);
            font-weight: 800;
            font-size: 15px;
            letter-spacing: 0;
        }

        .ww-hint {
            display: none;
            margin-top: 8px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--ink-3);
        }
        .ww-hint.show { display: block; }
        .ww-hint.valid { color: var(--primary); }
        .ww-hint.invalid { color: var(--danger); }

        /* Buttons */
        .ww-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            width: 100%;
            min-height: 56px;
            padding: 16px 22px;
            border: 0;
            border-radius: 999px;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -0.01em;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .25s ease, background-color .2s ease, color .2s ease;
            position: relative;
        }
        .ww-btn:disabled { opacity: .55; cursor: not-allowed; transform: none !important; }
        .ww-btn-primary {
            background: var(--primary);
            color: var(--primary-ink);
            box-shadow: 0 14px 30px -12px rgba(168,255,53,0.45), inset 0 -2px 0 rgba(0,0,0,0.1);
        }
        .ww-btn-primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 18px 36px -12px rgba(168,255,53,0.6); }
        .ww-btn-primary .arrow { transition: transform .22s ease; }
        .ww-btn-primary:hover:not(:disabled) .arrow { transform: translateX(4px); }

        .ww-btn-ghost {
            background: transparent;
            color: var(--ink);
            border: 1.5px solid var(--line-2);
        }
        .ww-btn-ghost:hover:not(:disabled) { background: var(--chip); border-color: var(--line-2); }

        /* Link row */
        .ww-linkrow {
            margin-top: 24px;
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

        /* Back button */
        .ww-back {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--chip);
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 10px 14px;
            color: var(--ink-2);
            font-size: 13px; font-weight: 700;
            cursor: pointer;
            margin-bottom: 22px;
            transition: background-color .2s ease, color .2s ease;
        }
        .ww-back:hover { background: var(--chip-2); color: var(--ink); }
        .ww-back svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }

        /* Message bar */
        .ww-msg {
            display: none; align-items: center; gap: 10px;
            padding: 13px 14px;
            border-radius: 12px;
            font-size: 13.5px;
            font-weight: 700;
            margin-bottom: 16px;
            letter-spacing: -0.01em;
        }
        .ww-msg.show { display: flex; }
        .ww-msg.error   { background: rgba(255,90,90,0.1); color: #ffb4b4; border: 1px solid rgba(255,90,90,0.24); }
        .ww-msg.success { background: rgba(168,255,53,0.1); color: var(--primary); border: 1px solid rgba(168,255,53,0.3); }

        /* Spinner */
        .ww-spin { width: 18px; height: 18px; border: 2px solid currentColor; border-top-color: transparent; border-radius: 50%; animation: spin .85s linear infinite; display: inline-block; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Method choice cards */
        .ww-choices { display: flex; flex-direction: column; gap: 12px; }
        .ww-choice {
            display: flex; align-items: center; gap: 14px;
            width: 100%;
            padding: 16px 18px;
            min-height: 74px;
            background: var(--chip);
            border: 1.5px solid var(--line);
            border-radius: 18px;
            cursor: pointer; text-align: left;
            color: var(--ink);
            font-family: inherit;
            transition: transform .18s ease, border-color .2s ease, background-color .2s ease;
        }
        .ww-choice:hover { transform: translateY(-1px); border-color: var(--line-2); background: var(--chip-2); }
        .ww-choice .cicon { flex: 0 0 44px; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; }
        .ww-choice .cicon svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .ww-choice .ctext { flex: 1; min-width: 0; }
        .ww-choice .ctext b { display: block; font-size: 15px; font-weight: 800; letter-spacing: -0.01em; }
        .ww-choice .ctext span { display: block; margin-top: 3px; font-size: 12.5px; color: var(--ink-3); font-weight: 600; }
        .ww-choice .chev { color: var(--ink-3); transition: transform .18s ease, color .2s ease; }
        .ww-choice:hover .chev { transform: translateX(3px); color: var(--ink); }

        .ww-choice.primary {
            background: linear-gradient(180deg, rgba(168,255,53,0.1), rgba(168,255,53,0.03));
            border-color: rgba(168,255,53,0.4);
        }
        .ww-choice.primary .cicon { background: var(--primary); color: var(--primary-ink); }
        .ww-choice.email    .cicon { background: #21263165; color: var(--primary); }
        .ww-choice.whatsapp .cicon { background: #2d4a3365; color: #25D366; }

        .ww-badge {
            display: inline-block;
            margin-left: 8px;
            padding: 2px 8px;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            background: var(--primary);
            color: var(--primary-ink);
            border-radius: 999px;
            vertical-align: 2px;
        }

        /* OTP screen — circular digit boxes */
        .ww-otp-dest {
            text-align: center;
            color: var(--ink-3);
            margin: 4px 0 22px;
            font-size: 14px;
            line-height: 1.55;
            font-weight: 500;
        }
        .ww-otp-dest b { color: var(--ink); font-weight: 800; }

        .ww-otp-circles {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin: 28px 0;
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
        .ww-otp-circles input:focus {
            border-color: var(--primary);
            background: rgba(168,255,53,0.06);
            transform: scale(1.04);
        }
        .ww-otp-circles input.filled {
            border-color: var(--primary);
            color: var(--primary);
        }

        .ww-resend {
            text-align: center;
            margin: 4px 0 24px;
            font-size: 13.5px;
            font-weight: 700;
            color: var(--primary);
        }
        .ww-resend #rcText { color: var(--ink-3); font-weight: 600; }
        .ww-resend button {
            background: none; border: 0; padding: 0;
            color: var(--primary); font: inherit; font-weight: 800;
            cursor: pointer;
        }
        .ww-resend button:hover { text-decoration: underline; }

        /* QR */
        .ww-qr-wrap { background: var(--chip); border: 1px solid var(--line); border-radius: 20px; padding: 24px; text-align: center; }
        .ww-qr-wrap .box { background: #fff; padding: 14px; border-radius: 14px; display: inline-block; }
        .ww-qr-wrap img { width: 180px; height: 180px; display: block; }
        .ww-qr-status { text-align: center; margin: 14px 0 16px; color: var(--ink-3); font-size: 13px; font-weight: 600; }

        /* Modal (biometric setup) */
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
            padding: 28px 24px 20px;
            text-align: center;
            box-shadow: 0 40px 80px -20px rgba(0,0,0,0.8), 0 0 0 1px rgba(168,255,53,0.08);
            animation: modalIn .35s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes modalIn { from { opacity: 0; transform: scale(0.92) translateY(12px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .ww-modal .m-icon {
            width: 68px; height: 68px; margin: 0 auto 16px;
            border-radius: 20px;
            background: var(--primary);
            color: var(--primary-ink);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 20px 40px -14px rgba(168,255,53,0.5);
            animation: pulse 2.2s ease-in-out infinite;
        }
        @keyframes pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.04); } }
        .ww-modal .m-icon svg { width: 32px; height: 32px; stroke: currentColor; fill: none; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
        .ww-modal h2 { font-size: 22px; font-weight: 900; letter-spacing: -0.02em; }
        .ww-modal p { font-size: 14px; color: var(--ink-3); font-weight: 500; margin: 8px 0 22px; line-height: 1.5; }
        .ww-modal .m-err {
            display: none;
            background: rgba(255,90,90,0.12);
            color: #ffb4b4;
            border: 1px solid rgba(255,90,90,0.3);
            border-radius: 12px;
            padding: 10px 12px;
            margin-bottom: 14px;
            font-size: 12.5px;
            font-weight: 600;
            text-align: left;
        }
        .ww-modal .m-err.show { display: block; }
        .ww-modal .skip {
            display: block; width: 100%;
            margin-top: 12px;
            background: none; border: 0;
            color: var(--ink-3);
            font: inherit; font-weight: 700; font-size: 14px;
            cursor: pointer;
            padding: 12px 10px;
        }
        .ww-modal .skip:hover { color: var(--ink); }

        /* Footer */
        .ww-foot { margin-top: 22px; text-align: center; font-size: 13px; color: var(--ink-4); }
        .ww-foot a { color: var(--ink-3); text-decoration: none; font-weight: 700; }
        .ww-foot a:hover { color: var(--ink); }

        /* Responsive */
        @media (max-width: 420px) {
            body.ww-page { padding: 28px 18px calc(28px + env(safe-area-inset-bottom)); }
            .ww-heading h1 { font-size: 30px; }
            .ww-otp-circles { gap: 10px; }
            .ww-otp-circles input { font-size: 22px; }
        }

        /* QR hide on mobile */
        @media (max-width: 640px) { .ww-qr-hide { display: none !important; } }
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
                // Render "<Word> <Pill>" brand style if 2 words, else just bold text
                $parts = preg_split('/\s+/', trim($company), 2);
                $first = $parts[0] ?? $company;
                $rest  = $parts[1] ?? '';
                ?>
                <span><?php echo esc_html($first); ?></span>
                <?php if ($rest): ?><span class="pill"><?php echo esc_html($rest); ?></span><?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- MAIN VIEW -->
        <div class="ww-view active" id="viewMain">
            <div class="ww-heading">
                <h1 id="hTitle"><?php echo esc_html($welcome); ?><span class="accent">.</span></h1>
                <p id="hSub">Sign up for a new account or log in to your existing one to seamlessly browse &amp; order with <?php echo esc_html($company); ?>.</p>
            </div>

            <div id="msg" class="ww-msg"></div>

            <div class="ww-toggle" role="tablist">
                <button type="button" class="active" data-mode="email" role="tab">Email</button>
                <button type="button" data-mode="phone" role="tab">Phone Number</button>
            </div>

            <form id="formLogin" novalidate>
                <div class="ww-field" id="fldEmail">
                    <label for="loginEmail">Email</label>
                    <div class="ww-input-wrap is-email">
                        <span class="icn">
                            <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </span>
                        <input type="email" id="loginEmail" class="ww-input" placeholder="you@example.com" autocomplete="email" inputmode="email">
                    </div>
                </div>

                <div class="ww-field" id="fldPhone" style="display:none;">
                    <label>Phone Number</label>
                    <div class="ww-phone-row">
                        <div class="ww-cc">+91</div>
                        <div class="ww-input-wrap is-phone">
                            <span class="icn">
                                <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51"/></svg>
                            </span>
                            <input type="tel" id="loginPhone" class="ww-input" placeholder="98765 43210" maxlength="10" autocomplete="tel-national" inputmode="numeric">
                        </div>
                    </div>
                </div>

                <button type="submit" class="ww-btn ww-btn-primary" id="btnContinue">
                    Continue <span class="arrow">→</span>
                </button>
            </form>

            <?php if ($methods['qr'] && !$is_mobile): ?>
            <div class="ww-qr-hide" style="margin-top: 14px;">
                <button type="button" class="ww-btn ww-btn-ghost" id="btnShowQR">Scan QR to Sign In</button>
            </div>
            <?php endif; ?>

            <div class="ww-linkrow">
                Don't have an account? <button type="button" id="btnGotoRegister">Sign up</button>
            </div>
        </div>

        <!-- REGISTER VIEW -->
        <div class="ww-view" id="viewRegister">
            <button type="button" class="ww-back" data-goto="viewMain">
                <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Back
            </button>

            <div class="ww-heading">
                <h1>Create <span class="accent">account.</span></h1>
                <p>Set up your <?php echo esc_html($company); ?> account in under a minute.</p>
            </div>

            <div id="msgReg" class="ww-msg"></div>

            <form id="formRegister" novalidate>
                <div class="ww-field">
                    <label for="regName">Full Name</label>
                    <div class="ww-input-wrap">
                        <span class="icn">
                            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </span>
                        <input type="text" id="regName" class="ww-input" placeholder="John Doe" autocomplete="name" required>
                    </div>
                </div>
                <div class="ww-field">
                    <label for="regEmail">Email</label>
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
                                <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            </span>
                            <input type="tel" id="regPhone" class="ww-input" placeholder="98765 43210" maxlength="10" autocomplete="tel-national" inputmode="numeric" required>
                        </div>
                    </div>
                    <div class="ww-hint" id="regPhoneHint"></div>
                </div>
                <button type="submit" class="ww-btn ww-btn-primary" id="btnRegister">
                    Create Account <span class="arrow">→</span>
                </button>
            </form>

            <div class="ww-linkrow">
                Already have an account? <button type="button" data-goto="viewMain">Log in</button>
            </div>
        </div>

        <!-- METHOD VIEW -->
        <div class="ww-view" id="viewMethod">
            <button type="button" class="ww-back" data-goto="viewMain">
                <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Back
            </button>

            <div class="ww-heading">
                <h1>How would you like to <span class="accent">sign in</span>?</h1>
                <p>Welcome back, <b id="mGreet">friend</b>. Pick your preferred method:</p>
            </div>

            <div id="msg2" class="ww-msg"></div>

            <div class="ww-choices" id="choices"></div>

            <div class="ww-linkrow">
                Not you? <button type="button" id="btnSwitchUser">Use a different account</button>
            </div>
        </div>

        <!-- OTP VIEW -->
        <div class="ww-view" id="viewOTP">
            <button type="button" class="ww-back" data-goto="back-otp">
                <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Back
            </button>

            <div class="ww-heading" style="text-align: center;">
                <h1 style="font-size: 28px;">Enter OTP to <span class="accent">Verify</span><br>Your Identity 🔒</h1>
            </div>

            <p class="ww-otp-dest">
                A one-time password (OTP) has been sent<br>
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

        <!-- QR VIEW -->
        <div class="ww-view" id="viewQR">
            <button type="button" class="ww-back" data-goto="viewMain" data-stop-qr="1">
                <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Back
            </button>
            <div class="ww-heading">
                <h1>Scan to <span class="accent">sign in</span></h1>
                <p>Open your phone camera and point it at the code below.</p>
            </div>
            <div class="ww-qr-wrap" id="qrBox"><div class="ww-spin" style="margin: 40px auto; color: var(--ink-3);"></div></div>
            <div class="ww-qr-status" id="qrStatus">Waiting for authorization…</div>
            <button type="button" class="ww-btn ww-btn-ghost" id="btnRefreshQR">Refresh QR Code</button>
        </div>

        <div class="ww-foot">
            <a href="<?php echo esc_url(home_url('/')); ?>">← Return to <?php echo esc_html($company); ?></a>
        </div>
    </div>

    <!-- BIOMETRIC MODAL (tight, dismissible) -->
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
        var CFG = {
            whatsapp: <?php echo $methods['whatsapp'] ? 'true' : 'false'; ?>,
            email:    <?php echo $methods['email']    ? 'true' : 'false'; ?>,
            passkeys: <?php echo $methods['passkeys'] ? 'true' : 'false'; ?>
        };

        /* ---------- helpers ---------- */
        function qs(s, el){ return (el||document).querySelector(s); }
        function qsa(s, el){ return Array.prototype.slice.call((el||document).querySelectorAll(s)); }
        function api(path, body, method){
            var opts = { method: method || 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE }, credentials: 'same-origin' };
            if (method !== 'GET') opts.body = JSON.stringify(body || {});
            return fetch(API + path, opts).then(function(r){ return r.json().catch(function(){ return { success:false, error:'Bad server response (HTTP ' + r.status + ').' }; }); });
        }
        function show(id, msg, kind){
            var el = qs('#' + id); if (!el) return;
            el.textContent = msg || '';
            el.className = 'ww-msg' + (msg ? (' show ' + (kind || 'error')) : '');
        }
        function clearMsgs(){ ['msg','msg2','msgOtp','msgReg'].forEach(function(id){ show(id, ''); }); }
        function setLoading(btn, on){
            if (on) {
                btn.dataset._lbl = btn.innerHTML;
                btn.innerHTML = '<span class="ww-spin"></span> Please wait…';
                btn.disabled = true;
            } else {
                btn.innerHTML = btn.dataset._lbl || btn.innerHTML;
                btn.disabled = false;
            }
        }
        function showView(id){
            qsa('.ww-view').forEach(function(v){ v.classList.remove('active'); });
            var t = qs('#' + id); if (t) t.classList.add('active');
            clearMsgs();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        /* ---------- toggle Email/Phone ---------- */
        var mode = 'email';
        qsa('.ww-toggle button').forEach(function(btn){
            btn.addEventListener('click', function(){
                qsa('.ww-toggle button').forEach(function(b){ b.classList.remove('active'); });
                btn.classList.add('active');
                mode = btn.getAttribute('data-mode');
                qs('#fldEmail').style.display = (mode === 'email') ? '' : 'none';
                qs('#fldPhone').style.display = (mode === 'phone') ? '' : 'none';
                clearMsgs();
            });
        });

        /* ---------- back buttons ---------- */
        qsa('[data-goto]').forEach(function(el){
            el.addEventListener('click', function(){
                var go = el.getAttribute('data-goto');
                if (el.getAttribute('data-stop-qr')) stopQR();
                if (go === 'back-otp') go = lookupState.userId ? 'viewMethod' : 'viewMain';
                showView(go);
            });
        });

        /* ---------- validators ---------- */
        function isEmail(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
        function isPhone10(v){ return /^\d{10}$/.test(String(v).replace(/\D/g,'')); }

        /* ---------- phone input sanitise ---------- */
        qs('#loginPhone').addEventListener('input', function(){ this.value = this.value.replace(/\D/g,'').slice(0,10); });
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

        /* ---------- lookup state ---------- */
        var lookupState = { identifier:'', type:'', userId:0, maskedEmail:'', maskedPhone:'', canEmail:false, canWhatsApp:false, hasPk:false, name:'' };

        /* ---------- Login/Continue ---------- */
        qs('#formLogin').addEventListener('submit', function(e){
            e.preventDefault();
            var btn = qs('#btnContinue');
            var identifier = '', type = mode;

            if (mode === 'email') {
                identifier = qs('#loginEmail').value.trim();
                if (!isEmail(identifier)) { show('msg','Enter a valid email'); return; }
            } else {
                var d = qs('#loginPhone').value.replace(/\D/g,'');
                if (d.length !== 10) { show('msg','Enter a 10-digit phone number'); return; }
                identifier = '+91' + d;
            }

            setLoading(btn, true); clearMsgs();
            api('lookup', { identifier: identifier, type: type }).then(function(r){
                setLoading(btn, false);
                if (!r || !r.exists) {
                    show('msg', "We couldn't find that account. Let's create one.", 'error');
                    showView('viewRegister');
                    if (type === 'email') qs('#regEmail').value = identifier;
                    else qs('#regPhone').value = identifier.replace(/\D/g,'').slice(-10);
                    return;
                }
                lookupState = {
                    identifier: identifier, type: type,
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
            }).catch(function(){ setLoading(btn,false); show('msg','Network error. Try again.'); });
        });

        /* ---------- Method choices ---------- */
        function renderChoices(){
            var c = qs('#choices'); c.innerHTML = '';

            if (CFG.passkeys && lookupState.hasPk && window.PublicKeyCredential) {
                c.appendChild(makeChoice('primary',
                    '<svg viewBox="0 0 24 24"><path d="M12 11v3"/><path d="M12 19c-3 0-5-2-5-5 0-4 2-7 5-7s5 3 5 7"/><path d="M6 9a6 6 0 0 1 12 0"/><path d="M3 12c0-5 4-9 9-9"/></svg>',
                    '<b>Biometric Login<span class="ww-badge">Fastest</span></b><span>Use Face ID / Touch ID / Passkey</span>',
                    startPasskeyLogin
                ));
            }
            if (lookupState.canEmail && lookupState.maskedEmail) {
                c.appendChild(makeChoice('email',
                    '<svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
                    '<b>Email Code</b><span>Sent to ' + escHtml(lookupState.maskedEmail) + '</span>',
                    sendEmailCode
                ));
            }
            if (lookupState.canWhatsApp && lookupState.maskedPhone) {
                c.appendChild(makeChoice('whatsapp',
                    '<svg viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>',
                    '<b>WhatsApp Code</b><span>Sent to ' + escHtml(lookupState.maskedPhone) + '</span>',
                    sendWhatsAppCode
                ));
            }
            if (!c.children.length) {
                var d = document.createElement('div');
                d.className = 'ww-msg show error';
                d.textContent = 'No sign-in method available right now. Please contact support.';
                c.appendChild(d);
            }
        }
        function makeChoice(kind, iconSVG, inner, onClick){
            var b = document.createElement('button');
            b.type='button';
            b.className='ww-choice ' + kind;
            b.innerHTML = '<span class="cicon">'+iconSVG+'</span><span class="ctext">'+inner+'</span>'
                       + '<span class="chev"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>';
            b.addEventListener('click', onClick);
            return b;
        }
        function escHtml(s){ return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

        /* ---------- register ---------- */
        qs('#btnGotoRegister').addEventListener('click', function(){ showView('viewRegister'); });
        qs('#formRegister').addEventListener('submit', function(e){
            e.preventDefault();
            var name  = qs('#regName').value.trim();
            var email = qs('#regEmail').value.trim();
            var phone = qs('#regPhone').value.replace(/\D/g,'');
            var btn = qs('#btnRegister');
            if (!name) return show('msgReg','Please enter your name');
            if (!isEmail(email)) return show('msgReg','Enter a valid email');
            if (!isPhone10(phone)) return show('msgReg','Enter a valid 10-digit WhatsApp number');

            setLoading(btn, true); clearMsgs();
            api('email/send', { email: email, name: name, phone: '+91' + phone }).then(function(r){
                setLoading(btn, false);
                if (!r || !r.success) return show('msgReg', (r && r.error) || 'Failed to send code');
                otpCtx = { kind:'email', realEmail: email, destLabel: email, userId: 0, isNew: true };
                enterOTP();
            }).catch(function(){ setLoading(btn, false); show('msgReg','Network error'); });
        });

        /* ---------- switch user ---------- */
        qs('#btnSwitchUser').addEventListener('click', function(){
            qs('#loginEmail').value=''; qs('#loginPhone').value='';
            showView('viewMain');
        });

        /* ---------- OTP ---------- */
        var otpCtx = { kind:'email', realEmail:'', destLabel:'', userId: 0, isNew: false };
        var resendInt = null;

        function enterOTP(){
            qs('#otpDest').textContent = otpCtx.destLabel || otpCtx.realEmail;
            qsa('.ww-otp-d').forEach(function(d){ d.value=''; d.classList.remove('filled'); });
            qs('#btnVerify').disabled = true;
            showView('viewOTP');
            startResend();
            setTimeout(function(){ qs('.ww-otp-d').focus(); }, 140);
        }
        function sendEmailCode(){
            clearMsgs();
            api('email/send', { user_id: lookupState.userId }).then(function(r){
                if (!r || !r.success) return show('msg2', (r && r.error) || 'Failed to send');
                otpCtx = { kind:'email', realEmail:'', destLabel: lookupState.maskedEmail, userId: lookupState.userId, isNew: false };
                enterOTP();
            }).catch(function(){ show('msg2','Network error'); });
        }
        function sendWhatsAppCode(){
            clearMsgs();
            api('whatsapp/send', { phone: lookupState.identifier }).then(function(r){
                if (!r || !r.success) return show('msg2', (r && r.error) || 'Failed to send');
                otpCtx = { kind:'whatsapp', realEmail:'', destLabel: lookupState.maskedPhone, userId: lookupState.userId, isNew: false };
                enterOTP();
            }).catch(function(){ show('msg2','Network error'); });
        }

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
            inp.addEventListener('keydown', function(e){ if (e.key==='Backspace' && !this.value && i>0) otpInputs[i-1].focus(); });
            inp.addEventListener('paste', function(e){
                e.preventDefault();
                var t = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'').slice(0,4);
                t.split('').forEach(function(d,idx){ if (otpInputs[idx]) { otpInputs[idx].value=d; otpInputs[idx].classList.add('filled'); } });
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
            if (otpCtx.kind === 'whatsapp') {
                path='whatsapp/verify'; body = { phone: lookupState.identifier, otp: code };
            } else {
                path='email/verify';
                body = { otp: code, user_id: otpCtx.userId || 0 };
                if (otpCtx.isNew && otpCtx.realEmail) body.email = otpCtx.realEmail;
                else if (lookupState.type === 'email') body.email = lookupState.identifier;
            }
            api(path, body).then(function(r){
                setLoading(btn, false);
                if (!r || !r.success) {
                    show('msgOtp', (r && r.error) || 'Invalid code');
                    otpInputs.forEach(function(x){ x.value=''; x.classList.remove('filled'); });
                    qs('#btnVerify').disabled = true; otpInputs[0].focus(); return;
                }
                // 🔑 CRITICAL: hot-swap to a fresh authenticated nonce before any authed API call
                if (r.nonce) NONCE = r.nonce;
                handlePostLogin(r);
            }).catch(function(){ setLoading(btn,false); show('msgOtp','Network error'); });
        });

        function startResend(){
            var n = 30;
            var lbl = qs('#rcText'), btn = qs('#btnResend');
            var num = qs('#rcNum');
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
            this.disabled = true;
            var path = otpCtx.kind === 'whatsapp' ? 'whatsapp/send' : 'email/send';
            var body = otpCtx.kind === 'whatsapp' ? { phone: lookupState.identifier } : (otpCtx.userId ? { user_id: otpCtx.userId } : { email: otpCtx.realEmail });
            var self = this;
            api(path, body).then(function(r){
                if (r && r.success) { show('msgOtp','Code resent!', 'success'); startResend(); }
                else show('msgOtp', (r && r.error) || 'Failed to resend');
                self.disabled = false;
            });
        });

        /* ---------- Base64URL helpers ---------- */
        function b64uToBuf(s){
            s = String(s).replace(/-/g,'+').replace(/_/g,'/'); while (s.length % 4) s += '=';
            var bin = atob(s); var bytes = new Uint8Array(bin.length);
            for (var i=0;i<bin.length;i++) bytes[i] = bin.charCodeAt(i);
            return bytes;
        }
        function bufToB64u(buf){
            var bytes = new Uint8Array(buf); var s = '';
            for (var i=0;i<bytes.length;i++) s += String.fromCharCode(bytes[i]);
            return btoa(s).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');
        }

        /* ---------- Passkey LOGIN (with allowCredentials from server) ---------- */
        function startPasskeyLogin(){
            clearMsgs();
            if (!window.isSecureContext) { show('msg2','Biometrics require HTTPS. Ask your host to enable SSL.'); return; }
            if (!window.PublicKeyCredential) { show('msg2','Biometrics are not supported on this browser.'); return; }
            api('passkeys/login/options', { user_id: lookupState.userId }).then(function(r){
                if (!r || !r.success) { show('msg2', (r && r.error) || 'Unable to start biometric login.'); return; }
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
                    if (!v || !v.success) { show('msg2', (v && v.error) || 'Biometric verification failed.'); return; }
                    if (v.nonce) NONCE = v.nonce;
                    try { localStorage.setItem('ww_auth_bio_done','1'); } catch(e){}
                    window.location.href = v.redirect || REDIRECT;
                });
            }).catch(function(e){
                if (e && e.name === 'NotAllowedError') show('msg2','Biometric cancelled.');
                else show('msg2','Biometric failed: ' + ((e && e.message) || 'unknown'));
            });
        }

        /* ---------- Post-login ---------- */
        function handlePostLogin(r){
            var user = r.user || {};
            var alreadyDone = false;
            try { alreadyDone = localStorage.getItem('ww_auth_bio_done') === '1'; } catch(e){}
            var canOffer = CFG.passkeys && window.isSecureContext && window.PublicKeyCredential && !user.has_passkey && !alreadyDone;
            if (canOffer) {
                window._wwRedirect = r.redirect || REDIRECT;
                qs('#bioModal').classList.add('show');
                return;
            }
            window.location.href = r.redirect || REDIRECT;
        }

        /* ---------- Biometric setup (post-login) ---------- */
        function bioError(msg){
            var e = qs('#bioErr');
            e.textContent = msg;
            e.classList.add('show');
        }
        function bioClear(){ qs('#bioErr').classList.remove('show'); qs('#bioErr').textContent = ''; }

        qs('#bioSkip').addEventListener('click', function(){
            try { localStorage.setItem('ww_auth_bio_done','1'); } catch(e){}
            qs('#bioModal').classList.remove('show');
            window.location.href = window._wwRedirect || REDIRECT;
        });

        qs('#bioEnable').addEventListener('click', function(){
            bioClear();
            var btn = this;

            if (!window.isSecureContext) { bioError('Biometric setup requires HTTPS. Please enable SSL on your site first.'); return; }
            if (!window.PublicKeyCredential) { bioError('Biometrics are not supported on this browser.'); return; }

            setLoading(btn, true);
            api('passkeys/register/options', {}).then(function(r){
                if (!r || !r.success) {
                    setLoading(btn, false);
                    bioError(((r && r.error) || 'Could not initialise biometric setup') + ' — make sure the Passkeys option is enabled in the plugin settings.');
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
                    if (!v || !v.success) { bioError((v && v.error) || 'Setup failed on server.'); return; }
                    try { localStorage.setItem('ww_auth_bio_done','1'); } catch(e){}
                    qs('#bioModal').classList.remove('show');
                    window.location.href = window._wwRedirect || REDIRECT;
                });
            }).catch(function(e){
                setLoading(btn, false);
                if (e && e.name === 'NotAllowedError') bioError('Setup was cancelled or timed out. Tap Enable to try again.');
                else if (e && e.name === 'InvalidStateError') bioError('This device is already registered. You can sign in with biometrics next time.');
                else if (e && e.name === 'SecurityError') bioError('Domain mismatch. Passkeys need the RP ID to match your site host.');
                else bioError('Biometric setup failed: ' + ((e && e.message) || e && e.name || 'unknown error'));
            });
        });

        /* ---------- QR ---------- */
        var qrInt = null, qrSess = null;
        qs('#btnShowQR')?.addEventListener('click', function(){ showView('viewQR'); genQR(); });
        qs('#btnRefreshQR')?.addEventListener('click', function(){ stopQR(); genQR(); });
        function genQR(){
            var box = qs('#qrBox');
            box.innerHTML = '<div class="ww-spin" style="margin:40px auto; color: var(--ink-3);"></div>';
            qs('#qrStatus').textContent = 'Generating QR code…';
            api('qr/generate', {}).then(function(r){
                if (!r || !r.success) { box.innerHTML = '<p style="color:#ff5a5a;">Failed to generate QR</p>'; return; }
                qrSess = r.session_id;
                var url = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(r.qr_data);
                box.innerHTML = '<div class="box"><img src="' + url + '" alt="QR"></div>';
                qs('#qrStatus').textContent = 'Waiting for authorization…';
                if (qrInt) clearInterval(qrInt);
                qrInt = setInterval(pollQR, 3000);
            });
        }
        function pollQR(){
            fetch(API + 'qr/poll?session_id=' + qrSess, { headers: { 'X-WP-Nonce': NONCE } }).then(function(r){ return r.json(); }).then(function(r){
                if (r.status === 'authorized') { stopQR(); qs('#qrStatus').textContent = 'Authorized! Redirecting…'; setTimeout(function(){ window.location.href = r.redirect || REDIRECT; }, 400); }
                else if (r.status === 'expired') { stopQR(); qs('#qrStatus').textContent = 'QR expired. Refresh.'; }
            });
        }
        function stopQR(){ if (qrInt) { clearInterval(qrInt); qrInt = null; } }
        window.stopQR = stopQR;

        /* ---------- Magic-link auto-redeem ---------- */
        (function(){
            try {
                var p = new URLSearchParams(window.location.search);
                var m = p.get('ww_magic'), em = p.get('email');
                if (!m || !em) return;
                show('msg','Signing you in securely…', 'success');
                fetch(API + 'magic-link?token=' + encodeURIComponent(m) + '&email=' + encodeURIComponent(em), { headers: { 'X-WP-Nonce': NONCE } })
                    .then(function(r){ return r.json(); })
                    .then(function(r){
                        if (r && r.success) {
                            if (r.nonce) NONCE = r.nonce;
                            show('msg','Signed in! Redirecting…','success');
                            setTimeout(function(){ window.location.href = r.redirect || REDIRECT; }, 400);
                        } else show('msg', (r && r.error) || 'Magic link expired or invalid.');
                    });
            } catch(e){}
        })();
    })();
    </script>
</body>
</html>
