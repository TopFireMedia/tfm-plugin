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

    /**
     * Format phone number as xxx-xxx-xxxx
     * @param {string} value - Input value
     * @returns {string} - Formatted phone number
     */
    function formatPhoneNumber(value) {
        // Remove all non-digit characters
        let cleaned = value.replace(/\D/g, '');

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
            let cleaned = pastedText.replace(/^\+?1[\s\-\(\)\.]*/, '').replace(/\D/g, '');
            
            // Format the pasted content
            this.value = formatPhoneNumber(cleaned);
            
            // Set cursor to end
            const length = this.value.length;
            this.setSelectionRange(length, length);
        });

        /**
         * Handle keydown - prevent invalid input and handle special keys
         */
        input.addEventListener('keydown', function(e) {
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
        input.setAttribute('pattern', '[0-9]{3}-[0-9]{3}-[0-9]{4}');
        input.setAttribute('maxlength', '12'); // 10 digits + 2 dashes
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
    function init() {
        // Priority 1: Elementor Pro Forms
        detectElementorForms();

        // Priority 2: Gravity Forms
        detectGravityForms();

        // Priority 3: Contact Form 7
        detectContactForm7();

        // Backward compatibility: Legacy phone-us class
        detectLegacyFields();
    }

    /**
     * MutationObserver to handle dynamically added forms
     */
    function setupMutationObserver() {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType !== 1) return; // Only element nodes

                    // Check if a form container was added
                    let formContainer = null;

                    // Check if the added node is a form container
                    if (node.matches && (
                        node.matches(CONFIG.formContainers.elementor) ||
                        node.matches(CONFIG.formContainers.gravity) ||
                        node.matches(CONFIG.formContainers.contactForm7)
                    )) {
                        formContainer = node;
                    } else {
                        // Check if the added node contains a form container
                        const elementorForm = node.querySelector && node.querySelector(CONFIG.formContainers.elementor);
                        const gravityForm = node.querySelector && node.querySelector(CONFIG.formContainers.gravity);
                        const cf7Form = node.querySelector && node.querySelector(CONFIG.formContainers.contactForm7);
                        
                        formContainer = elementorForm || gravityForm || cf7Form;
                    }

                    if (formContainer) {
                        // Determine form type and initialize
                        if (formContainer.matches(CONFIG.formContainers.elementor)) {
                            initializeFieldsInContainer(formContainer, CONFIG.selectors.elementor);
                        } else if (formContainer.matches(CONFIG.formContainers.gravity)) {
                            initializeFieldsInContainer(formContainer, CONFIG.selectors.gravity);
                        } else if (formContainer.matches(CONFIG.formContainers.contactForm7)) {
                            initializeFieldsInContainer(formContainer, CONFIG.selectors.contactForm7);
                        }
                    }

                    // Also check for legacy fields
                    if (node.querySelectorAll) {
                        node.querySelectorAll(CONFIG.selectors.legacy.join(',')).forEach(field => {
                            initializeFormatter(field);
                        });
                    }
                });
            });
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
