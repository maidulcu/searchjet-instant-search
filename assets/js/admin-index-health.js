(function ($) {
    'use strict';

    var IndexHealth = {
        ajaxUrl: searchjetIndexHealth.ajaxUrl,
        nonce: searchjetIndexHealth.nonce,
        strings: searchjetIndexHealth.strings,

        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            $('#run-diagnostics-btn').on('click', this.runDiagnostics.bind(this));
        },

        runDiagnostics: function () {
            var self = this;
            var btn = $('#run-diagnostics-btn');

            btn.prop('disabled', true).addClass('loading');
            $('#health-empty').addClass('hidden');
            $('#health-loading').removeClass('hidden');
            $('#health-results').addClass('hidden').html('');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'searchjet_run_index_health',
                    nonce: this.nonce,
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success && response.data && response.data.checks) {
                        self.renderResults(response.data.checks);
                    } else {
                        self.showError(self.strings.error);
                    }
                },
                error: function () {
                    self.showError(self.strings.error);
                },
                complete: function () {
                    $('#health-loading').addClass('hidden');
                    btn.prop('disabled', false).removeClass('loading');
                }
            });
        },

        renderResults: function (checks) {
            var self = this;
            var html = '';
            var hasErrors = false;
            var hasWarnings = false;

            $.each(checks, function (idx, check) {
                if (check.status === 'error') hasErrors = true;
                if (check.status === 'warning') hasWarnings = true;
            });

            var bannerClass = hasErrors ? 'error' : (hasWarnings ? 'warning' : 'success');
            var bannerTitle = hasErrors ? 'Issues Found' : (hasWarnings ? 'Warnings Found' : 'All Systems OK');
            var bannerMsg = hasErrors ? 'Critical issues found in your search index.' : 
                           (hasWarnings ? 'Some aspects of your index require attention.' : 
                           'Your search index is properly synchronized.');

            // Status Banner
            html += '<div class="sj-card sj-result-card ' + bannerClass + '">';
            html += '  <div class="sj-result-icon ' + bannerClass + '">';
            if (bannerClass === 'success') {
                html += '<img src="' + searchjetIndexHealth.imgUrl + 'health-check-green.svg" alt="" width="20" height="20" style="display:block;">';
            } else if (bannerClass === 'warning') {
                html += '<img src="' + searchjetIndexHealth.imgUrl + 'health-warning-amber.svg" alt="" width="20" height="20" style="display:block;">';
            } else {
                html += '<img src="' + searchjetIndexHealth.imgUrl + 'health-x-red.svg" alt="" width="20" height="20" style="display:block;">';
            }
            html += '  </div>';
            html += '  <div class="sj-result-content">';
            html += '    <h3 class="sj-result-title">' + bannerTitle + '</h3>';
            html += '    <p class="sj-result-message">' + bannerMsg + '</p>';
            html += '  </div>';
            html += '</div>';

            // Individual Checks
            html += '<div class="sj-results-grid">';
            $.each(checks, function (idx, check) {
                var statusClass = check.status;
                var iconSvg = '';
                
                if (statusClass === 'success') {
                    iconSvg = '<img src="' + searchjetIndexHealth.imgUrl + 'health-check-green.svg" alt="" width="20" height="20" style="display:block;">';
                } else if (statusClass === 'warning') {
                    iconSvg = '<img src="' + searchjetIndexHealth.imgUrl + 'health-warning-amber.svg" alt="" width="20" height="20" style="display:block;">';
                } else {
                    iconSvg = '<img src="' + searchjetIndexHealth.imgUrl + 'health-x-red.svg" alt="" width="20" height="20" style="display:block;">';
                }

                html += '<div class="sj-result-card ' + statusClass + '">';
                html += '  <div class="sj-result-icon ' + statusClass + '">' + iconSvg + '</div>';
                html += '  <div class="sj-result-content">';
                html += '    <h4 class="sj-result-title">' + self.escapeHtml(check.title) + '</h4>';
                html += '    <p class="sj-result-message">' + self.escapeHtml(check.message) + '</p>';
                html += '  </div>';
                html += '</div>';
            });
            html += '</div>';

            $('#health-empty').addClass('hidden');
            $('#health-results').html(html).removeClass('hidden');
        },

        showError: function (message) {
            var html = '<div class="sj-card sj-result-card error">';
            html += '  <div class="sj-result-icon error">';
            html += '<img src="' + searchjetIndexHealth.imgUrl + 'health-x-red.svg" alt="" width="20" height="20" style="display:block;">';
            html += '  </div>';
            html += '  <div class="sj-result-content">';
            html += '    <h4 class="sj-result-title">Scan Failed</h4>';
            html += '    <p class="sj-result-message">' + this.escapeHtml(message) + '</p>';
            html += '  </div>';
            html += '</div>';

            $('#health-empty').addClass('hidden');
            $('#health-results').html(html).removeClass('hidden');
        },

        escapeHtml: function (text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    $(document).ready(function () {
        IndexHealth.init();
    });

})(jQuery);
