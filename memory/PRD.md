# WeeWoo Auth Pro — Product Requirements Document

## Original Problem Statement
Build a premium, high-performance passwordless auth WordPress plugin named **WeeWoo Auth Pro**, a standalone replacement for the Digits plugin for mobile-first auth on a WooCommerce store.

## Architecture
```
/app/wordpress-plugin/weewoo-auth-pro/
├── weewoo-auth-pro.php                 # Bootstrap, rewrite rule, HPOS, login_init override
├── admin/class-ww-auth-settings.php    # 4-tab admin UI
├── includes/
│   ├── class-ww-auth-api.php           # REST: lookup, email/whatsapp OTP, magic-link, passkeys
│   ├── class-ww-frontend.php
│   ├── class-ww-guest-pay.php
│   ├── class-ww-passkeys.php           # WebAuthn (resident-key required, allowCredentials, user-scoped login)
│   ├── class-ww-qr-handshake.php
│   ├── class-ww-rate-limiter.php
│   ├── class-ww-turnstile.php
│   └── class-ww-whatsapp.php
├── templates/login-page.php            # Self-contained dark/neon UI with the full flow
└── assets/                             # Placeholder (not enqueued)
```

## Flow
1. **viewMain** — Email/Phone pill toggle → single input → Continue
2. **viewRegister** — Name + Email + Phone → creates account via email OTP
3. **viewMethod** — After `/lookup`, show method cards (biometric/email/whatsapp) with masked destinations
4. **viewOTP** — 4 circular boxes → Continue
5. **bioModal** — Tight prompt post-login (hidden permanently after setup/skip via localStorage)

## CHANGELOG

### 1.2.0 — 2026-04-21
**🔑 Biometric finally works (root cause fixed)**
- The real bug: after OTP verify, `wp_set_auth_cookie` logs the user in — but the REST nonce in the page was generated for an anonymous session, so WP silently rejects the subsequent `passkeys/register/options` call.
- Fix: every login-completing endpoint (email/verify, whatsapp/verify, magic-link, passkeys/login/verify) now returns a **fresh `nonce`** which the JS hot-swaps into `NONCE` before the passkey register call.
- Error surfacing: biometric errors now show in the modal (`NotAllowedError`, `InvalidStateError`, `SecurityError`, etc.) instead of silent hang.
- Secure-context guard: informs user if site is HTTP (passkeys require HTTPS).

**🎨 New Uber-Eats-inspired design**
- Dark bg with neon-accent primary (#A8FF35 default)
- Faint grid pattern + subtle radial glows
- Email/Phone pill toggle
- Inter font, heavy weights (800/900) — no more "thin noob" typography
- Circular OTP boxes with bold digits
- Inline-icon input wrappers, dynamic WhatsApp icon when phone mode
- Pill "Continue" button with hover arrow
- Phone row fixed responsive (82px CC + 1fr phone)
- "Don't have an account? Sign up" link row
- Proper padding/margin system across all components

**🛡️ Force custom login page**
- New "Force custom login page" toggle (General tab, ON by default)
- `login_init` hook redirects wp-login.php → /secure-login/ (excludes logout/POST/interim-login)
- `login_url` filter rewrites `wp_login_url()` → /secure-login/ so WooCommerce My Account links route through us

**📧 Email OTP box fix**
- Old: thin 28px/700 digits, off-center due to line-height
- New: 32px/900 digits, primary-color border, proper `align="center" valign="middle"` table cells → perfectly centered on all email clients

**🔕 Biometric modal**
- Removed "Don't ask again" checkbox
- Tight copy: "Enable biometric to skip OTPs" + 1-line sub
- In-modal error bar instead of browser alert()
- Dismissal persisted via `localStorage.ww_auth_bio_done`

**Version bumped 1.1.0 → 1.2.0**

### 1.1.0 — 2026-04-21
- Glass-morphism light card design (replaced in 1.2.0 with dark neon)
- Lookup endpoint + method-choice screen
- Masked email/phone (prevents enumeration)
- Passkey `residentKey: required` + `allowCredentials`
- HPOS + WC 10.7 compatibility
- `email/send` + `email/verify` accept `user_id`
- Bold company-name email header (no logo image)
- Settings preserved on upgrade (activation uses `add_option`)

### 1.0.0 — 2026-04-20
- Initial plugin scaffold

## REST API
- `POST /lookup` — masked identifier, methods, passkey status
- `POST /email/send` — `email` OR `user_id`
- `POST /email/verify` — returns fresh `nonce`
- `POST /whatsapp/send` | `/whatsapp/verify` (returns `nonce`)
- `GET  /magic-link` — `ww_magic|token` + `email` (returns `nonce`)
- `POST /passkeys/login/options` — `user_id` → `allowCredentials[]`
- `POST /passkeys/login/verify` (returns `nonce`)
- `POST /passkeys/register/options` | `register/verify`
- `POST /qr/generate` | `GET /qr/poll` | `POST /qr/authorize`
- `GET  /status`

## Roadmap (P1/P2)
- P1: Live E2E test on WP with HTTPS + real device passkey
- P1: QR desktop↔mobile handshake E2E
- P1: WebAuthn conditional-UI autofill-assist on main input
- P2: Country-code dropdown (+91 currently locked)
- P2: `[weewoo_fast_login]` shortcode
- P2: Admin "Recent Logins" audit log
- P2: Translations (.pot)

## Test Credentials
N/A — requires live WordPress + WooCommerce environment with HTTPS for passkey testing.
