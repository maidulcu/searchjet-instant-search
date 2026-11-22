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
            const $eyeOpen = $toggle.find('.searchjet-eye-open');
            const $eyeClosed = $toggle.find('.searchjet-eye-closed');
            
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $eyeOpen.hide();
                $eyeClosed.show();
            } else {
                $input.attr('type', 'password');
                $eyeOpen.show();
                $eyeClosed.hide();
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
            const val = $('#searchjet_hosted_api_key').val();
            const apiKey = val ? val.trim() : '';
            
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

        showNotification: function(message, type = 'info', persistent = false) {
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
            
            // Auto-hide after 5 seconds (unless persistent)
            if (!persistent) {
                setTimeout(() => {
                    $notification.removeClass('show');
                    setTimeout(() => $notification.remove(), 300);
                }, 5000);
            }
            
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
            var adminData = window.SearchJetAdmin;
            if (!adminData || !adminData.ajax_url || !adminData.ajax_action) {
                SearchJetAdminApp.showNotification(
                    'SearchJet configuration is missing. Try refreshing the page.',
                    'error'
                );
                return;
            }

            // Pre-check daily reindex limit
            if (adminData.reindex_limit) {
                var used = adminData.reindex_limit.used || 0;
                var max = adminData.reindex_limit.max || 1;
                if (used >= max) {
                    SearchJetAdminApp.showNotification(
                        adminData.strings.limit_reached || 'Daily reindex limit reached.',
                        'error',
                        true
                    );
                    return;
                }
            }

            var $btn = $('#searchjet-reindex-btn');
            var $progress = $('#searchjet-reindex-progress');
            var $progressBar = $('#searchjet-progress-bar');
            var $progressText = $('#searchjet-progress-text');

            $btn.prop('disabled', true).html('<span class="searchjet-loading"></span> Reindexing...');
            $progress.show();

            $.post(adminData.ajax_url, {
                action: adminData.ajax_action,
                nonce: adminData.nonce
            })
            .done(function(response) {
                if (response.success) {
                    var count = response.data.document_count;
                    $progressBar.css('width', '100%');
                    $progressText.text(response.data.message || 'Reindexing completed');
                    $btn.removeClass('searchjet-btn-primary').addClass('searchjet-btn-success').html('\u2713 Complete');

                    // Update all displayed document counts on the page
                    var countFormatted = parseInt(count, 10).toLocaleString();
                    $('.text-5xl').each(function() {
                        var $el = $(this);
                        if ($el.next().filter('.text-gray-600').text().indexOf('Indexed Documents') !== -1) {
                            $el.text(countFormatted);
                        }
                    });

                    // Update reindexes today counter
                    if (response.data.reindexes_today !== undefined) {
                        adminData.reindex_limit.used = response.data.reindexes_today;
                        $('.text-4xl').each(function() {
                            var $el = $(this);
                            if ($el.next().filter('.text-gray-600').text().indexOf('Reindexes Today') !== -1) {
                                $el.text(response.data.reindexes_today);
                            }
                        });
                        // Disable button if limit now reached
                        if (adminData.reindex_limit.used >= adminData.reindex_limit.max) {
                            $btn.prop('disabled', true).removeClass('searchjet-btn-success').addClass('searchjet-btn-primary').html('Reindex All Content');
                            $progressText.text('Daily reindex limit reached. The limit resets tomorrow.');
                        }
                    }

                    SearchJetAdminApp.showNotification(
                        (response.data.message || 'Reindexing completed!') + ' (' + countFormatted + ' documents)',
                        'success',
                        true
                    );
                } else {
                    var message = response.data && response.data.message
                        ? response.data.message
                        : 'Reindexing failed. Please check your settings and try again.';
                    $progressText.text(message);
                    SearchJetAdminApp.showNotification(message, 'error', true);
                    $btn.removeClass('searchjet-btn-primary').addClass('searchjet-btn-danger').html('\u2717 Error');
                }
            })
            .fail(function(jqXHR, textStatus) {
                var message = 'Reindexing request failed.';
                if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                    message = jqXHR.responseJSON.data.message;
                } else if (textStatus === 'timeout') {
                    message = 'Request timed out. Your server may have too many posts to process.';
                } else if (textStatus === 'parsererror') {
                    message = 'Invalid server response. Check the PHP error log.';
                }
                $progressText.text(message);
                SearchJetAdminApp.showNotification(message, 'error', true);
                $btn.removeClass('searchjet-btn-primary').addClass('searchjet-btn-danger').html('\u2717 Error');
            })
            .always(function() {
                setTimeout(function() {
                    $btn.prop('disabled', false)
                        .removeClass('searchjet-btn-success searchjet-btn-danger')
                        .addClass('searchjet-btn-primary')
                        .text('Reindex All Content');
                    $progress.hide();
                }, 5000);
});
        },

        // Save attributes via AJAX
        handleSaveAttributes: function() {
            var adminData = window.SearchJetAdmin;
            if (!adminData || !adminData.ajax_url || !adminData.attributes_nonce) {
                SearchJetAdminApp.showNotification('SearchJet configuration is missing. Try refreshing the page.', 'error');
                return;
            }
            var attributesField = document.getElementById('searchjet_attributes');
            if (!attributesField) {
                SearchJetAdminApp.showNotification('Attributes field not found.', 'error');
                return;
            }
            var $btn = $('#searchjet-save-attributes-btn');
            $btn.prop('disabled', true).html('<span class="searchjet-loading"></span> Saving...');
            $.post(adminData.ajax_url, {
                action: 'searchjet_save_attributes',
                nonce: adminData.attributes_nonce,
                attributes: attributesField.value
            })
            .done(function(response) {
                if (response.success) {
                    adminData.attributes = response.data.attributes || [];
                    SearchJetAdminApp.showNotification(response.data.message || 'Attributes saved successfully', 'success', true);
                } else {
                    var message = response.data && response.data.message ? response.data.message : 'Failed to save attributes.';
                    SearchJetAdminApp.showNotification(message, 'error', true);
                }
            })
            .fail(function() {
                SearchJetAdminApp.showNotification('Failed to save attributes. Please try again.', 'error', true);
            })
            .always(function() {
                $btn.prop('disabled', false).text('Save Attributes');
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        SearchJetAdminApp.init();
        initAttributesBuilder();
        
        // Restore active tab from localStorage
        const activeTab = localStorage.getItem('searchjet_active_tab');
        if (activeTab) {
            $(`a[href="#${activeTab}"]`).trigger('click');
        }
        
        // Don't auto-restore drafts on page load
        
        // Bind reindex button
        $(document).on('click', '#searchjet-reindex-btn', SearchJetAdminApp.handleReindex);
        
        // Bind save attributes button
        $(document).on('click', '#searchjet-save-attributes-btn', SearchJetAdminApp.handleSaveAttributes);
        
        // Fallback for API key toggle if jQuery binding fails
        document.addEventListener('click', function(e) {
            const button = e.target.closest('.searchjet-api-key-toggle');
            if (button) {
                const input = document.getElementById('searchjet_hosted_api_key');
                const eyeOpen = button.querySelector('.searchjet-eye-open');
                const eyeClosed = button.querySelector('.searchjet-eye-closed');
                
                if (input && eyeOpen && eyeClosed) {
                    if (input.type === 'password') {
                        input.type = 'text';
                        eyeOpen.style.display = 'none';
                        eyeClosed.style.display = 'block';
                    } else {
                        input.type = 'password';
                        eyeOpen.style.display = 'block';
                        eyeClosed.style.display = 'none';
                    }
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

    // Quick Presets & Attributes Builder
    const attributesPresets = {
        basic: ['id', 'title', 'url', 'type', 'excerpt'],
        woocommerce: ['id', 'title', 'url', 'type', 'excerpt', 'image', 'price', 'sku', 'stock_status', 'categories'],
        advanced: ['id', 'title', 'url', 'type', 'excerpt', 'image', 'price', 'sku', 'stock_status', 'categories', 'tags', 'meta', 'author']
    };

    let selectedAttributes = [];
    let attributesInitialized = false;

    function initAttributesBuilder() {
        if (attributesInitialized) return;
        const attributesField = document.getElementById('searchjet_attributes');
        if (!attributesField) return;
        attributesInitialized = true;
        const currentValue = attributesField.value;
        selectedAttributes = currentValue ? currentValue.split(',').map(function(attr) { return attr.trim(); }).filter(function(attr) { return attr; }) : [];
        // Default to "basic" preset if no attributes are saved
        if (selectedAttributes.length === 0) {
            applyPreset('basic');
        } else {
            renderAttributesList();
        }
        bindAttributeEvents();
    }

    function renderAttributesList() {
        const container = document.getElementById('searchjet-attributes-list');
        if (!container) return;
        container.innerHTML = '';
        selectedAttributes.forEach(function(attr, index) {
            const item = document.createElement('div');
            item.className = 'searchjet-attribute-item';
            const name = document.createElement('span');
            name.className = 'searchjet-attribute-name';
            name.textContent = attr;
            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'searchjet-remove-attr';
            removeButton.dataset.index = String(index);
            removeButton.textContent = '\u00d7';
            item.appendChild(name);
            item.appendChild(removeButton);
            container.appendChild(item);
        });
        updateHiddenField();
        updatePresetActiveState();
    }

    function updateHiddenField() {
        const attributesField = document.getElementById('searchjet_attributes');
        if (attributesField) {
            attributesField.value = selectedAttributes.join(',');
        }
    }

    function applyPreset(presetName) {
        if (!attributesPresets[presetName]) return;
        selectedAttributes = attributesPresets[presetName].slice();
        renderAttributesList();
    }

    function addAttribute(attr) {
        if (attr && selectedAttributes.indexOf(attr) === -1) {
            selectedAttributes.push(attr);
            renderAttributesList();
        }
    }

    function removeAttribute(index) {
        selectedAttributes.splice(index, 1);
        renderAttributesList();
    }

    function updatePresetActiveState() {
        document.querySelectorAll('.searchjet-preset-btn').forEach(function(btn) {
            const preset = btn.getAttribute('data-preset');
            const presetAttributes = attributesPresets[preset] || [];
            const isActive = presetAttributes.length === selectedAttributes.length
                && presetAttributes.every(function(attr, i) { return attr === selectedAttributes[i]; });
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function bindAttributeEvents() {
        document.querySelectorAll('.searchjet-preset-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const preset = this.getAttribute('data-preset');
                applyPreset(preset);
            });
        });

        const addButton = document.getElementById('searchjet-add-btn');
        if (addButton) {
            addButton.addEventListener('click', function() {
                const input = document.getElementById('searchjet-new-attribute');
                if (!input) return;
                const value = input.value.trim();
                if (value) {
                    addAttribute(value);
                    input.value = '';
                }
            });
        }

        const newAttrInput = document.getElementById('searchjet-new-attribute');
        if (newAttrInput) {
            newAttrInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    var addBtn = document.getElementById('searchjet-add-btn');
                    if (addBtn) addBtn.click();
                }
            });
        }

        const attributesList = document.getElementById('searchjet-attributes-list');
        if (attributesList) {
            attributesList.addEventListener('click', function(e) {
                if (e.target.classList.contains('searchjet-remove-attr')) {
                    var index = parseInt(e.target.getAttribute('data-index'), 10);
                    removeAttribute(index);
                }
            });
        }
    }

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
