(function($) {
    'use strict';

    var BulkReindex = {
        ajaxUrl: searchjetBulkReindex.ajaxUrl,
        nonce: searchjetBulkReindex.nonce,
        strings: searchjetBulkReindex.strings,
        reindexLimit: searchjetBulkReindex.reindexLimit || null,
        currentTaskUid: null,
        progressInterval: null,

        init: function() {
            this.bindEvents();
            this.initScopeCards();
        },

        bindEvents: function() {
            var self = this;

            $('#start-reindex-btn').on('click', function() {
                // Pre-check daily reindex limit
                if (self.reindexLimit) {
                    var used = self.reindexLimit.used || 0;
                    var max = self.reindexLimit.max || 1;
                    if (used >= max) {
                        alert(self.strings.limitReached);
                        return;
                    }
                }
                if (!confirm(self.strings.confirm)) {
                    return;
                }
                self.startReindex();
            });
        },

        initScopeCards: function() {
            var scopeOptions = $('.sj-scope-option');
            
            scopeOptions.each(function() {
                var card = $(this);
                var input = card.find('input[type="radio"]');
                var select = card.find('select');
                var dateInput = card.find('input[type="date"]');

                function updateState() {
                    var isChecked = input.prop('checked');
                    
                    card.toggleClass('active', isChecked);

                    if (select.length) {
                        select.prop('disabled', !isChecked);
                    }
                    if (dateInput.length) {
                        dateInput.prop('disabled', !isChecked);
                    }
                }

                input.on('change', updateState);

                card.on('click', function(e) {
                    if ($(e.target).is('select, input, option')) return;
                    input.prop('checked', true);
                    scopeOptions.removeClass('active');
                    card.addClass('active');
                    updateState();
                });

                updateState();
            });
        },

        startReindex: function() {
            var self = this;
            var mode = $('input[name="reindex_mode"]:checked').val();
            var forceFull = $('#force-full').is(':checked');

            var data = {
                action: 'searchjet_start_bulk_reindex',
                nonce: this.nonce,
                mode: mode,
                force_full: forceFull ? 'true' : 'false',
            };

            if (mode === 'type') {
                data.post_type = $('#post-type-select').val();
            } else if (mode === 'date') {
                data.date_from = $('#date-from').val();
            }

            var btn = $('#start-reindex-btn');
            btn.prop('disabled', true).addClass('loading');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        self.currentTaskUid = response.data.task_uid;
                        self.showProgress(response.data);
                        self.startProgressPolling();
                    } else {
                        self.showError(response.data.message || self.strings.error);
                        btn.prop('disabled', false).removeClass('loading');
                    }
                },
                error: function() {
                    self.showError(self.strings.error);
                    btn.prop('disabled', false).removeClass('loading');
                }
            });
        },

        showProgress: function(data) {
            $('#reindex-form-card').hide();
            $('#progress-card').show();

            $('#task-uid').text(this.currentTaskUid);
            $('#records-processed').text('0 / ' + data.total_posts);
            $('#progress-status-text').text(this.strings.processing);
            $('#progress-percent').text('0%');
            $('#progress-bar').css('width', '0%');
            $('#result-message').addClass('hidden').hide();
        },

        startProgressPolling: function() {
            var self = this;

            this.progressInterval = setInterval(function() {
                $.ajax({
                    url: self.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'searchjet_bulk_reindex_progress',
                        nonce: self.nonce,
                        task_uid: self.currentTaskUid,
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data) {
                            var data = response.data;
                            self.updateProgress(data);

                            if (data.finished) {
                                clearInterval(self.progressInterval);
                                self.showResult(data);
                            }
                        }
                    }
                });
            }, 3000);
        },

        updateProgress: function(data) {
            var percentage = Math.min(data.progress || 0, 100);

            $('#progress-bar').css('width', percentage + '%');
            $('#progress-percent').text(percentage + '%');
            $('#progress-status-text').text(data.message || this.strings.processing);

            var processed = Math.floor((percentage / 100) * parseInt($('#records-processed').text().split('/')[1] || 0));
            var total = parseInt($('#records-processed').text().split('/')[1] || 0);
            if (total > 0) {
                $('#records-processed').text(processed + ' / ' + total);
            }
        },

        showResult: function(data) {
            var resultDiv = $('#result-message');
            var btn = $('#start-reindex-btn');
            
            btn.hide();

            resultDiv.removeClass('hidden').show();

            if (data.status === 'completed') {
                resultDiv.html(
                    '<div class="sj-result-success">' +
                    '<img src="' + searchjetBulkReindex.imgUrl + 'bulk-check-circle-green.svg" alt="" width="24" height="24" style="display:block;margin-right:10px;">' +
                    '<div>' +
                    '<strong>Reindex Completed</strong>' +
                    '<span>' + this.escapeHtml(this.strings.success) + '</span>' +
                    '</div>' +
                    '</div>' +
                    '<a href="' + window.location.href + '" class="searchjet-btn searchjet-btn-secondary mt-4">' + this.escapeHtml('Start Another Reindex') + '</a>'
                );
            } else if (data.status === 'failed') {
                resultDiv.html(
                    '<div class="sj-result-error">' +
                    '<img src="' + searchjetBulkReindex.imgUrl + 'bulk-x-circle-red.svg" alt="" width="24" height="24" style="display:block;margin-right:10px;">' +
                    '<div>' +
                    '<strong>Reindex Failed</strong>' +
                    '<span>' + this.escapeHtml(data.message || this.strings.error) + '</span>' +
                    '</div>' +
                    '</div>' +
                    '<a href="' + window.location.href + '" class="searchjet-btn searchjet-btn-secondary mt-4">' + this.escapeHtml('Try Again') + '</a>'
                );
            }
        },

        showError: function(message) {
            $('#progress-card').show();
            $('#reindex-form-card').hide();
            
            $('#result-message').removeClass('hidden').show().html(
                '<div class="sj-result-error">' +
                '<img src="' + searchjetBulkReindex.imgUrl + 'bulk-x-circle-red.svg" alt="" width="24" height="24" style="display:block;margin-right:10px;">' +
                '<div>' +
                '<strong>Error</strong>' +
                '<span>' + this.escapeHtml(message) + '</span>' +
                '</div>' +
                '</div>' +
                '<a href="' + window.location.href + '" class="searchjet-btn searchjet-btn-secondary mt-4">' + this.escapeHtml('Try Again') + '</a>'
            );
        },

        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    $(document).ready(function() {
        BulkReindex.init();
    });

})(jQuery);
