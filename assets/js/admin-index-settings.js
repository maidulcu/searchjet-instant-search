(function ($) {
    'use strict';

    const Settings = {
        currentSettings: {},
        attributeTypes: ['searchable', 'filterable', 'sortable'],

        init() {
            this.loadSettings();
            this.loadStats();
            this.bindEvents();

            setInterval(() => this.loadStats(), 30000);
        },

        bindEvents() {
            $('#searchjet-index-settings-form').on('submit', (e) => {
                e.preventDefault();
                this.saveSettings();
            });

            $(document).on('click', '.sj-btn-add', (e) => {
                const type = $(e.currentTarget).data('type');
                this.promptAddAttribute(type);
            });
        },

        loadSettings() {
            const $loading = $('#searchjet-form-loading');
            const $content = $('#searchjet-form-content');

            $.ajax({
                url: searchjetIndexSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'searchjet_load_index_settings',
                    nonce: searchjetIndexSettings.nonce,
                },
                success: (response) => {
                    if (response.success) {
                        this.currentSettings = response.data;
                        this.renderSettings();
                        $loading.hide();
                        $content.removeClass('hidden').fadeIn();
                    } else {
                        $loading.html('<div class="sj-error-message">' + (response.data || searchjetIndexSettings.strings.error) + '</div>');
                    }
                },
                error: () => {
                    $loading.html('<div class="sj-error-message">' + searchjetIndexSettings.strings.error + '</div>');
                }
            });
        },

        loadStats() {
            $.ajax({
                url: searchjetIndexSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'searchjet_load_index_stats',
                    nonce: searchjetIndexSettings.nonce,
                },
                success: (response) => {
                    if (response.success) {
                        this.renderStats(response.data);
                    }
                }
            });
        },

        renderSettings() {
            const settings = this.currentSettings;

            this.attributeTypes.forEach(type => {
                const list = settings[type + 'Attributes'] || [];
                this.renderAttributeList(type, list);
            });

            if (settings.rankingRules && Array.isArray(settings.rankingRules)) {
                $('#searchjet-ranking-rules').val(settings.rankingRules.join(', '));
            }
        },

        renderAttributeList(type, attributes) {
            const $container = $(`#searchjet-${type}-attributes`);
            $container.empty();

            if (attributes.length === 0) {
                $container.html('<span class="sj-empty-text">No attributes</span>');
                return;
            }

            attributes.forEach((attr) => {
                const $tag = $(`
                    <div class="sj-tag" data-value="${this.escapeHtml(attr)}">
                        <span>${this.escapeHtml(attr)}</span>
                        <span class="sj-tag-remove">
                            <img src="${searchjetIndexSettings.imgUrl}index-x-gray.svg" alt="" width="12" height="12" style="display:block;">
                        </span>
                    </div>
                `);

                $container.append($tag);

                $tag.find('.sj-tag-remove').on('click', () => {
                    $tag.fadeOut(200, function () {
                        $(this).remove();
                        if ($container.children().length === 0) {
                            $container.html('<span class="sj-empty-text">No attributes</span>');
                        }
                    });
                });
            });
        },

        promptAddAttribute(type) {
            const $container = $(`#searchjet-${type}-attributes`);
            $container.find('.sj-empty-text').remove();

            const $inputWrap = $(`
                <div class="sj-tag sj-input-mode">
                    <input type="text" class="sj-tag-input" placeholder="Attribute name..." />
                    <span class="sj-tag-add">
                        <img src="${searchjetIndexSettings.imgUrl}index-plus-indigo.svg" alt="" width="12" height="12" style="display:block;">
                    </span>
                </div>
            `);

            $container.append($inputWrap);
            const $input = $inputWrap.find('input');
            $input.focus();

            const commit = () => {
                const val = $input.val().trim();
                if (val) {
                    const existing = Array.from($container.find('.sj-tag:not(.sj-input-mode) span:first-child')).map(s => $(s).text());
                    if (existing.includes(val)) {
                        $inputWrap.shake();
                        return;
                    }

                    const $tag = $(`
                        <div class="sj-tag" data-value="${this.escapeHtml(val)}">
                            <span>${this.escapeHtml(val)}</span>
                            <span class="sj-tag-remove">
                                <img src="${searchjetIndexSettings.imgUrl}index-x-gray.svg" alt="" width="12" height="12" style="display:block;">
                            </span>
                        </div>
                    `);
                    $inputWrap.replaceWith($tag);
                    $tag.find('.sj-tag-remove').on('click', () => $tag.remove());
                } else {
                    $inputWrap.remove();
                }
            };

            $input.on('blur', commit);
            $input.on('keypress', (e) => { if (e.which === 13) { e.preventDefault(); commit(); } });
            $inputWrap.find('.sj-tag-add').on('click', commit);
        },

        saveSettings() {
            const data = {
                action: 'searchjet_save_index_settings',
                nonce: searchjetIndexSettings.nonce,
            };

            this.attributeTypes.forEach((type) => {
                const attrs = [];
                $(`#searchjet-${type}-attributes`).find('.sj-tag:not(.sj-input-mode)').each(function () {
                    attrs.push($(this).data('value'));
                });
                const key = type + 'Attributes';
                data[key] = JSON.stringify(attrs);
            });

            const rankingRules = $('#searchjet-ranking-rules').val().trim();
            if (rankingRules) {
                data.rankingRules = rankingRules;
            }

            const $btn = $('#searchjet-index-settings-form button[type="submit"]');
            const originalHtml = $btn.html();

            $btn.prop('disabled', true).html('<img src="' + searchjetIndexSettings.imgUrl + 'bulk-refresh-white.svg" alt="" width="18" height="18" style="display:inline-block;vertical-align:middle;margin-right:6px;" class="animate-spin"> Saving...');

            $.ajax({
                url: searchjetIndexSettings.ajaxUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        $('#last-saved').text('Saved at ' + new Date().toLocaleTimeString());
                        $btn.html('<img src="' + searchjetIndexSettings.imgUrl + 'bulk-check-white.svg" alt="" width="18" height="18" style="display:inline-block;vertical-align:middle;margin-right:6px;"> Saved!').removeClass('searchjet-btn-primary').addClass('searchjet-btn-success');
                        
                        setTimeout(() => {
                            $btn.html(originalHtml).prop('disabled', false).removeClass('searchjet-btn-success').addClass('searchjet-btn-primary');
                        }, 2000);
                    } else {
                        alert(response.data || searchjetIndexSettings.strings.error);
                        $btn.html(originalHtml).prop('disabled', false);
                    }
                },
                error: () => {
                    alert(searchjetIndexSettings.strings.error);
                    $btn.html(originalHtml).prop('disabled', false);
                }
            });
        },

        renderStats(stats) {
            $('#searchjet-stats-loading').hide();
            $('#searchjet-stats-content').removeClass('hidden').fadeIn();

            $('#stat-documents').text(stats.numberOfDocuments ? stats.numberOfDocuments.toLocaleString() : '0');

            const $indexing = $('#stat-indexing');
            $indexing.text(stats.isIndexing ? 'Active' : 'Idle');
            
            $('#stat-pending').text(stats.pendingTasks || '0');
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    $.fn.shake = function () {
        this.animate({ marginLeft: '-10px' }, 50)
            .animate({ marginLeft: '10px' }, 50)
            .animate({ marginLeft: '-10px' }, 50)
            .animate({ marginLeft: '10px' }, 50)
            .animate({ marginLeft: '0px' }, 50);
        return this;
    };

    $(document).ready(() => Settings.init());

})(jQuery);
