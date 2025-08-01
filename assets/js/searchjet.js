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
    input.parentNode.appendChild(resultsDiv);
  }

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

    searchClient = async function (query) {
      const attrs = ['id', 'title', 'url']; // define attributes
      const url = `${SearchJetSettings.searchApiBaseUrl}?q=${encodeURIComponent(query)}&page=1&limit=5&attributes[]=${attrs.join('&attributes[]=')}`;
      const response = await fetch(url, {
        headers: {
          'Authorization': `Bearer ${SearchJetSettings.key}`,
          'Content-Type': 'application/json',
        },
      });
      if (!response.ok) throw new Error('Proxy search failed');
      const data = await response.json();
      //console.log(data);
      return { hits: data.results || [] };
    };
 
  let debounceTimer;
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
      return;
    }

    try {
      const response = await searchClient(query);
      const hits = response.hits || [];

      container.innerHTML = '';

      if (hits.length === 0) {
        container.innerHTML = `<div style="padding:8px;"><em>No results found.</em></div>`;
      } else {
        //const limitedHits = hits.slice(0, 5);

        scheduleLog(query, hits.length);

        hits.forEach((hit) => {
          const div = document.createElement('div');
          div.className = 'searchjet-hit';
          const a = document.createElement('a');
          a.href = hit.url || '#';
          a.textContent = hit.title || hit.content || 'Untitled';
          div.appendChild(a);
          container.appendChild(div);
        });

        const seeAll = document.createElement('div');
        seeAll.className = 'searchjet-see-all';

        const link = document.createElement('a');
        link.href = `/?s=${encodeURIComponent(query)}`;
        link.textContent = 'See all results';

        seeAll.appendChild(link);
        container.appendChild(seeAll);
      }
    } catch (e) {
      console.error(e);
    }
  }  
});