<?php
namespace SearchJet\Admin;

class DashboardScripts {

    public static function renderInlineScripts(): void {
        self::renderSearchJourneyScript();
        self::renderSetupWizardScript();
    }

    private static function renderSearchJourneyScript(): void {
        ?>
        <script>
        var searchjetImgUrl = '<?php echo esc_js(SEARCHJET_PLUGIN_URL . 'assets/img/'); ?>';
        var sampleQueries = [
            { typed: 'wirelss headphnes', corrected: 'wireless headphones', results: [
                { icon: 'dash-product-headphones', title: 'Wireless Headphones Pro', rating: '★★★★☆', reviews: '128', price: '$79.99', stock: 'In Stock', category: 'Electronics > Audio > Headphones' },
                { icon: 'dash-product-music', title: 'Wireless Earbuds Mini', rating: '★★★★★', reviews: '89', price: '$49.99', stock: 'In Stock', category: 'Electronics > Audio > Earbuds' }
            ]},
            { typed: 'runing shoess', corrected: 'running shoes', results: [
                { icon: 'dash-product-shoe', title: 'Running Shoes Ultra', rating: '★★★★★', reviews: '256', price: '$129.99', stock: 'In Stock', category: 'Sports > Footwear > Running' },
                { icon: 'dash-product-runner', title: 'Marathon Pro Trainer', rating: '★★★★☆', reviews: '184', price: '$99.99', stock: 'Low Stock', category: 'Sports > Footwear > Running' }
            ]},
            { typed: 'offee mugs', corrected: 'coffee mugs', results: [
                { icon: 'dash-product-coffee', title: 'Ceramic Coffee Mug Set', rating: '★★★★★', reviews: '312', price: '$24.99', stock: 'In Stock', category: 'Kitchen > Drinkware > Mugs' },
                { icon: 'dash-product-teapot', title: 'Travel Coffee Tumbler', rating: '★★★★☆', reviews: '167', price: '$34.99', stock: 'In Stock', category: 'Kitchen > Drinkware > Travel' }
            ]}
        ];
        var currentQueryIndex = 0;
        var journeyRunning = false;
        var journeyAnimInterval = null;

        function startJourneyAnim() {
            if (journeyRunning) return;
            journeyRunning = true;
            step1();
        }

        function stopJourneyAnim() {
            journeyRunning = false;
            if (journeyAnimInterval) { clearInterval(journeyAnimInterval); journeyAnimInterval = null; }
        }

        function step1() {
            var q = sampleQueries[currentQueryIndex];
            var el = document.getElementById('searchjet-typed-text');
            var list = document.getElementById('searchjet-results-list');
            if (!el || !list) { journeyRunning = false; return; }

            list.innerHTML = '';
            document.querySelectorAll('.searchjet-journey-processing-item .check').forEach(function(c) {
                c.innerHTML = '<img src="' + searchjetImgUrl + 'dash-clock-gray.svg" alt="" width="16" height="16" style="display:block;">';
            });

            var i = 0;
            el.textContent = '';
            journeyAnimInterval = setInterval(function() {
                if (i < q.typed.length) { el.textContent += q.typed[i]; i++; }
                else { clearInterval(journeyAnimInterval); setTimeout(step2, 400); }
            }, 60);
        }

        function step2() {
            var items = ['searchjet-proc-typo', 'searchjet-proc-intent', 'searchjet-proc-rank'];
            var idx = 0;
            var pi = setInterval(function() {
                if (idx < items.length) {
                    var el = document.getElementById(items[idx]);
                    if (el) {
                        var c = el.querySelector('.check');
                        c.innerHTML = '<img src="' + searchjetImgUrl + 'dash-check-green.svg" alt="" width="16" height="16" style="display:block;">';
                    }
                    idx++;
                } else { clearInterval(pi); setTimeout(step3, 300); }
            }, 350);
        }

        function step3() {
            var q = sampleQueries[currentQueryIndex];
            var list = document.getElementById('searchjet-results-list');
            if (!list) { journeyRunning = false; return; }

            list.innerHTML = '';
            q.results.forEach(function(r, i) {
                setTimeout(function() {
                    var card = document.createElement('div');
                    card.className = 'searchjet-journey-result-card';
                    card.innerHTML = '<div class="searchjet-journey-result-img"><img src="' + searchjetImgUrl + r.icon + '.svg" alt="" width="24" height="24" style="display:block;"></div>' +
                        '<div class="searchjet-journey-result-info"><div class="searchjet-journey-result-title">' + r.title + '</div>' +
                        '<div class="searchjet-journey-result-meta"><span class="searchjet-journey-result-rating">' + r.rating + '</span>' +
                        '<span>(' + r.reviews + ')</span><span class="searchjet-journey-result-price">' + r.price + '</span>' +
                        '<span class="searchjet-journey-result-stock">' + r.stock + '</span></div>' +
                        '<div style="font-size:12px;color:#64748b;margin-top:4px;">' + r.category + '</div></div>';
                    list.appendChild(card);
                }, i * 200);
            });

            setTimeout(function() {
                currentQueryIndex = (currentQueryIndex + 1) % sampleQueries.length;
                journeyRunning = false;
                setTimeout(startJourneyAnim, 3500);
            }, q.results.length * 200 + 500);
        }

        function showHowItWorksModal() {
            var overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(21,42,83,0.6);z-index:100000;display:flex;align-items:center;justify-content:center;animation:fadeIn 0.2s ease;';

            var modal = document.createElement('div');
            modal.style.cssText = 'background:#fff;border-radius:16px;max-width:800px;width:92%;max-height:88vh;box-shadow:0 25px 60px rgba(0,0,0,0.3);overflow:hidden;transform:translateY(0);animation:slideUp 0.3s ease;display:flex;flex-direction:column;';

            var header = document.createElement('div');
            header.style.cssText = 'background:linear-gradient(135deg,#152A53,#2563eb);padding:24px 28px;position:relative;flex-shrink:0;';
            header.innerHTML = '<div style="display:flex;align-items:center;gap:12px;">' +
                '<div style="width:40px;height:40px;background:rgba(255,255,255,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;">' +
                '<img src="' + searchjetImgUrl + 'dash-search-white.svg" alt="" width="22" height="22" style="display:block;"></div>' +
                '<div><h2 style="margin:0;font-size:20px;font-weight:700;color:white;"><?php echo esc_js(__('Welcome to SearchJet!', 'searchjet-instant-search')); ?></h2>' +
                '<p style="margin:4px 0 0;font-size:13px;color:rgba(255,255,255,0.75);"><?php echo esc_js(__('Watch what happens when a visitor searches on your site', 'searchjet-instant-search')); ?></p></div></div>' +
                '<button id="how-it-works-close" style="position:absolute;top:16px;right:16px;width:32px;height:32px;border-radius:50%;border:none;background:rgba(255,255,255,0.15);color:white;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background 0.2s;" onmouseover="this.style.background=\'rgba(255,255,255,0.25)\'" onmouseout="this.style.background=\'rgba(255,255,255,0.15)\'">&times;</button>';

            var body = document.createElement('div');
            body.style.cssText = 'padding:28px;overflow-y:auto;flex:1;';
            body.innerHTML =
                '<div class="searchjet-journey">' +
                '<div class="searchjet-journey-story">' +
                '<div class="searchjet-journey-step" id="modal-step-typing">' +
                '<div class="searchjet-journey-step-label"><span class="step-icon typing"><img src="' + searchjetImgUrl + 'dash-message-blue.svg" alt="" width="18" height="18" style="display:block;"></span><?php echo esc_js(__('Step 1: Visitor Types', 'searchjet-instant-search')); ?></div>' +
                '<div class="searchjet-journey-typing"><div class="searchjet-journey-searchbox"><span class="searchjet-journey-typed-text" id="modal-typed-text"></span><span class="cursor"></span></div></div>' +
                '</div>' +
                '<div class="searchjet-journey-arrow"><img src="' + searchjetImgUrl + 'dash-arrowdown-light.svg" alt="" width="24" height="24" style="display:block;"></div>' +
                '<div class="searchjet-journey-step" id="modal-step-processing">' +
                '<div class="searchjet-journey-step-label"><span class="step-icon processing"><img src="' + searchjetImgUrl + 'dash-clock-amber.svg" alt="" width="18" height="18" style="display:block;"></span><?php echo esc_js(__('Step 2: AI Processing (~30ms)', 'searchjet-instant-search')); ?></div>' +
                '<div class="searchjet-journey-processing-content">' +
                '<div class="searchjet-journey-processing-item" id="modal-proc-typo"><span class="check pending"><img src="' + searchjetImgUrl + 'dash-clock-gray.svg" alt="" width="16" height="16" style="display:block;"></span><span><?php echo esc_js(__('Typo detected: wirelss → wireless', 'searchjet-instant-search')); ?></span></div>' +
                '<div class="searchjet-journey-processing-item" id="modal-proc-intent"><span class="check pending"><img src="' + searchjetImgUrl + 'dash-clock-gray.svg" alt="" width="16" height="16" style="display:block;"></span><span><?php echo esc_js(__('Intent understood: headphones', 'searchjet-instant-search')); ?></span></div>' +
                '<div class="searchjet-journey-processing-item" id="modal-proc-rank"><span class="check pending"><img src="' + searchjetImgUrl + 'dash-clock-gray.svg" alt="" width="16" height="16" style="display:block;"></span><span><?php echo esc_js(__('AI ranking by relevance + popularity', 'searchjet-instant-search')); ?></span></div>' +
                '</div></div>' +
                '<div class="searchjet-journey-arrow"><img src="' + searchjetImgUrl + 'dash-arrowdown-light.svg" alt="" width="24" height="24" style="display:block;"></div>' +
                '<div class="searchjet-journey-step" id="modal-step-results">' +
                '<div class="searchjet-journey-step-label"><span class="step-icon results"><img src="' + searchjetImgUrl + 'dash-trending-green.svg" alt="" width="18" height="18" style="display:block;"></span><?php echo esc_js(__('Step 3: Results Appear (instantly)', 'searchjet-instant-search')); ?></div>' +
                '<div class="searchjet-journey-results-list" id="modal-results-list"></div>' +
                '</div></div>' +
                '<div class="searchjet-journey-demo">' +
                '<div class="searchjet-journey-demo-header"><h4><img src="' + searchjetImgUrl + 'dash-search-dark.svg" alt="" width="18" height="18" style="display:block;"><?php echo esc_js(__('Try It Yourself', 'searchjet-instant-search')); ?></h4><span class="try-badge"><?php echo esc_js(__('Interactive', 'searchjet-instant-search')); ?></span></div>' +
                '<div class="searchjet-journey-demo-input-wrapper"><img class="searchjet-journey-demo-search-icon" src="' + searchjetImgUrl + 'dash-search-light.svg" alt="" width="20" height="20" style="display:block;"><input type="text" class="searchjet-journey-demo-input" id="modal-demo-search" placeholder="<?php echo esc_attr__('Try searching: wireless headphones, running shoes, coffee...', 'searchjet-instant-search'); ?>" /></div>' +
                '<div class="searchjet-journey-demo-results" id="modal-demo-results"></div>' +
                '</div>' +
                '<div class="searchjet-journey-stats">' +
                '<div class="searchjet-journey-stat"><div class="searchjet-journey-stat-icon"><img src="' + searchjetImgUrl + 'dash-zap2-dark.svg" alt="" width="20" height="20" style="display:block;"></div><div class="searchjet-journey-stat-value">&lt;50ms</div><div class="searchjet-journey-stat-label"><?php echo esc_js(__('Search Speed', 'searchjet-instant-search')); ?></div></div>' +
                '<div class="searchjet-journey-stat"><div class="searchjet-journey-stat-icon"><img src="' + searchjetImgUrl + 'dash-activity-dark.svg" alt="" width="20" height="20" style="display:block;"></div><div class="searchjet-journey-stat-value">99.9%</div><div class="searchjet-journey-stat-label"><?php echo esc_js(__('Uptime', 'searchjet-instant-search')); ?></div></div>' +
                '<div class="searchjet-journey-stat"><div class="searchjet-journey-stat-icon"><img src="' + searchjetImgUrl + 'dash-clock-dark.svg" alt="" width="20" height="20" style="display:block;"></div><div class="searchjet-journey-stat-value"><?php echo esc_js(__('AI', 'searchjet-instant-search')); ?></div><div class="searchjet-journey-stat-label"><?php echo esc_js(__('Powered', 'searchjet-instant-search')); ?></div></div>' +
                '<div class="searchjet-journey-stat"><div class="searchjet-journey-stat-icon"><img src="' + searchjetImgUrl + 'dash-monitor-dark.svg" alt="" width="20" height="20" style="display:block;"></div><div class="searchjet-journey-stat-value"><?php echo esc_js(__('Mobile', 'searchjet-instant-search')); ?></div><div class="searchjet-journey-stat-label"><?php echo esc_js(__('Optimized', 'searchjet-instant-search')); ?></div></div>' +
                '</div></div>';

            modal.appendChild(header);
            modal.appendChild(body);
            overlay.appendChild(modal);
            document.body.appendChild(overlay);

            document.getElementById('how-it-works-close').onclick = function() {
                document.body.removeChild(overlay);
                stopJourneyAnim();
            };
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    document.body.removeChild(overlay);
                    stopJourneyAnim();
                }
            });

            setTimeout(startModalJourneyAnim, 300);

            var demoInput = document.getElementById('modal-demo-search');
            if (demoInput) {
                demoInput.addEventListener('input', function() {
                    var val = this.value.toLowerCase().trim();
                    var rd = document.getElementById('modal-demo-results');
                    if (!rd) return;
                    if (val.length < 2) { rd.innerHTML = ''; return; }
                    var all = [];
                    sampleQueries.forEach(function(q) { q.results.forEach(function(r) { all.push(r); }); });
                    var m = all.filter(function(r) { return r.title.toLowerCase().indexOf(val) > -1 || r.category.toLowerCase().indexOf(val) > -1; });
                    if (m.length === 0) { rd.innerHTML = '<div style="text-align:center;padding:16px;color:#64748b;font-size:14px;">No results found</div>'; return; }
                    rd.innerHTML = m.slice(0, 3).map(function(r) {
                        return '<div class="searchjet-journey-result-card"><div class="searchjet-journey-result-img"><img src="' + searchjetImgUrl + r.icon + '.svg" alt="" width="24" height="24" style="display:block;"></div>' +
                            '<div class="searchjet-journey-result-info"><div class="searchjet-journey-result-title">' + r.title + '</div>' +
                            '<div class="searchjet-journey-result-meta"><span class="searchjet-journey-result-rating">' + r.rating + '</span>' +
                            '<span>(' + r.reviews + ')</span><span class="searchjet-journey-result-price">' + r.price + '</span>' +
                            '<span class="searchjet-journey-result-stock">' + r.stock + '</span></div></div></div>';
                    }).join('');
                });
            }
        }

        var modalQueryIndex = 0;
        var modalJourneyRunning = false;

        function startModalJourneyAnim() {
            if (modalJourneyRunning) return;
            modalJourneyRunning = true;
            modalStep1();
        }

        function modalStep1() {
            var q = sampleQueries[modalQueryIndex];
            var el = document.getElementById('modal-typed-text');
            var list = document.getElementById('modal-results-list');
            if (!el || !list) { modalJourneyRunning = false; return; }

            list.innerHTML = '';
            document.querySelectorAll('#modal-step-processing .check').forEach(function(c) {
                c.innerHTML = '<img src="' + searchjetImgUrl + 'dash-clock-gray.svg" alt="" width="16" height="16" style="display:block;">';
            });

            var i = 0;
            el.textContent = '';
            var t = setInterval(function() {
                if (i < q.typed.length) { el.textContent += q.typed[i]; i++; }
                else { clearInterval(t); setTimeout(modalStep2, 400); }
            }, 60);
        }

        function modalStep2() {
            var items = ['modal-proc-typo', 'modal-proc-intent', 'modal-proc-rank'];
            var idx = 0;
            var pi = setInterval(function() {
                if (idx < items.length) {
                    var el = document.getElementById(items[idx]);
                    if (el) {
                        var c = el.querySelector('.check');
                        c.innerHTML = '<img src="' + searchjetImgUrl + 'dash-check-green.svg" alt="" width="16" height="16" style="display:block;">';
                    }
                    idx++;
                } else { clearInterval(pi); setTimeout(modalStep3, 300); }
            }, 350);
        }

        function modalStep3() {
            var q = sampleQueries[modalQueryIndex];
            var list = document.getElementById('modal-results-list');
            if (!list) { modalJourneyRunning = false; return; }

            list.innerHTML = '';
            q.results.forEach(function(r, i) {
                setTimeout(function() {
                    var card = document.createElement('div');
                    card.className = 'searchjet-journey-result-card';
                    card.innerHTML = '<div class="searchjet-journey-result-img"><img src="' + searchjetImgUrl + r.icon + '.svg" alt="" width="24" height="24" style="display:block;"></div>' +
                        '<div class="searchjet-journey-result-info"><div class="searchjet-journey-result-title">' + r.title + '</div>' +
                        '<div class="searchjet-journey-result-meta"><span class="searchjet-journey-result-rating">' + r.rating + '</span>' +
                        '<span>(' + r.reviews + ')</span><span class="searchjet-journey-result-price">' + r.price + '</span>' +
                        '<span class="searchjet-journey-result-stock">' + r.stock + '</span></div>' +
                        '<div style="font-size:12px;color:#64748b;margin-top:4px;">' + r.category + '</div></div>';
                    list.appendChild(card);
                }, i * 200);
            });

            setTimeout(function() {
                modalQueryIndex = (modalQueryIndex + 1) % sampleQueries.length;
                modalJourneyRunning = false;
                setTimeout(startModalJourneyAnim, 3500);
            }, q.results.length * 200 + 500);
        }

        document.addEventListener('DOMContentLoaded', function() {
            var showBtn = document.getElementById('searchjet-show-how-it-works');
            if (showBtn) {
                showBtn.addEventListener('click', function() {
                    showHowItWorksModal();
                });
            }

            var hasSeenWelcome = localStorage.getItem('searchjet_welcome_seen');
            if (!hasSeenWelcome) {
                localStorage.setItem('searchjet_welcome_seen', '1');
                setTimeout(function() {
                    showSetupWizard();
                }, 800);
            }
        });
        </script>
        <?php
    }

    private static function renderSetupWizardScript(): void {
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var setupBtn = document.getElementById('searchjet-start-setup');
                if (setupBtn) {
                    setupBtn.addEventListener('click', function() { showSetupWizard(); });
                }

                function showSetupWizard() {
                    var overlay = document.createElement('div');
                    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(21,42,83,0.6);z-index:100000;display:flex;align-items:center;justify-content:center;animation:fadeIn 0.2s ease;';

                    var modal = document.createElement('div');
                    modal.style.cssText = 'background:#fff;border-radius:16px;max-width:520px;width:90%;box-shadow:0 25px 60px rgba(0,0,0,0.3);overflow:hidden;transform:translateY(0);animation:slideUp 0.3s ease;';

                    var header = document.createElement('div');
                    header.style.cssText = 'background:linear-gradient(135deg,#152A53,#2563eb);padding:28px 28px 24px;position:relative;';
                    header.innerHTML = '<div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">' +
                        '<div style="width:40px;height:40px;background:rgba(255,255,255,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;">' +
                        '<img src="' + searchjetImgUrl + 'dash-search-white.svg" alt="" width="22" height="22" style="display:block;"></div>' +
                        '<h2 style="margin:0;font-size:20px;font-weight:700;color:white;">' + '<?php echo esc_js(__('Welcome to SearchJet!', 'searchjet-instant-search')); ?>' + '</h2></div>' +
                        '<p style="margin:0;font-size:14px;color:rgba(255,255,255,0.75);">' + '<?php echo esc_js(__('Get AI-powered instant search running in 2 minutes', 'searchjet-instant-search')); ?>' + '</p>' +
                        '<button id="wizard-close" style="position:absolute;top:16px;right:16px;width:32px;height:32px;border-radius:50%;border:none;background:rgba(255,255,255,0.15);color:white;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background 0.2s;" onmouseover="this.style.background=\'rgba(255,255,255,0.25)\'" onmouseout="this.style.background=\'rgba(255,255,255,0.15)\'">&times;</button>';

                    var body = document.createElement('div');
                    body.style.cssText = 'padding:28px;';

                    var steps = document.createElement('div');
                    steps.style.cssText = 'display:flex;gap:8px;margin-bottom:24px;';
                    steps.innerHTML = '<div class="wiz-step active" data-step="1" style="flex:1;height:4px;border-radius:2px;background:#2563eb;transition:background 0.3s;"></div>' +
                        '<div class="wiz-step" data-step="2" style="flex:1;height:4px;border-radius:2px;background:#e9ecef;transition:background 0.3s;"></div>';
                    body.appendChild(steps);

                    var content = document.createElement('div');
                    content.id = 'wiz-content';
                    body.appendChild(content);

                    var footer = document.createElement('div');
                    footer.id = 'wiz-footer';
                    footer.style.cssText = 'padding:0 28px 28px;';
                    body.appendChild(footer);

                    showStep1(content, footer, steps);

                    modal.appendChild(header);
                    modal.appendChild(body);
                    overlay.appendChild(modal);
                    document.body.appendChild(overlay);

                    document.getElementById('wizard-close').onclick = function() { document.body.removeChild(overlay); };
                    overlay.addEventListener('click', function(e) {
                        if (e.target === overlay) document.body.removeChild(overlay);
                    });

                    var style = document.createElement('style');
                    style.textContent = '@keyframes fadeIn{from{opacity:0}to{opacity:1}}@keyframes slideUp{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}@keyframes pulse{0%{opacity:1}50%{opacity:0.5}100%{opacity:1}}';
                    document.head.appendChild(style);
                }

                function showStep1(content, footer, steps, overlay) {
                    content.innerHTML =
                        '<h3 style="margin:0 0 8px;font-size:18px;font-weight:600;color:#152A53;">' + '<?php echo esc_js(__('Transform Your Site Search', 'searchjet-instant-search')); ?>' + '</h3>' +
                        '<p style="margin:0 0 20px;font-size:14px;color:#6c757d;">' + '<?php echo esc_js(__('Here\'s what SearchJet brings to your site:', 'searchjet-instant-search')); ?>' + '</p>' +
                        '<div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;">' +
                        '<div style="display:flex;align-items:center;gap:14px;padding:14px 16px;background:#f8f9fa;border-radius:10px;border-left:3px solid #2563eb;">' +
                        '<div style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#2563eb,#667eea);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><img src="' + searchjetImgUrl + 'dash-zap2-white.svg" alt="" width="18" height="18" style="display:block;"></div>' +
                        '<div><strong style="display:block;font-size:14px;color:#212529;">' + '<?php echo esc_js(__('Lightning Fast', 'searchjet-instant-search')); ?>' + '</strong><span style="font-size:13px;color:#6c757d;">' + '<?php echo esc_js(__('Instant search results as users type', 'searchjet-instant-search')); ?>' + '</span></div></div>' +
                        '<div style="display:flex;align-items:center;gap:14px;padding:14px 16px;background:#f8f9fa;border-radius:10px;border-left:3px solid #667eea;">' +
                        '<div style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><img src="' + searchjetImgUrl + 'dash-clock-white.svg" alt="" width="18" height="18" style="display:block;"></div>' +
                        '<div><strong style="display:block;font-size:14px;color:#212529;">' + '<?php echo esc_js(__('AI-Powered', 'searchjet-instant-search')); ?>' + '</strong><span style="font-size:13px;color:#6c757d;">' + '<?php echo esc_js(__('Smart typo tolerance and relevance ranking', 'searchjet-instant-search')); ?>' + '</span></div></div>' +
                        '<div style="display:flex;align-items:center;gap:14px;padding:14px 16px;background:#f8f9fa;border-radius:10px;border-left:3px solid #28a745;">' +
                        '<div style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#28a745,#20c997);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><img src="' + searchjetImgUrl + 'dash-barchart2-white.svg" alt="" width="18" height="18" style="display:block;"></div>' +
                        '<div><strong style="display:block;font-size:14px;color:#212529;">' + '<?php echo esc_js(__('Analytics', 'searchjet-instant-search')); ?>' + '</strong><span style="font-size:13px;color:#6c757d;">' + '<?php echo esc_js(__('Track search performance and insights', 'searchjet-instant-search')); ?>' + '</span></div></div>' +
                        '</div>';

                    footer.innerHTML = '<button class="searchjet-btn searchjet-btn-primary" id="wiz-next" style="width:100%;padding:14px;font-size:15px;">' + '<?php echo esc_js(__('Continue to Setup', 'searchjet-instant-search')); ?>' + '</button>';

                    var next = footer.querySelector('#wiz-next');
                    if (next) {
                        next.onclick = function() {
                            steps.querySelector('[data-step="1"]').style.background = '#28a745';
                            steps.querySelector('[data-step="2"]').style.background = '#2563eb';
                            showStep2(content, footer, steps, overlay);
                        };
                    }
                }

                function showStep2(content, footer, steps, overlay) {
                    content.innerHTML =
                        '<h3 style="margin:0 0 8px;font-size:18px;font-weight:600;color:#152A53;">' + '<?php echo esc_js(__('Connect to SearchJet', 'searchjet-instant-search')); ?>' + '</h3>' +
                        '<p style="margin:0 0 16px;font-size:14px;color:#6c757d;">' + '<?php echo esc_js(__('Enter your API key to link your site with SearchJet\'s search engine.', 'searchjet-instant-search')); ?>' + '</p>' +
                        '<div style="background:#f0f7ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px;margin-bottom:20px;display:flex;gap:12px;align-items:flex-start;">' +
                        '<img src="' + searchjetImgUrl + 'dash-help-blue.svg" alt="" width="20" height="20" style="display:block;flex-shrink:0;margin-top:1px;">' +
                        '<div><strong style="font-size:13px;color:#1e40af;display:block;margin-bottom:2px;">' + '<?php echo esc_js(__('Don\'t have an account?', 'searchjet-instant-search')); ?>' + '</strong>' +
                        '<span style="font-size:13px;color:#2563eb;"><a href="https://app.searchjetengine.com/register" target="_blank" style="color:#2563eb;font-weight:500;">' + '<?php echo esc_js(__('Sign up free', 'searchjet-instant-search')); ?>' + '</a> ' + '<?php echo esc_js(__('— no credit card required', 'searchjet-instant-search')); ?>' + '</span></div></div>' +
                        '<div style="margin-bottom:4px;">' +
                        '<label style="font-size:14px;font-weight:600;color:#212529;display:block;margin-bottom:6px;">' + '<?php echo esc_js(__('API Key', 'searchjet-instant-search')); ?>' + '</label>' +
                        '<input type="text" id="wizard-api-key" placeholder="' + '<?php echo esc_js(__('Paste your API key here', 'searchjet-instant-search')); ?>' + '" style="width:100%;padding:14px 16px;border:2px solid #e9ecef;border-radius:10px;font-size:14px;box-sizing:border-box;transition:border-color 0.2s;" onfocus="this.style.borderColor=\'#2563eb\'" onblur="this.style.borderColor=\'#e9ecef\'">' +
                        '<p style="margin:6px 0 0;font-size:12px;color:#6c757d;">' + '<?php echo esc_js(__('Found at', 'searchjet-instant-search')); ?>' + ' <a href="https://app.searchjetengine.com/dashboard" target="_blank" style="color:#2563eb;">app.searchjetengine.com/dashboard</a></p>' +
                        '</div>';

                    footer.innerHTML = '<div style="display:flex;gap:10px;">' +
                        '<button id="wiz-back" class="searchjet-btn searchjet-btn-secondary" style="flex:1;padding:14px;font-size:14px;">' + '<?php echo esc_js(__('Back', 'searchjet-instant-search')); ?>' + '</button>' +
                        '<button id="wiz-save" class="searchjet-btn searchjet-btn-primary" style="flex:3;padding:14px;font-size:14px;position:relative;">' + '<?php echo esc_js(__('Save & Go to Settings', 'searchjet-instant-search')); ?>' + '</button>' +
                        '</div>';

                    document.getElementById('wiz-back').onclick = function() {
                        steps.querySelector('[data-step="2"]').style.background = '#e9ecef';
                        steps.querySelector('[data-step="1"]').style.background = '#2563eb';
                    showStep1(content, footer, steps, overlay);
                    };

                    document.getElementById('wiz-save').onclick = function() {
                        var key = document.getElementById('wizard-api-key').value.trim();
                        if (!key) {
                            document.getElementById('wizard-api-key').style.borderColor = '#dc3545';
                            document.getElementById('wizard-api-key').placeholder = '<?php echo esc_js(__('Please enter your API key', 'searchjet-instant-search')); ?>';
                            return;
                        }
                        var btn = document.getElementById('wiz-save');
                        btn.disabled = true;
                        btn.textContent = '<?php echo esc_js(__('Saving...', 'searchjet-instant-search')); ?>';
                        jQuery.post(ajaxurl, {
                            action: 'searchjet_save_api_key',
                            nonce: '<?php echo esc_js(wp_create_nonce('searchjet_wizard_nonce')); ?>',
                            api_key: key
                        }, function(response) {
                            if (response.success) {
                                window.location.href = '<?php echo esc_js(admin_url('admin.php?page=searchjet-settings')); ?>';
                            } else {
                                btn.disabled = false;
                                btn.textContent = '<?php echo esc_js(__('Save & Go to Settings', 'searchjet-instant-search')); ?>';
                                alert(response.data.message || '<?php echo esc_js(__('Failed to save API key', 'searchjet-instant-search')); ?>');
                            }
                        }).fail(function() {
                            btn.disabled = false;
                            btn.textContent = '<?php echo esc_js(__('Save & Go to Settings', 'searchjet-instant-search')); ?>';
                            alert('<?php echo esc_js(__('Connection error. Please try again.', 'searchjet-instant-search')); ?>');
                        });
                    };
                }
            });
        </script>
        <?php
    }
}
