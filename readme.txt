=== SearchJet – Instant Search ===
Contributors: maidulcu
Tags: search, instant search, woocommerce
Requires at least: 5.5
Tested up to: 6.8
Stable tag: 1.0.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered WooCommerce and WordPress search with SKUs, typo correction, blazing-fast results, and real-time analytics tracking.

== Description ==


SearchJet is a blazing-fast, AI-powered instant search plugin for WordPress and WooCommerce. It helps your visitors find content or products quickly—boosting engagement and reducing bounce rates. With features like live search suggestions, typo-tolerant matching, SKU support, and real-time analytics, SearchJet offers a powerful alternative to traditional WordPress search solutions.

Designed for WooCommerce and content-heavy sites, SearchJet supports smart filtering, multilingual search compatibility, and customizable display options. Easily index products, posts, custom post types, and media to deliver faster, more accurate results.

With predictive Ajax search and built-in analytics, you'll understand exactly what your users are searching for—making it easier to optimize content and increase sales. SearchJet is built for performance and flexibility, integrating seamlessly into your theme while giving you full control over rendering and ranking behavior.

Whether you're running a shop, portfolio, blog, or enterprise portal, SearchJet enhances your WordPress search UX with scalable infrastructure and intelligent result prioritization.

🔎 [Try the Live Demo](https://demo.searchjetengine.com/) | 🌐 [Official Website](https://searchjetengine.com/)


* Blazing-fast, typo-tolerant search
* Reindexing & real-time indexing status updates  
* Secure API key authentication for every request  
* Track document count, search usage & performance  
* Instant, predictive Ajax search with analytics tracking  
* WooCommerce product search support included  
* All public post types supported by default  
* AI-enhanced relevance scoring for smarter results  
* SEO-friendly, mobile-optimized experience  
* Developer-friendly with clear REST endpoints & docs
* Content exclusion rules for full control (post types, statuses, MIME types, protected content, and more)

SearchJet runs on lightning-fast, AI-optimized infrastructure—ensuring ultra-low latency, high uptime, and smarter instant search for WordPress and WooCommerce sites.

 * Instant, predictive search suggestions
 * AI-enhanced relevance
 * Detailed analytics dashboard (top queries, zero-result queries, daily volume)
 * Mobile-optimized, SEO-friendly search experience
 * Filters/synonyms/boosting (in paid plans)
 * Enterprise-grade performance across platforms


== Privacy ==

SearchJet only collects the minimum necessary data to operate its search indexing and analytics services. We do not index or store sensitive or private data such as user passwords or admin content. Your data remains yours—secure, isolated, and encrypted in transit. For more details, please refer to your server's privacy settings and documentation. For more information, visit our [Privacy Policy](https://searchjetengine.com/privacy).

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/searchjet` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings > SearchJet and enter your API key.

== Frequently Asked Questions ==

= Does this plugin work with WooCommerce? =
Yes, WooCommerce support is included in the Free plan.

= Does this plugin track search usage? =
Yes, SearchJet tracks and reports search usage and analytics to help you monitor how your site search is performing. You can view this data through the SearchJet.io dashboard.

= Is this plugin compatible with all themes? =
Yes, SearchJet is designed to work with any well-coded WordPress theme. You may optionally customize the CSS to better match your design.

= How often is the index updated? =
The plugin updates the index in near real-time when you create, update, or delete posts, products, or custom content types.

= Can I exclude certain content from being indexed? =
Yes! You can exclude specific post types, post statuses (like drafts), password-protected posts, sticky posts, and specific MIME types (like images, PDFs) using the plugin settings. Additional rules can be configured via code filters or in your SearchJet dashboard (Pro).

SearchJet supports all public post types including WooCommerce products and custom content types.

= Does this plugin support multilingual search? =
Multilingual support is available via integration with compatible plugins like WPML or Polylang. We recommend using our Pro plan for advanced language handling.

== Demo & Learn More ==
🔎 [Live Demo](https://demo.searchjetengine.com/) | 📘 [Docs](https://searchjetengine.com/docs) | 💼 [Pricing](https://searchjetengine.com/price/) | 🌐 [More Info](https://searchjetengine.com/)


== Screenshots ==

1. Settings Page – Search API Configuration
2. Search Results – Instant Ajax Search

== Changelog ==

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