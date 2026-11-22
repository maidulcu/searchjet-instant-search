window.SearchJetFullSearchLoaded = true;

document.addEventListener('DOMContentLoaded', function () {
  // Show the theme's own search results again. Used whenever SearchJet cannot show results,
  // so the visitor never gets an empty search page.
  function restoreNativeResults() {
    document.body.classList.add('searchjet-native');
    const box = document.getElementById('searchjet-page-results');
    if (box) box.remove();
  }

  if (typeof SearchJetSettings === 'undefined' || !SearchJetSettings.key) {
    restoreNativeResults();
    return;
  }

  // Check if helpers are available
  if (typeof SearchJetHelpers === 'undefined') {
    console.error('SearchJetHelpers not loaded');
    restoreNativeResults();
    return;
  }

  // Use shared helpers
  const { handleSearchAnalytics, renderProductHit, renderPageHit, groupItems, escapeHtml, formatCurrency, attachClickTracking, displayAIAnswer } = SearchJetHelpers;

  const usp = new URLSearchParams(window.location.search);
  const query = usp.get('s') || '';
  const lang = usp.get('lang') || SearchJetSettings.language || '';
  
  if (!query) {
    restoreNativeResults();
    return;
  }

  // If this script was delayed (for example by a "delay JavaScript" optimisation) the safety net
  // may already have shown the theme's results. SearchJet takes over now, so hide them again.
  document.body.classList.remove('searchjet-native');

  if (typeof SearchJet !== 'undefined' && SearchJetSettings.key) {
    initSearchJetConnect(query);
  } else {
    initOriginalSearch(query);
  }

  function initSearchJetConnect(initialQuery) {
    try {
      const searchJet = new SearchJet({
        apiKey: SearchJetSettings.key,
        baseUrl: SearchJetSettings.searchApiBaseUrl.replace('/api/v1/search', '/api')
      });

      const container = document.getElementById('searchjet-page-results') || createResultsContainer();
      
      container.innerHTML = `
        <div class="searchjet-page-header">
          <h1>Search Results for "${escapeHtml(initialQuery)}"</h1>
        </div>
        <div id="searchjet-hits"></div>
        <div id="searchjet-pagination"></div>
      `;

      const search = searchJet.instantSearch({
        searchParameters: lang ? { filters: `language = "${lang}"` } : {}
      });

      search.addWidget(
        SearchJet.widgets.hits({
          container: '#searchjet-hits',
          templates: {
            item: (hit) => {
              const isProduct = hit.type === 'product' || (hit.url && hit.url.includes('/product/'));
              const position = hit._position || 0;
              return isProduct ? renderProductHit(hit, position) : renderPageHit(hit, position);
            },
            empty: () => `
              <div class="searchjet-no-results">
                <h3>No results found</h3>
                <p>Try adjusting your search terms or browse our categories.</p>
              </div>
            `
          },
          transformItems: (items) => {
            handleSearchAnalytics(initialQuery, items);
            const grouped = groupItems(items);

            // Display AI answer and attach click tracking after render
            setTimeout(() => {
              const hitsContainer = document.getElementById('searchjet-hits');
              displayAIAnswer(hitsContainer, initialQuery, items);
              attachClickTracking(hitsContainer, initialQuery);
            }, 100);

            return grouped;
          }
        })
      );

      search.addWidget(
        SearchJet.widgets.pagination({
          container: '#searchjet-pagination'
        })
      );

      search.start();

      // Service unreachable or key rejected: give the visitor the theme's results instead.
      search.helper.on('error', function (error) {
        console.error('SearchJet search failed:', error);
        restoreNativeResults();
      });
      
      // Set initial query and search
      search.helper.setQuery(initialQuery).search();

    } catch (error) {
      console.error('SearchJet Connect initialization failed:', error);
      initOriginalSearch(initialQuery);
    }
  }
  



  function initOriginalSearch(query) {
    const container = document.getElementById('searchjet-page-results') || createResultsContainer();
    container.innerHTML = '<div class="searchjet-loading">Searching...</div>';

    const page = parseInt(usp.get('page') || '1', 10);
    const limit = 20;
    const attrs = SearchJetSettings.attributes || ['id', 'title', 'url', 'type', 'thumbnail', 'price', 'currency'];

    // Add ai_answer parameter if enabled
    let url = `${SearchJetSettings.searchApiBaseUrl}?q=${encodeURIComponent(query)}&page=${page}&limit=${limit}&${attrs.map(a => `attributes[]=${encodeURIComponent(a)}`).join('&')}`;
    if (lang) {
      url += `&filters=language%20%3D%20${encodeURIComponent('"' + lang + '"')}`;
    }
    if (SearchJetSettings?.aiAnswersEnabled && page === 1) {
      url += '&ai_answer=true';
    }

    fetch(url, {
      headers: {
        'Authorization': `Bearer ${SearchJetSettings.key}`,
        'Content-Type': 'application/json',
      },
    })
    .then(response => {
      if (!response.ok) throw new Error('HTTP ' + response.status);
      return response.json();
    })
    .then(data => {
      const hits = data.results || [];
      const total = data.total || hits.length;
      const aiAnswer = data.answer || null;

      if (hits.length === 0) {
        container.innerHTML = `
          <div class="searchjet-no-results">
            <h3>No results found</h3>
            <p>Try adjusting your search terms or browse our categories.</p>
          </div>
        `;
        return;
      }

      const products = hits.filter(hit => hit.type === 'product' || (hit.url && hit.url.includes('/product/')));
      const pages = hits.filter(hit => hit.type !== 'product' && !(hit.url && hit.url.includes('/product/')));

      let html = `<div class="searchjet-page-header"><h2>Search Results for "${escapeHtml(query)}" (${total} found)</h2></div>`;

      // Add AI answer if available (only on first page)
      if (aiAnswer && page === 1) {
        if (typeof aiAnswer === 'object' && aiAnswer.type === 'upgrade') {
          html += `
            <div class="searchjet-ai-upgrade">
              <div class="searchjet-ai-badge">🔒 AI Answer</div>
              <div class="searchjet-ai-content">${escapeHtml(aiAnswer.message)}</div>
              <a href="${aiAnswer.upgrade_url}" class="searchjet-upgrade-button" target="_blank">Upgrade Now</a>
            </div>
          `;
        } else {
          html += `
            <div class="searchjet-ai-answer">
              <div class="searchjet-ai-badge">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                  <path d="M8 0l2.5 5.1 5.5.8-4 3.9.9 5.5L8 13.1 3.1 15.3l.9-5.5-4-3.9 5.5-.8L8 0z"/>
                </svg>
                AI Answer
              </div>
              <div class="searchjet-ai-content">${escapeHtml(aiAnswer)}</div>
              <div class="searchjet-ai-disclaimer">Generated by AI. Check for accuracy.</div>
            </div>
          `;
        }
      }

      if (products.length > 0) {
        html += `<h2 class="searchjet-group-title">Products (${products.length})</h2>`;
        products.forEach(hit => {
          html += renderProductHit(hit);
        });
      }

      if (pages.length > 0) {
        html += `<h2 class="searchjet-group-title">Pages (${pages.length})</h2>`;
        pages.forEach(hit => {
          html += renderPageHit(hit);
        });
      }

      html += renderPagination({ page: page - 1, nbPages: Math.ceil(total / limit), nbHits: total }, query);
      container.innerHTML = html;
    })
    .catch(error => {
      console.error('Search failed:', error);
      restoreNativeResults();
    });
  }
  
  function renderPagination(result, query) {
    const currentPage = (result.page || 0) + 1;
    const totalPages = result.nbPages || 1;
    
    if (totalPages <= 1) return '';
    
    let html = '<div class="searchjet-pagination">';
    
    if (currentPage > 1) {
      html += `<a href="?s=${encodeURIComponent(query)}&page=${currentPage - 1}" class="page-link prev">← Previous</a>`;
    }
    
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    if (startPage > 1) {
      html += `<a href="?s=${encodeURIComponent(query)}&page=1" class="page-link">1</a>`;
      if (startPage > 2) html += '<span class="page-dots">...</span>';
    }
    
    for (let i = startPage; i <= endPage; i++) {
      if (i === currentPage) {
        html += `<span class="page-link current">${i}</span>`;
      } else {
        html += `<a href="?s=${encodeURIComponent(query)}&page=${i}" class="page-link">${i}</a>`;
      }
    }
    
    if (endPage < totalPages) {
      if (endPage < totalPages - 1) html += '<span class="page-dots">...</span>';
      html += `<a href="?s=${encodeURIComponent(query)}&page=${totalPages}" class="page-link">${totalPages}</a>`;
    }
    
    if (currentPage < totalPages) {
      html += `<a href="?s=${encodeURIComponent(query)}&page=${currentPage + 1}" class="page-link next">Next →</a>`;
    }
    
    html += '</div>';
    return html;
  }
  
  function createResultsContainer() {
    const container = document.createElement('div');
    container.id = 'searchjet-page-results';
    container.className = 'searchjet-page-results';
    container.style.cssText = 'padding: 20px; margin: 20px 0; width: 100%; max-width: 1200px;';
    
    // Try multiple selectors in order of preference
    const targets = [
      '.site-main',
      '.content-area', 
      'main',
      '.main',
      '#main',
      '.content',
      '#content',
      '.container',
      '#primary',
      '.entry-content',
      'body'
    ];
    
    let targetElement = null;
    for (const selector of targets) {
      try {
        targetElement = document.querySelector(selector);
        if (targetElement && targetElement.nodeType === Node.ELEMENT_NODE) {
          break;
        }
      } catch (e) {
        console.warn(`Failed to query selector: ${selector}`, e);
      }
    }
    
    // Guaranteed fallback - body always exists
    if (!targetElement) {
      targetElement = document.body;
    }
    
    try {
      if (targetElement === document.body) {
        targetElement.appendChild(container);
      } else {
        targetElement.insertBefore(container, targetElement.firstChild);
      }
    } catch (e) {
      console.warn('Failed to insert container, using body as fallback', e);
      document.body.appendChild(container);
    }
    
    return container;
  }

});