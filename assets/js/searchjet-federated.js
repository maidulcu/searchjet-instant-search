document.addEventListener('DOMContentLoaded', function () {
    const input = document.querySelector('.searchjet-federated-input');
    const container = document.querySelector('.searchjet-federated-container');
    const resultsContainer = document.getElementById('searchjet-federated-results');
    const productsContainer = document.getElementById('searchjet-federated-products-grid');
    const suggestionsContainer = document.getElementById('searchjet-federated-suggestions');
    const pagesContainer = document.getElementById('searchjet-federated-pages');
    const emptyContainer = document.getElementById('searchjet-federated-empty');
    const productCountContainer = document.getElementById('searchjet-product-count');

    if (!input || !container || typeof SearchJetSettings === 'undefined' || typeof SearchJet === 'undefined') {
        return;
    }

    if (typeof SearchJetHelpers === 'undefined') {
        console.error('SearchJetHelpers not loaded');
        return;
    }

    const { handleSearchAnalytics, formatCurrency, escapeHtml, attachClickTracking } = SearchJetHelpers;

    // Initialize SearchJet Client
    const searchJet = new SearchJet({
        apiKey: SearchJetSettings.key,
        baseUrl: SearchJetSettings.searchApiBaseUrl.replace('/api/v1/search', '/api')
    });

    const search = searchJet.instantSearch();

    // Helper to render a single product card
    function renderProductCard(hit) {
        const title = escapeHtml(hit.title || 'Untitled');
        const price = hit.sale_price !== undefined ? hit.sale_price : hit.price;
        const hasSale = typeof hit.sale_price !== 'undefined' && hit.sale_price !== null;
        const currency = hit.currency || '';
        const documentId = hit.id || hit.url || '';
        // Default image if needed
        const image = hit.thumbnail || 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDgiIGhlaWdodD0iNDgiIHZpZXdCb3g9IjAgMCA0OCA0OCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQ4IiBoZWlnaHQ9IjQ4IiBmaWxsPSIjRjVGNUY3Ii8+CjxwYXRoIGQ9Ik0yNCAzNkMzMC42Mjc0IDM2IDM2IDMwLjYyNzQgMzYgMjRDMzYgMTcuMzcyNiAzMC42Mjc0IDEyIDI0IDEyQzE3LjM3MjYgMTIgMTIgMTcuMzcyNiAxMiAyNEMxMiAzMC42Mjc0IDE3LjM3MjYgMzYgMjQgMzYiIGZpbGw9IiNEMUQxRDYiLz4KPC9zdmc+';

        // Add to cart URL
        const addToCartUrl = `?add-to-cart=${hit.id}`; // Simple WooCommerce add to cart link

        return `
            <div class="searchjet-federated-card">
                <a href="${hit.url || '#'}" class="searchjet-hit" data-document-id="${documentId}">
                    <div class="federated-image-wrapper">
                        <img src="${image}" alt="${title}" loading="lazy">
                    </div>
                    <div class="federated-details">
                        <div class="federated-category">${escapeHtml(hit.categories && hit.categories[0] ? hit.categories[0] : 'Apparel')}</div>
                        <h4 class="federated-title">${title}</h4>
                        ${hit.stock_status === 'low_stock' ? '<span class="federated-badge low-stock">Low Stock</span>' : ''}
                        
                        <div class="federated-price-row">
                            <div class="federated-price">
                                ${hasSale
                ? `<span class="sale">${formatCurrency(price, currency)}</span>`
                : `<span>${formatCurrency(price, currency)}</span>`
            }
                            </div>
                           
                        </div>
                    </div>
                </a>
                 <div class="federated-action">
                    <a href="${addToCartUrl}" class="federated-add-btn" aria-label="Add to cart">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="white"><path d="M11 9h2V6h3V4h-3V1h-2v3H8v2h3v3zm-4 9c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2zm-8.9-5h7.45c.75 0 1.41-.41 1.75-1.03l3.86-7.01L19.42 4l-3.87 7H8.53L4.27 2H1v2h2l3.6 7.59-1.35 2.44C4.52 15.37 5.48 17 7 17h12v-2H7l1.1-2z"/></svg>
                        Add to Cart
                    </a>
                </div>
            </div>
        `;
    }

    function renderPageItem(hit) {
        return `
            <a href="${hit.url || '#'}" class="searchjet-page-item searchjet-hit" data-document-id="${hit.id}">
                <div class="page-content">
                    <h4>${escapeHtml(hit.title || 'Untitled')}</h4>
                    <p>${escapeHtml(hit.excerpt || '')}</p>
                </div>
                <svg width="20" height="20" fill="none" class="arrow"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
        `;
    }

    function renderSuggestion(text) {
        return `<div class="searchjet-suggestion-item">${escapeHtml(text)}</div>`;
    }

    // Input listener
    let debounceTimer;
    input.addEventListener('input', function () {
        const query = this.value.trim();
        clearTimeout(debounceTimer);

        if (!query) {
            resultsContainer.style.display = 'none';
            emptyContainer.style.display = 'none';
            return;
        }

        debounceTimer = setTimeout(() => {
            search.helper.setQuery(query).search();
        }, 300);
    });

    // Custom widget
    search.addWidget({
        render({ results }) {
            if (!results || !results.query) {
                return;
            }

            const hits = results.hits || [];

            if (hits.length === 0) {
                resultsContainer.style.display = 'none';
                emptyContainer.style.display = 'block';
                return;
            }

            resultsContainer.style.display = 'flex'; // It's flex in CSS
            emptyContainer.style.display = 'none';

            // Filter hits
            const products = hits.filter(h => h.type === 'product' || (h.url && h.url.includes('/product/')));
            const pages = hits.filter(h => h.type !== 'product' && !(h.url && h.url.includes('/product/')));

            // Update Counts
            if (productCountContainer) productCountContainer.innerText = `(${products.length} results)`;

            // Render Products
            if (products.length) {
                productsContainer.innerHTML = products.map(renderProductCard).join('');
            } else {
                productsContainer.innerHTML = '<div class="no-category-results">No products found.</div>';
            }

            // Render Pages
            if (pages.length) {
                // Limit to 3 pages for sidebar
                pagesContainer.innerHTML = pages.slice(0, 3).map(renderPageItem).join('');
                pagesContainer.parentElement.style.display = 'block';
            } else {
                pagesContainer.parentElement.style.display = 'none';
            }

            // Render Suggestions 
            // Mock suggestions logic for demo: generate from categories of found products or title words
            const uniqueCategories = [...new Set(products.flatMap(p => p.categories || []))].slice(0, 4);
            const words = results.query.split(' ');
            const related = [];

            // Basic mock suggestion logic
            if (uniqueCategories.length) {
                uniqueCategories.forEach(c => related.push(c));
            }
            // Add some "related" terms
            if (products.length > 0) {
                related.push(products[0].title.split(' ').slice(0, 2).join(' '));
            }

            if (related.length) {
                suggestionsContainer.innerHTML = related.map(renderSuggestion).join('');
                suggestionsContainer.parentElement.style.display = 'block';
            } else {
                suggestionsContainer.parentElement.style.display = 'none';
            }

            // Analytics
            handleSearchAnalytics(results.query, hits);
            attachClickTracking(productsContainer, results.query);
            attachClickTracking(pagesContainer, results.query);
        }
    });

    search.start();
});
