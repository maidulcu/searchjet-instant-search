/**
 * SearchJet Common Helper Functions
 * Shared utilities for analytics, logging, and rendering
 */
window.SearchJetHelpers = (function() {
    'use strict';

    // Logging and analytics setup
    const BATCH_LOG_KEY = 'searchjet_log_batch';
    let logQueue = JSON.parse(sessionStorage.getItem(BATCH_LOG_KEY) || '[]');
    let logTimer;

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

    function scheduleLog(query, resultCount) {
        clearTimeout(logTimer);
        logTimer = setTimeout(() => {
            if (query && shouldLogSearch(query)) {
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

    function trackGA4Search(query, resultCount) {
        if (SearchJetSettings?.integrations?.ga4 && typeof gtag === 'function') {
            gtag('event', 'search_performed', {
                search_term: query,
                results_count: resultCount
            });
        }
    }

    function trackClick(documentId, position, query = null) {
        try {
            const url = (window.SearchJetSettings && SearchJetSettings.clickAnalyticsEndpoint) || '';
            if (!url) return;

            const payload = {
                document_id: documentId,
                position: position,
                query: query || sessionStorage.getItem('searchjet_last_query') || null,
                session_id: SearchJetSettings.session_id || null,
            };

            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                keepalive: true
            }).catch(() => {});
        } catch (_) {
            // no-op
        }
    }

    function attachClickTracking(container, query) {
        if (!container) return;

        const links = container.querySelectorAll('a.searchjet-hit');
        links.forEach((link, index) => {
            link.addEventListener('click', function(e) {
                const documentId = this.dataset.documentId || this.href;
                const position = parseInt(this.dataset.position) || (index + 1);
                trackClick(documentId, position, query);
            });
        });
    }

    async function requestAIAnswer(query, results = []) {
        if (!SearchJetSettings?.aiAnswersEnabled) {
            return null;
        }

        // AI answer comes from search API with ai_answer=true parameter
        const searchUrl = SearchJetSettings.searchApiBaseUrl;
        if (!searchUrl) return null;

        try {
            const lang = new URLSearchParams(window.location.search).get('lang') || SearchJetSettings.language || '';
            const params = new URLSearchParams({
                q: query,
                ai_answer: 'true',
                limit: '5',
                ...(lang ? { filters: `language = "${lang}"` } : {})
            });

            const response = await fetch(`${searchUrl}?${params}`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${SearchJetSettings.key}`,
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();

            if (!response.ok) {
                // Handle rate limiting (429) - quota exceeded
                if (response.status === 429) {
                    return {
                        type: 'upgrade',
                        message: 'You have reached your AI answers limit for this month.',
                        upgrade_url: 'https://app.searchjetengine.com/pricing'
                    };
                }
                return null;
            }

            // AI answer is in the 'answer' field of search response
            if (data.answer) {
                return data.answer;
            }

            // No answer in response (could be disabled, quota exceeded, or not available)
            return null;

        } catch (error) {
            console.warn('AI answer request failed:', error);
            return null;
        }
    }

    function renderAIAnswer(answer) {
        if (!answer) return '';

        return `
            <div class="searchjet-ai-answer">
                <div class="searchjet-ai-badge">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                        <path d="M8 0l2.5 5.1 5.5.8-4 3.9.9 5.5L8 13.1 3.1 15.3l.9-5.5-4-3.9 5.5-.8L8 0z"/>
                    </svg>
                    AI Answer
                </div>
                <div class="searchjet-ai-content">${escapeHtml(answer)}</div>
                <div class="searchjet-ai-disclaimer">Generated by AI. Check for accuracy.</div>
            </div>
        `;
    }

    async function displayAIAnswer(container, query, results) {
        if (!container || !SearchJetSettings?.aiAnswersEnabled) {
            return;
        }

        // Create placeholder
        const placeholder = document.createElement('div');
        placeholder.className = 'searchjet-ai-loading';
        placeholder.innerHTML = `
            <div class="searchjet-ai-badge">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                    <path d="M8 0l2.5 5.1 5.5.8-4 3.9.9 5.5L8 13.1 3.1 15.3l.9-5.5-4-3.9 5.5-.8L8 0z"/>
                </svg>
                AI Answer
            </div>
            <div class="searchjet-ai-skeleton">
                <div class="searchjet-skeleton-line"></div>
                <div class="searchjet-skeleton-line"></div>
                <div class="searchjet-skeleton-line short"></div>
            </div>
        `;

        // Insert at the beginning of container
        container.insertBefore(placeholder, container.firstChild);

        // Request AI answer
        const answer = await requestAIAnswer(query, results);

        // Remove placeholder
        placeholder.remove();

        // Handle different response types
        if (answer) {
            const answerEl = document.createElement('div');

            // Check if it's an upgrade message
            if (typeof answer === 'object' && answer.type === 'upgrade') {
                answerEl.innerHTML = `
                    <div class="searchjet-ai-upgrade">
                        <div class="searchjet-ai-badge">
                            🔒 AI Answer
                        </div>
                        <div class="searchjet-ai-content">${escapeHtml(answer.message)}</div>
                        <a href="${answer.upgrade_url}" class="searchjet-upgrade-button" target="_blank">
                            Upgrade Now
                        </a>
                    </div>
                `;
            } else {
                // Regular answer
                answerEl.innerHTML = renderAIAnswer(answer);
            }

            container.insertBefore(answerEl.firstChild, container.firstChild);
        }
    }

    function handleSearchAnalytics(query, items) {
        if (!query) return;

        // Store last query for click tracking
        sessionStorage.setItem('searchjet_last_query', query);

        scheduleLog(query, items.length);
        trackGA4Search(query, items.length);

        if (items.length === 0) {
            reportZeroResult(query, 0);
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function sanitizeUrl(url) {
        if (!url) return '#';
        // Only allow http, https, and relative URLs
        const trimmed = url.trim();
        if (/^(https?:\/\/|\/|#)/i.test(trimmed)) {
            return escapeHtml(trimmed);
        }
        return '#';
    }

    function formatCurrency(amount, currency) {
        const symbols = {
            BDT: '৳', USD: '$', EUR: '€', GBP: '£', INR: '₹', AUD: 'A$',
            CAD: 'C$', JPY: '¥', CNY: '¥', AED: 'د.إ', SAR: '﷼', CHF: 'CHF',
            SEK: 'kr', NOK: 'kr', DKK: 'kr', PLN: 'zł', RUB: '₽', ZAR: 'R',
            TRY: '₺', MXN: 'MX$', BRL: 'R$', NZD: 'NZ$', HKD: 'HK$',
            SGD: 'S$', MYR: 'RM', THB: '฿', KRW: '₩', VND: '₫', NGN: '₦',
            PKR: '₨', EGP: 'E£'
        };
        const symbol = symbols[currency] || currency || '';
        return symbol + amount;
    }

    function renderProductHit(hit, position = 0) {
        if (hit.type === 'section-header') {
            return `<h2 class="searchjet-group-title">${hit.title} (${hit.count})</h2>`;
        }

        const showThumb = SearchJetSettings?.ui?.showThumbnail ?? true;
        const showPrice = SearchJetSettings?.ui?.showPrice ?? true;
        const showExcerpts = SearchJetSettings?.ui?.showExcerpts ?? true;
        const showRating = SearchJetSettings?.ui?.showRating ?? true;
        const showStock = SearchJetSettings?.ui?.showStock ?? true;
        const showCategory = SearchJetSettings?.ui?.showCategory ?? true;

        const price = hit.sale_price !== undefined ? hit.sale_price : hit.price;
        const hasSale = typeof hit.sale_price !== 'undefined' && hit.sale_price !== null;
        const currency = hit.currency || '';

        // Default product image if no thumbnail
        const defaultImage = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDgiIGhlaWdodD0iNDgiIHZpZXdCb3g9IjAgMCA0OCA0OCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQ4IiBoZWlnaHQ9IjQ4IiBmaWxsPSIjRjVGNUY3Ii8+CjxwYXRoIGQ9Ik0yNCAzNkMzMC42Mjc0IDM2IDM2IDMwLjYyNzQgMzYgMjRDMzYgMTcuMzcyNiAzMC42Mjc0IDEyIDI0IDEyQzE3LjM3MjYgMTIgMTIgMTcuMzcyNiAxMiAyNEMxMiAzMC42Mjc0IDE3LjM3MjYgMzYgMjQgMzYiIGZpbGw9IiNEMUQxRDYiLz4KPC9zdmc+';
        const imageUrl = hit.thumbnail || defaultImage;

        const documentId = hit.id || hit.url || '';

        return `
            <a href="${sanitizeUrl(hit.url)}" class="searchjet-hit product-hit" data-document-id="${escapeHtml(documentId)}" data-position="${position}">
                ${showThumb ? `<img src="${imageUrl}" alt="${hit.title || ''}" class="product-image" loading="lazy">` : ''}
                <div class="hit-content">
                    <div class="hit-title">${escapeHtml(hit.title || 'Untitled')}</div>
                    ${showExcerpts && hit.excerpt ? `<div class="hit-description">${escapeHtml(hit.excerpt)}</div>` : ''}
                    <div class="hit-meta">
                        ${showCategory && hit.categories && hit.categories.length ? `<div class="hit-category">${hit.categories.join(', ')}</div>` : ''}
                        ${showRating && hit.rating ? `<div class="hit-rating">★ ${hit.rating}</div>` : ''}
                        ${showStock && hit.stock_status ? `<div class="hit-stock ${hit.stock_status}">${hit.stock_status}</div>` : ''}
                        ${showPrice && price ? `<div class="hit-price">${hasSale ? `<span class="sale-price">${formatCurrency(price, currency)}</span> <span class="regular-price">${formatCurrency(hit.regular_price || '', currency)}</span>` : `${formatCurrency(price, currency)}`}</div>` : ''}
                    </div>
                </div>

            </a>
        `;
    }
    
    function renderPageHit(hit, position = 0) {
        if (hit.type === 'section-header') {
            return `<h2 class="searchjet-group-title">${hit.title} (${hit.count})</h2>`;
        }

        const showExcerpts = SearchJetSettings?.ui?.showExcerpts ?? true;
        const documentId = hit.id || hit.url || '';

        return `
            <a href="${sanitizeUrl(hit.url)}" class="searchjet-hit page-hit" data-document-id="${escapeHtml(documentId)}" data-position="${position}">
                <div class="page-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zM16 18H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                    </svg>
                </div>
                <div class="hit-content">
                    <div class="hit-title">${escapeHtml(hit.title || 'Untitled')}</div>
                    ${showExcerpts && hit.excerpt ? `<div class="hit-description">${escapeHtml(hit.excerpt)}</div>` : ''}
                </div>
            </a>
        `;
    }

    function groupItems(items) {
        // Add position to each item
        const itemsWithPosition = items.map((item, index) => ({
            ...item,
            _position: index + 1
        }));

        const products = itemsWithPosition.filter(item => item.type === 'product' || (item.url && item.url.includes('/product/')));
        const pages = itemsWithPosition.filter(item => item.type !== 'product' && !(item.url && item.url.includes('/product/')));

        let grouped = [];
        if (products.length > 0) {
            grouped.push({ type: 'section-header', title: 'Products', count: products.length });
            grouped = grouped.concat(products);
        }
        if (pages.length > 0) {
            grouped.push({ type: 'section-header', title: 'Pages', count: pages.length });
            grouped = grouped.concat(pages);
        }

        return grouped;
    }

    // Initialize periodic log flushing
    function initLogFlushing() {
        setInterval(() => {
            if (logQueue.length && SearchJetSettings.logApiUrl) {
                const payload = new Blob([JSON.stringify(logQueue)], { type: 'application/json' });
                navigator.sendBeacon(SearchJetSettings.logApiUrl, payload);
                sessionStorage.removeItem(BATCH_LOG_KEY);
                logQueue = [];
            }
        }, 15000);

        window.addEventListener('beforeunload', () => {
            if (logQueue.length && SearchJetSettings.logApiUrl) {
                const payload = new Blob([JSON.stringify(logQueue)], { type: 'application/json' });
                navigator.sendBeacon(SearchJetSettings.logApiUrl, payload);
                sessionStorage.removeItem(BATCH_LOG_KEY);
                logQueue = [];
            }
        });
    }

    // Auto-initialize log flushing
    if (typeof SearchJetSettings !== 'undefined') {
        initLogFlushing();
    }

    // Public API
    return {
        scheduleLog,
        reportZeroResult,
        trackGA4Search,
        trackClick,
        attachClickTracking,
        requestAIAnswer,
        renderAIAnswer,
        displayAIAnswer,
        handleSearchAnalytics,
        escapeHtml,
        sanitizeUrl,
        formatCurrency,
        renderProductHit,
        renderPageHit,
        groupItems
    };
})();
