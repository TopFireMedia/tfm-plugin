/**
 * TFM Phone Formatter
 * Auto-formats phone inputs for Elementor Pro Forms, Gravity Forms, and Contact Form 7
 * Format: xxx-xxx-xxxx (US phone format)
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        format: 'xxx-xxx-xxxx',
        maxLength: 12, // 10 digits + 2 dashes
        selectors: {
            elementor: [
                '.elementor-field-type-tel input',
                'input[type="tel"]'
            ],
            gravity: [
                '.gfield_phone input',
                '.ginput_container_phone input',
                'input[type="tel"]'
            ],
            contactForm7: [
                '.wpcf7-tel input',
                'input[type="tel"]'
            ],
            // Backward compatibility
            legacy: [
                'input.phone-us'
            ]
        },
        formContainers: {
            elementor: '.elementor-form',
            gravity: '.gform_wrapper',
            contactForm7: '.wpcf7-form'
        }
    };

    // Track initialized fields to prevent duplicates
    const initializedFields = new WeakSet();

    const US_PATTERN = '[0-9]{3}-[0-9]{3}-[0-9]{4}';

    /**
     * Is the visitor entering a non-US number?
     *
     * A leading "+" is the visitor explicitly declaring a country code. If that
     * code is anything other than 1, US formatting is simply wrong: this
     * formatter keeps the first 10 digits, so "+44 20 7946 0958" became
     * "442-079-4609" - a real UK number turned into nonsense.
     *
     * That is not theoretical. Phone fields on at least one client site were
     * switched from Tel to Text specifically to escape this behaviour, which
     * left them with no formatting or validation at all and let short numbers
     * through. Recognising international input means a field can keep its
     * validation for US numbers without corrupting everyone else's.
     */
    function isInternational(value) {
        const compact = String(value == null ? '' : value).replace(/[\s\-().]/g, '');
        return compact.charAt(0) === '+' && compact.charAt(1) !== '1';
    }

    /**
     * Format phone number as xxx-xxx-xxxx
     * @param {string} value - Input value
     * @returns {string} - Formatted phone number
     */
    function formatPhoneNumber(value) {
        // Remove all non-digit characters
        let cleaned = value.replace(/\D/g, '');

        /*
         * Drop a single leading country code before truncating.
         *
         * Without this, an 11-digit number keeps its leading 1 and slice(0, 10)
         * cuts the REAL last digit instead: "+1 (555) 123-4567" became
         * 155-512-3456. The paste handler stripped "+1" itself, but `input` -
         * which is what fires for typing and for browser/iOS/Android autofill -
         * did not, so the two entry paths disagreed.
         *
         * Safe for US numbers: under the NANP an area code never begins with
         * 0 or 1, so a leading 1 on a 10- or 11-digit string is always the
         * country code and never part of the subscriber number.
         *
         * The threshold is 10, not 11, because of the keydown guard below: once
         * the field holds 10 digits it blocks further input, so a typed
         * "15551234567" never reaches 11 and an 11-only check never fired.
         * Stripping at 10 turns "1555123456" into a 9-digit "555-123-456",
         * which leaves room for the final keystroke. Shorter values are left
         * alone so a half-typed number is not rewritten under the user.
         */
        if (cleaned.length >= 10 && cleaned.charAt(0) === '1') {
            cleaned = cleaned.slice(1);
        }

        // Limit to 10 digits
        cleaned = cleaned.slice(0, 10);

        // Format as xxx-xxx-xxxx
        if (cleaned.length === 0) {
            return '';
        } else if (cleaned.length <= 3) {
            return cleaned;
        } else if (cleaned.length <= 6) {
            return cleaned.slice(0, 3) + '-' + cleaned.slice(3);
        } else {
            return cleaned.slice(0, 3) + '-' + cleaned.slice(3, 6) + '-' + cleaned.slice(6);
        }
    }

    /**
     * Initialize phone formatter on an input field
     * @param {HTMLInputElement} input - Input element to format
     */
    function initializeFormatter(input) {
        // Skip if already initialized or not a valid input
        if (!input || initializedFields.has(input) || input.tagName !== 'INPUT') {
            return;
        }

        // Mark as initialized
        initializedFields.add(input);

        // Store the last valid value for cursor position calculation
        let lastValue = '';

        /**
         * Get the character position for a given digit position in formatted string
         * Format: xxx-xxx-xxxx
         * @param {number} digitPosition - Position of digit (0-9)
         * @returns {number} - Character position in formatted string
         */
        function getCharPositionForDigit(digitPosition) {
            if (digitPosition < 0) return 0; // Start of string
            if (digitPosition < 3) return digitPosition; // First 3 digits
            if (digitPosition < 6) return digitPosition + 1; // After first dash
            return digitPosition + 2; // After second dash
        }

        /**
         * Handle input events - format as user types
         */
        input.addEventListener('input', function(e) {
            // International entry: leave the value exactly as typed and drop the
            // US pattern so the browser does not reject a valid foreign number.
            if (isInternational(this.value)) {
                this.removeAttribute('pattern');
                lastValue = this.value;
                return;
            }
            this.setAttribute('pattern', US_PATTERN);

            const cursorPosition = this.selectionStart;
            const oldValue = this.value;
            const oldDigits = oldValue.replace(/\D/g, '');
            const oldDigitCount = oldDigits.length;
            
            // Count digits before cursor in old value to determine digit position
            let digitsBeforeCursor = 0;
            for (let i = 0; i < Math.min(cursorPosition, oldValue.length); i++) {
                if (/\d/.test(oldValue[i])) {
                    digitsBeforeCursor++;
                }
            }
            
            // Format the value (this already limits to 10 digits)
            const formatted = formatPhoneNumber(this.value);
            const newDigits = formatted.replace(/\D/g, '');
            const newDigitCount = newDigits.length;
            
            this.value = formatted;
            
            // Calculate new cursor position based on digit position
            let newCursorPosition;
            
            // Determine if we're adding or removing
            const isAdding = newDigitCount > oldDigitCount;
            const isDeleting = newDigitCount < oldDigitCount;
            
            if (isAdding) {
                // User added a digit - cursor should be after the new digit
                newCursorPosition = getCharPositionForDigit(newDigitCount);
            } else if (isDeleting) {
                // User deleted - cursor should stay at the digit position we were at
                newCursorPosition = getCharPositionForDigit(digitsBeforeCursor - 1);
            } else {
                // No change in digit count (maybe formatting changed) - maintain relative position
                newCursorPosition = getCharPositionForDigit(digitsBeforeCursor);
            }
            
            // Ensure cursor position is within bounds
            newCursorPosition = Math.min(Math.max(1, newCursorPosition), formatted.length);
            this.setSelectionRange(newCursorPosition, newCursorPosition);
            
            lastValue = formatted;
        });

        /**
         * Handle paste events - format pasted content
         */
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedText = (e.clipboardData || window.clipboardData).getData('text');
            
            // Remove country code (+1) and any formatting characters that follow it
            // Handles formats like: +1(971)832-9247, +1-971-832-9247, +1 (971) 832-9247, etc.
            if (isInternational(pastedText)) {
                this.removeAttribute('pattern');
                this.value = pastedText.trim();
            } else {
                this.setAttribute('pattern', US_PATTERN);
                // formatPhoneNumber strips the country code itself, so paste and
                // typing/autofill go through exactly one implementation.
                this.value = formatPhoneNumber(pastedText);
            }
            
            // Set cursor to end
            const length = this.value.length;
            this.setSelectionRange(length, length);
        });

        /**
         * Handle keydown - prevent invalid input and handle special keys
         */
        input.addEventListener('keydown', function(e) {
            // A leading "+" starts international entry, so it must be typeable.
            if (e.key === '+' && this.selectionStart === 0) {
                return;
            }
            // Once the value is international, stop policing it: other countries
            // use different lengths, spacing and grouping than the NANP.
            if (isInternational(this.value)) {
                return;
            }
            // Allow: backspace, delete, tab, escape, enter
            if ([46, 8, 9, 27, 13].indexOf(e.keyCode) !== -1 ||
                // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true) ||
                // Allow: home, end, left, right, up, down
                (e.keyCode >= 35 && e.keyCode <= 40)) {
                return;
            }
            
            // Check if we already have 10 digits (excluding dashes)
            const currentDigits = this.value.replace(/\D/g, '');
            const isNumericKey = (e.keyCode >= 48 && e.keyCode <= 57) || (e.keyCode >= 96 && e.keyCode <= 105);
            
            // If we have 10 digits and user is trying to type a number, prevent it
            if (currentDigits.length >= 10 && isNumericKey && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                return;
            }
            
            // Ensure that it is a number and stop the keypress if not
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });

        // Set input attributes for better UX and validation
        input.setAttribute('type', 'tel');
        input.setAttribute('pattern', US_PATTERN);
        /*
         * No maxlength. Real browser autofill respects it, so a value arriving as
         * "+1 (555) 123-4567" (17 chars) was truncated by the browser before our
         * input handler ever saw it. The formatter caps the value at 10 digits
         * itself, and the pattern attribute below still validates the final
         * shape, so the attribute only ever cost us digits.
         */
        input.removeAttribute('maxlength');
        if (!input.getAttribute('placeholder')) {
            input.setAttribute('placeholder', '___-___-____');
        }
        input.setAttribute('autocomplete', 'tel');
        
        // Add data attribute to mark as formatted
        input.setAttribute('data-tfm-phone-formatted', 'true');
    }

    /**
     * Find and initialize phone fields within a form container
     * @param {HTMLElement} container - Form container element
     * @param {Array<string>} selectors - Array of CSS selectors to try
     */
    function initializeFieldsInContainer(container, selectors) {
        if (!container) return;

        selectors.forEach(selector => {
            try {
                const fields = container.querySelectorAll(selector);
                fields.forEach(field => {
                    // Only initialize if field is within this container (not nested forms)
                    if (container.contains(field)) {
                        initializeFormatter(field);
                    }
                });
            } catch (e) {
                // Invalid selector, skip
                console.warn('TFM Phone Formatter: Invalid selector', selector, e);
            }
        });
    }

    /**
     * Detect and initialize Elementor Pro Forms
     * Handles multiple forms per page by scoping to each form container
     */
    function detectElementorForms() {
        // Find all Elementor form containers (don't require elementorFrontend to be defined)
        const formContainers = document.querySelectorAll(CONFIG.formContainers.elementor);
        
        if (formContainers.length === 0) {
            return;
        }
        
        formContainers.forEach(container => {
            // Find phone fields within this specific form container
            // Try multiple selectors to catch different Elementor field types
            const selectors = [
                '.elementor-field-type-tel input',
                '.elementor-field-group-tel input',
                'input[type="tel"]',
                'input[name*="tel"]',
                'input[name*="phone"]'
            ];
            
            selectors.forEach(selector => {
                try {
                    const fields = container.querySelectorAll(selector);
                    fields.forEach(field => {
                        // Only initialize if field is within this container and is an input
                        if (container.contains(field) && field.tagName === 'INPUT') {
                            initializeFormatter(field);
                        }
                    });
                } catch (e) {
                    // Skip invalid selectors
                }
            });
        });

        // Also listen for Elementor form render events (for AJAX-loaded forms)
        if (typeof elementorFrontend !== 'undefined' && elementorFrontend.hooks) {
            elementorFrontend.hooks.addAction('frontend/element_ready/form.default', function($scope) {
                const container = $scope[0];
                if (container) {
                    const selectors = [
                        '.elementor-field-type-tel input',
                        '.elementor-field-group-tel input',
                        'input[type="tel"]',
                        'input[name*="tel"]',
                        'input[name*="phone"]'
                    ];
                    selectors.forEach(selector => {
                        try {
                            const fields = container.querySelectorAll(selector);
                            fields.forEach(field => {
                                if (container.contains(field) && field.tagName === 'INPUT') {
                                    initializeFormatter(field);
                                }
                            });
                        } catch (e) {
                            // Skip invalid selectors
                        }
                    });
                }
            });
        }
    }

    /**
     * Detect and initialize Gravity Forms
     * Handles multiple forms per page by scoping to each form wrapper
     */
    function detectGravityForms() {
        // Check if Gravity Forms is present
        if (typeof gform === 'undefined') {
            return;
        }

        // Find all Gravity Form wrappers
        const formWrappers = document.querySelectorAll(CONFIG.formContainers.gravity);
        
        formWrappers.forEach(wrapper => {
            // Find phone fields within this specific form wrapper
            CONFIG.selectors.gravity.forEach(selector => {
                try {
                    const fields = wrapper.querySelectorAll(selector);
                    fields.forEach(field => {
                        if (wrapper.contains(field)) {
                            initializeFormatter(field);
                        }
                    });
                } catch (e) {
                    // Skip invalid selectors
                }
            });
        });

        // Listen for Gravity Forms post-render event (for AJAX pagination and dynamic forms)
        if (typeof jQuery !== 'undefined') {
            jQuery(document).on('gform_post_render', function(event, formId, currentPage) {
                // Find the specific form that was rendered
                const formWrapper = document.querySelector('#gform_wrapper_' + formId);
                if (formWrapper) {
                    initializeFieldsInContainer(formWrapper, CONFIG.selectors.gravity);
                }
            });
        }
    }

    /**
     * Detect and initialize Contact Form 7
     * Handles multiple forms per page by scoping to each form container
     */
    function detectContactForm7() {
        // Find all Contact Form 7 form containers
        const formContainers = document.querySelectorAll(CONFIG.formContainers.contactForm7);
        
        formContainers.forEach(container => {
            // Find phone fields within this specific form container
            CONFIG.selectors.contactForm7.forEach(selector => {
                try {
                    const fields = container.querySelectorAll(selector);
                    fields.forEach(field => {
                        if (container.contains(field)) {
                            initializeFormatter(field);
                        }
                    });
                } catch (e) {
                    // Skip invalid selectors
                }
            });
        });
    }

    /**
     * Find phone fields that are not declared as phone fields.
     *
     * Every detector above keys off something the form builder was told: an
     * input typed `tel`, an `elementor-field-type-tel` wrapper, or a name
     * containing "phone"/"tel". A field built as a plain Text field satisfies
     * none of them - and Elementor names its inputs `form_fields[field_353cb4f]`,
     * so the name carries no meaning either. The formatter then loads on the page
     * and silently does nothing.
     *
     * That is not hypothetical: on 3nativesacaicafefranchise.com both phone
     * fields are Text, so nothing formatted them, nothing capped their length and
     * nothing validated them - which is how a short number reached the CRM.
     *
     * So fall back to what the visitor actually sees: the placeholder, the
     * associated label, or the aria-label. A field a human reads as "Phone" is
     * treated as one however it was configured, which makes this self-healing
     * across sites we did not build the forms on.
     */
    function detectFieldsByVisibleLabel() {
        const LOOKS_LIKE_PHONE = /\b(phone|telephone|tel|mobile|cell)\b/i;
        // Types that can never be a phone field, plus ones we must not touch.
        const SKIP_TYPES = ['email', 'password', 'number', 'hidden', 'file',
                            'checkbox', 'radio', 'submit', 'button', 'date', 'url'];

        document.querySelectorAll('input').forEach(input => {
            if (SKIP_TYPES.indexOf((input.type || '').toLowerCase()) !== -1) return;

            let labelText = '';
            if (input.id) {
                const lbl = document.querySelector('label[for="' + CSS.escape(input.id) + '"]');
                if (lbl) labelText = lbl.textContent || '';
            }
            if (!labelText) {
                const wrapping = input.closest('label');
                if (wrapping) labelText = wrapping.textContent || '';
            }

            const haystack = [input.placeholder, labelText, input.getAttribute('aria-label')]
                .filter(Boolean).join(' ');

            if (LOOKS_LIKE_PHONE.test(haystack)) {
                initializeFormatter(input);
            }
        });
    }

    /**
     * Initialize legacy phone-us class fields (backward compatibility)
     */
    function detectLegacyFields() {
        document.querySelectorAll(CONFIG.selectors.legacy.join(',')).forEach(field => {
            initializeFormatter(field);
        });
    }

    /**
     * Main initialization function
     * Priority: Elementor Pro > Gravity Forms > Contact Form 7 > Legacy
     */
    /**
     * Catch-all: format every phone field on the page, regardless of which form
     * (or no form) it belongs to. initializeFormatter is idempotent (guarded by a
     * WeakSet), so fields already handled by the form-specific detectors below
     * are not re-initialized. This makes site-specific "format all tel inputs"
     * custom scripts unnecessary.
     */
    function detectAllTelFields() {
        document.querySelectorAll('input[type="tel"]').forEach(initializeFormatter);
    }

    function init() {
        // Format EVERY tel field on the page — including ones outside recognized
        // form builders (plain Elementor tel widgets, custom/HTML forms, etc.).
        detectAllTelFields();

        // Form-builder detectors additionally catch name*="phone"/name*="tel"
        // text inputs and hook AJAX form-render events for late-loading forms.
        detectElementorForms();
        detectGravityForms();
        detectContactForm7();

        // Backward compatibility: Legacy phone-us class (may not be type="tel").
        detectLegacyFields();

        // Last resort: fields a visitor reads as "Phone" but which were never
        // declared as phone fields. Runs last so declared fields are claimed by
        // the specific detectors first; initializeFormatter is idempotent.
        detectFieldsByVisibleLabel();
    }

    /**
     * MutationObserver to handle dynamically added forms
     */
    function setupMutationObserver() {
        // Coalesce mutations and process added nodes once per idle cycle instead
        // of running form detection synchronously on every DOM change.
        let pending = [];
        let scheduled = false;
        const schedule = window.requestIdleCallback
            || window.requestAnimationFrame
            || function (cb) { return setTimeout(cb, 200); };

        function processNode(node) {
            if (node.nodeType !== 1) return; // Only element nodes

            let formContainer = null;
            if (node.matches && (
                node.matches(CONFIG.formContainers.elementor) ||
                node.matches(CONFIG.formContainers.gravity) ||
                node.matches(CONFIG.formContainers.contactForm7)
            )) {
                formContainer = node;
            } else {
                const elementorForm = node.querySelector && node.querySelector(CONFIG.formContainers.elementor);
                const gravityForm = node.querySelector && node.querySelector(CONFIG.formContainers.gravity);
                const cf7Form = node.querySelector && node.querySelector(CONFIG.formContainers.contactForm7);
                formContainer = elementorForm || gravityForm || cf7Form;
            }

            if (formContainer) {
                if (formContainer.matches(CONFIG.formContainers.elementor)) {
                    initializeFieldsInContainer(formContainer, CONFIG.selectors.elementor);
                } else if (formContainer.matches(CONFIG.formContainers.gravity)) {
                    initializeFieldsInContainer(formContainer, CONFIG.selectors.gravity);
                } else if (formContainer.matches(CONFIG.formContainers.contactForm7)) {
                    initializeFieldsInContainer(formContainer, CONFIG.selectors.contactForm7);
                }
            }

            // Catch-all: any tel field in the added subtree (and the node itself
            // if it is one), plus legacy .phone-us fields.
            if (node.matches && node.matches('input[type="tel"]')) {
                initializeFormatter(node);
            }
            if (node.querySelectorAll) {
                node.querySelectorAll('input[type="tel"], ' + CONFIG.selectors.legacy.join(',')).forEach(field => {
                    initializeFormatter(field);
                });
            }
        }

        function flush() {
            scheduled = false;
            const nodes = pending;
            pending = [];
            nodes.forEach(processNode);
        }

        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) {
                        pending.push(node);
                    }
                });
            });
            if (pending.length && !scheduled) {
                scheduled = true;
                schedule(flush);
            }
        });

        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            init();
            setupMutationObserver();
        });
    } else {
        init();
        setupMutationObserver();
    }

    // Also run on window load for late-loading forms
    window.addEventListener('load', function() {
        init();
    });

})();
