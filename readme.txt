=== SearchJetEngine – Instant Search for WordPress & WooCommerce ===
Contributors: maidulcu
Tags: search, instant-search, ajax-search, woocommerce-search, typo-tolerant
Requires at least: 5.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Instant, typo-tolerant search for WordPress and WooCommerce, run by the SearchJetEngine service. Needs a free account and API key.

== Description ==

SearchJetEngine adds instant results that appear as visitors type in your site's search box, and a search results page that still finds the right content when the visitor makes a typing mistake.

**This plugin needs a SearchJetEngine account and API key.** The search itself runs on the SearchJetEngine service at searchjetengine.com, not on your server. You create a free account there, copy your API key into SearchJet > Settings, and click "Index Your Content". Until a valid key is entered, your theme's own search keeps working as before, so nothing breaks while you set it up. See "External services" below for exactly what is sent to the service.

**How it works**

1. Create an account at searchjetengine.com and copy your API key.
2. In WordPress open SearchJet > Settings, paste the key and save.
3. Click "Index Your Content". The plugin uploads your content to the service in batches. After that it sends changes when you publish, update or delete content.
4. Visitors get instant results in the search box and on the search results page.

**What you get**

* Instant results while the visitor types, with highlighted matches.
* Typo-tolerant matching: small spelling mistakes still return results.
* WooCommerce products with image, price, SKU, category and stock status in the results.
* A results page that replaces the theme's search results.
* Search analytics in the SearchJetEngine dashboard: top searches, searches with no results, usage against your plan.
* Settings for colours, font size and borders, with a live preview.
* Language detection with WPML: each post is stored with its language and results can be filtered by `?lang=`.
* A webhook URL for notifications (for example to n8n or Zapier) if you enter one.
* A safe fallback: if the service cannot be reached, visitors see your theme's own search results instead of an error.

**What you should know before installing**

* A free plan exists. Plan limits (searches per month, indexed pages, re-indexes per day) are set by the service and are listed at searchjetengine.com. Paid plans lift them.
* Which content is indexed: public post types that your site already includes in its own search, such as posts, pages and products. Admin-only content (WooCommerce coupons, forms, reusable blocks) and password-protected posts are never indexed. You can leave out more post types under SearchJet > Settings.
* To use your theme's own search again, tick "Disable Search Override" under SearchJet > Settings, or deactivate the plugin.
* The service stores your indexed content and your visitors' search queries. If you have visitors in the EU, add the service to your privacy policy.

**Learn more**

* [Live search demo](https://searchjetengine.com/vector-search-demo/)
* [SearchJetEngine for WordPress](https://searchjetengine.com/wordpress-search-plugin/) and [for WooCommerce](https://searchjetengine.com/woocommerce-search/)
* [Documentation](https://searchjetengine.com/docs/) and [WordPress setup guide](https://searchjetengine.com/integrations/wordpress/)
* [Plans and limits](https://searchjetengine.com/pricing/)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/searchjet-instant-search` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Create a free account at [app.searchjetengine.com/register](https://app.searchjetengine.com/register) and copy your API key from the dashboard.
4. Go to SearchJet > Settings, paste the API key and save.
5. Click "Index Your Content" and wait for it to finish.

== Frequently Asked Questions ==

= Do I need an account? =
Yes. SearchJetEngine is a connected service. Without an account and API key from searchjetengine.com the plugin does nothing, and your theme's normal search is used. Sign up at app.searchjetengine.com/register, then paste the key in SearchJet > Settings.

= What happens before I connect, or if the service is down? =
Your theme's own search results are shown. The plugin only replaces them once a valid API key is saved, and it falls back to them if the service cannot be reached. Run "Index Your Content" after connecting, or the results will be empty. To go back to your theme's search for good, tick "Disable Search Override" under SearchJet > Settings, or deactivate the plugin.

= Is my content or my visitors' data sent to another company? =
Yes. Your posts, pages, products and other indexed content are uploaded to the SearchJetEngine service so it can build the search index. Every search a visitor types is sent from the visitor's browser to the service, and the plugin sends search and click statistics (query, page, browser, session id; for clicks also the IP address). See "External services" for the list and the terms and privacy links.

= Which content is indexed? =
Public post types that are included in your site's own search: posts, pages, products and similar custom post types. Admin-only types such as WooCommerce coupons, Contact Form 7 forms and reusable blocks are not indexed, and neither are password-protected posts. You can leave out more post types under SearchJet > Settings; automatic sync and Bulk Re-Index follow the same rules. Anything you index is searchable by the public.

= Does it work with WooCommerce? =
Yes. Products are indexed with price, SKU, categories and stock status, and appear in the instant results.

= What does it cost? =
The service has a free plan and paid plans. Limits are set on the service side and can change; check searchjetengine.com/pricing for the current numbers.

= What happens if I stop using it? =
Deactivate the plugin and your theme's search works as before. Your index stays on the service until you delete it in your SearchJetEngine account. The plugin does not delete it for you.

= Does it support other languages? =
If you use WPML, each post is stored with its language and results can be filtered with `?lang=fr`. Without WPML the site language is used.

= How is this different from Relevanssi or SearchWP? =
Relevanssi and SearchWP build their index inside your WordPress database and run every search on your server. SearchJetEngine keeps the index on its own service, so searches do not load your database, and results appear as visitors type, with typo tolerance. The trade-off is that it needs an account and sends your content to the service. See the [SearchJetEngine vs Relevanssi comparison](https://searchjetengine.com/comparisons/searchjet-vs-relevanssi/) and the [WordPress search plugins guide](https://searchjetengine.com/blog/best-wordpress-search-plugins/).

= Can I change the design? =
Yes. You can change colours, font size, borders and more under SearchJet > Search UI, with a live preview.

== External services ==

This plugin is a client for the SearchJetEngine service. It does nothing useful without it.

**SearchJetEngine (https://app.searchjetengine.com, run by Dynamic Web Lab)**

What it is used for: building the search index, answering search requests, search analytics and usage limits.

What is sent and when:

* When you click "Index Your Content", and again whenever you publish, update or delete content: for each indexed item the title, full content, URL, author display name, images, categories, tags, language and, for products, SKU, price and stock status. Only public, searchable post types are indexed, and password-protected posts are never sent (see FAQ "Which content is indexed?").
* Every time a visitor types in the search box or opens a search results page: the search text, sent from the visitor's browser to app.searchjetengine.com with your site's public search key.
* Search and click analytics: the search text, number of results, the page the visitor was on, browser user agent and a session id; for clicks also the visitor's IP address. These are sent by the visitor's browser and by your server in batches.
* When you save the API key, and when the admin pages load: a request that returns your plan, usage and index statistics.

Terms of use: https://searchjetengine.com/terms-of-use/
Privacy policy: https://searchjetengine.com/privacy

== Screenshots ==

1. Instant results in the site's search box: products with thumbnails and prices, then matching pages
2. SearchJet dashboard in the WordPress admin, with quick links to indexing, settings and analytics
3. Dashboard before setup: the "Start Setup Wizard" button
4. Setup wizard, step 1: welcome screen
5. Setup wizard, step 2: paste the API key from your SearchJetEngine account
6. Settings: connect your site in three steps (create an account, get the API key, paste it)
7. Search UI: theme, colours, number of results and display options, with a live preview
8. Indexing: reindex all content and choose which attributes are returned with results
9. Analytics: searches this month, top search queries and queries that returned no results
10. Agents: optional local helpers (attribute suggestions, text summary, keyword extraction), off by default

== Changelog ==

= 2.0.8 =
* Readme: describes how 2.0.7 behaves (theme search before setup and when the service is down, public post types only) and adds links to the documentation and guides.

= 2.0.7 =
* **PRIVACY** - Only public, searchable post types are indexed. Admin-only content such as WooCommerce coupons, forms and reusable blocks is no longer sent to the search service
* **PRIVACY** - Password-protected posts are never indexed, and are removed from the index when a password is added
* **FIX** - The search results page is no longer blank when the plugin is not connected yet: the theme's own results are shown
* **FIX** - If the search service cannot be reached, visitors see the theme's own results instead of an error
* **FIX** - Bulk Re-Index, Index Health and automatic sync now all use the same rules for what is indexed
* **NOTE** - Run Bulk Re-Index once after updating

= 2.0.6 =
* **IMPROVEMENT** - Streamlined the in-plugin Documentation page by removing the Admin Panel Guide, SearchJet Connect, AI Agents, Roadmap, and Troubleshooting cards

= 2.0.5 =
* **IMPROVEMENT** - Code refactoring for improved readability and maintainability
* **IMPROVEMENT** - Added Defaults helper class for centralized option management
* **IMPROVEMENT** - Added NetworkHelper for improved network operations
* **IMPROVEMENT** - Enhanced PlanManager with get_usage_limits() method for better usage tracking
* **IMPROVEMENT** - Fixed text domain inconsistencies across service classes
* **IMPROVEMENT** - Removed unused AI/Router.php and Automations/Schedules.php files
* **IMPROVEMENT** - Added comprehensive test coverage for core functionality

= 2.0.4 =
* **NEW** - Added "Delete Data on Uninstall" option under Advanced Settings. When enabled, deleting the plugin removes all SearchJet data (API key, settings, transients, and cron schedules); by default no data is removed
* **IMPROVEMENT** - Uninstall handler safely cleans up all `searchjet_*` options, transients, and scheduled events to keep your database tidy

= 2.0.3 =
* **FIX** - Output escaping hardening across admin pages (wp_die messages, translation printing, and JS-context echoes)
* **FIX** - Add direct file access protection to autoloader and shortcode class
* **FIX** - Apply WPCS compliance to database queries in Index Health and Task Status tracker

= 2.0.2 =
* **IMPROVEMENT** - Prepared for WordPress 7.1 (tested up to 7.1)

= 2.0.1 =
* **FIX** - Update Demo link to working URL (https://searchjetengine.com/vector-search-demo/)

= 2.0.0 =
* **BREAKING** - Complete admin panel reorganization: new Dashboard, Settings, Search UI, and Indexing pages
* **BREAKING** - EnhancedSettingsPage gutted (2,992→221 lines; settings registration only, all render methods moved)
* **BREAKING** - Old SearchUISettingsPage removed (dead code)
* **NEW** - DashboardPage with System Status Bar, Feature Usage cards, Recent Activity, Search Health, Environment Info
* **NEW** - SettingsPage with 3-step onboarding wizard for new users and "Connected Successfully!" card for connected users
* **NEW** - SearchUIPage with theme dropdown, color picker, max results, display toggles with Pro tags, and live preview
* **NEW** - IndexingPage with reindex button, attributes builder, and sub-navigation
* **NEW** - Quick Access section on Dashboard with 7 SVG icon cards linking to hidden pages
* **NEW** - Floating chatbot on all admin pages with 16 FAQ entries, word-score search, and docs fallback
* **NEW** - Breadcrumb navigation and related links on all hidden sub-pages (Bulk Re-Index, Index Settings, Index Health, Automations, AI Agents, Task History, Documentation)
* **NEW** - Agent category metadata (configuration, content, analysis, merchandising) in AgentRegistry
* **FIX** - Broken admin page URLs fixed: replaced remove_submenu_page() with CSS display:none to keep pages accessible
* **FIX** - Fixed Analytics page missing CSS: added enqueueAssets() to EnhancedSettingsPage
* **FIX** - Fixed BulkReindexPage scope cards using invalid <label> → <div>, replaced emoji with SVG icons
* **FIX** - Fixed orphaned submenu parent slugs (searchjet-instant-search → searchjet)
* **IMPROVEMENT** - Admin menu consolidated: DashboardPage registers all 12 submenu items
* **IMPROVEMENT** - Setup wizard redesigned with gradient header, progress steps, branded benefit cards, localStorage key transfer
* **IMPROVEMENT** - Agents page redesigned with status badges, category tags, toggle switches, SVG icons
* **IMPROVEMENT** - Agent tester with dark-themed JSON textarea, colored result panel, debug badges, copy-to-clipboard
* **IMPROVEMENT** - Restyled remaining admin pages (Automations, AI Agents, Task History, Documentation, Bulk Re-Index, Index Settings, Index Health) to match the new Dashboard design system
* **IMPROVEMENT** - Converted all inline SVG and emoji icons to standalone SVG files in assets/img/ across every admin surface (Dashboard, Bulk Re-Index, Index Settings, Index Health, Automations, AI Agents, Task History, Indexing, Settings, Analytics, Search UI, Dashboard Widget)
* **IMPROVEMENT** - Replaced remaining status glyphs (✓/✕/⚠/📋) and onboarding emoji with SVG icons; added white/checked-state variants for scope cards and primary buttons
* **IMPROVEMENT** - Converted health-check spinner to standalone SVG with CSS rotation; scope-card icons flip to white when selected via CSS filter
* **IMPROVEMENT** - Added imgUrl localization to admin scripts (bulk-reindex, index-settings, index-health, task-history, dashboard widget) for dynamic SVG rendering
* **FIX** - Search UI preview search icon now uses standalone SVG; theme-dynamic page icons kept inline to preserve live primary-color preview


= 1.7.1 =
* **FIX** - Fixed critical bug in BatchDeleteHelper::deleteByPostType() using wrong variable (getApiBaseUrl instead of $type)
* **FIX** - Fixed text domains in 6 admin files (~100+ strings, searchjet -> searchjet-instant-search)
* **FIX** - Fixed public REST endpoints leaking server info (/health, /ping, /ai/usage now require manage_options)
* **FIX** - Added nonce verification and capability check to settings form submission
* **FIX** - Added error logging to empty catch blocks in IndexSync.php
* **FIX** - Replaced alert() in AutomationPage.php with inline WordPress notice
* **IMPROVEMENT** - Agents page redesigned with status badges, category tags, and toggle switches
* **IMPROVEMENT** - Added agent category metadata to AgentRegistry
* **IMPROVEMENT** - Overview tab now shows stat cards and quick start checklist
* **IMPROVEMENT** - Agent tester now has dark-themed JSON textarea and colored result panel
* **IMPROVEMENT** - Debug tab now shows version badges and copy-to-clipboard button
* **IMPROVEMENT** - Replaced emoji icons with SVG icons on Agents page
* **IMPROVEMENT** - Removed empty tool stub files (SearchApiTool, WPContentTool, WooCatalogTool)

= 1.7.0 =
* **FIX** - Fixed Automations n8n/Zapier webhook URLs not being used (dispatch now reads provider-specific URLs)
* **FIX** - Fixed zero-result burst detection calling non-existent method (zeroResultOccurred -> recordZeroResult)
* **FIX** - Fixed daily cron runner calling non-existent indexSummary method (now uses indexCompleted)
* **FIX** - Fixed duplicate cron registration between Runner.php and Schedules.php (Schedules deprecated)
* **FIX** - Fixed Registry.php callback references to use proper method arrays
* **FIX** - Implemented actual data collection for dailyHealthSnapshot (was sending null data)
* **FIX** - Fixed test webhook button to respect selected provider (n8n/Zapier/Custom)
* **FIX** - Fixed text domains in AutomationPage.php (searchjet -> searchjet-instant-search)
* **FIX** - Fixed text domains in DocumentationPage.php (searchjet -> searchjet-instant-search)
* **FIX** - Registered TaskStatusTracker cron hook (searchjet_check_task_status was never handled)
* **FIX** - Fixed Task History tasks staying stuck in pending (synchronous tasks now marked completed)
* **IMPROVEMENT** - Task History page now records tasks for reindex, settings changes, and index sync
* **IMPROVEMENT** - Task History page redesigned with cleaner UI, inline notifications, and better labels
* **IMPROVEMENT** - Documentation tab redesigned with Live Search Journey animation
* **IMPROVEMENT** - Documentation page simplified to hub linking to online docs
* **IMPROVEMENT** - Added Sentient AI cross-promotion in Documentation hub
* **IMPROVEMENT** - Task History labels updated (Neural Event Log -> Activity Log, etc.)

= 1.6.1 =
* **SECURITY** - Removed resetColorToBlack() that was overwriting user color/theme settings on every admin page load
* **SECURITY** - Fixed localStorage.clear() / sessionStorage.clear() in discardChanges() that was wiping all site storage data; now only clears SearchJet-specific keys
* **SECURITY** - Added URL sanitization (sanitizeUrl) to prevent XSS via search result URLs in href attributes
* **SECURITY** - Replaced alert() in API key wizard with inline WordPress notice for better UX
* **FIX** - Removed redundant "SearchJet Setup Required" admin notice that duplicated the in-page Get Started section
* **FIX** - Fixed setup wizard to show manual API key entry directly instead of broken "Connect Automatically" link
* **FIX** - Fixed text domain inconsistency across admin files (searchjet -> searchjet-instant-search) for proper translation support
* **FIX** - Changed version constant from 1.6.0 to 1.6.1

= 1.6.0 =
* Improved UI Customization page with polished Live Preview matching frontend search styles
* Added SVG icons throughout admin panel replacing emoji icons for consistency
* Added connection status badge to API Connection tab showing Connected/Not Connected with plan info
* Redesigned Documentation tab with external docs banner, quick link cards, and full API reference
* Added "Explore All Advanced Features" and "View Full API Documentation" buttons with links
* Improved API Key input field with key icon and eye toggle inside the field
* Improved Recommendations section with horizontal card layout and SVG icons
* Added method badges (GET/POST/JS) to API Reference endpoints
* Updated Live Preview search icon to right side with SVG
* Improved checkbox toggles, select dropdowns, and color picker styling
* Added responsive design improvements for documentation and guidance sections
* General UI/UX refinements and performance improvements

= 1.5.2 =
* **Fix** - Fixed Task History page spacing issues (reduced header padding, removed excessive margins, fixed min-height overflow).


= 1.5.1 =
* **WPML Fix** - Language filter now works correctly with `filters=language = "en"` syntax.
* **WPML Fix** - URLs are now translated to the correct language (e.g., `/cs/`, `/fr/`) instead of always returning English URLs.
* **WPML Fix** - Language field uses WPML's language code when available, falls back to site locale.
* **WPML Fix** - Fixed `indexPost()` method name (was camelCase, now snake_case) causing fatal error on post save.
* **WooCommerce Fix** - Post types now use `show_ui` instead of `public` to include WooCommerce products and other custom post types.
* **Post Types** - Available Post Types list now shows all post types with admin UI (WooCommerce, CPTs, etc.).
* **AI Answers Fix** - Fixed PlanManager method calls (camelCase → snake_case) causing fatal errors.
* **Shortcode Fix** - SearchForm shortcode `[searchjet_form]` now properly registered.
* **Reindex Cache** - Document count and plan config caches are now cleared after successful reindex.

= 1.5.0 =
* **Bug Fix** - Fixed missing index-settings.js, bulk-reindex.js, index-health.js file references (wrong filenames).
* **Bug Fix** - Fixed missing automations.css and automations.js files that caused 404 errors.
* **Bug Fix** - Fixed JS error in admin-enhanced.js when API key input field is missing on page.
* **Bug Fix** - Fixed document count always showing 0 due to stale transient cache (reduced cache TTL from 24h to 5min).
* **Bug Fix** - Fixed document count not updating after reindex completion.
* **Bug Fix** - Fixed wp_remote_patch (non-existent WordPress function) replaced with wp_remote_request.
* **Bug Fix** - Fixed Index Health page not working due to reliance on non-existent /v1/index/stats API endpoint.
* **Improvement** - Added missing getIndexSettings(), updateIndexSettings(), and getIndexStats() methods to SearchJetRemoteClient.
* **Improvement** - Reindex now shows persistent success/error notification instead of disappearing message.
* **Improvement** - Document count now updates live on the page after reindex without requiring page reload.
* **Improvement** - Settings page now forces fresh API call for document count instead of serving stale cache.

= 1.4.3 =
* **Missing CSS Fix** - Added missing admin CSS files: bulk-reindex, index-settings, index-health, task-history.

= 1.4.2 =
* Fix JS error on analytics page

= 1.4.1 =
* **WPML Language Filtering** - Added per-language search filtering so users only see results in their language.
* **Language Detection** - Posts now include a `language` field detected via WPML or site locale.
* **Reindexer Fix** - Changed post type query from `public` to `show_ui` to include custom post types.
* **SearchForm Shortcode** - Added SearchForm shortcode initialization.
* **Debug Cleanup** - Removed all debug error_log statements from production code.

= 1.4.0 =
* **Index Settings Page** - New admin page for configuring searchable/filterable attributes, ranking rules, and sorting options.
* **Task History Page** - Track indexing task history and status updates from the admin.
* **Index Health Page** - Monitor index health, connection status, and document counts.
* **Bulk Reindex Page** - New interface for bulk re-indexing content with batch progress tracking.
* **Batch Delete Helper** - Utilities for bulk deleting documents by post type, category, date, or custom query.
* **Task Status Tracker** - Service for tracking indexing tasks with polling and callback handling.
* **Advanced Search Parameters** - Enhanced search API with semantic search, filtering, sorting, faceting, highlighting, crop, typo tolerance, and matching strategy options.

= 1.3.10 =
* **WPML Multi-Language Support** - Fixed indexing to include posts from ALL WPML languages, not just the default language.
* **Language Field** - Added `language` field to all indexed documents for proper language-based filtering.
* **Reindex Dashboard** - Fixed reindex count showing 0 by always refreshing usage data on admin page visit.
* **Cache Optimization** - Reduced usage data cache from 10min to 5min for faster updates.

= 1.3.9 =
* **Reindex Dashboard** - Fixed reindex count showing 0 by always refreshing usage data on admin page visit.
* **Cache Optimization** - Reduced usage data cache from 10min to 5min for faster updates.
* **Backend Compatibility** - Updated to work with latest SearchJet backend plan limits and API changes.

= 1.3.8 =
* **Attribute Management** - Enhanced preset handling with improved event flow and active-state updates.
* **Analytics Improvements** - Improved search analytics normalization and added local fallback support.
* **Markdown Rendering** - Enhanced heading ID stability and link handling for documentation output.

= 1.3.7 =
* **Indexed Bug Fixed** - Fixed a bug with indexing operations.

= 1.3.6 =
* **Federated Search Demo** - Added new Federated Search layout accessible with `[searchjet_federated]` shortcode. This layout features a modern, segmented view with Products, Pages, and Suggestions.
* **Layout Improvements** - Enhanced layout separation and CSS styling for the federated results view.

= 1.3.5 =
* **Backend API Integration** - Complete alignment with SearchJet backend API documentation
* **Enhanced Indexing** - Fixed document ID format consistency across all indexing methods
* **Quota Management** - Added HTTP 429 quota error handling with detailed user feedback
* **Analytics Improvements** - Added click analytics batch sending via daily cron job
* **New API Methods** - Added AI generation, web scraping, subscription, and utility endpoints
* **Code Quality** - Removed unused properties and cleaned up error logging
* **Performance** - Optimized error propagation throughout the indexing pipeline
* **Developer Experience** - Full API coverage for all SearchJet backend features

= 1.3.4 =
* Bug fixes and performance improvements
* Enhanced error handling for API requests
* Improved admin UI stability

= 1.3.3 =
* **SECURITY FIXES**: Fixed log injection vulnerabilities (CWE-117) by sanitizing external data before logging
* **SECURITY FIXES**: Replaced insecure MD5 hash with SHA-256 for better cryptographic security
* **PERFORMANCE IMPROVEMENTS**: Added caching for document count API calls to prevent repeated requests
* **PERFORMANCE IMPROVEMENTS**: Optimized getAllFeatures() method to eliminate redundant database/API calls
* **PERFORMANCE IMPROVEMENTS**: Fixed memory leaks in Reindexer by adding proper wp_reset_postdata() cleanup
* **BUG FIXES**: Fixed critical indexed_pages quota calculation bug causing incorrect remaining/percentage values
* **BUG FIXES**: Fixed race conditions in usage counter increments with atomic operations and retry logic
* **BUG FIXES**: Added proper null checks to prevent PHP warnings in plan management
* **CODE QUALITY**: Converted all service classes to WordPress coding standards (snake_case, tabs, array() syntax)
* **CODE QUALITY**: Removed manual file includes in favor of PSR-4 autoloading for better performance
* **CODE QUALITY**: Enhanced error handling with consistent return values across all API methods
* **UI IMPROVEMENTS**: API Key description now only shows when key is empty for cleaner interface
* **UI IMPROVEMENTS**: Enhanced "Get Started" card with modern emerald-to-blue gradient design
* **ARCHITECTURE**: Replaced hardcoded feature mappings with backend-derived logic for consistency
* **DEVELOPER**: Added comprehensive input validation and improved method documentation
* Improved API Key card to show description only when API key is empty
* Enhanced "Get Started with SearchJet" card with modern emerald-to-blue gradient design
* Better visual hierarchy and styling for setup experience

= 1.3.2 =
* New search-results dropdown with improved design
* Added product Category, Stock, On-backorder and Price on Dropdown with vibrant UI
* Compatible with Dark background

= 1.3.1 =
* Enhanced search input detection for popular WordPress themes (Astra, OceanWP, Kadence, GeneratePress, Neve, Hestia, Storefront)
* Added support for premium themes (Divi, Avada, Enfold, BeTheme, Flatsome, WoodMart, Porto, The7, Salient, Bridge, Jupiter, Uncode, Total)
* Improved Elementor page builder compatibility with search widget detection
* Enhanced hideDefaultSearch functionality to properly hide theme-specific search results
* Added comprehensive :not() selectors to prevent hiding SearchJet containers
* Improved fetchClientInfo caching with 1-minute cache duration and automatic error recovery
* Fixed cache busting issues that could cause stale client data
* Better theme compatibility across popular WordPress themes and page builders

= 1.3.0 =
* **NEW: SearchJet Connect Integration** - Modern JavaScript SDK for enhanced search performance and reliability
* **NEW: Advanced Search UI Customization** - Complete control over search interface appearance with live preview
* **NEW: Updated Search Results Page** - Enhanced full-page search experience with improved pagination and styling
* Improved autocomplete performance with instant first search (0ms delay) and smart caching
* Updated search form shortcode with modern HTML structure and customizable attributes
* Enhanced search form styling with clean, responsive design
* Added proper accessibility features and ARIA labels to search forms
* Fixed search result rendering to use URL field from API responses
* Improved CSS for search result links with proper hover states and focus indicators
* Enhanced JavaScript architecture with modular helper functions and shared utilities
* Added support for custom CSS variables for advanced theme customization
* Improved mobile responsiveness across all search interfaces

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
