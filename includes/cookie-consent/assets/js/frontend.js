/**
 * TFM Cookie Consent - Frontend JavaScript
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        // Enable debug mode based on admin setting
        window.tfmCookieConsentDebug = false; // Default to false
        
        // Check if debug mode is enabled in settings
        if (typeof tfm_cookie_consent !== 'undefined' && 
            tfm_cookie_consent.settings && 
            tfm_cookie_consent.settings.debug_mode) {
            window.tfmCookieConsentDebug = true;
            console.log('TFM Cookie Consent: Debug mode enabled via admin settings');
        } else {
            console.log('TFM Cookie Consent: Debug mode disabled');
        }
        
        TFM_Cookie_Consent_Frontend.init();
    });

    // Main frontend object
    var TFM_Cookie_Consent_Frontend = {
        
        /**
         * Plugin settings
         */
        settings: null,
        
        /**
         * Consent data
         */
        consentData: null,
        
        /**
         * Initialize frontend functionality
         */
        init: function() {
            this.settings = tfm_cookie_consent.settings;
            this.loadConsentData();
            
            if (!this.hasValidConsent()) {
                this.showPopup();
                // Test cookie blocking
                this.testCookieBlocking();
            } else {
                this.handleExistingConsent();
            }
            
            this.bindEvents();
            this.applyCustomColors();
        },

        /**
         * Load consent data from sessionStorage
         */
        loadConsentData: function() {
            try {
                var stored = sessionStorage.getItem('tfm_cookie_consent');
                if (stored) {
                    this.consentData = JSON.parse(stored);
                }
            } catch (e) {
                console.warn('Error loading consent data from sessionStorage:', e);
            }
        },

        /**
         * Save consent data to sessionStorage
         */
        saveConsentData: function(data) {
            try {
                sessionStorage.setItem('tfm_cookie_consent', JSON.stringify(data));
                this.consentData = data;
            } catch (e) {
                console.warn('Error saving consent data to sessionStorage:', e);
            }
        },

        /**
         * Check if user has valid consent
         */
        hasValidConsent: function() {
            if (!this.consentData) {
                return false;
            }

            // Check if consent has expired
            if (this.consentData.timestamp) {
                var expiryTime = this.consentData.timestamp + (this.settings.expiry_days * 24 * 60 * 60 * 1000);
                if (Date.now() > expiryTime) {
                    return false;
                }
            }

            return true;
        },

        /**
         * Show the consent popup
         */
        showPopup: function() {
            $('#tfm-cookie-consent').fadeIn(300);
            
            // Focus management for accessibility
            setTimeout(function() {
                $('#tfm-cookie-consent .tfm-cookie-consent__button--accept-all').focus();
            }, 350);
        },

        /**
         * Hide the consent popup
         */
        hidePopup: function() {
            $('#tfm-cookie-consent').fadeOut(300);
        },

        /**
         * Handle existing consent
         */
        handleExistingConsent: function() {
            if (this.consentData) {
                this.executeConsentActions(this.consentData);
            }
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Accept all cookies
            $(document).on('click', '.tfm-cookie-consent__button--accept-all', function(e) {
                e.preventDefault();
                self.acceptAll();
            });

            // Deny all cookies
            $(document).on('click', '.tfm-cookie-consent__button--deny-all', function(e) {
                e.preventDefault();
                self.denyAll();
            });

            // Customize cookies
            $(document).on('click', '.tfm-cookie-consent__button--customize', function(e) {
                e.preventDefault();
                self.showCustomizeView();
            });

            // Save preferences
            $(document).on('click', '.tfm-cookie-consent__button--save-preferences', function(e) {
                e.preventDefault();
                self.savePreferences();
            });

            // Back button
            $(document).on('click', '.tfm-cookie-consent__button--back', function(e) {
                e.preventDefault();
                self.showMainView();
            });

            // Close button
            $(document).on('click', '.tfm-cookie-consent__close', function(e) {
                e.preventDefault();
                self.hidePopup();
            });

            // Overlay click
            $(document).on('click', '.tfm-cookie-consent__overlay', function(e) {
                if (e.target === this) {
                    self.hidePopup();
                }
            });

            // Keyboard navigation
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#tfm-cookie-consent').is(':visible')) {
                    self.hidePopup();
                }
            });
        },

        /**
         * Accept all cookies
         */
        acceptAll: function() {
            var consentData = {
                timestamp: Date.now(),
                all_accepted: true,
                all_denied: false,
                categories: {}
            };

            // Set all categories to accepted
            if (this.settings.cookie_categories) {
                for (var category in this.settings.cookie_categories) {
                    consentData.categories[category] = true;
                }
            }

            this.saveConsent(consentData, 'accept_all');
        },

        /**
         * Deny all cookies
         */
        denyAll: function() {
            var consentData = {
                timestamp: Date.now(),
                all_accepted: false,
                all_denied: true,
                categories: {}
            };

            // Set all categories to denied except necessary
            if (this.settings.cookie_categories) {
                for (var category in this.settings.cookie_categories) {
                    consentData.categories[category] = this.settings.cookie_categories[category].required || false;
                }
            }

            this.saveConsent(consentData, 'deny_all');
        },

        /**
         * Show customize view
         */
        showCustomizeView: function() {
            $('.tfm-cookie-consent__buttons--main').hide();
            $('.tfm-cookie-consent__customize').show();
            
            // Focus on first toggle
            setTimeout(function() {
                $('.tfm-cookie-consent__category-toggle input:first').focus();
            }, 100);
        },

        /**
         * Show main view
         */
        showMainView: function() {
            $('.tfm-cookie-consent__customize').hide();
            $('.tfm-cookie-consent__buttons--main').show();
        },

        /**
         * Save preferences from customize view
         */
        savePreferences: function() {
            var consentData = {
                timestamp: Date.now(),
                all_accepted: false,
                all_denied: false,
                categories: {}
            };

            // Collect category preferences
            $('.tfm-cookie-consent__category-toggle input').each(function() {
                var category = $(this).closest('.tfm-cookie-consent__category').data('category');
                var checked = $(this).is(':checked');
                var required = $(this).is(':disabled');
                
                consentData.categories[category] = checked || required;
            });

            this.saveConsent(consentData, 'customize');
        },

        /**
         * Save consent data
         */
        saveConsent: function(consentData, actionType) {
            var self = this;
            
            // Show loading state
            this.showLoadingState();

            // Save to sessionStorage immediately
            this.saveConsentData(consentData);

            // Prepare blocked cookies data
            var blockedCookies = window.tfmBlockedCookies || [];
            if (window.tfmCookieConsentDebug) {
                console.log('TFM Cookie Consent: Sending blocked cookies to server:', blockedCookies.length);
            }

            // Send to server
            $.ajax({
                url: tfm_cookie_consent.ajax_url,
                type: 'POST',
                data: {
                    action: 'tfm_save_consent',
                    nonce: tfm_cookie_consent.nonce,
                    action_type: actionType,
                    categories: consentData.categories,
                    blocked_cookies: blockedCookies // ✅ Fixed: Send blocked cookies to server
                },
                success: function(response) {
                    if (response.success) {
                        self.hidePopup();
                        self.executeConsentActions(consentData);
                        self.showSuccessMessage();
                    } else {
                        self.showErrorMessage();
                    }
                },
                error: function() {
                    self.showErrorMessage();
                },
                complete: function() {
                    self.hideLoadingState();
                }
            });
        },

        /**
         * Execute actions based on consent
         */
        executeConsentActions: function(consentData) {
            // Handle cookie enabling based on consent type
            if (consentData.all_denied) {
                // All cookies denied - keep blocking
                if (window.tfmCookieConsentDebug) {
                    console.log('TFM Cookie Consent: Consent denied, keeping cookies blocked');
                }
            } else if (consentData.all_accepted) {
                // All cookies accepted - enable all cookies
                this.enableCookies();
            } else {
                // Customize mode - enable cookies for accepted categories only
                this.enableCookiesForCategories(consentData.categories);
            }
            
            // Execute custom scripts if provided
            if (this.settings.custom_scripts) {
                this.executeCustomScripts(consentData);
            }

            // Handle integrations
            this.handleIntegrations(consentData);
        },
        
        /**
         * Enable cookies after consent
         */
        enableCookies: function() {
            // Debug logging
            if (window.tfmCookieConsentDebug) {
                console.log('TFM Cookie Consent: Enabling cookies after consent');
            }
            
            // Set blocked cookies if any
            if (typeof document.setBlockedCookies === 'function') {
                if (window.tfmCookieConsentDebug) {
                    console.log('TFM Cookie Consent: Setting blocked cookies:', window.tfmBlockedCookies ? window.tfmBlockedCookies.length : 0);
                }
                document.setBlockedCookies();
            }
            
            // Mark cookie blocking as disabled
            window.tfmCookieBlockingDisabled = true;
            if (window.tfmCookieConsentDebug) {
                console.log('TFM Cookie Consent: Cookie blocking disabled');
            }
            
            if (window.tfmCookieConsentDebug) {
                console.log('TFM Cookie Consent: Cookies enabled successfully');
            }
        },

        /**
         * Enable cookies for specific categories only
         */
        enableCookiesForCategories: function(acceptedCategories) {
            if (window.tfmCookieConsentDebug) {
                console.log('TFM Cookie Consent: Enabling cookies for specific categories:', acceptedCategories);
            }
            
            // Store accepted categories for cookie blocking script to check
            window.tfmAcceptedCategories = acceptedCategories;
            
            // Set blocked cookies that belong to accepted categories
            if (typeof document.setBlockedCookiesForCategories === 'function') {
                document.setBlockedCookiesForCategories(acceptedCategories);
            }
            
            // Mark cookie blocking as selective (not fully disabled)
            window.tfmCookieBlockingSelective = true;
            if (window.tfmCookieConsentDebug) {
                console.log('TFM Cookie Consent: Cookie blocking set to selective mode');
            }
            
            if (window.tfmCookieConsentDebug) {
                console.log('TFM Cookie Consent: Cookies enabled for accepted categories');
            }
        },

        /**
         * Execute custom scripts
         */
        executeCustomScripts: function(consentData) {
            try {
                var script = this.settings.custom_scripts;
                
                // Replace placeholders
                for (var category in consentData.categories) {
                    var accepted = consentData.categories[category];
                    script = script.replace(new RegExp('\\{category:' + category + '\\}', 'g'), accepted);
                    script = script.replace(new RegExp('\\{category_' + category + '\\}', 'g'), accepted);
                }
                
                // Execute the script
                eval(script);
            } catch (e) {
                console.warn('Error executing custom script:', e);
            }
        },

        /**
         * Handle integrations
         */
        handleIntegrations: function(consentData) {
            var integrations = this.settings.integrations;
            
            // Google Analytics
            if (integrations.google_analytics && consentData.categories.analytics) {
                this.enableGoogleAnalytics();
            }
            
            // Google Tag Manager
            if (integrations.google_tag_manager && (consentData.categories.analytics || consentData.categories.marketing)) {
                this.enableGoogleTagManager();
            }
            
            // Facebook Pixel
            if (integrations.facebook_pixel && consentData.categories.marketing) {
                this.enableFacebookPixel();
            }
        },

        /**
         * Enable Google Analytics
         */
        enableGoogleAnalytics: function() {
            // This would typically involve loading GA scripts
            // For now, we'll just set a flag
            window.tfm_analytics_enabled = true;
        },

        /**
         * Enable Google Tag Manager
         */
        enableGoogleTagManager: function() {
            // This would typically involve loading GTM scripts
            window.tfm_gtm_enabled = true;
        },

        /**
         * Enable Facebook Pixel
         */
        enableFacebookPixel: function() {
            // This would typically involve loading Facebook Pixel scripts
            window.tfm_facebook_enabled = true;
        },

        /**
         * Show loading state
         */
        showLoadingState: function() {
            $('.tfm-cookie-consent__button').prop('disabled', true).text(tfm_cookie_consent.strings.saving || 'Saving...');
        },

        /**
         * Hide loading state
         */
        hideLoadingState: function() {
            $('.tfm-cookie-consent__button').prop('disabled', false);
            
            // Restore original button text
            $('.tfm-cookie-consent__button--accept-all').text(this.settings.accept_button_text);
            $('.tfm-cookie-consent__button--deny-all').text(this.settings.deny_button_text);
            $('.tfm-cookie-consent__button--customize').text(this.settings.customize_button_text);
            $('.tfm-cookie-consent__button--save-preferences').text('Save Preferences');
            $('.tfm-cookie-consent__button--back').text('Back');
        },

        /**
         * Show success message
         */
        showSuccessMessage: function() {
            // You could show a toast notification here
            if (window.tfmCookieConsentDebug) {
                console.log('Cookie preferences saved successfully');
            }
        },

        /**
         * Show error message
         */
        showErrorMessage: function() {
            // You could show an error notification here
            console.error('Error saving cookie preferences');
        },

        /**
         * Check if a specific category is allowed
         */
        isCategoryAllowed: function(category) {
            if (!this.consentData) {
                return false;
            }
            
            if (this.consentData.all_accepted) {
                return true;
            }
            
            return this.consentData.categories[category] || false;
        },

        /**
         * Get all allowed categories
         */
        getAllowedCategories: function() {
            if (!this.consentData) {
                return ['necessary'];
            }
            
            var allowed = ['necessary'];
            
            if (this.consentData.all_accepted) {
                for (var category in this.settings.cookie_categories) {
                    if (category !== 'necessary') {
                        allowed.push(category);
                    }
                }
            } else if (this.consentData.categories) {
                for (var category in this.consentData.categories) {
                    if (this.consentData.categories[category] && category !== 'necessary') {
                        allowed.push(category);
                    }
                }
            }
            
            return allowed;
        },

        /**
         * Clear consent data
         */
        clearConsent: function() {
            try {
                sessionStorage.removeItem('tfm_cookie_consent');
                this.consentData = null;
            } catch (e) {
                console.warn('Error clearing consent data:', e);
            }
        },

        /**
         * Apply custom colors to the popup
         */
        applyCustomColors: function() {
            var colors = this.settings.colors;
            if (!colors) return;

            var $popup = $('#tfm-cookie-consent');
            var $content = $popup.find('.tfm-cookie-consent__content');
            var $overlay = $popup.find('.tfm-cookie-consent__overlay');

            // Apply background colors
            if (colors.background_color) {
                $content.css('background-color', colors.background_color);
            }

            // Apply text colors
            if (colors.text_color) {
                $popup.find('.tfm-cookie-consent__title').css('color', colors.text_color);
                $popup.find('.tfm-cookie-consent__category-name').css('color', colors.text_color);
            }

            if (colors.secondary_text_color) {
                $popup.find('.tfm-cookie-consent__message').css('color', colors.secondary_text_color);
                $popup.find('.tfm-cookie-consent__category-description').css('color', colors.secondary_text_color);
            }

            // Apply border colors
            if (colors.border_color) {
                $popup.find('.tfm-cookie-consent__header').css('border-bottom-color', colors.border_color);
                $popup.find('.tfm-cookie-consent__category').css('border-color', colors.border_color);
            }

            // Apply button colors
            if (colors.accept_button_color) {
                $popup.find('.tfm-cookie-consent__button--accept-all').css('background-color', colors.accept_button_color);
            }
            if (colors.accept_button_text_color) {
                $popup.find('.tfm-cookie-consent__button--accept-all').css('color', colors.accept_button_text_color);
            }

            if (colors.deny_button_color) {
                $popup.find('.tfm-cookie-consent__button--deny-all').css('background-color', colors.deny_button_color);
            }
            if (colors.deny_button_text_color) {
                $popup.find('.tfm-cookie-consent__button--deny-all').css('color', colors.deny_button_text_color);
            }

            if (colors.customize_button_color) {
                $popup.find('.tfm-cookie-consent__button--customize').css('background-color', colors.customize_button_color);
            }
            if (colors.customize_button_text_color) {
                $popup.find('.tfm-cookie-consent__button--customize').css('color', colors.customize_button_text_color);
            }

            if (colors.save_button_color) {
                $popup.find('.tfm-cookie-consent__button--save-preferences').css('background-color', colors.save_button_color);
            }
            if (colors.save_button_text_color) {
                $popup.find('.tfm-cookie-consent__button--save-preferences').css('color', colors.save_button_text_color);
            }

            if (colors.back_button_color) {
                $popup.find('.tfm-cookie-consent__button--back').css('background-color', colors.back_button_color);
            }
            if (colors.back_button_text_color) {
                $popup.find('.tfm-cookie-consent__button--back').css('color', colors.back_button_text_color);
            }

            // Apply toggle colors
            if (colors.toggle_active_color) {
                $popup.find('.tfm-cookie-consent__toggle-slider').css('background-color', colors.toggle_active_color);
            }
            if (colors.toggle_inactive_color) {
                $popup.find('.tfm-cookie-consent__category-toggle input:not(:checked) + .tfm-cookie-consent__toggle-slider').css('background-color', colors.toggle_inactive_color);
            }

            // Apply category background colors
            if (colors.category_background_color) {
                $popup.find('.tfm-cookie-consent__category').css('background-color', colors.category_background_color);
            }

            // Apply overlay colors
            if (colors.overlay_color && colors.overlay_opacity !== undefined) {
                var opacity = colors.overlay_opacity / 100;
                var overlayColor = this.hexToRgba(colors.overlay_color, opacity);
                $overlay.css('background-color', overlayColor);
            }
        },

        /**
         * Convert hex color to rgba
         */
        hexToRgba: function(hex, alpha) {
            var r = parseInt(hex.slice(1, 3), 16);
            var g = parseInt(hex.slice(3, 5), 16);
            var b = parseInt(hex.slice(5, 7), 16);
            return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
        },

        /**
         * Test cookie blocking
         */
        testCookieBlocking: function() {
            if (window.tfmCookieConsentDebug) {
                console.log('TFM Cookie Consent: Testing cookie blocking');
            }
            
            // Clear any existing test cookie
            document.cookie = 'tfm_test_cookie=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
            
            // Try to set a test cookie - this should be blocked
            setTimeout(function() {
                if (window.tfmCookieConsentDebug) {
                    console.log('TFM Cookie Consent: Attempting to set test cookie...');
                }
                document.cookie = 'tfm_test_cookie=blocked_value; path=/';
                
                // Check if the cookie was set
                setTimeout(function() {
                    if (window.tfmCookieConsentDebug) {
                        console.log('TFM Cookie Consent: Checking if test cookie was blocked...');
                        console.log('TFM Cookie Consent: Current document.cookie:', document.cookie);
                    }
                    
                    // Check if cookie is in document.cookie
                    var testCookie = document.cookie.indexOf('tfm_test_cookie');
                    var wasBlocked = testCookie === -1;
                    
                    // Check if cookie is in blocked cookies array
                    var blockedCount = window.tfmBlockedCookies ? window.tfmBlockedCookies.length : 0;
                    var wasStored = blockedCount > 0;
                    
                    if (wasBlocked && wasStored) {
                        if (window.tfmCookieConsentDebug) {
                            console.log('TFM Cookie Consent: Test cookie was successfully blocked!');
                            console.log('TFM Cookie Consent: Blocked cookies count:', blockedCount);
                            console.log('TFM Cookie Consent: Blocked cookies:', window.tfmBlockedCookies);
                        }
                    } else if (wasBlocked && !wasStored) {
                        if (window.tfmCookieConsentDebug) {
                            console.log('TFM Cookie Consent: Test cookie was blocked but not stored!');
                        }
                    } else if (!wasBlocked && wasStored) {
                        if (window.tfmCookieConsentDebug) {
                            console.log('TFM Cookie Consent: Test cookie was stored but still appears in document.cookie!');
                            console.log('TFM Cookie Consent: Blocked cookies:', window.tfmBlockedCookies);
                        }
                    } else {
                        if (window.tfmCookieConsentDebug) {
                            console.log('TFM Cookie Consent: Test cookie was NOT blocked!');
                            console.log('TFM Cookie Consent: Cookie found in document.cookie at position:', testCookie);
                        }
                    }
                }, 100);
            }, 1000);
        }
    };

    // Make functions globally available
    window.TFM_Cookie_Consent_Frontend = TFM_Cookie_Consent_Frontend;

    // Provide a simple API for other scripts
    window.TFM_Cookie_Consent = {
        isAllowed: function(category) {
            return TFM_Cookie_Consent_Frontend.isCategoryAllowed(category);
        },
        getAllowed: function() {
            return TFM_Cookie_Consent_Frontend.getAllowedCategories();
        },
        clear: function() {
            TFM_Cookie_Consent_Frontend.clearConsent();
        }
    };

})(jQuery); 