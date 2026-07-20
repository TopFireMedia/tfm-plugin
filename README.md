# TopFireMedia Plugin

A comprehensive WordPress plugin for TopFireMedia functionality including logging, video optimization, and more.

## Features

### Video Optimization
- Deferred loading of YouTube videos for improved page performance
- Support for Elementor Pro and Divi page builders
- Configurable loading thresholds and margins
- Performance tracking and debugging tools
- Automatic detection of video containers
- Smooth loading transitions with placeholders

### Logging System
- Comprehensive logging of user actions
- File-based logging with rotation
- Configurable log retention
- Secure log storage
- Admin interface for log viewing

### Security
- Activation failsafe system
- Environment compatibility checks
- Secure file handling
- Input sanitization
- Error tracking and reporting

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- Required PHP extensions: json, mbstring
- Elementor Pro 3.0+ (optional)
- Divi 4.0+ (optional)

## Installation

1. Upload the plugin files to `/wp-content/plugins/topfiremedia`
2. Activate the plugin through the WordPress admin interface
3. Configure the plugin settings in the TopFireMedia settings page

## Configuration

### Video Defer Settings
- Enable/disable video deferring
- Select supported page builders
- Configure loading thresholds
- Set root margin for intersection observer
- Enable/disable debug features

### Logging Settings
- Configure log retention period
- Set log file size limits
- Enable/disable specific log types
- Configure log rotation

## Usage

### Video Defer
The plugin automatically detects and defers YouTube videos in Elementor and Divi containers. Videos will only load when they come into view, improving page performance.

### Logging
The plugin automatically logs various user actions and system events. Logs can be viewed and managed through the plugin's admin interface.

### Shortcodes
The plugin provides various shortcodes for dynamic content:

#### Contact Information
- `[phone]` - Displays formatted phone number
- `[phone_link]` - Outputs sanitized phone number with +1 prefix for tel: links (Elementor compatible)
- `[email]` - Displays email as clickable mailto link
- `[full_address]` - Displays complete business address

#### Franchisee Financial Information
- `[estimated_initial_investment]` - Shows estimated initial investment range
- `[minimum_liquid_capital]` - Displays minimum liquid capital requirements
- `[franchise_fee]` - Shows franchise fee amount
- `[net_worth]` - Displays net worth requirements
- `[average_unit_volume]` - Shows average unit volume

#### General
- `[year]` - Current year
- `[site_title]` - Site title
- `[page_title]` - Current page title

#### Lead Magnet
- `[lead_magnet_image]` - Displays lead magnet promotional image
- `[lead_magnet_link]` - Creates download link for lead magnet file

## Changelog

### 3.12.11
- **Fixed News Settings Save Regression**: Saving the News Settings screen no longer resets unrelated plugin settings.
- **Fixed Shortcodes Being Disabled**: `enable_shortcodes` now remains unchanged when toggling News, so shortcode registration is not unintentionally disabled.
- **Improved Settings Isolation**: Added a News-only settings context marker so only `enable_news` is updated from the News Settings form.

### 3.12.10
- **Fixed Deferred JS Default**: The `defer_handles` default was `font-awesome,userway-widget`, breaking JS on sites that had never explicitly saved the setting. Default is now empty — nothing is deferred until an admin explicitly configures handles.
- **Fixed Custom Script Encoding**: Custom Head/Footer Scripts were sanitised with `wp_kses`, which HTML-encoded `<`, `>`, and `=` characters. Now uses `wp_unslash()` to preserve code exactly as entered. **Note:** Code already saved through the previous broken sanitiser will still contain encoded characters and must be re-pasted after updating.
- **Fixed Invalid `defer` on Inline Scripts**: Removed incorrect application of `defer` attribute to inline script blocks in custom head/footer fields.

### 3.12.9
- **Improved News Quick Edit Support**: Added author support to the `tfm_news` custom post type so the Author field is available in Quick Edit
- **Uses Standard Publish Controls**: News items now use standard WordPress publish/status controls in Quick Edit for users with permission

### 3.12.8
- **New News Feature**: Added a dedicated News Settings submenu page under TFM Custom Functions
- **New `tfm_news` Custom Post Type**: News items can now be managed separately from blog posts
- **New Outbound Article Fields**: Added Outbound URL and Source Name fields to News Items
- **New TFM News Elementor Widget**: Added a stylable Elementor widget for outbound news cards with query controls and pagination options

### 3.12.7
- **New accessiBe Trigger Controls**: Added trigger icon, size, and shape settings for the accessWidget button
- **Fixed accessiBe Trigger Configuration**: Corrected an invalid trigger size value in the widget configuration
- **Improved Position Options**: Added support for centered vertical positioning in accessiBe settings

### 3.12.6
- **New accessiBe Integration**: Added accessWidget support in the Accessibility settings
- **New accessiBe Settings**: Added controls for language, position, color, accessibility statement URL, and mobile visibility

### 3.12.5
- **Fixed Lead Magnet Image Shortcode**: `[lead_magnet_image]` now renders only the image without automatically linking to the file
- **New Lead Magnet Elementor Widget**: Added a stylable Elementor widget for lead magnet images with optional linking

### 3.12.4
- **Fixed Phone Formatter Paste Bug**: Improved phone number parsing when users paste formatted numbers with country code (e.g., +1(971)832-9247). The formatter now correctly handles various formatting styles including parentheses, dashes, and spaces after the country code prefix.

### 3.12.3
- **Fixed Undefined Function Error**: Resolved a fatal error where HTML sitemap functions were not being defined correctly, causing "undefined function" errors, particularly in the WordPress editor.

### 3.12.2
- **Fixed Sitemap Metabox Save**: Corrected the placement of the `save_post` action hook for the HTML sitemap metabox, ensuring that exclusion settings now persist correctly after saving a post.

### 3.12.1
- **New Environment Information Section**: Added comprehensive system diagnostics to the Debug & Debloat tab
  - PHP version with compatibility status
  - WordPress version with update status
  - Server software identification
  - Memory limits and performance indicators
  - Database version compatibility
  - Active plugins and themes information
  - PHP extensions status
  - System paths for debugging
  - TFM plugin status details

### 3.12.0
- **New Debug & Debloat Settings**: Complete WordPress optimization suite
  - WordPress Revisions control with limit/disable options
  - Disable Emojis to improve page load times
  - Disable jQuery Migrate to resolve conflicts with modern jQuery
  - Disable oEmbeds to prevent automatic content embedding
- **Improved Settings UI**: Increased max-width to 1400px for better space utilization

### 3.11.0
- **New HTML Sitemap Generator**: Complete HTML sitemap functionality with user-friendly interface
  - `[tfm_sitemap]` shortcode for easy integration
  - Metabox in post/page editors for granular exclusion control
  - Support for all public post types (posts, pages, custom post types)
  - Hierarchical display for pages and organized category display for posts
  - Configurable display options (show dates, post counts, etc.)
  - Automatic caching for performance optimization
  - Admin settings integration with existing TFM settings page
  - Backward compatible - feature disabled by default

### 3.10.1
- **Fixed Phone Formatter Parentheses Issue**: Changed front-end phone formatter from `(xxx) xxx-xxxx` to `xxx-xxx-xxxx` format to prevent form validation failures caused by parentheses in input fields
- **Updated Input Attributes**: Modified pattern, maxlength, and placeholder to match the new format
- **Improved Line Endings**: Fixed line ending consistency in phone-formatter.js

### 3.10.0
- **Phone Number Formatting System**:
  - Added phone format selector with 4 display options:
    - `+1 (xxx) xxx-xxxx`
    - `+1-xxx-xxx-xxxx`
    - `(xxx) xxx-xxxx`
    - `xxx-xxx-xxxx`
  - Phone number validation requiring minimum 10 digits on save
  - `[phone]` shortcode now respects selected format from settings
- **New Shortcode**: `[phone_text_link]` - Displays formatted phone number with clickable tel: link
- **Front-End Phone Formatter**:
  - Automatic phone number formatting for Elementor Pro Forms, Gravity Forms, and Contact Form 7
  - Formats as `(xxx) xxx-xxxx` with 10-digit limit enforcement
  - Handles multiple forms per page with form-scoped initialization
  - Smart cursor position management for better typing experience
  - Works with dynamically loaded forms via AJAX

### 3.9.5
- **Fixed UTF-8 BOM Issue**: Removed UTF-8 BOM from plugin files to prevent potential output issues and improve compatibility
- **Maintenance**: Ensured all PHP files use UTF-8 encoding without BOM for better cross-platform compatibility

### 3.9.4
- **Complete Activity Log Viewer Redesign**: 
  - Human-readable event summaries and formatted messages
  - Log entry templates system that converts technical actions into readable descriptions (e.g., "User signed in" instead of "user_login")
  - Dashboard-style statistics cards showing events loaded, latest entry timestamp, log file size/location, and retention period
  - Severity badges and contextual chips for quick visual identification of event types
  - Expandable detail panels for each log entry showing full metadata (IP address, user agent, role, email, etc.)
  - Quick search functionality and action type filtering in the Activity Log viewer
- **Enhanced Log Entries**: Added user display name, role, and email for better context
- **Improved UI/UX**: 
  - DataTables theming with card-based design
  - Pill-style pagination controls
  - Simplified sort indicators
  - Better visual hierarchy and spacing in the Activity Log interface

### 3.9.3
- **New Elementor Widgets**: Added dedicated Elementor widgets for phone and email that auto-populate from plugin settings
- **Comprehensive Styling Controls**: Contact widgets include icon placement, spacing, and alignment controls
- **Improved Elementor Integration**: Proper asset loading, click-to-call behavior, and vertical centering
- **Updated Widget Browser**: Icons and notices now reflect saved contact information

### 3.9.2
- **Fixed Syntax Errors**: Resolved brace mismatches and syntax issues in plugin code
- **Fixed Admin Text**: Corrected corrupted arrow characters in admin help text
- **Code Improvements**: Better structure and formatting for maintainability
- **Enhanced Admin Interface**: Clearer Elementor usage instructions

### 3.8.0
- **Enhanced Elementor Compatibility**: Added `[phone_link]` shortcode that outputs sanitized phone numbers with +1 prefix for use in Elementor link fields
- **New Franchisee Financial Fields**: Added comprehensive financial information fields with corresponding shortcodes:
  - `[estimated_initial_investment]` - Display estimated initial investment range
  - `[minimum_liquid_capital]` - Show minimum liquid capital requirements
  - `[franchise_fee]` - Display franchise fee amount
  - `[net_worth]` - Show net worth requirements
  - `[average_unit_volume]` - Display average unit volume
- **Full Address Field**: Added textarea field for complete business address with `[full_address]` shortcode
- **Improved Admin Interface**: 
  - New "Franchisee Info" tab in settings page
  - Click-to-copy functionality for all shortcodes
  - Comprehensive shortcode reference section
  - Enhanced help text and examples
- **Better User Experience**: All new fields include placeholder text and copy buttons for easy shortcode usage

### 3.7.1
- Enhanced `[lead_magnet_image]` shortcode to automatically wrap images in clickable links to lead magnet files
- Added new shortcode attributes: `link_class`, `target`, and `rel` for link customization
- Improved user experience: clicking lead magnet images now directly accesses the downloadable file
- Added smart linking that only activates when both image and file are configured
- Maintained backward compatibility with existing shortcode usage

### 3.7.0
- Added Lead Magnet settings (image and file upload) with media uploader
- New shortcodes: `[lead_magnet_image]` and `[lead_magnet_link]`
- Admin UI: persistent tabs via URL hash; shortcode tips in Lead Magnet tab
- Defer JS: added allowlist field to avoid third‑party conflicts
- Login page: configurable logo URL setting (no more hardcoded asset)
- Stability: fixed logout hook signature; standardized logs under uploads with stronger .htaccess
- Video Defer: removed duplicate logic, gated console logs behind debug flag

### 3.6.3
- Fixed JavaScript error in video defer functionality where selectors were undefined
- Added default selectors for Elementor and Divi video containers
- Added safety checks to prevent "Cannot convert undefined or null to object" error
- Added proper error handling for missing selectors
- Enhanced video defer initialization with fallback selectors
- Added debug logging for selector-related issues
- Improved error handling in video container detection

### 3.6.2
- Fixed function redeclaration errors when plugin is installed in multiple locations
- Added proper settings initialization to prevent undefined array key warnings
- Improved error handling during plugin activation
- Added output buffering to prevent "headers already sent" warnings
- Enhanced settings sanitization and validation

### 3.6.0
- Initial release with core functionality
- Video deferring system
- Activity logging
- Custom script management
- Accessibility tools integration
- Contact information shortcodes
- SVG upload support

## License

This plugin is proprietary software. All rights reserved.

## Support

For support, please contact support@topfiremedia.com 