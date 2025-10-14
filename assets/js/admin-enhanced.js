/****
 * SearchJet Enhanced Admin JavaScript
 */

(function($) {
    'use strict';

    const SearchJetAdminApp = {
        init: function() {
            this.bindEvents();
            this.initTooltips();
            this.initProgressBars();
            this.checkConnectionStatus();
        },

        bindEvents: function() {
            // Tab switching with smooth transitions
            $('.nav-tab').on('click', this.handleTabSwitch);
            
            // Form validation
            $('#searchjet-settings-form').on('submit', this.validateForm);
            
            // API key visibility toggle
            $('.searchjet-api-key-toggle').on('click', this.toggleApiKeyVisibility);
            
            // Real-time validation
            $('#searchjet_hosted_api_key').on('input', this.validateApiKey);
            
            // Auto-save draft settings
            $('#searchjet-settings-form input, #searchjet-settings-form select, #searchjet-settings-form textarea')
                .on('change', this.autoSaveDraft);
        },

        handleTabSwitch: function(e) {
            e.preventDefault();
            
            const $tab = $(this);
            const target = $tab.attr('href').substring(1);
            
            // Remove active classes
            $('.nav-tab').removeClass('nav-tab-active');
            $('.searchjet-tab-content').removeClass('active');
            
            // Add active classes with animation
            $tab.addClass('nav-tab-active');
            $('#' + target).addClass('active');
            
            // Save current tab to localStorage
            localStorage.setItem('searchjet_active_tab', target);
            
            // Trigger custom event
            $(document).trigger('searchjet:tab_changed', [target]);
        },

        validateForm: function(e) {
            const apiKey = $('#searchjet_hosted_api_key').val().trim();
            
            if (apiKey && !SearchJetAdminApp.isValidApiKey(apiKey)) {
                e.preventDefault();
                SearchJetAdminApp.showNotification('Please enter a valid API key.', 'error');
                $('#searchjet_hosted_api_key').focus();
                return false;
            }
            
            return true;
        },

        validateApiKey: function() {
            const $input = $(this);
            const apiKey = $input.val().trim();
            const $indicator = $input.siblings('.searchjet-validation-indicator');
            
            if (!apiKey) {
                $indicator.remove();
                return;
            }
            
            if (!$indicator.length) {
                $input.after('<span class="searchjet-validation-indicator"></span>');
            }
            
            const $newIndicator = $input.siblings('.searchjet-validation-indicator');
            
            if (SearchJetAdminApp.isValidApiKey(apiKey)) {
                $newIndicator.html('✓').removeClass('invalid').addClass('valid');
            } else {
                $newIndicator.html('✗').removeClass('valid').addClass('invalid');
            }
        },

        isValidApiKey: function(key) {
            // Basic API key validation - adjust pattern as needed
            return /^[a-zA-Z0-9_-]{20,}$/.test(key);
        },

        toggleApiKeyVisibility: function() {
            const $input = $('#searchjet_hosted_api_key');
            const $toggle = $(this);
            
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $toggle.html('🙈');
            } else {
                $input.attr('type', 'password');
                $toggle.html('👁️');
            }
        },

        initTooltips: function() {
            // Add tooltips to form elements with descriptions
            $('.description').each(function() {
                const $desc = $(this);
                const $input = $desc.siblings('input, select, textarea');
                
                if ($input.length) {
                    $input.attr('title', $desc.text());
                }
            });
        },

        initProgressBars: function() {
            $('.searchjet-progress-bar').each(function() {
                const $bar = $(this);
                const width = $bar.data('width') || $bar.css('width');
                
                // Animate progress bars on load
                $bar.css('width', '0%');
                setTimeout(() => {
                    $bar.css('width', width);
                }, 500);
            });
        },

        checkConnectionStatus: function() {
            const apiKey = $('#searchjet_hosted_api_key').val().trim();
            
            if (!apiKey) return;
            
            // Visual feedback for connection status
            this.updateConnectionIndicator('checking');
            
            // Simulate connection check (replace with actual API call)
            setTimeout(() => {
                this.updateConnectionIndicator('connected');
            }, 1000);
        },

        updateConnectionIndicator: function(status) {
            const $indicator = $('.searchjet-connection-indicator');
            const $status = $indicator.siblings('div');
            
            if (!$indicator.length) return;
            
            $indicator.removeClass('connected checking');
            
            const strings = window.SearchJetAdmin?.strings || {
                connected: 'Connected',
                connecting: 'Connecting...',
                disconnected: 'Disconnected'
            };
            
            switch (status) {
                case 'connected':
                    $indicator.addClass('connected');
                    $status.find('strong').text(strings.connected);
                    break;
                case 'checking':
                    $indicator.addClass('checking');
                    $status.find('strong').text(strings.connecting);
                    break;
                default:
                    $status.find('strong').text(strings.disconnected);
            }
        },

        autoSaveDraft: function() {
            const formData = $('#searchjet-settings-form').serialize();
            localStorage.setItem('searchjet_draft_settings', formData);
            this.showAutoSaveIndicator();
        },

        showAutoSaveIndicator: function() {
            let $indicator = $('.searchjet-autosave-indicator');
            
            if (!$indicator.length) {
                $indicator = $('<div class="searchjet-autosave-indicator">Draft saved</div>');
                $('body').append($indicator);
            }
            
            $indicator.addClass('show');
            
            setTimeout(() => {
                $indicator.removeClass('show');
            }, 2000);
        },

        showNotification: function(message, type = 'info') {
            const $notification = $(`
                <div class="searchjet-notification searchjet-notification-${type}">
                    <span>${message}</span>
                    <button class="searchjet-notification-close">&times;</button>
                </div>
            `);
            
            $('body').append($notification);
            
            setTimeout(() => {
                $notification.addClass('show');
            }, 100);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                $notification.removeClass('show');
                setTimeout(() => $notification.remove(), 300);
            }, 5000);
            
            // Manual close
            $notification.find('.searchjet-notification-close').on('click', function() {
                $notification.removeClass('show');
                setTimeout(() => $notification.remove(), 300);
            });
        },

        restoreDraftSettings: function() {
            const draftData = localStorage.getItem('searchjet_draft_settings');
            
            if (draftData) {
                const params = new URLSearchParams(draftData);
                
                params.forEach((value, key) => {
                    const $field = $(`[name="${key}"]`);
                    
                    if ($field.is(':checkbox')) {
                        $field.prop('checked', value === '1');
                    } else {
                        $field.val(value);
                    }
                });
                
                localStorage.removeItem('searchjet_draft_settings');
                this.showNotification('Draft settings restored', 'success');
            }
        },
        
        discardDraft: function() {
            localStorage.removeItem('searchjet_draft_settings');
            this.showNotification('Draft discarded', 'info');
        },

        // Reindexing functionality
        handleReindex: function() {
            // console.log('%c🔍 SearchJet Reindex Triggered', 'color: #0073aa; font-size: 14px; font-weight: bold;');
            // console.log('%c🚀 Starting reindexing process...', 'color: #28a745; font-size: 12px;');
            // console.log('%c🦁 Preparing reindex request...', 'color: #ff9800; font-size: 12px;');
            // console.log('%c🦁 Sending AJAX request to server...', 'color: #ff5722; font-size: 12px;');
            // console.log('%c🦁 Awaiting server response...', 'color: #9c27b0; font-size: 12px;');
            const $btn = $('#searchjet-reindex-btn');
            const $progress = $('#searchjet-reindex-progress');
            const $progressBar = $('#searchjet-progress-bar');
            const $progressText = $('#searchjet-progress-text');
            
            $btn.prop('disabled', true).html('<span class="searchjet-loading"></span> Reindexing...');
            $progress.show();
            
//             console.log('%c🧠 AJAX Debug:', 'color:#00bcd4; font-weight:bold;');
// console.log('AJAX URL:', window.SearchJetAdmin.ajax_url);
// console.log('Action:', window.SearchJetAdmin.ajax_action);
// console.log('Nonce:', window.SearchJetAdmin.nonce);

            $.post(window.SearchJetAdmin.ajax_url, {
                action: window.SearchJetAdmin.ajax_action,
                nonce: window.SearchJetAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    $progressBar.css('width', '100%');
                    $progressText.text(response.data.message || 'Reindexing completed');
                    $btn.removeClass('searchjet-btn-primary').addClass('searchjet-btn-success').html('✓ Complete');
                } else {
                    $btn.removeClass('searchjet-btn-primary').addClass('searchjet-btn-danger').html('✗ Error');
                }
            })
            .fail(function() {
                $btn.removeClass('searchjet-btn-primary').addClass('searchjet-btn-danger').html('✗ Error');
            })
            .always(function() {
                setTimeout(() => {
                    $btn.prop('disabled', false).removeClass('searchjet-btn-success searchjet-btn-danger')
                        .addClass('searchjet-btn-primary').text('Reindex All Content');
                    $progress.hide();
                }, 3000);
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        SearchJetAdminApp.init();
        
        // Restore active tab from localStorage
        const activeTab = localStorage.getItem('searchjet_active_tab');
        if (activeTab) {
            $(`a[href="#${activeTab}"]`).trigger('click');
        }
        
        // Don't auto-restore drafts on page load
        
        // Bind reindex button
        $(document).on('click', '#searchjet-reindex-btn', SearchJetAdminApp.handleReindex);
        
        // Fallback for API key toggle if jQuery binding fails
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('searchjet-api-key-toggle')) {
                const input = document.getElementById('searchjet_hosted_api_key');
                const button = e.target;
                
                if (input.type === 'password') {
                    input.type = 'text';
                    button.innerHTML = '🙈';
                } else {
                    input.type = 'password';
                    button.innerHTML = '👁️';
                }
            }
        });
    });

    // Helper: Programmatic tab switching for cross-section links
    window.switchToSearchJetTab = function(tabId) {
        const $tabs = jQuery('.nav-tab');
        const $contents = jQuery('.searchjet-tab-content');
        
        $tabs.removeClass('nav-tab-active');
        $contents.removeClass('active');
        
        const $targetTab = jQuery(`a[href="#${tabId}"]`);
        const $targetContent = jQuery(`#${tabId}`);
        
        if ($targetTab.length) $targetTab.addClass('nav-tab-active');
        if ($targetContent.length) $targetContent.addClass('active');
        
        // Save to localStorage for persistence
        localStorage.setItem('searchjet_active_tab', tabId);
        
        // Smooth scroll to section
        if ($targetContent.length) {
            $targetContent[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    // Expose SearchJetAdmin globally
    window.SearchJetAdminApp = SearchJetAdminApp;

})(jQuery);

// Additional CSS for notifications and indicators
const additionalCSS = `
    .searchjet-validation-indicator {
        margin-left: 8px;
        font-weight: bold;
    }
    .searchjet-validation-indicator.valid {
        color: #28a745;
    }
    .searchjet-validation-indicator.invalid {
        color: #dc3545;
    }
    
    .searchjet-autosave-indicator {
        position: fixed;
        top: 32px;
        right: 20px;
        background: #28a745;
        color: white;
        padding: 8px 16px;
        border-radius: 4px;
        font-size: 12px;
        opacity: 0;
        transform: translateY(-10px);
        transition: all 0.3s ease;
        z-index: 9999;
    }
    .searchjet-autosave-indicator.show {
        opacity: 1;
        transform: translateY(0);
    }
    
    .searchjet-notification {
        position: fixed;
        top: 32px;
        right: 20px;
        max-width: 400px;
        padding: 16px 20px;
        border-radius: 8px;
        color: white;
        font-size: 14px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .searchjet-notification.show {
        opacity: 1;
        transform: translateX(0);
    }
    .searchjet-notification-success {
        background: #28a745;
    }
    .searchjet-notification-error {
        background: #dc3545;
    }
    .searchjet-notification-warning {
        background: #ffc107;
        color: #212529;
    }
    .searchjet-notification-info {
        background: #17a2b8;
    }
    .searchjet-notification-close {
        background: none;
        border: none;
        color: inherit;
        font-size: 18px;
        cursor: pointer;
        padding: 0;
        line-height: 1;
    }
    
    .searchjet-connection-indicator.checking {
        background: #ffc107;
        animation: pulse 1.5s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
`;

// Inject additional CSS
const style = document.createElement('style');
style.textContent = additionalCSS;
document.head.appendChild(style);