# WeeWoo Auth Pro — Product Requirements Document

## Original Problem Statement
Premium passwordless WordPress auth plugin, Digits replacement, mobile-first for WooCommerce.

## Architecture
```
/app/wordpress-plugin/weewoo-auth-pro/
├── weewoo-auth-pro.php              # Bootstrap, rewrite, login/logout redirects, HPOS
├── admin/class-ww-auth-settings.php # 4-tab admin
├── includes/
│   ├── class-ww-auth-api.php        # REST: /lookup, /me, OTP, magic-link, passkeys (token+cookie auth)
│   ├── class-ww-admin-2fa.php       # NEW: admin password → OTP 2FA intercept
│   ├── class-ww-frontend.php
│   ├── class-ww-guest-pay.php
│   ├── class-ww-passkeys.php
│   ├── class-ww-qr-handshake.php
│   ├── class-ww-rate-limiter.php
│   ├── class-ww-turnstile.php
│   └── class-ww-whatsapp.php
└── templates/login-page.php         # Self-contained single-input auto-detect UI
```

## CHANGELOG

### 1.3.0 — 2026-04-21
**🔑 Biometric — real root cause fix**
- The prior nonce fix wasn't enough on some hosts (SameSite cookie + REST REST nonce mismatch right after wp_set_auth_cookie). NEW: login endpoints issue a short-lived **`ww_token`** (15 min transient). JS sends it as `X-WW-Auth-Token` header on passkey register calls. New permission callback `check_logged_in_or_token` accepts either cookie-auth OR token → biometric now works regardless of cookie quirks.
- Modal now surfaces the real server response (code + message) instead of a generic "couldn't initialise".

**🎯 Single-input auto-flow (as requested)**
- Removed Email/Phone pill toggle. **Single input** with dynamic icon (mail by default, WhatsApp when user types digits).
- Label auto-switches to "WhatsApp Number" on mobile when phone is typed.
- On Continue:
  - email → email OTP straight
  - phone + WhatsApp enabled → WA OTP straight
  - phone + WhatsApp disabled → email OTP (to user's registered email, masked in UI)

**🎯 Layout fixes**
- `align-items: center` on body → vertically centered card
- Brand header **centered** (no longer left-aligned)
- Phone input overflow fixed on small screens (grid `72px 1fr` at ≤420px)

**🔀 Force-redirect everywhere**
- `login_init` → `/secure-login/` (wp-login.php)
- `login_url` filter → all `wp_login_url()` calls route to us (covers Minimog/theme login buttons)
- `template_redirect` → `/my-account/` (unauthed) redirects to `/secure-login/` with `redirect_to` preserved → **fixes theme showing its own login form on My Account page**
- `logout_redirect` filter + `wp_logout` action → logout goes to `/secure-login/`
- `woocommerce_login_redirect` + `woocommerce_registration_redirect` filters → honor `redirect_to` so user returns to cart/checkout/home after login

**🔒 Admin 2FA (new!)**
- New `class-ww-admin-2fa.php`. When the Admin 2FA toggle is ON (General tab) and an admin submits a valid password, we:
  1. Don't auto-login
  2. Issue a pending-2FA token
  3. Redirect to `/secure-login/?ww_2fa=TOKEN`
- New `/view2FA` screen with **Email / WhatsApp channel picker** → Send Code → 4-digit OTP → verify → admin-login.
- New REST endpoints: `POST /admin-2fa/send`, `POST /admin-2fa/verify`.

**👤 Already-logged-in short-circuit**
- On page load, `/me` checks session. If logged in, show *"You're already signed in"* card with Continue / Sign out → no re-login prompt.

**🐞 Existing-user → signup bug fixed**
- Phone lookup now uses wildcard LIKE (`'%98765%'`) + normalises digits. Previously `LIKE '9876543210'` without `%` wouldn't match stored `+91 98765 43210`.
- Register form now **double-checks** (email + phone) via lookup before creating → never enrolls an existing customer.

**🛒 Checkout verification (#4)**
- New "Verify WhatsApp/Email on checkout" toggle (General tab). Option is registered & persists on upgrade. Enforcement hook to be wired in 1.4 (currently stores preference only).

**📧 OTP email polish**
- 32px / weight-900 digits, primary-color border, table `align/valign=center` → perfectly centered across all mail clients.

**🛡️ Security hardening**
- All REST inputs `sanitize_*`'d and typed (already did; audited again)
- `hash_equals` for OTP/token comparisons (timing-safe)
- `X-Content-Type-Options: nosniff` and `Referrer-Policy: same-origin` on login page
- Parameterised SQL everywhere (`$wpdb->prepare` + `esc_like`)
- HTML escaping on every echo; `escHtml` JS helper for dynamic DOM

**Version bumped 1.2.0 → 1.3.0**

### Older changelog
- 1.2.0: Uber-Eats neon design, force login toggle, circular OTP, nonce-refresh
- 1.1.0: Glass-morphism light, lookup endpoint, masked identifiers, passkey residentKey/allowCredentials, HPOS, WC 10.7
- 1.0.0: Initial scaffold

## REST API (current)
- `GET /me` — current user (short-circuit)
- `POST /lookup` — masked identifier, methods, passkey
- `POST /check-user` — legacy
- `POST /email/send` | `/email/verify` — accepts `user_id` or `email`; returns `ww_token` + `nonce`
- `POST /whatsapp/send` | `/whatsapp/verify` — returns `ww_token` + `nonce`
- `GET /magic-link` — returns `ww_token` + `nonce`
- `POST /passkeys/login/options` — with `user_id` → `allowCredentials[]`
- `POST /passkeys/login/verify` — returns `ww_token` + `nonce`
- `POST /passkeys/register/options` | `/register/verify` — **accept cookie auth OR `X-WW-Auth-Token` header**
- `POST /admin-2fa/send` | `/admin-2fa/verify`
- `POST /qr/generate` | `GET /qr/poll` | `POST /qr/authorize`

## Roadmap (P1/P2)
- P1: Wire checkout-verification toggle (intercept `woocommerce_checkout_process` → OTP confirm)
- P1: QR desktop↔mobile E2E test
- P1: WebAuthn conditional-UI autofill
- P2: International country-code dropdown
- P2: `[weewoo_fast_login]` shortcode
- P2: Admin "Recent Logins" log viewer

## Test Credentials
N/A — needs live WP + HTTPS + real device for passkey.
