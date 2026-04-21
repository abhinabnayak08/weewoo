# WeeWoo Auth Pro — Product Requirements Document

## Original Problem Statement
Build a premium, high-performance authentication WordPress plugin named **WeeWoo Auth Pro**, a standalone replacement for the Digits plugin for mobile-first auth on a WooCommerce store.

## Architecture (WordPress plugin)
```
/app/wordpress-plugin/weewoo-auth-pro/
├── weewoo-auth-pro.php                 # Bootstrap, rewrite rule, HPOS declaration
├── admin/class-ww-auth-settings.php    # 4-tab admin UI (General / Meta API / Security / Branding)
├── includes/
│   ├── class-ww-auth-api.php           # REST: lookup, check-user, email/whatsapp OTP, magic-link, QR, passkeys
│   ├── class-ww-frontend.php           # Branding / method helpers
│   ├── class-ww-guest-pay.php          # WC order-key bypass + billing auto-fill
│   ├── class-ww-passkeys.php           # WebAuthn (resident-key required, allowCredentials)
│   ├── class-ww-qr-handshake.php
│   ├── class-ww-rate-limiter.php
│   ├── class-ww-turnstile.php
│   └── class-ww-whatsapp.php
├── templates/login-page.php            # Self-contained glass-morphism UI + flow + JS
└── assets/{css,js,images}/             # Placeholder, NOT enqueued
```

## Flow (as shipped in 1.1.0)
1. **viewMain** — Email or WhatsApp number input with Login/Register pill tabs.
2. **viewMethod** — After `/lookup` matches user, show method choices:
   - Biometric (Fastest) — only if user has a passkey & device supports WebAuthn
   - Get code on Email — destination shown masked: `try*****weew@gmail.com`
   - Get code on WhatsApp — destination shown masked: `+91 98***43210`
3. **viewOTP** — 4-digit code entry; supports resend & magic-link auto-redeem.
4. **Biometric setup modal** — Shown post-login only if user has no passkey yet; offers
   "Enable Biometric Login" / "Skip for now" + "Don't ask again" checkbox.
   Dismissal persists via `localStorage` (`ww_auth_bio_done`, `ww_auth_bio_never`).

## REST API
- `POST /ww-auth/v1/lookup` — identifier + type → `{exists, user_id, display_name, masked_email, masked_phone, has_passkey, can_email, can_whatsapp}`
- `POST /ww-auth/v1/check-user` — legacy compat
- `POST /ww-auth/v1/email/send` — accepts `email` OR `user_id` (trust canonical email from DB)
- `POST /ww-auth/v1/email/verify` — accepts `email+otp` OR `user_id+otp`
- `POST /ww-auth/v1/whatsapp/send` | `/whatsapp/verify`
- `GET  /ww-auth/v1/magic-link` — accepts `token` or `ww_magic` + `email`
- `POST /ww-auth/v1/qr/generate` | `GET /qr/poll` | `POST /qr/authorize`
- `POST /ww-auth/v1/passkeys/register/options` | `register/verify`
- `POST /ww-auth/v1/passkeys/login/options` — accepts `user_id` → returns `allowCredentials`
- `POST /ww-auth/v1/passkeys/login/verify`

## CHANGELOG

### 1.1.0 — 2026-04-21
**Design overhaul**
- Glass-morphism premium UI: radial gradients + ambient orbs + frosted card
- Bold company-name brand header (shield logo removed)
- Login/Register pill tabs with animated indicator (Rotta-style)
- Mobile-responsive phone input (CSS grid, fixes overflow of +91 box)
- Dynamic input icon — WhatsApp icon only when the first character is a digit/+, mail icon otherwise
- Hover arrows on primary CTAs; smooth micro-interactions

**New 2-step flow**
- `lookup` endpoint returns masked email/phone + available methods
- Method-choice screen with 3 options (biometric / email OTP / WhatsApp OTP)
- Biometric card marked "Fastest" when available

**Security**
- Masked identifiers (`try*****weew@gmail.com`, `+91 98***43210`) — prevents enumeration of other users' contact details
- `email/send` + `email/verify` accept `user_id` so real email never leaves DB when lookup came via phone

**Biometric / Passkey fixes**
- Registration now uses `residentKey: 'required' + requireResidentKey: true` (widest support)
- Login options accept `user_id` and return `allowCredentials` → fixes "no credentials found"
- Removed forced `platform` authenticator (security keys + phone-as-key now supported)
- Post-login modal: premium perks list, "Skip for now" + "Don't ask again" with persistent dismissal
- Successful biometric login flags `ww_auth_bio_done` in localStorage → modal never shown again

**WooCommerce**
- Bumped `WC tested up to` header to 10.7
- HPOS + cart/checkout-blocks compatibility declared via `FeaturesUtil::declare_compatibility`

**Email template**
- Removed logo image, bold company-name text header (new Branding setting: `ww_auth_email_company_name`)
- Footer © uses bold company name

**Data preservation on upgrade**
- Activation uses `add_option` (only adds if missing) — existing settings are PRESERVED on update
- Admin settings form documented in PRD

**Plugin meta**
- Version bumped `1.0.0 → 1.1.0`

### 1.0.0 — 2026-04-20
- Initial scaffold, premium dark-glass login, check-user endpoint, passwordless flow, HPOS
- See full changelog on GitHub

## Roadmap (P1/P2)
- P1: Live test with real Meta Cloud API keys + Cloudflare Turnstile on a staging WP
- P1: Full QR desktop↔mobile E2E validation
- P1: Add WebAuthn conditional-UI autofill-assist to the main identifier input
- P2: Admin "Recent Logins" log viewer
- P2: Country-code dropdown (currently +91 locked)
- P2: "Fast Checkout" shortcode `[weewoo_fast_login]` for product pages
- P2: Translations (.pot generation)

## Test Credentials
N/A — requires live WordPress + WooCommerce.
