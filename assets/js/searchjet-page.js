document.addEventListener('DOMContentLoaded', function () {
  if (typeof SearchJetSettings === 'undefined') return;

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

  const query = new URLSearchParams(window.location.search).get('s') || '';
  const page = parseInt(new URLSearchParams(window.location.search).get('page') || '1', 10);
  const limit = 10;

  if (!query) {
    container.innerHTML = '<p>No search query provided.</p>';
    return;
  }

  container.innerHTML = '<div class="searchjet-loading">Searching...</div>';

  fetch(`${SearchJetSettings.searchApiBaseUrl}?q=${encodeURIComponent(query)}&page=${page}&limit=${limit}`, {
    headers: {
      'Authorization': `Bearer ${SearchJetSettings.key}`,
      'Content-Type': 'application/json'
    }
  })
    .then(res => res.json())
    .then(data => {
      container.innerHTML = '';
      const results = data.results || [];

      if (results.length === 0) {
        container.innerHTML = '<p>No results found.</p>';
        return;
      }

      results.forEach(hit => {
        const div = document.createElement('div');
        div.className = 'searchjet-hit';
        div.innerHTML = `
          <h3><a href="${hit.url || '#'}">${hit.title || 'Untitled'}</a></h3>
          <p>${hit.excerpt || ''}</p>
        `;
        container.appendChild(div);
      });

      if (data.total > limit) {
        const totalPages = Math.ceil(data.total / limit);
        const pagination = document.createElement('div');
        pagination.className = 'searchjet-pagination';

        for (let i = 1; i <= totalPages; i++) {
          const link = document.createElement('a');
          link.href = `?s=${encodeURIComponent(query)}&page=${i}`;
          link.textContent = i;
          if (i === page) link.style.fontWeight = 'bold';
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