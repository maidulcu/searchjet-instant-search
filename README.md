# SearchJetEngine – Instant Search for WordPress & WooCommerce

[![WordPress plugin version](https://img.shields.io/wordpress/plugin/v/searchjet-instant-search)](https://wordpress.org/plugins/searchjet-instant-search/)
[![Tested up to](https://img.shields.io/wordpress/plugin/tested/searchjet-instant-search)](https://wordpress.org/plugins/searchjet-instant-search/)
[![License: GPL v2 or later](https://img.shields.io/badge/license-GPLv2%2B-blue)](LICENSE)

**Instant, typo-tolerant search for WordPress and WooCommerce, run by the [SearchJetEngine](https://searchjetengine.com/) service.**

This is the source of the plugin published on WordPress.org as **[SearchJet Instant Search](https://wordpress.org/plugins/searchjet-instant-search/)**. To use it on your site, install it from WordPress.org (Plugins → Add New → search "SearchJet").

- Website: <https://searchjetengine.com/>
- WordPress setup guide: <https://searchjetengine.com/integrations/wordpress/>
- Documentation: <https://searchjetengine.com/docs/>
- Plans and limits: <https://searchjetengine.com/pricing/>
- Live demo: <https://searchjetengine.com/vector-search-demo/>

## What it does

SearchJetEngine adds results that appear as visitors type in your site's search box, and a search results page that still finds the right content when the visitor makes a typing mistake.

- Instant results while the visitor types, with highlighted matches
- Typo-tolerant matching: small spelling mistakes still return results
- WooCommerce products with image, price, SKU, category and stock status in the results
- A results page that replaces the theme's search results
- Search analytics in the SearchJetEngine dashboard: top searches, searches with no results, usage against your plan
- Colour, font size and border settings with a live preview
- Language detection with WPML: each post is stored with its language and results can be filtered by `?lang=`
- An optional webhook URL for notifications (for example to n8n or Zapier)
- A safe fallback: if the service cannot be reached, visitors see your theme's own search results instead of an error

More: [SearchJetEngine for WordPress](https://searchjetengine.com/wordpress-search-plugin/) · [for WooCommerce](https://searchjetengine.com/woocommerce-search/)

## How it works

The plugin is a client for the SearchJetEngine service. The search itself runs on the service, not on your server, so searches do not load your WordPress database.

1. Create an account at [app.searchjetengine.com/register](https://app.searchjetengine.com/register) and copy your API key.
2. In WordPress, open **SearchJet → Settings**, paste the key and save.
3. Click **Index Your Content**. The plugin uploads your content in batches, then sends changes whenever you publish, update or delete content.
4. Visitors get instant results in the search box and on the search results page.

Until a valid key is saved, your theme's own search keeps working, so nothing breaks while you set it up. A free plan exists; plan limits are listed on the [pricing page](https://searchjetengine.com/pricing/).

## Which content is indexed

Public post types that your site already includes in its own search: posts, pages, products and similar custom post types. Admin-only types (WooCommerce coupons, Contact Form 7 forms, reusable blocks) and password-protected posts are never indexed. You can leave out more post types under SearchJet → Settings.

## Data sent to the service

Your indexed content (title, content, URL, author name, images, terms, language and, for products, SKU, price and stock) is uploaded to SearchJetEngine to build the index. Each search a visitor types is sent from their browser to `app.searchjetengine.com` with your site's public search key, along with search and click statistics. The full list is in the "External services" section of [`readme.txt`](readme.txt). [Terms of use](https://searchjetengine.com/terms-of-use/) · [Privacy policy](https://searchjetengine.com/privacy)

## How it compares to Relevanssi and SearchWP

Relevanssi and SearchWP build their index inside your WordPress database and run every search on your server. SearchJetEngine keeps the index on its own service and shows results as visitors type, with typo tolerance. The trade-off is that it needs an account and sends your content to the service. See [SearchJetEngine vs Relevanssi](https://searchjetengine.com/comparisons/searchjet-vs-relevanssi/) and the [WordPress search plugins guide](https://searchjetengine.com/blog/best-wordpress-search-plugins/).

## Requirements

- WordPress 5.5 or later (tested up to 7.1)
- PHP 7.4 or later
- A SearchJetEngine account and API key

## Development

```bash
git clone https://github.com/maidulcu/searchjet-instant-search.git
cd searchjet-instant-search
composer install   # dev dependencies for the PHPUnit tests
composer test
```

The plugin has no runtime Composer dependencies; classes in `includes/` load through `autoload.php`. Releases are published to WordPress.org; the changelog is in [`readme.txt`](readme.txt).

Bug reports and pull requests are welcome in [Issues](https://github.com/maidulcu/searchjet-instant-search/issues). For account or billing questions, contact SearchJetEngine through [searchjetengine.com](https://searchjetengine.com/).

## Related

- [searchjet-connect](https://github.com/maidulcu/searchjet-connect): JavaScript library for adding SearchJet instant search to any website

## License

GPL v2 or later. See [LICENSE](LICENSE). The SearchJetEngine search service is a separate managed service with its own [terms](https://searchjetengine.com/terms-of-use/).
