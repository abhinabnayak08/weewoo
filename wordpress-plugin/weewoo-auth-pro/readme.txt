=== WeeWoo Auth Pro ===
Contributors: weewoo
Tags: authentication, login, otp, whatsapp, passkeys, webauthn, woocommerce, 2fa
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Premium mobile-first authentication plugin with WhatsApp OTP, Passkeys (WebAuthn), QR Code login, and WooCommerce guest checkout bypass.

== Description ==

WeeWoo Auth Pro is a premium authentication solution designed for modern WooCommerce stores. It provides multiple secure login methods with a beautiful, mobile-first interface.

= Features =

* **WhatsApp OTP** - Send verification codes via WhatsApp using Meta Cloud API
* **Email OTP** - Traditional email-based one-time passwords
* **Passkeys (WebAuthn)** - Biometric authentication with Face ID, Touch ID, or security keys
* **QR Code Login** - Desktop-to-mobile login bridge
* **WooCommerce Guest Pay** - Bypass login for order payment links
* **Cloudflare Turnstile** - Bot protection without annoying CAPTCHAs
* **Rate Limiting** - IP-based brute force protection
* **Beautiful UI** - Studio White design inspired by modern Fintech apps

= Requirements =

* WordPress 6.0 or higher
* PHP 8.0 or higher
* WooCommerce 7.0 or higher (for WooCommerce features)
* Meta Business Account (for WhatsApp OTP)
* Cloudflare Account (for Turnstile, optional)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/weewoo-auth-pro/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to WeeWoo Auth in the admin menu to configure settings
4. Visit `/secure-login/` to see your new login page

== Configuration ==

= Meta Cloud API (WhatsApp) =

1. Create a Meta Business Account
2. Set up a WhatsApp Business API phone number
3. Create an Authentication message template
4. Get your Phone Number ID and Access Token
5. Enter credentials in WeeWoo Auth > Meta API tab

= Cloudflare Turnstile =

1. Log into Cloudflare Dashboard
2. Go to Turnstile section
3. Create a new site widget
4. Copy Site Key and Secret Key
5. Enter in WeeWoo Auth > Security tab

== Frequently Asked Questions ==

= Does this replace the default WordPress login? =

No, this plugin provides an alternative login page at `/secure-login/`. You can redirect users there or link to it from your site.

= Is WhatsApp OTP free? =

Meta charges per message sent. Check Meta Business pricing for current rates.

= Do Passkeys work on all devices? =

Passkeys require a compatible browser and device. Most modern smartphones and computers support WebAuthn.

== Changelog ==

= 1.0.0 =
* Initial release
* WhatsApp OTP via Meta Cloud API v19+
* Email OTP authentication
* Passkeys (WebAuthn) support
* QR Code desktop-to-mobile login
* WooCommerce guest pay bypass
* Cloudflare Turnstile integration
* IP-based rate limiting
* Admin settings with 4 tabs

== Upgrade Notice ==

= 1.0.0 =
Initial release of WeeWoo Auth Pro.
