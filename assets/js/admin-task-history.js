(function ($) {
    'use strict';

    var TaskHistory = {
        ajaxUrl: searchjetTaskHistory.ajaxUrl,
        nonce: searchjetTaskHistory.nonce,
        strings: searchjetTaskHistory.strings,
        currentTasks: [],

        init: function () {
            this.bindEvents();
            this.loadTasks();
            setInterval(this.loadTasks.bind(this), 30000);
        },

        bindEvents: function () {
            $('#status-filter').on('change', this.loadTasks.bind(this));
            $('#type-filter').on('change', this.loadTasks.bind(this));
            $('#refresh-btn').on('click', this.loadTasks.bind(this));
            $('#cleanup-btn').on('click', this.cleanup.bind(this));
        },

        loadTasks: function () {
            var self = this;
            var statusFilter = $('#status-filter').val();
            var typeFilter = $('#type-filter').val();

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'searchjet_get_task_history',
                    nonce: this.nonce,
                    status_filter: statusFilter,
                    type_filter: typeFilter,
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success && response.data) {
                        self.renderTasks(response.data);
                    } else {
                        self.showError(self.strings.error);
                    }
                },
                error: function () {
                    self.showError(self.strings.error);
                }
            });
        },

        renderTasks: function (data) {
            this.currentTasks = data.tasks;
            var stats = data.statistics;

            var total = stats.total || 0;
            var failed = stats.by_status.failed || 0;
            var successRate = total > 0 ? (((total - failed) / total) * 100).toFixed(1) : '100';

            $('#searchjet-task-stats').html(
                '<div class="searchjet-stat-card">' +
                '<div class="searchjet-stat-number">' + total.toLocaleString() + '</div>' +
                '<div class="searchjet-stat-label">Total Tasks</div>' +
                '</div>' +
                '<div class="searchjet-stat-card">' +
                '<div class="searchjet-stat-number" style="color:#28a745;">' + successRate + '%</div>' +
                '<div class="searchjet-stat-label">Success Rate</div>' +
                '</div>' +
                '<div class="searchjet-stat-card">' +
                '<div class="searchjet-stat-number" style="color:#667eea;">' + (stats.total_active || 0) + '</div>' +
                '<div class="searchjet-stat-label">Active Tasks</div>' +
                '</div>' +
                '<div class="searchjet-stat-card">' +
                '<div class="searchjet-stat-number" style="color:#dc3545;">' + failed.toLocaleString() + '</div>' +
                '<div class="searchjet-stat-label">Failed Tasks</div>' +
                '</div>'
            );

            $('#task-loading').addClass('hidden');

            if (data.tasks.length === 0) {
                $('#task-content').addClass('hidden');
                $('#no-tasks').removeClass('hidden');
            } else {
                $('#no-tasks').addClass('hidden');
                $('#task-content').removeClass('hidden');
                this.populateTable(data.tasks);
            }
        },

        populateTable: function (tasks) {
            var tbody = $('#task-tbody');
            tbody.empty();

            var self = this;
            $.each(tasks, function (idx, task) {
                var row = $('<tr>');

                row.append(
                    $('<td>').append(
                        $('<span class="sj-task-uid">').text(task.uid.substring(0, 12).toUpperCase())
                    )
                );

                row.append($('<td>').append($('<span class="sj-task-type-badge ' + task.type + '">').text(task.type.toUpperCase())));

                row.append(
                    $('<td>').append(
                        $('<span class="sj-status-chip-v3 ' + task.status + '">').text(task.status.toUpperCase())
                    )
                );

                var createdDate = new Date(task.created_at * 1000);
                row.append($('<td class="sj-task-date">').text(createdDate.toLocaleString()));

                var duration = '—';
                if (task.status === 'completed' || task.status === 'failed') {
                    var seconds = (task.updated_at - task.created_at);
                    duration = seconds > 0 ? seconds + 's' : '< 1s';
                }
                row.append($('<td class="sj-task-duration">').text(duration));

                var details = '';
                if (task.progress) {
                    details = task.progress + ' items';
                }
                row.append($('<td class="sj-task-details">').text(details || '—'));

                tbody.append(row);

                if (task.error || (task.status === 'failed')) {
                    var errorRow = $('<tr class="sj-error-row hidden">');
                    var errorContent = task.error ? (typeof task.error === 'string' ? task.error : JSON.stringify(task.error, null, 2)) : 'An error occurred during task execution.';
                    var errorCell = $('<td colspan="6">').append(
                        $('<div class="sj-task-error-box">').text(errorContent)
                    );
                    errorRow.append(errorCell);

                    row.addClass('sj-clickable-row');
                    row.on('click', function () {
                        errorRow.toggleClass('hidden');
                    });

                    tbody.append(errorRow);
                }
            });
        },

        cleanup: function () {
            if (!confirm(this.strings.cleanupConfirm)) {
                return;
            }

            var self = this;
            var btn = $('#cleanup-btn');
            btn.prop('disabled', true).addClass('sj-btn-loading');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'searchjet_cleanup_task_history',
                    nonce: this.nonce,
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        self.showNotification(self.strings.cleanupSuccess, 'success');
                        self.loadTasks();
                    } else {
                        self.showNotification(response.data.message || self.strings.error, 'error');
                    }
                },
                error: function () {
                    self.showNotification(self.strings.error, 'error');
                },
                complete: function () {
                    btn.prop('disabled', false).removeClass('sj-btn-loading');
                }
            });
        },

        showNotification: function (message, type) {
            var container = $('#task-notification');
            container.removeClass('hidden sj-notification-success sj-notification-error')
                .addClass('sj-notification-' + type)
                .html(
                    '<div class="sj-notification-content">' +
                    '<span class="sj-notification-icon">' + (type === 'success' ? '<img src="' + searchjetTaskHistory.imgUrl + 'bulk-check-white.svg" alt="" width="12" height="12" style="display:block;">' : '<img src="' + searchjetTaskHistory.imgUrl + 'task-x-white.svg" alt="" width="12" height="12" style="display:block;">') + '</span>' +
                    '<span class="sj-notification-message">' + escapeHtml(message) + '</span>' +
                    '</div>'
                );

            setTimeout(function () {
                container.addClass('hidden');
            }, 4000);
        },

        showError: function (message) {
            $('#task-loading').html(
                '<div class="sj-error-state">' +
                '<div class="sj-error-icon"><img src="' + searchjetTaskHistory.imgUrl + 'task-warning-amber.svg" alt="" width="48" height="48" style="display:block;margin:0 auto;"></div>' +
                '<div class="sj-error-title">Error</div>' +
                '<div class="sj-error-message">' + escapeHtml(message) + '</div>' +
                '</div>'
            );
            $('#task-content').addClass('hidden');
            $('#no-tasks').addClass('hidden');
        }
    };

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    $(document).ready(function () {
        TaskHistory.init();
    });

})(jQuery);
