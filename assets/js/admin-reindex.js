jQuery(function ($) {

    window.SearchJetUI = window.SearchJetUI || {
        showProgressBar: function () {
            $('#searchjet-progress-container').show();
        },
        updateProgress: function (percent) {
            $('#searchjet-progress-bar')
                .css('width', percent + '%')
                .attr('aria-valuenow', percent)
                .text(Math.round(percent) + '%');
        }
    };

    let offset = 0;
    const batchSize = SearchJetReindex.batchSize;

    function updateProgressBar(percent) {
        if (typeof SearchJetUI !== 'undefined' && SearchJetUI.updateProgress) {
            SearchJetUI.updateProgress(percent);
        }
    }

    function processBatch() {
        $.post(SearchJetReindex.ajax_url, {
            action: SearchJetReindex.ajax_action,
            _ajax_nonce: SearchJetReindex.nonce
        }, function (response) {
            if (response.success) {
                updateProgressBar(100);
                const message = response.data.message || 'Reindexing complete!';
                $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>')
                    .insertAfter('#searchjet-progress-container');
            } else {
                const errorMsg = response.data?.message || 'Reindexing failed. Please try again.';
                $('<div class="notice notice-error is-dismissible"><p>' + errorMsg + '</p></div>')
                    .insertAfter('#searchjet-progress-container');
            }
        });
    }

    $('#searchjet_reindex').on('click', function (e) {
        e.preventDefault();
        offset = 0;
        if (typeof SearchJetUI !== 'undefined' && SearchJetUI.showProgressBar) {
            SearchJetUI.showProgressBar();
        }
        processBatch();
    });
});