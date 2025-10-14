=== SearchJet – AI-Powered Instant Search for WooCommerce & WordPress ===
Contributors: maidulcu
Tags: search, woocommerce, ajax, instant, analytics
Requires at least: 5.5
Tested up to: 6.8
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered instant search for WordPress & WooCommerce with typo tolerance and real-time analytics.

== Description ==

SearchJet delivers AI-powered instant search for WordPress and WooCommerce with millisecond performance and intelligent relevance scoring.

**Key Features:**

* **Lightning-Fast Search** - Instant AJAX results with typo tolerance
* **WooCommerce Ready** - Product search with SKU, price, and category matching
* **Real-Time Analytics** - Track top searches, zero-result queries, and usage
* **Customizable UI** - Multiple themes, colors, and display options with live preview
* **Smart Indexing** - Auto-sync with content updates and exclusion rules
* **Developer Friendly** - REST API, webhooks, and automation framework

**Perfect For:**
* E-commerce stores needing fast product search
* Content sites with large libraries
* Developers requiring search APIs
* Sites needing search analytics

**Advanced Features:**
* AI-enhanced relevance scoring
* Multilingual support (WPML/Polylang)
* Zero-result burst detection
* Custom post type support
* Mobile-optimized interface
* SEO-friendly implementation

🔎 [Live Demo](https://demo.searchjetengine.com/) | 📘 [Documentation](https://searchjetengine.com/docs) | 💼 [Pricing](https://searchjetengine.com/pricing)

== Privacy ==

SearchJet collects only necessary data for search indexing and analytics. No sensitive data like passwords or admin content is stored. All data transmission is encrypted. View our [Privacy Policy](https://searchjetengine.com/privacy) for details.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/searchjet` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings > SearchJet and enter your API key.

== Frequently Asked Questions ==

= Does this plugin work with WooCommerce? =
Yes, WooCommerce support is included in the Free plan.

= Can I track what users are searching for? =
Yes, SearchJet tracks and reports search usage and analytics to help you monitor how your site search is performing. You can view this data through the SearchJet.io dashboard.

= Does this plugin slow down my site? =
No. SearchJet is built for speed using async AJAX requests and optimized indexing. It runs independently of your main WordPress loop.

= Can I customize the search design? =
Yes. You can change font size, colors, borders, and more via the plugin settings. Developers can also override templates or apply custom CSS.

= Is this plugin compatible with all themes? =
Yes, SearchJet is designed to work with any well-coded WordPress theme. You may optionally customize the CSS to better match your design.

= How often is the index updated? =
The plugin updates the index in near real-time when you create, update, or delete posts, products, or custom content types.

= Can I exclude certain content from being indexed? =
Yes! You can exclude specific post types, post statuses (like drafts), password-protected posts, sticky posts, and specific MIME types (like images, PDFs) using the plugin settings. Additional rules can be configured via code filters or in your SearchJet dashboard (Pro).

SearchJet supports all public post types including WooCommerce products and custom content types.

= Does this plugin support multilingual search? =
Multilingual support is available via integration with compatible plugins like WPML or Polylang. We recommend using our Pro plan for advanced language handling.

= What are Automations and AI Agents? =
Automations are workflows within SearchJet that use built-in events and triggers to automate tasks such as monitoring search performance or managing indexing. AI Agents are intelligent assistants designed to enable future integrations with external platforms like n8n, Zapier, or custom AI workflows to extend SearchJet's capabilities.

= Can I connect SearchJet with external automation tools? =
Yes, SearchJet supports webhook triggers and REST API endpoints that allow you to connect with external automation tools. Plans are in place to integrate with popular platforms like n8n and Zapier, enabling seamless automation across your systems.

= What is Zero‑Result Burst detection? =
Zero‑Result Burst detection is an automation that monitors when multiple zero-result searches occur in a short period. This helps you identify gaps in your content or indexing issues so you can improve search relevance and user experience.




== Screenshots ==

1. Instant Ajax results with thumbnails, prices, SKUs, and category badges
2. SearchJet settings: Connection Tab
3. SearchJet settings: Index Tab
4. SearchJet settings: Contenct Exclusions
5. SearchJet settings: Advanced Settings
6. Search performance insights and usage statistics
7. Intelligent search optimization and automated improvements
8. Intelligent search optimization powered by AI agents

== Changelog ==
= 1.2.0 =
* Added comprehensive Search UI Customization with live preview
* New theme variants: Default, Dark Mode, Minimal, and Rounded
* Added primary color picker for custom branding
* Enhanced display options: toggle thumbnails, prices, categories, and excerpts
* Implemented live preview that updates in real-time as settings change
* Added keyboard navigation support (arrow keys, enter, escape)
* Improved accessibility with ARIA attributes and screen reader support
* Enhanced loading states with animated indicators
* Added click-outside-to-close functionality
* Improved error handling with user-friendly messages
* Added CSS custom properties for advanced theme customization
* Enhanced mobile responsiveness for search interface
* Improved admin interface with tabbed navigation and modern styling
* Added comprehensive documentation section with troubleshooting guides

= 1.1.9 =
* Hardened the AI Agents REST endpoints to require admin access and return clearer validation errors.
* Sanitized incoming analytics log payloads, capped backlog growth, and stored it without autoload to cut memory usage.
* Versioned frontend assets with the plugin version (while keeping `SCRIPT_DEBUG` support) to restore browser caching.

= 1.1.8 =
* Added support for indexing Gutenberg
* Added support for indexing Seo Plugin
* Added support for indexing WPML
* Added support for indexing ACF Meta

= 1.1.7 =
* Added support for indexing Elementor meta

= 1.1.6 =
* Added support for indexing Astra Theme meta

= 1.1.5 =
* Added support for indexing JetEngine meta

= 1.1.4 =
* Improved `formatCurrency` function with support for 30+ currencies
* Switched to WooCommerce-friendly number formatting using Intl.NumberFormat
* Ensured inline CSS support for styling controls like font size, border radius, and colors
* Added support for dynamic inline styles on frontend load
* Improved responsiveness and spacing when optional UI sections are hidden
* Added fallback behavior for missing SearchJet UI settings

= 1.1.3 =
* Added Lean GA4 Tracker integration with `search_performed` and `search_result_click` events
* Extended GA4 tracking to include product category, price, currency, and variant SKU
* Added new styling options (font size, colors, border radius, image size) to settings
* Inline CSS now generated dynamically based on saved settings for instant styling
* Centralized admin styles for all SearchJet admin pages
* Improved layout fallback behavior for hidden UI sections
* Fixed duplication bug on first search result in some cases
* Improved JavaScript currency formatting for localized price display

= 1.1.2 =
* Enhanced Analytics page design with modern card layout, progress bars, and cleaner tables
* Added visual improvements for top queries and zero-result queries display
* Improved compact table design for "Searches by day"
* Minor CSS enhancements for better admin UI consistency
* Improved price formatting with currency symbols.
* Fixed UI issue where sale and regular prices appeared confusingly together.
* Added CSS for sale price (highlighted in red) and regular price (strikethrough).

= 1.1.1 =

= 1.1.0 =
* Added Automations Core framework with Events, Registry, and REST integration
* Added "Zero-Result Burst" detection automation
* Added "Daily Health Snapshot" and "Index Summary" automation placeholders
* New Automations and AI Agents admin submenu pages under SearchJet
* Integrated webhook support for automation triggers
* Improved admin UI to support automation management
* Back-end structure for future integrations with n8n and Zapier
* General performance improvements and bug fixes

= 1.0.12 =
* Moved Analytics into its own admin submenu page under SearchJet
* Added dedicated render method for Analytics page to improve maintainability
* Updated admin script enqueue logic to work on new Analytics page
* Minor UI adjustments to settings page to remove Analytics tab

= 1.0.11 =
* Added support for saving and passing custom attributes via SearchJetSettings to frontend scripts
* Improved settings localization for both instant search dropdown and search page
* Enhanced bulk indexing logic to skip invalid posts with empty titles
* Expanded indexing to allow all public post types without restrictions
* Improved debugging logs for bulk indexing process
* Fixed issue where Laravel logs were not showing indexing data

= 1.0.10 =
* Added friendly admin notice recommending Lean GA4 Tracker for simple analytics integration
* Improved SearchJet settings page layout for better clarity
* Enhanced plugin compatibility with minimal install setups
* Minor bug fixes and UI refinements

= 1.0.9 =
* Added "Disable Search Rendering Mode override?" option in settings
* Improved dashboard analytics to display top and zero-result queries
* Added helper methods and caching for search mode logic
* Minor UI improvements to filters and onboarding flow

= 1.0.8 =
* Added support for `attributes[]` query param to control returned fields
* Fixed analytics panel to show top queries and zero result queries properly
* Improved error handling and logging on search store process

= 1.0.7 =
* Improved reindexing logic: full index reset now only occurs on first batch
* Prevented stale data retention by ensuring clean index on full reindex
* Added `fullReindex` control to prevent accidental wipes during partial sync
* Optimized admin and JS integration for accurate progress and status updates

= 1.0.6 =
* Integrated external search query override
* Added “Connect to SearchJet” onboarding button in settings

= 1.0.5 =
* Added usage progress bars to admin settings page
* Improved storage model: moved usage limits to transient cache for performance
* Added rate-limiting to client-info endpoint for security
* Enforced proper fallback and error messages for invalid API keys
* Improved real-time display of reindex count and index usage

= 1.0.4 =
* Allowed full index clearing via full_reindex flag
* Automatically flushes cache after full reindex
* Improved usage tracking performance and accuracy

= 1.0.3 =
* Enforced indexing limits based on plan tier (Free, Pro, Growth, Enterprise)
* Added daily reindex restriction logic per plan
* Improved bulk indexing feedback with API messages and error handling
* Dynamic progress messages shown in WordPress admin during reindexing
* Fixed casting issue for last_reindexed_at on the Site model

= 1.0.2 =
* Added new Content Exclusion settings in the admin panel (post types, statuses, MIME types, sticky and protected posts)
* Updated indexing logic to respect exclusion settings in reindexing jobs

= 1.0.1 =
* Update readme

= 1.0.0 =
* Initial release
