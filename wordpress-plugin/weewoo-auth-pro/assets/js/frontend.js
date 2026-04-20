/**
 * WeeWoo Auth Pro - Frontend JavaScript
 * 
 * This file contains additional utilities and can be extended
 * for custom implementations. Core functionality is in the template.
 */

(function() {
    'use strict';

    // Extend window.wwAuth if needed
    window.wwAuthUtils = window.wwAuthUtils || {};

    /**
     * Phone number formatter
     */
    wwAuthUtils.formatPhone = function(input) {
        let value = input.value.replace(/\D/g, '');
        
        if (value.length > 0) {
            if (value.length <= 3) {
                value = value;
            } else if (value.length <= 6) {
                value = value.slice(0, 3) + ' ' + value.slice(3);
            } else {
                value = value.slice(0, 3) + ' ' + value.slice(3, 6) + ' ' + value.slice(6, 10);
            }
        }
        
        input.value = value;
    };

    /**
     * Email validator
     */
    wwAuthUtils.isValidEmail = function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    };

    /**
     * Phone validator (basic)
     */
    wwAuthUtils.isValidPhone = function(phone) {
        const cleaned = phone.replace(/\D/g, '');
        return cleaned.length >= 10 && cleaned.length <= 15;
    };

    /**
     * Copy to clipboard
     */
    wwAuthUtils.copyToClipboard = async function(text) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (err) {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            
            try {
                document.execCommand('copy');
                document.body.removeChild(textarea);
                return true;
            } catch (e) {
                document.body.removeChild(textarea);
                return false;
            }
        }
    };

    /**
     * Debounce function
     */
    wwAuthUtils.debounce = function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };

    /**
     * Check if WebAuthn is supported
     */
    wwAuthUtils.isWebAuthnSupported = function() {
        return window.PublicKeyCredential !== undefined &&
               typeof window.PublicKeyCredential === 'function';
    };

    /**
     * Check if platform authenticator is available (Face ID, Touch ID, etc.)
     */
    wwAuthUtils.isPlatformAuthenticatorAvailable = async function() {
        if (!this.isWebAuthnSupported()) {
            return false;
        }
        
        try {
            return await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
        } catch (e) {
            return false;
        }
    };

    /**
     * Convert ArrayBuffer to Base64URL
     */
    wwAuthUtils.arrayBufferToBase64Url = function(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary)
            .replace(/\+/g, '-')
            .replace(/\//g, '_')
            .replace(/=/g, '');
    };

    /**
     * Convert Base64URL to ArrayBuffer
     */
    wwAuthUtils.base64UrlToArrayBuffer = function(base64url) {
        const base64 = base64url
            .replace(/-/g, '+')
            .replace(/_/g, '/');
        const padding = '='.repeat((4 - base64.length % 4) % 4);
        const binary = atob(base64 + padding);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes.buffer;
    };

    /**
     * Detect if user is on mobile
     */
    wwAuthUtils.isMobile = function() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    };

    /**
     * Detect if user is on iOS
     */
    wwAuthUtils.isIOS = function() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent);
    };

    /**
     * Get device type for analytics/logging
     */
    wwAuthUtils.getDeviceType = function() {
        if (this.isMobile()) {
            if (this.isIOS()) {
                return 'ios';
            }
            return 'android';
        }
        return 'desktop';
    };

    /**
     * Simple analytics/event tracking hook
     */
    wwAuthUtils.trackEvent = function(eventName, eventData = {}) {
        // Can be extended to send to analytics
        if (typeof window.wwAuthOnEvent === 'function') {
            window.wwAuthOnEvent(eventName, eventData);
        }
        
        // Debug logging
        if (window.WW_AUTH_DEBUG) {
            console.log('[WeeWoo Auth]', eventName, eventData);
        }
    };

    // Auto-initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Track page load
        wwAuthUtils.trackEvent('login_page_loaded', {
            device: wwAuthUtils.getDeviceType(),
            webauthn_supported: wwAuthUtils.isWebAuthnSupported()
        });

        // Check for platform authenticator
        wwAuthUtils.isPlatformAuthenticatorAvailable().then(function(available) {
            wwAuthUtils.trackEvent('platform_auth_check', { available: available });
            
            // Could hide/show passkey button based on this
            if (!available) {
                const passkeyBtn = document.querySelector('[data-method="passkey"]');
                if (passkeyBtn) {
                    passkeyBtn.style.display = 'none';
                }
            }
        });
    });

})();
