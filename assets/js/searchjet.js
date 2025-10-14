document.addEventListener('DOMContentLoaded', function () {
    const input = document.querySelector('input.search-field, input[type="search"][name="s"]');
    if (!input) {
      return;
    }
    if (typeof SearchJetSettings === 'undefined') {
      return;
    }
    const wrapper = document.createElement('div');
    wrapper.id = 'searchjet-searchbox-container';
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);
    // Dynamically inject the results container if not present
    if (!document.getElementById('searchjet-results')) {
      const resultsDiv = document.createElement('div');
      resultsDiv.id = 'searchjet-results';
      resultsDiv.setAttribute('role', 'listbox');
      resultsDiv.setAttribute('aria-label', 'Search results');
      input.parentNode.appendChild(resultsDiv);
    }

    // Add ARIA attributes to input
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', 'searchjet-results');

    // Apply custom styling
    applyCustomStyles();
  
    const BATCH_LOG_KEY = 'searchjet_log_batch';
    let logQueue = JSON.parse(sessionStorage.getItem(BATCH_LOG_KEY) || '[]');
  
    function shouldLogSearch(query) {
      const normalized = query.toLowerCase().trim();
      const logged = logQueue.map(item => item.query);
      return !logged.includes(normalized);
    }
  
    function queueLog(query, resultCount) {
      const normalized = query.toLowerCase().trim();
      logQueue.push({
        query: normalized,
        results: resultCount,
        ts: Date.now(),
        filters: SearchJetSettings.filters || null,
        referer: document.referrer || null,
        user_agent: navigator.userAgent || null,
        session_id: SearchJetSettings.session_id || null,
        ip: SearchJetSettings.ip || null
      });
      if (logQueue.length > 50) {
        logQueue = logQueue.slice(-50);
      }
      sessionStorage.setItem(BATCH_LOG_KEY, JSON.stringify(logQueue));
    }
  
    let logTimer;
    function scheduleLog(query, resultCount) {
      clearTimeout(logTimer);
      logTimer = setTimeout(() => {
        if (shouldLogSearch(query)) {
          queueLog(query, resultCount);
        }
      }, 1000);
    }

  function reportZeroResult(q, total) {
    try {
      const url = (window.SearchJetSettings && SearchJetSettings.eventsEndpoint) || '';
      if (!url) return;

      const headers = { 'Content-Type': 'application/json' };
      if (SearchJetSettings && SearchJetSettings.requireEventNonce === 1 && SearchJetSettings.restNonce) {
        headers['X-WP-Nonce'] = SearchJetSettings.restNonce;
      }

      fetch(url, {
        method: 'POST',
        headers,
        body: JSON.stringify({ type: 'zero_result', q, total: Number(total) || 0 }),
        keepalive: true
      }).catch(() => {});
    } catch (_) {
      // no-op
    }
  }
  
    // Flush logs every 15 seconds if pending
    setInterval(() => {
      if (logQueue.length && SearchJetSettings.logApiUrl) {
        navigator.sendBeacon(SearchJetSettings.logApiUrl, JSON.stringify(logQueue));
        sessionStorage.removeItem(BATCH_LOG_KEY);
        logQueue = [];
      }
    }, 15000);
  
    window.addEventListener('beforeunload', () => {
      if (logQueue.length && SearchJetSettings.logApiUrl) {
        navigator.sendBeacon(SearchJetSettings.logApiUrl, JSON.stringify(logQueue));
        sessionStorage.removeItem(BATCH_LOG_KEY);
        logQueue = [];
      }
    });
  
    // MeiliSearch client setup (direct or hosted)
    let searchClient = null;
  
function formatCurrency(amount, currency) {
  const symbols = {
    BDT: '৳',
    USD: '$',
    EUR: '€',
    GBP: '£',
    INR: '₹',
    AUD: 'A$',
    CAD: 'C$',
    JPY: '¥',
    CNY: '¥',
    AED: 'د.إ',
    SAR: '﷼',
    CHF: 'CHF',
    SEK: 'kr',
    NOK: 'kr',
    DKK: 'kr',
    PLN: 'zł',
    RUB: '₽',
    ZAR: 'R',
    TRY: '₺',
    MXN: 'MX$',
    BRL: 'R$',
    NZD: 'NZ$',
    HKD: 'HK$',
    SGD: 'S$',
    MYR: 'RM',
    THB: '฿',
    KRW: '₩',
    VND: '₫',
    NGN: '₦',
    PKR: '₨',
    EGP: 'E£'
  };

  const symbol = symbols[currency] || currency || '';
  return symbol + amount;
}

function applyCustomStyles() {
  if (!SearchJetSettings?.ui) return;
  
  const resultsContainer = document.getElementById('searchjet-results');
  if (!resultsContainer) return;
  
  // Apply theme class
  if (SearchJetSettings.ui.theme && SearchJetSettings.ui.theme !== 'default') {
    resultsContainer.classList.add(`searchjet-theme-${SearchJetSettings.ui.theme}`);
  }
  
  // Apply primary color
  if (SearchJetSettings.ui.primaryColor) {
    resultsContainer.style.setProperty('--searchjet-primary-color', SearchJetSettings.ui.primaryColor);
  }
}

let debounceTimer;
let isSearching = false;
searchClient = async function (query) {
  const attrs = (Array.isArray(SearchJetSettings.attributes) && SearchJetSettings.attributes.length)
    ? SearchJetSettings.attributes
    : ['id', 'title', 'url', 'type', 'thumbnail', 'price', 'regular_price', 'sale_price', 'currency'];

  const base = `${SearchJetSettings.searchApiBaseUrl}?q=${encodeURIComponent(query)}&page=1&limit=10`;
  const url = base + `&` + attrs.map(a => `attributes[]=${encodeURIComponent(a)}`).join('&');

  try {


    const response = await fetch(url, {
      headers: {
        'Authorization': `Bearer ${SearchJetSettings.key}`,
        'Content-Type': 'application/json',
      },
    });

    const data = await response.json();
 
    if (!response.ok) {
      throw new Error(`HTTP error: ${response.status}`);
    }

    if (!data || !Array.isArray(data.results)) {
      return { hits: [] };
    }

    return { hits: data.results };
  } catch (err) {
    throw err;
  }
};
   
    input.addEventListener('input', function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        handleSearch(input.value.trim());
      }, 200);
    });
  
    async function handleSearch(query) {
      const container = document.getElementById('searchjet-results');
    
      if (!query) {
        container.innerHTML = '';
        container.style.display = 'none';
        return;
      }
    
      try {
        const response = await searchClient(query);
        const hits = response.hits || [];


    
        container.innerHTML = '';
    
        if (hits.length === 0) {
          container.innerHTML = `<div class="searchjet-no-results"><em>No results found.</em></div>`;
          container.style.display = 'block';
          // Notify automation endpoint for burst detection
          reportZeroResult(query, 0);
          return;
        }
    
        scheduleLog(query, hits.length);

        // GA4: track search event
        if (SearchJetSettings?.integrations?.ga4 && typeof gtag === 'function') {
          gtag('event', 'search_performed', {
            search_term: query
          });
        }
    
        //hits by type
        const groupedHits = hits.reduce((acc, hit) => {
          let type = 'page';
          if (hit.url && hit.url.includes('/product/')) {
              type = 'product';
          }

          if (!acc[type]) {
              acc[type] = [];
          }
          acc[type].push(hit);
          return acc;
        }, {});
    
        // Render each group
        const groupOrder = ['product', 'page']; 
    
        groupOrder.forEach(groupName => {
          if (groupedHits[groupName]) {
            const groupTitle = document.createElement('h3');
            groupTitle.className = 'searchjet-group-title';
            groupTitle.textContent = groupName.charAt(0).toUpperCase() + groupName.slice(1) + 's';
            container.appendChild(groupTitle);

            groupedHits[groupName].forEach(hit => {
              let hitHTML = '';

              if (groupName === 'product') {
                const price = (hit.sale_price ?? hit.price);
                const hasSale = typeof hit.sale_price !== 'undefined' && hit.sale_price !== null && hit.sale_price !== '';
                const currency = hit.currency || '';

                const showThumb = SearchJetSettings?.ui?.showThumbnail ?? true;
                const showPrice = SearchJetSettings?.ui?.showPrice ?? true;
                const showCategory = SearchJetSettings?.ui?.showCategory ?? true;
                const showExcerpts = SearchJetSettings?.ui?.showExcerpts ?? true;
                const showRating = SearchJetSettings?.ui?.showRating ?? true;

                hitHTML = `
                  <a href="${hit.url || '#'}" class="searchjet-hit product-hit">
                    ${showThumb ? `<img src="${hit.thumbnail || ''}" alt="${hit.title || ''}" class="product-image">` : ''}
                    <div class="hit-details">
                      <div class="hit-title">${hit.title || 'Untitled'}</div>
                      ${showExcerpts ? `<div class="hit-description">${hit.excerpt || ''}</div>` : ''}
                      ${showCategory && hit.category ? `<div class="hit-meta">${hit.category}</div>` : ''}
                      ${showRating && hit.rating ? `<div class="hit-rating">⭐ ${hit.rating} (${hit.review_count || 0})</div>` : ''}
                    </div>
                    ${showPrice ? `<div class="hit-price">${hasSale ? `<span class="sale-price">${formatCurrency(price, currency)}</span> <span class="regular-price">${formatCurrency(hit.regular_price ?? '', currency)}</span>` : `${formatCurrency(hit.price ?? '', currency)}`}</div>` : ''}
                  </a>
                `;
              } else {
                const showExcerpts = SearchJetSettings?.ui?.showExcerpts ?? true;
                hitHTML = `
                  <a href="${hit.url || '#'}" class="searchjet-hit page-hit">
                    <div class="page-icon">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zM16 18H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                    </div>
                    <div class="hit-details">
                      <div class="hit-title">${hit.title || 'Untitled'}</div>
                      ${showExcerpts ? `<div class="hit-description">${hit.excerpt || ''}</div>` : ''}
                    </div>
                  </a>
                `;
              }
              container.insertAdjacentHTML('beforeend', hitHTML);
              const linkEl = container.lastElementChild;
              linkEl.setAttribute('role', 'option');
              linkEl.setAttribute('tabindex', '-1');
              
              if (SearchJetSettings?.integrations?.ga4 && typeof gtag === 'function') {
                linkEl.addEventListener('click', function () {
                  gtag('event', 'search_result_click', {
                    item_id: hit.id || '',
                    item_name: hit.title || '',
                    position: 1,
                    search_term: query,
                    category: hit.category || '',
                    price: hit.sale_price ?? hit.price ?? '',
                    currency: hit.currency || '',
                    variant: hit.sku || ''
                  });
                });
              }
            });

          }
        });
    
        if (hits.length > 0) {
            const seeAll = document.createElement('div');
            seeAll.className = 'searchjet-see-all';
            const link = document.createElement('a');
            link.href = `/?s=${encodeURIComponent(query)}`;
            link.textContent = 'See all results';
            seeAll.appendChild(link);
            container.appendChild(seeAll);
        }
        
        container.style.display = 'block';
    
      } catch (e) {
        isSearching = false;
        container.innerHTML = '<div class="searchjet-error">Search failed. Please try again.</div>';
        container.style.display = 'block';
      }
    }
  });