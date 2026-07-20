=== TFM Custom Functions ===
Contributors: topfiremedia
Tags: custom functions, shortcodes, elementor, franchise, lead magnet, logging
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.0
Stable tag: 3.12.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A comprehensive plugin for TFM functionality including logging, video optimization, Elementor compatibility, franchise information, and GitHub-based automatic updates.

== Description ==

TFM Custom Functions is a powerful WordPress plugin designed to enhance website functionality with custom shortcodes, Elementor compatibility, franchise-specific features, and automatic updates from GitHub.

**Key Features:**

* **Elementor Compatibility** - Special shortcodes that work perfectly with Elementor link fields
* **Franchise Information** - Comprehensive financial and contact information fields
* **Lead Magnet System** - Easy file and image management for lead generation
* **Custom Shortcodes** - Over 15 shortcodes for dynamic content
* **GitHub Updates** - Automatic updates directly from your private GitHub repository
* **Activity Logging** - Optional logging system for debugging and monitoring
* **Video Optimization** - Defer video loading for better performance
* **Accessibility Tools** - UserWay integration for enhanced accessibility

**Shortcodes Available:**

*Contact Information:*
* `[phone]` - Displays formatted phone number (format selectable in settings)
* `[phone_text_link]` - Displays formatted phone number with clickable tel: link
* `[phone_link]` - Outputs tel: link for Elementor buttons
* `[phone_number]` - Outputs tel: link for Elementor Dynamic Tags
* `[email]` - Creates clickable mailto link
* `[full_address]` - Displays complete business address

*Franchise Financial Information:*
* `[estimated_initial_investment]` - Shows initial investment range
* `[minimum_liquid_capital]` - Displays minimum liquid capital requirement
* `[franchise_fee]` - Shows franchise fee amount
* `[net_worth]` - Displays net worth requirement
* `[average_unit_volume]` - Shows average unit volume

*General:*
* `[year]` - Current year
* `[site_title]` - Site title
* `[page_title]` - Current page title

*Lead Magnet:*
* `[lead_magnet_image]` - Displays promotional image
* `[lead_magnet_link]` - Creates complete download link
* `[lead_magnet_url]` - Outputs URL only (for Elementor buttons)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/tfm-plugin-main` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings->TFM Custom Functions screen to configure the plugin
4. Use the shortcodes in your content, Elementor widgets, or theme files

== Frequently Asked Questions ==

= How do I use the shortcodes? =

Simply add any shortcode to your content, Elementor widgets, or theme files. For example: `[phone]` will display your configured phone number.

= How do the Elementor-specific shortcodes work? =

Use `[phone_link]` and `[lead_magnet_url]` in Elementor link fields. These output raw URLs that Elementor can process correctly.

= How do I set up automatic updates? =

The plugin automatically checks for updates from your GitHub repository. Make sure your repository URL and access token are configured correctly.

= Can I customize the lead magnet display? =

Yes! Use the shortcode attributes to customize size, class, alt text, and more. Check the documentation for available attributes.

== Screenshots ==

1. Plugin settings page with tabbed interface
2. Contact information configuration
3. Franchise financial fields setup
4. Lead magnet management
5. Shortcode reference with copy buttons

== Changelog ==

= 3.12.11 =
* FIXED: Saving the News Settings screen no longer resets unrelated plugin settings.
* FIXED: `enable_shortcodes` now remains unchanged when toggling News, preventing shortcode registration from being unintentionally disabled.
* IMPROVED: Added a News-only settings context marker so only `enable_news` is updated from the News Settings form.

= 3.12.10 =
* FIXED: Deferred JavaScript default value (`defer_handles`) was set to `font-awesome,userway-widget` causing JS breakage on sites that had never explicitly saved that setting. The default is now empty — no handles are deferred until explicitly configured by an admin.
* FIXED: Custom Head Scripts and Custom Footer Scripts were being sanitised with `wp_kses`, which HTML-encoded `<`, `>`, and `=` characters in saved code. Now uses `wp_unslash()` to preserve code exactly as entered. **Note:** Code already saved through the previous broken sanitiser will still contain encoded characters and must be re-pasted after updating.
* FIXED: Removed incorrect `defer` attribute from inline (no `src`) script output in custom head/footer script fields. `defer` is only valid on external scripts and was being applied incorrectly.

= 3.12.9 =
* IMPROVED: Added author support to the `tfm_news` custom post type so Author is available in Quick Edit
* IMPROVED: News items now use standard WordPress publish/status controls in Quick Edit for users with permission

= 3.12.8 =
* NEW: News feature with dedicated News Settings submenu page under TFM Custom Functions
* NEW: `tfm_news` custom post type for managing outbound news/article cards
* NEW: Outbound URL and Source Name fields for News Items
* NEW: TFM News Elementor widget with grid layout, content toggles, query controls, and pagination options

= 3.12.7 =
* NEW: accessiBe trigger icon selector with official icon options
* NEW: accessiBe trigger size and trigger shape controls
* FIXED: Corrected invalid accessiBe trigger size configuration value
* IMPROVED: Added support for centered vertical trigger positioning in accessiBe settings

= 3.12.6 =
* NEW: accessiBe accessWidget integration in the Accessibility settings tab
* NEW: accessiBe settings for language, position, color, accessibility statement URL, and mobile visibility

= 3.12.5 =
* FIXED: `[lead_magnet_image]` shortcode now renders only the image instead of automatically linking to the file
* NEW: TFM Lead Magnet Elementor widget with optional link behavior and image styling controls

= 3.12.4 =
* FIXED: Improved phone number parsing when users paste formatted numbers with country code prefixes

= 3.12.3 =
* FIXED: Corrected an issue where sitemap functions were commented out due to incorrect placement within the plugin header block, leading to "undefined function" errors.

= 3.12.2 =
* FIXED: Corrected the placement of 'save_post' action hook for sitemap metabox to ensure exclusion settings persist.

= 3.12.1 =
* NEW: Environment Information section in Debug & Debloat tab
* NEW: System diagnostics showing PHP version, WordPress version, server info, memory limits, database version, and more
* NEW: Performance status indicators for all environment metrics
* NEW: System paths display for debugging file location issues
* NEW: TFM plugin status information for troubleshooting

= 3.12.0 =
* NEW: Debug & Debloat settings section with WordPress optimization features
* NEW: WordPress Revisions control - limit or completely disable post/page revisions
* NEW: Disable Emojis - remove WordPress emoji JavaScript and CSS for better performance
* NEW: Disable jQuery Migrate - prevent jQuery Migrate script loading to avoid conflicts
* NEW: Disable oEmbeds - stop WordPress from automatically embedding content from external sites
* IMPROVED: Settings page max-width increased to 1400px for better space utilization

= 3.11.0 =
* NEW: HTML Sitemap Generator - Generate user-friendly HTML sitemaps with granular exclusion controls
* NEW: [tfm_sitemap] shortcode for displaying HTML sitemaps
* NEW: Metabox in post/page editors to exclude individual content from HTML sitemap
* NEW: HTML Sitemap settings section with post type selection and display options
* NEW: Automatic caching and performance optimization for large sitemaps
* NEW: Hierarchical display for pages and category organization for posts

= 3.10.1 =
* FIXED: Phone formatter now uses xxx-xxx-xxxx format (removed parentheses) to prevent form validation failures
* FIXED: Updated input pattern, maxlength, and placeholder to match new format
* IMPROVED: Better line ending consistency in phone-formatter.js

= 3.10.0 =
* NEW: Phone number format selector with 4 display options (+1 (xxx) xxx-xxxx, +1-xxx-xxx-xxxx, (xxx) xxx-xxxx, xxx-xxx-xxxx)
* NEW: Phone number validation requiring minimum 10 digits on save
* NEW: [phone_text_link] shortcode that displays formatted phone with clickable tel: link
* NEW: Front-end phone formatter for Elementor Pro Forms, Gravity Forms, and Contact Form 7
* NEW: Auto-formatting phone inputs as (xxx) xxx-xxxx with 10-digit limit enforcement
* IMPROVED: [phone] shortcode now respects selected format from settings
* IMPROVED: Phone formatter handles multiple forms per page with form-scoped initialization
* IMPROVED: Cursor position management for better user experience when typing phone numbers

= 3.9.5 =
* FIXED: Removed UTF-8 BOM from plugin files to prevent potential output issues and improve compatibility
* MAINTENANCE: Ensured all PHP files use UTF-8 encoding without BOM for better cross-platform compatibility

= 3.9.4 =
* NEW: Complete Activity Log viewer redesign with human-readable event summaries and formatted messages
* NEW: Log entry templates system that converts technical actions into readable descriptions (e.g., "User signed in" instead of "user_login")
* NEW: Dashboard-style statistics cards showing events loaded, latest entry timestamp, log file size/location, and retention period
* NEW: Severity badges and contextual chips for quick visual identification of event types
* NEW: Expandable detail panels for each log entry showing full metadata (IP address, user agent, role, email, etc.)
* NEW: Quick search functionality and action type filtering in the Activity Log viewer
* IMPROVED: Enhanced log entries with user display name, role, and email for better context
* IMPROVED: DataTables theming with card-based design, pill-style pagination controls, and simplified sort indicators
* IMPROVED: Better visual hierarchy and spacing in the Activity Log interface

= 3.9.3 =
* NEW: Added dedicated Elementor widgets for phone and email that auto-populate from plugin settings
* NEW: Comprehensive styling controls for contact widgets including icon placement, spacing, and alignment
* IMPROVED: Elementor integration with proper asset loading, click-to-call behavior, and vertical centering
* UPDATED: Widget browser icons and notices to reflect saved contact information

= 3.9.2 =
* FIXED: Syntax errors and brace mismatches in plugin code
* FIXED: Corrupted arrow characters in admin help text
* IMPROVED: Code structure and formatting for better maintainability
* ENHANCED: Admin interface with clearer Elementor usage instructions

= 3.8.9 =
* FIXED: Elementor phone number shortcode compatibility
* Added [phone_number] shortcode for Elementor Dynamic Tags (outputs complete tel: links)
* Updated admin interface with Elementor Dynamic Tags usage instructions
* Improved Elementor integration using Dynamic Tags → Shortcode feature

= 3.8.8 =
* Final production release with all debug logging removed
* Clean, optimized codebase for maximum performance
* Streamlined updater class without debug overhead
* Professional-grade plugin ready for distribution

= 3.8.7 =
* Removed all debugging code for production-ready release
* Cleaned up error logging and debug statements
* Streamlined updater class for better performance
* Optimized plugin initialization process

= 3.8.6 =
* Fixed parameter count error in add_update_message method for PUC v5.6 compatibility
* Updated Plugin Update Checker to v5.6 for modern GitHub authentication
* Implemented singleton pattern to prevent slug conflicts

= 3.8.5 =
* Added singleton pattern to TFM_Updater class
* Fixed slug conflicts in Plugin Update Checker
* Improved error handling and debugging

= 3.8.4 =
* Updated Plugin Update Checker to latest version (v5.6)
* Fixed GitHub authentication to use Authorization headers instead of deprecated query parameters
* Improved private repository support with modern authentication
* Enhanced debugging capabilities

= 3.8.3 =
* Enhanced Elementor compatibility with new [phone_link] shortcode for tel: links
* Added [lead_magnet_url] shortcode for Elementor button link fields
* Fixed line break handling in [full_address] shortcode to properly display formatted addresses
* Added comprehensive franchisee financial fields (Estimated Initial Investment, Minimum Liquid Capital, Franchise Fee, Net Worth, Average Unit Volume)
* Improved admin UI with click-to-copy shortcode buttons throughout all tabs
* Enhanced media library integration for Login Logo upload
* Moved Business Address field from Franchisee tab to Contact tab for better organization
* Added help text and descriptions for all new fields

= 3.8.2 =
* Improved GitHub update system debugging
* Enhanced error handling for update checker initialization
* Fixed authentication issues with private repositories

= 3.8.1 =
* Added comprehensive franchisee financial information fields
* Implemented click-to-copy functionality for all shortcodes
* Enhanced admin interface with better organization
* Added full address field with proper formatting

= 3.8.0 =
* Major enhancement: Added franchisee-specific financial fields
* New shortcodes: [estimated_initial_investment], [minimum_liquid_capital], [franchise_fee], [net_worth], [average_unit_volume]
* Added full address field with [full_address] shortcode
* Enhanced Elementor compatibility with [phone_link] shortcode
* Improved admin UI with click-to-copy buttons
* Added comprehensive shortcode reference section
* Enhanced media library integration
* Moved Business Address to Contact tab
* Added help text for all fields

== Upgrade Notice ==

= 3.10.0 =
FEATURE UPDATE: Phone number formatting improvements, validation, new shortcode, and automatic front-end form formatting for Elementor/Gravity Forms/Contact Form 7. Phone numbers now auto-format as (xxx) xxx-xxxx in forms.

= 3.9.5 =
MAINTENANCE: Removed UTF-8 BOM from plugin files for improved compatibility and to prevent potential output issues. Recommended for all users.

= 3.9.4 =
MAJOR UPDATE: Complete Activity Log viewer redesign with human-readable event summaries, dashboard statistics, severity badges, expandable detail panels, and enhanced search/filtering capabilities. Makes auditing site activity much easier and more intuitive.

= 3.9.3 =
NEW FEATURES: Adds Elementor phone/email widgets that pull from plugin settings with full styling controls and improved click-to-call handling.

= 3.9.2 =
BUG FIXES: Fixed syntax errors, brace mismatches, and corrupted admin text. Improved code structure and admin interface clarity.

= 3.8.9 =
BUG FIX: Fixed Elementor phone number shortcode compatibility using Dynamic Tags. Added [phone_number] shortcode that outputs complete tel: links for Elementor button links.

= 3.8.8 =
Final production release! All debug logging removed for optimal performance. This is the recommended version for all production environments.

= 3.8.7 =
Production-ready release with all debugging code removed. Recommended for all users to improve performance and reduce log file clutter.

= 3.8.6 =
Fixed critical parameter count error that was causing fatal errors with the update system. This update ensures smooth operation with the latest Plugin Update Checker.

= 3.8.5 =
Important update that fixes slug conflicts in the Plugin Update Checker. Update to prevent potential issues with the update system.

= 3.8.4 =
Major update that fixes GitHub authentication issues and updates to the latest Plugin Update Checker version. Highly recommended for all users.

= 3.8.3 =
Enhanced Elementor compatibility and added comprehensive franchisee financial fields. Includes new shortcodes and improved admin interface.

== Support ==

For support and documentation, please visit [TopFireMedia.com](https://topfiremedia.com) or contact our support team.

== Privacy Policy ==

This plugin does not collect, store, or transmit any personal data. All configuration is stored locally in your WordPress database.
