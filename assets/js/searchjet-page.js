document.addEventListener('DOMContentLoaded', function () {
  if (typeof SearchJetSettings === 'undefined') return;

  // --- helpers -------------------------------------------------------------
  function buildAttributesQuery() {
    try {
      const attrs = Array.isArray(SearchJetSettings.attributes)
        ? SearchJetSettings.attributes
        : [];
      if (!attrs.length) return '';
      const usp = new URLSearchParams();
      attrs.forEach(a => usp.append('attributes[]', a));
      const str = usp.toString();
      return str ? `&${str}` : '';
    } catch (_) {
      return '';
    }
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

  function escapeHtml(str) {
    if (!str && str !== 0) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // ------------------------------------------------------------------------
  if (document.body.classList.contains('searchjet-js-mode')) {
    const existing = document.querySelector('.wp-block-query');
    const resultsDiv = document.createElement('div');
    resultsDiv.id = 'searchjet-page-results';

    if (existing && existing.parentNode) {
      existing.parentNode.replaceChild(resultsDiv, existing);
    } else {
      const fallback = document.querySelector('main') || document.body;
      fallback.appendChild(resultsDiv);
    }
  }

  const container = document.getElementById('searchjet-page-results');
  if (!container) return;

  const usp = new URLSearchParams(window.location.search);
  const query = usp.get('s') || '';
  const page = parseInt(usp.get('page') || '1', 10);
  const limit = 10;

  if (!query) {
    container.innerHTML = '<p>No search query provided.</p>';
    return;
  }

  container.innerHTML = '<div class="searchjet-loading">Searching...</div>';

  const attrsQuery = buildAttributesQuery();
  const url = `${SearchJetSettings.searchApiBaseUrl}?q=${encodeURIComponent(query)}&page=${page}&limit=${limit}${attrsQuery}`;

  fetch(url, {
    headers: {
      'Authorization': `Bearer ${SearchJetSettings.key}`,
      'Content-Type': 'application/json'
    }
  })
    .then(res => res.json())
    .then(data => {
      container.innerHTML = '';
      const results = Array.isArray(data.results) ? data.results : [];
      const total = typeof data.total === 'number' ? data.total : results.length;

      if (results.length === 0) {
        container.innerHTML = '<p>No results found.</p>';
        reportZeroResult(query, total);
        return;
      }

      results.forEach(hit => {
        const div = document.createElement('div');
        div.className = 'searchjet-hit';
        const url = escapeHtml(hit.url || '#');
        const title = escapeHtml(hit.title || 'Untitled');
        const excerpt = escapeHtml(hit.excerpt || '');
        const type = escapeHtml(hit.type || '');
        const price = (hit.price !== undefined && hit.price !== null) ? escapeHtml(hit.price) : '';
        const currency = escapeHtml(hit.currency || '');
        const thumbnail = escapeHtml(hit.thumbnail || hit.image || '');

        div.innerHTML = `
          <article class="sj-item" itemscope itemtype="https://schema.org/CreativeWork">
            <div class="sj-media">
              ${thumbnail ? `<a href="${url}" class="sj-thumb"><img src="${thumbnail}" alt="" loading="lazy"/></a>` : ''}
            </div>
            <div class="sj-body">
              <h3 class="sj-title" itemprop="name"><a href="${url}" itemprop="url">${title}</a></h3>
              ${type ? `<span class="sj-type">${type}</span>` : ''}
              ${excerpt ? `<p class="sj-excerpt" itemprop="description">${excerpt}</p>` : ''}
              ${(price !== '' && currency) ? `<div class="sj-price" itemprop="offers" itemscope itemtype="https://schema.org/Offer"><span itemprop="priceCurrency" content="${currency}"></span><span itemprop="price">${price}</span></div>` : ''}
            </div>
          </article>
        `;
        container.appendChild(div);
      });

      if (total > limit) {
        const totalPages = Math.ceil(total / limit);
        const pagination = document.createElement('nav');
        pagination.className = 'searchjet-pagination';
        pagination.setAttribute('aria-label', 'Search results pagination');

        for (let i = 1; i <= totalPages; i++) {
          const link = document.createElement('a');
          const p = new URLSearchParams(window.location.search);
          p.set('s', query);
          p.set('page', String(i));
          link.href = `?${p.toString()}`;
          link.textContent = i;
          if (i === page) link.setAttribute('aria-current', 'page');
          pagination.appendChild(link);
        }

        container.appendChild(pagination);
      }
    })
    .catch(err => {
      console.error(err);
      container.innerHTML = '<p>Failed to load search results. Please try again.</p>';
    });
});