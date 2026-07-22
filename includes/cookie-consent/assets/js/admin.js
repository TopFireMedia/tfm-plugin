/**
 * TFM Cookie Consent - Admin JavaScript
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        TFM_Cookie_Consent_Admin.init();
    });

    // Main admin object
    var TFM_Cookie_Consent_Admin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.initTabs();
            this.initColorPickers();
            this.initCategoryManagement();
            this.initExportFunctionality();
            this.bindEvents();
        },

        /**
         * Initialize tab navigation
         */
        initTabs: function() {
            // Show first tab by default
            $('.tab-content').first().addClass('active');
            $('.nav-tab').first().addClass('nav-tab-active');

            // Tab click handler
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                var target = $(this).attr('href');
                
                // Update active tab
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Show target content
                $('.tab-content').removeClass('active');
                $(target).addClass('active');
            });
        },

        /**
         * Initialize color pickers
         */
        initColorPickers: function() {
            $('input[type="color"]').on('change', function() {
                // Update preview if needed
                var color = $(this).val();
                var fieldName = $(this).attr('name');
                
                // You can add live preview updates here
                console.log('Color changed:', fieldName, color);
            });
        },

        /**
         * Initialize category management
         */
        initCategoryManagement: function() {
            // Add new category
            $('#add-category').on('click', function(e) {
                e.preventDefault();
                var categoryKey = 'custom_' + Date.now();
                var categoryHtml = `
                    <div class="cookie-category" data-key="${categoryKey}">
                        <h3>New Category</h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="category_${categoryKey}_name">Category Name</label>
                                </th>
                                <td>
                                    <input type="text" id="category_${categoryKey}_name" name="tfm_cookie_consent_settings[cookie_categories][${categoryKey}][name]" value="New Category" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="category_${categoryKey}_description">Description</label>
                                </th>
                                <td>
                                    <textarea id="category_${categoryKey}_description" name="tfm_cookie_consent_settings[cookie_categories][${categoryKey}][description]" rows="3" class="large-text">Description for this category.</textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="category_${categoryKey}_required">Required</label>
                                </th>
                                <td>
                                    <input type="checkbox" id="category_${categoryKey}_required" name="tfm_cookie_consent_settings[cookie_categories][${categoryKey}][required]" value="1">
                                    <p class="description">Required categories cannot be disabled by users.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="category_${categoryKey}_enabled">Enabled by Default</label>
                                </th>
                                <td>
                                    <input type="checkbox" id="category_${categoryKey}_enabled" name="tfm_cookie_consent_settings[cookie_categories][${categoryKey}][enabled]" value="1">
                                    <p class="description">Whether this category is enabled by default in the customize view.</p>
                                </td>
                            </tr>
                        </table>
                        <button type="button" class="button remove-category">Remove Category</button>
                    </div>
                `;
                
                $('.cookie-categories-container').append(categoryHtml);
            });

            // Remove category
            $(document).on('click', '.remove-category', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to remove this category?')) {
                    $(this).closest('.cookie-category').remove();
                }
            });
        },

        /**
         * Initialize export functionality
         */
        initExportFunctionality: function() {
            $('#export-analytics').on('click', function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: tfm_cookie_consent_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'tfm_export_analytics',
                        nonce: tfm_cookie_consent_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Create download link
                            var dataStr = JSON.stringify(response.data, null, 2);
                            var dataBlob = new Blob([dataStr], {type: 'application/json'});
                            var url = window.URL.createObjectURL(dataBlob);
                            var link = document.createElement('a');
                            link.href = url;
                            link.download = 'tfm-cookie-consent-analytics-' + new Date().toISOString().split('T')[0] + '.json';
                            link.click();
                            window.URL.revokeObjectURL(url);
                        } else {
                            alert('Export failed: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Export failed. Please try again.');
                    }
                });
            });
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Clear discovered cookies
            $('#clear-discovered-cookies').on('click', function(e) {
                e.preventDefault();
                
                if (confirm('Are you sure you want to clear all discovered cookies? This action cannot be undone.')) {
                    $.ajax({
                        url: tfm_cookie_consent_admin.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'tfm_clear_discovered_cookies',
                            nonce: tfm_cookie_consent_admin.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Failed to clear discovered cookies.');
                            }
                        },
                        error: function() {
                            alert('Failed to clear discovered cookies. Please try again.');
                        }
                    });
                }
            });

            // Export cookie list
            $('#export-cookie-list').on('click', function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: tfm_cookie_consent_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'tfm_export_cookie_list',
                        nonce: tfm_cookie_consent_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Create download link
                            var dataStr = JSON.stringify(response.data, null, 2);
                            var dataBlob = new Blob([dataStr], {type: 'application/json'});
                            var url = window.URL.createObjectURL(dataBlob);
                            var link = document.createElement('a');
                            link.href = url;
                            link.download = 'tfm-cookie-list-' + new Date().toISOString().split('T')[0] + '.json';
                            link.click();
                            window.URL.revokeObjectURL(url);
                        } else {
                            alert('Export failed: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Export failed. Please try again.');
                    }
                });
            });

            // Clear blocked cookies (legacy)
            $('#clear-blocked-cookies').on('click', function(e) {
                e.preventDefault();
                
                if (confirm('Are you sure you want to clear all blocked cookies? This action cannot be undone.')) {
                    $.ajax({
                        url: tfm_cookie_consent_admin.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'tfm_clear_blocked_cookies',
                            nonce: tfm_cookie_consent_admin.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Failed to clear blocked cookies.');
                            }
                        },
                        error: function() {
                            alert('Failed to clear blocked cookies. Please try again.');
                        }
                    });
                }
            });

            // Form submission
            $('#tfm-cookie-consent-form').on('submit', function(e) {
                // Add loading state
                $('input[type="submit"]').prop('disabled', true).val('Saving...');
            });

            // Debug mode toggle
            $('#debug_mode').on('change', function() {
                var isEnabled = $(this).is(':checked');
                if (isEnabled) {
                    if (!$('.debug-mode-notice').length) {
                        $('<div class="debug-mode-notice">Debug mode is enabled. Check browser console for detailed logs.</div>').insertBefore('#tfm-cookie-consent-form');
                    }
                } else {
                    $('.debug-mode-notice').remove();
                }
            });
        }
    };

    // Make functions globally available
    window.TFM_Cookie_Consent_Admin = TFM_Cookie_Consent_Admin;

})(jQuery); 