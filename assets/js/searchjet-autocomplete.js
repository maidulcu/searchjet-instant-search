document.addEventListener('DOMContentLoaded', function () {
    const input = document.querySelector(`
        input.search-field, 
        input[type="search"][name="s"],
        .elementor-search-form__input,
        .elementor-widget-search-form input[type="search"],
        .astra-search-box input[type="search"],
        .ast-search-box input[type="search"],
        .oceanwp-mobile-search input[type="search"],
        .site-search input[type="search"],
        .header-search input[type="search"],
        .search-form input[type="search"],
        .wp-block-search__input,
        .kadence-search input[type="search"],
        .generatepress-search input[type="search"],
        .neve-search input[type="search"],
        .hestia-search input[type="search"],
        .storefront-search input[type="search"],
        .divi-search input[type="search"],
        .avada-search input[type="search"],
        .enfold-search input[type="search"],
        .betheme-search input[type="search"],
        .flatsome-search input[type="search"],
        .woodmart-search input[type="search"],
        .porto-search input[type="search"],
        .the7-search input[type="search"],
        .salient-search input[type="search"],
        .bridge-search input[type="search"],
        .jupiter-search input[type="search"],
        .uncode-search input[type="search"],
        .total-search input[type="search"]
    `.replace(/\s+/g, ' ').trim());

    if (!input || typeof SearchJetSettings === 'undefined' || typeof SearchJet === 'undefined') {
        return;
    }

    // Check if helpers are available
    if (typeof SearchJetHelpers === 'undefined') {
        console.error('SearchJetHelpers not loaded');
        return;
    }

    // Use shared helpers
    const { handleSearchAnalytics, renderProductHit, renderPageHit, groupItems, escapeHtml, formatCurrency, attachClickTracking } = SearchJetHelpers;

    // Create SearchJet Connect instance
    const searchJet = new SearchJet({
        apiKey: SearchJetSettings.key,
        baseUrl: SearchJetSettings.searchApiBaseUrl.replace('/api/v1/search', '/api')
    });

    // Setup containers
    const wrapper = document.createElement('div');
    wrapper.id = 'searchjet-wrapper';
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);

    const resultsContainer = document.createElement('div');
    resultsContainer.id = 'searchjet-results';
    resultsContainer.style.display = 'none'; // Initially hidden
    wrapper.appendChild(resultsContainer);

    const lang = new URLSearchParams(window.location.search).get('lang') || SearchJetSettings.language || '';

    // Create InstantSearch instance
    const search = searchJet.instantSearch({
        searchParameters: lang ? { filters: `language = "${lang}"` } : {}
    });

    // Add custom search widget that uses existing input
    search.addWidget({
        init({ helper }) {
            let debounceTimer;
            let isFirstSearch = true;
            
            input.addEventListener('input', function() {
                const query = this.value.trim();
                clearTimeout(debounceTimer);
                
                if (!query) {
                    resultsContainer.style.display = 'none';
                    isFirstSearch = true;
                    return;
                }
                
                // First search is instant, subsequent searches are debounced
                if (isFirstSearch) {
                    isFirstSearch = false;
                    helper.setQuery(query).search();
                } else {
                    debounceTimer = setTimeout(() => {
                        helper.setQuery(query).search();
                    }, 100);
                }
            });
        },
        render({ results }) {
            // Show/hide results container based on query
            const hasQuery = results && results.query && results.query.trim();
            resultsContainer.style.display = hasQuery ? 'block' : 'none';
        }
    });

    // Add hits widget
    search.addWidget(
        SearchJet.widgets.hits({
            container: '#searchjet-results',
            templates: {
                item: (hit) => {
                    const isProduct = hit.type === 'product' || (hit.url && hit.url.includes('/product/'));
                    const position = hit._position || 0;
                    return isProduct ? renderProductHit(hit, position) : renderPageHit(hit, position);
                },
                empty: () => `<div class="searchjet-no-results"><em>No results found.</em></div>`,
                footer: (results) => {
                    if (!results.hits || results.hits.length === 0) return '';
                    return `
                        <div class="searchjet-footer">
                            Press <strong>enter</strong> to select, <strong>↑↓</strong> to navigate, <strong>esc</strong> to dismiss
                        </div>
                    `;
                }
            },
            transformItems: (items) => {
                const query = search.helper.state.query;
                handleSearchAnalytics(query, items);
                const grouped = groupItems(items);

                // Attach click tracking after render
                setTimeout(() => {
                    attachClickTracking(resultsContainer, query);
                }, 100);

                return grouped;
            }
        })
    );

    // Start the search
    search.start();

    // Apply the configured "Max Results" setting to the dropdown
    const maxResults = parseInt(SearchJetSettings.ui && SearchJetSettings.ui.maxResults, 10);
    if (search.helper && maxResults > 0) {
        search.helper.state.hitsPerPage = maxResults;
    }
});