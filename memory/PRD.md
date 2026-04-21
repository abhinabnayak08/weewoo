# WeeWoo Auth Pro — Product Requirements Document

## Original Problem Statement
Build a premium, high-performance authentication WordPress plugin named **WeeWoo Auth Pro**, a standalone replacement for the Digits plugin for mobile-first auth on a WooCommerce store.

### Core Requirements
- Passwordless authentication (OTP via WhatsApp/Email + Magic Link)
- Cloudflare Turnstile verification
- Passkeys (WebAuthn) / Biometric login
- QR handshake (desktop ↔ mobile)
- WooCommerce: bypass native email verification, auto-fill billing on login
- Admin dashboard with per-form method toggles
- Premium Fintech UI: light/white theme, gradients, 3D elements, smooth animations
- Passwordless-only flow (no password inputs)
- +91 default country code, 10-digit validation
- Dynamic input icons (email ↔ WhatsApp based on typed value)
- Auto slide between Login/Register based on user existence in WC billing
- HTML email with OTP boxes + one-click Magic Link
- Post-login dismissible Biometric/Passkey setup popup
- QR hidden on mobile

## Architecture (WordPress plugin)
```
/app/wordpress-plugin/weewoo-auth-pro/
├── weewoo-auth-pro.php                 # Bootstrap, rewrite rule, HPOS declaration
├── admin/class-ww-auth-settings.php    # 4-tab admin UI with register_setting
├── includes/
│   ├── class-ww-auth-api.php           # REST: check-user, email/whatsapp OTP, magic-link, QR, passkeys
│   ├── class-ww-frontend.php           # Branding / method helpers
│   ├── class-ww-guest-pay.php          # WC order-key bypass + billing auto-fill
│   ├── class-ww-passkeys.php           # WebAuthn
│   ├── class-ww-qr-handshake.php       # QR session logic
│   ├── class-ww-rate-limiter.php       # IP / attempt throttling
│   ├── class-ww-turnstile.php          # Cloudflare Turnstile
│   └── class-ww-whatsapp.php           # Meta Cloud API
├── templates/login-page.php            # Self-contained HTML + inline CSS/JS
└── assets/{css,js,images}/             # Placeholder, not enqueued
```

## REST API
- `POST /ww-auth/v1/check-user` — identifier + type (email|phone) → exists?
- `POST /ww-auth/v1/email/send` | `/email/verify` — HTML email w/ OTP boxes + magic link
- `POST /ww-auth/v1/whatsapp/send` | `/whatsapp/verify`
- `GET  /ww-auth/v1/magic-link` — accepts `token` or `ww_magic` + `email`
- `POST /ww-auth/v1/qr/generate` | `GET /qr/poll` | `POST /qr/authorize`
- `POST /ww-auth/v1/passkeys/register/options` | `register/verify` | `login/options` | `login/verify`
- `GET  /ww-auth/v1/status`

## CHANGELOG
### 2026-04-21
- Fixed magic-link flow: email URL uses `?ww_magic=` and login-page JS now auto-redeems it
- Added WooCommerce HPOS + cart/checkout blocks compatibility declaration (removes WC incompatibility warning)
- Removed stale external `frontend.js` / `frontend.css` enqueue — login template is fully self-contained with inline CSS/JS for theme isolation
- Repackaged plugin ZIP at `/app/wordpress-plugin/weewoo-auth-pro.zip` (44 KB, 23 files) + mirrored to `/app/public/`

### 2026-04-20 (prior)
- Passwordless premium light-theme `login-page.php` rewrite (inline CSS + JS)
- Dynamic email/WhatsApp input icons, +91 default, 10-digit validation
- `check-user` endpoint + auto-slide between Login ↔ Register
- Premium HTML email template with OTP digit boxes + Magic Link CTA
- Post-login biometric/passkey setup modal
- Per-form WhatsApp/Email toggles in admin (General tab)
- 4-tab admin UI using WP Settings API (`register_setting` + `settings_fields`)
- CSS-isolated `ww-*` namespace
- QR hidden on mobile via server-side `wp_is_mobile()` check

## Roadmap (P1/P2)
- P1: Live test with real Meta Cloud API keys + Cloudflare Turnstile keys on staging WordPress
- P1: Full QR desktop↔mobile E2E validation
- P1: Passkey conditional-UI autofill assist on main login input
- P2: Admin "Recent Logins" log viewer
- P2: Add dropdown for top-10 international country codes (currently +91 locked)
- P2: Custom rate-limit rules per endpoint
- P2: Translations (.pot generation)

## Test Credentials
N/A — plugin requires live WordPress + WooCommerce environment for end-to-end testing.
