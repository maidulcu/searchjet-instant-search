<?php
namespace SearchJet\Admin;

class DashboardChatbot {

    public static function init(): void {
        add_action('admin_footer', [self::class, 'render']);
    }

    public static function render(): void {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'searchjet') === false) return;
        if (!current_user_can('manage_options')) return;

        $faqs = [
            ['q' => __('How do I get an API key?', 'searchjet-instant-search'),
             'a' => __('Sign up free at app.searchjetengine.com/register (no credit card required). Copy your API key from the dashboard and paste it in SearchJet → Settings.', 'searchjet-instant-search'),
             'l' => 'https://app.searchjetengine.com/register'],
            ['q' => __('How do I connect SearchJet to my site?', 'searchjet-instant-search'),
             'a' => __('Go to SearchJet → Settings, paste your API key, and click Save Settings. The connection status will update automatically.', 'searchjet-instant-search'),
             'l' => admin_url('admin.php?page=searchjet-settings')],
            ['q' => __('How do I index my content?', 'searchjet-instant-search'),
             'a' => __('Go to SearchJet → Indexing and click "Start Indexing". SearchJet will automatically index your posts, pages, and products. You can also use Bulk Re-Index for targeted reindexing.', 'searchjet-instant-search'),
             'l' => admin_url('admin.php?page=searchjet-indexing')],
            ['q' => __('How long does indexing take?', 'searchjet-instant-search'),
             'a' => __('For most sites, initial indexing completes within a few minutes. Large sites (10,000+ documents) may take longer. You can monitor progress in Task History.', 'searchjet-instant-search'),
             'l' => admin_url('admin.php?page=searchjet-task-history')],
            ['q' => __('How do I customize the search UI?', 'searchjet-instant-search'),
             'a' => __('Go to SearchJet → Search UI to customize theme style, colors, display options (thumbnail, price, category, excerpt, rating, stock), and see a live preview of your changes.', 'searchjet-instant-search'),
             'l' => admin_url('admin.php?page=searchjet-search-ui')],
            ['q' => __('Does SearchJet work with WooCommerce?', 'searchjet-instant-search'),
             'a' => __('Yes! SearchJet fully supports WooCommerce product search, including thumbnails, prices, ratings, stock status, and category filtering.', 'searchjet-instant-search'),
             'l' => 'https://searchjetengine.com/docs/woocommerce'],
            ['q' => __('What is Bulk Re-Index?', 'searchjet-instant-search'),
             'a' => __('Bulk Re-Index lets you refresh your search index in three modes: Global Sync (everything), Targeted Sync (specific post types), and Differential Sync (specific date range).', 'searchjet-instant-search'),
             'l' => admin_url('admin.php?page=searchjet-bulk-reindex')],
            ['q' => __('What are AI Agents?', 'searchjet-instant-search'),
             'a' => __('AI Agents are intelligent assistants for managing search. They include Configuration agents (setup), Content Analysis agents (quality scoring), and Merchandising agents (boost rules, synonyms).', 'searchjet-instant-search'),
             'l' => admin_url('admin.php?page=searchjet-agents')],
            ['q' => __('How do I view search analytics?', 'searchjet-instant-search'),
             'a' => __('Go to SearchJet → Analytics to see search volume, top queries, zero-result queries, indexed documents, and reindex activity.', 'searchjet-instant-search'),
             'l' => admin_url('admin.php?page=searchjet-analytics')],
            ['q' => __('What plans are available?', 'searchjet-instant-search'),
             'a' => __('Free: 10,000 searches/mo, 1,000 pages, 50 AI answers. Pro ($19/mo): 50,000 searches, 25,000 pages, semantic search. Growth ($79/mo): 250,000 searches, 100,000 pages. Enterprise ($299/mo): unlimited everything.', 'searchjet-instant-search'),
             'l' => 'https://searchjetengine.com/pricing'],
            ['q' => __('What is the free tier?', 'searchjet-instant-search'),
             'a' => __('The free tier includes 10,000 searches/month, 1,000 indexed pages, 1 site, 7 days analytics, and 50 AI answers/month. No credit card required.', 'searchjet-instant-search'),
             'l' => 'https://searchjetengine.com/pricing'],
            ['q' => __('What does the Pro plan include?', 'searchjet-instant-search'),
             'a' => __('Pro ($19/mo) includes 50,000 searches/month, 25,000 indexed pages, 5 sites, 30 days analytics, 500 AI answers/month, 8 reindexes/day, AI Semantic Search, and 24h priority support.', 'searchjet-instant-search'),
             'l' => 'https://searchjetengine.com/pricing'],
            ['q' => __('What does the Growth plan include?', 'searchjet-instant-search'),
             'a' => __('Growth ($79/mo) includes 250,000 searches/month, 100,000 indexed pages, 3 sites, 90 days analytics, 5,000 AI answers/month, 20 reindexes/day, AI Semantic Search, and 4h priority support.', 'searchjet-instant-search'),
             'l' => 'https://searchjetengine.com/pricing'],
            ['q' => __('What does the Enterprise plan include?', 'searchjet-instant-search'),
             'a' => __('Enterprise ($299/mo) includes unlimited searches, pages, sites, and analytics. 100,000 AI answers/month, unlimited reindexes, AI Semantic Search, 1h priority support, and a dedicated instance.', 'searchjet-instant-search'),
             'l' => 'https://searchjetengine.com/pricing'],
            ['q' => __('How do I upgrade my plan?', 'searchjet-instant-search'),
             'a' => __('Visit searchjetengine.com/pricing to compare plans and upgrade. Your new limits activate instantly after payment.', 'searchjet-instant-search'),
             'l' => 'https://searchjetengine.com/pricing'],
            ['q' => __('What post types are indexed?', 'searchjet-instant-search'),
             'a' => __('By default, SearchJet indexes posts, pages, and products (if WooCommerce is active). You can configure additional post types in Index Settings.', 'searchjet-instant-search'),
             'l' => admin_url('admin.php?page=searchjet-index-settings')],
            ['q' => __('How do I check index health?', 'searchjet-instant-search'),
             'a' => __('Go to SearchJet → Index Health to monitor index status, error rates, document counts, and last sync times for each content type.', 'searchjet-instant-search'),
             'l' => admin_url('admin.php?page=searchjet-index-health')],
            ['q' => __('Can I use SearchJet with other search plugins?', 'searchjet-instant-search'),
             'a' => __('SearchJet replaces WordPress default search. Disable other search plugins first if needed. Toggle "Disable Search Override" in Settings to fall back.', 'searchjet-instant-search'),
             'l' => admin_url('admin.php?page=searchjet-settings')],
            ['q' => __('How do I contact support?', 'searchjet-instant-search'),
             'a' => __('Visit searchjetengine.com/contact for support. For documentation and guides, check searchjetengine.com/docs/.', 'searchjet-instant-search'),
             'l' => 'https://searchjetengine.com/contact'],
        ];

        ?>
        <div class="searchjet-chatbot-float">
            <div class="searchjet-chatbot-panel" id="searchjet-chatbot-panel">
                <div class="searchjet-chatbot-panel-header">
                    <div class="sj-chatbot-header-avatar">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-search-white.svg'); ?>" alt="" width="22" height="22" style="display:block;">
                    </div>
                    <div>
                        <h3><?php esc_html_e('SearchJet AI Bot', 'searchjet-instant-search'); ?></h3>
                        <span class="sj-chatbot-status"><?php esc_html_e('Ask me anything about SearchJet', 'searchjet-instant-search'); ?></span>
                    </div>
                    <button class="searchjet-chatbot-close" id="searchjet-chatbot-close">&times;</button>
                </div>
                <div class="searchjet-chatbot-panel-body" id="searchjet-chatbot-body">
                    <div class="searchjet-chatbot-message bot">
                        <div class="avatar sj-avatar-bot">
                            <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-search-white.svg'); ?>" alt="" width="16" height="16" style="display:block;">
                        </div>
                        <div class="bubble"><?php esc_html_e('Hi! I\'m SearchJet Assistant. How can I help you today?', 'searchjet-instant-search'); ?></div>
                    </div>
                    <div class="sj-quick-questions" id="sj-quick-questions">
                        <button class="sj-quick-btn" data-query="How do I get started?">
                            <span class="sj-quick-icon"><img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-rocket.svg'); ?>" alt="" width="13" height="13" style="display:block;"></span> <?php esc_html_e('Getting Started', 'searchjet-instant-search'); ?>
                        </button>
                        <button class="sj-quick-btn" data-query="How do I index content?">
                            <span class="sj-quick-icon"><img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-package.svg'); ?>" alt="" width="13" height="13" style="display:block;"></span> <?php esc_html_e('Indexing', 'searchjet-instant-search'); ?>
                        </button>
                        <button class="sj-quick-btn" data-query="What plans are available?">
                            <span class="sj-quick-icon"><img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-gem.svg'); ?>" alt="" width="13" height="13" style="display:block;"></span> <?php esc_html_e('Plans & Pricing', 'searchjet-instant-search'); ?>
                        </button>
                        <button class="sj-quick-btn" data-query="How do I customize search UI?">
                            <span class="sj-quick-icon"><img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-palette.svg'); ?>" alt="" width="13" height="13" style="display:block;"></span> <?php esc_html_e('Customize UI', 'searchjet-instant-search'); ?>
                        </button>
                        <button class="sj-quick-btn" data-query="WooCommerce integration">
                            <span class="sj-quick-icon"><img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-cart.svg'); ?>" alt="" width="13" height="13" style="display:block;"></span> <?php esc_html_e('WooCommerce', 'searchjet-instant-search'); ?>
                        </button>
                        <button class="sj-quick-btn" data-query="How do I contact support?">
                            <span class="sj-quick-icon"><img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-chat.svg'); ?>" alt="" width="13" height="13" style="display:block;"></span> <?php esc_html_e('Support', 'searchjet-instant-search'); ?>
                        </button>
                    </div>
                    <div id="searchjet-chatbot-matches"></div>
                    <div id="searchjet-chatbot-noresult" style="display:none;">
                        <div class="searchjet-chatbot-message bot">
                            <div class="avatar sj-avatar-bot">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-help-white.svg'); ?>" alt="" width="16" height="16" style="display:block;">
                            </div>
                            <div class="bubble"><?php esc_html_e('I couldn\'t find a specific answer. Try these resources:', 'searchjet-instant-search'); ?></div>
                        </div>
                        <div class="searchjet-chatbot-links">
                            <a href="https://searchjetengine.com/docs/" target="_blank" class="searchjet-chatbot-link">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-book-blue.svg'); ?>" alt="" width="18" height="18" style="display:block;flex-shrink:0;">
                                <?php esc_html_e('Full Documentation', 'searchjet-instant-search'); ?>
                            </a>
                            <a href="https://searchjetengine.com/contact" target="_blank" class="searchjet-chatbot-link">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-phone-blue.svg'); ?>" alt="" width="18" height="18" style="display:block;flex-shrink:0;">
                                <?php esc_html_e('Contact Support', 'searchjet-instant-search'); ?>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-docs')); ?>" class="searchjet-chatbot-link">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-help-blue.svg'); ?>" alt="" width="18" height="18" style="display:block;flex-shrink:0;">
                                <?php esc_html_e('Plugin Docs Hub', 'searchjet-instant-search'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="searchjet-chatbot-panel-footer">
                    <input type="text" id="searchjet-chatbot-input" placeholder="<?php esc_attr_e('Type your question...', 'searchjet-instant-search'); ?>" autocomplete="off">
                    <button id="searchjet-chatbot-send">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-send-white.svg'); ?>" alt="" width="16" height="16" style="display:block;">
                    </button>
                </div>
            </div>
            <div class="searchjet-chatbot-tooltip" id="searchjet-chatbot-tooltip"><?php esc_html_e('Need help? I\'m here for you!', 'searchjet-instant-search'); ?></div>
            <button class="searchjet-chatbot-btn" id="searchjet-chatbot-btn" aria-label="<?php esc_attr_e('Open help chat', 'searchjet-instant-search'); ?>">
                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/dash-message-white.svg'); ?>" alt="" width="24" height="24" style="display:block;">
                <span class="sj-chatbot-badge" id="sj-chatbot-badge">1</span>
            </button>
        </div>

        <script>
        var searchjetImgUrl = '<?php echo esc_js(SEARCHJET_PLUGIN_URL . 'assets/img/'); ?>';
        var searchjetFAQ = <?php echo wp_json_encode($faqs, JSON_UNESCAPED_UNICODE); ?>;
        var searchjetAdminUrl = <?php echo wp_json_encode(admin_url('/')); ?>;
        </script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var chatBtn = document.getElementById('searchjet-chatbot-btn');
            var chatPanel = document.getElementById('searchjet-chatbot-panel');
            var chatClose = document.getElementById('searchjet-chatbot-close');
            var chatInput = document.getElementById('searchjet-chatbot-input');
            var chatSend = document.getElementById('searchjet-chatbot-send');
            var matchesDiv = document.getElementById('searchjet-chatbot-matches');
            var noresultDiv = document.getElementById('searchjet-chatbot-noresult');
            var quickBtns = document.querySelectorAll('.sj-quick-btn');
            var badge = document.getElementById('sj-chatbot-badge');

            function playNotifSound() {
                try {
                    var ctx = new (window.AudioContext || window.webkitAudioContext)();
                    var osc = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.frequency.value = 880;
                    osc.type = 'sine';
                    gain.gain.setValueAtTime(0.15, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.3);
                    setTimeout(function() {
                        var osc2 = ctx.createOscillator();
                        var gain2 = ctx.createGain();
                        osc2.connect(gain2);
                        gain2.connect(ctx.destination);
                        osc2.frequency.value = 1320;
                        osc2.type = 'sine';
                        gain2.gain.setValueAtTime(0.12, ctx.currentTime);
                        gain2.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.25);
                        osc2.start(ctx.currentTime);
                        osc2.stop(ctx.currentTime + 0.25);
                    }, 150);
                } catch(e) {}
            }

            function togglePanel(open) {
                if (!chatPanel) return;
                chatPanel.classList.toggle('open', open);
                if (open) {
                    if (badge) badge.style.display = 'none';
                    if (chatInput) chatInput.focus();
                }
            }

            if (chatBtn && chatPanel) {
                chatBtn.addEventListener('click', function() {
                    togglePanel(!chatPanel.classList.contains('open'));
                });
            }
            if (chatClose && chatPanel) {
                chatClose.addEventListener('click', function() { togglePanel(false); });
            }
            document.addEventListener('click', function(e) {
                if (chatPanel && chatPanel.classList.contains('open')) {
                    var f = document.querySelector('.searchjet-chatbot-float');
                    if (f && !f.contains(e.target)) togglePanel(false);
                }
            });

            quickBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var query = this.getAttribute('data-query');
                    if (chatInput) chatInput.value = query;
                    searchFAQ(query);
                    quickBtns.forEach(function(b) { b.classList.remove('sj-quick-active'); });
                    btn.classList.add('sj-quick-active');
                });
            });

            function searchFAQ(query) {
                if (!matchesDiv || !noresultDiv) return;
                var q = query.toLowerCase().trim();
                if (!q) {
                    matchesDiv.innerHTML = '';
                    noresultDiv.style.display = 'none';
                    quickBtns.forEach(function(b) { b.classList.remove('sj-quick-active'); });
                    return;
                }
                var words = q.split(/\s+/).filter(Boolean);
                var scored = searchjetFAQ.map(function(f) {
                    var haystack = (f.q + ' ' + f.a).toLowerCase();
                    var score = 0;
                    words.forEach(function(w) {
                        if (haystack.indexOf(w) > -1) score += 1;
                    });
                    return { faq: f, score: score };
                }).filter(function(s) { return s.score > 0; });
                scored.sort(function(a, b) { return b.score - a.score; });
                var top = scored.slice(0, 3);
                if (top.length === 0) {
                    matchesDiv.innerHTML = '';
                    noresultDiv.style.display = 'block';
                    return;
                }
                noresultDiv.style.display = 'none';
                matchesDiv.innerHTML = top.map(function(s) {
                    var faq = s.faq;
                    var link = faq.l && faq.l.indexOf('http') === 0 ? faq.l : (searchjetAdminUrl.replace(/\/+$/, '') + '/' + (faq.l || '').replace(/^\//, ''));
                    var isExternal = faq.l && faq.l.indexOf('http') === 0;
                    return '<div class="searchjet-chatbot-message bot sj-msg-animate">' +
                        '<div class="avatar sj-avatar-bot"><img src="' + searchjetImgUrl + 'dash-search-white.svg" alt="" width="16" height="16" style="display:block;"></div>' +
                        '<div class="bubble"><strong>' + faq.q + '</strong><br>' + faq.a +
                        (link ? ' <a href="' + link + '" ' + (isExternal ? 'target="_blank"' : '') + ' class="sj-learn-more">' + '<?php echo esc_js(__('Learn more', 'searchjet-instant-search')); ?>' + ' →</a>' : '') +
                        '</div></div>';
                }).join('');
            }

            var debounceTimer;
            if (chatInput) {
                chatInput.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(function() { searchFAQ(chatInput.value); }, 200);
                });
                chatInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') searchFAQ(chatInput.value);
                });
            }
            if (chatSend) {
                chatSend.addEventListener('click', function() {
                    if (chatInput) searchFAQ(chatInput.value);
                });
            }

            var hasSeenHelp = localStorage.getItem('searchjet_help_seen');
            if (!hasSeenHelp) {
                localStorage.setItem('searchjet_help_seen', '1');
                setTimeout(function() {
                    playNotifSound();
                    togglePanel(true);
                    if (badge) badge.style.display = 'flex';
                }, 1200);
            }
        });
        </script>
        <?php
    }
}
