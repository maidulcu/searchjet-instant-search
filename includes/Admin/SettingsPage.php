<?php
namespace SearchJet\Admin;

class SettingsPage {

    public static function init(): void {
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    public static function enqueueAssets($hook): void {
        if ($hook !== 'searchjet_page_searchjet-settings') return;

        wp_enqueue_style(
            'searchjet-admin-tailwind',
            SEARCHJET_PLUGIN_URL . 'assets/css/admin-tailwind.css',
            [],
            SEARCHJET_VERSION
        );

        wp_enqueue_style(
            'searchjet-admin-enhanced',
            SEARCHJET_PLUGIN_URL . 'assets/css/admin-style-enhanced.css',
            [],
            SEARCHJET_VERSION
        );
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'searchjet-instant-search'));
        }

        // Auto-validate if key exists but client_info is missing
        $api_key = get_option('searchjet_hosted_api_key', '');
        $client_info = get_option('searchjet_client_info', []);
        if (!empty($api_key) && empty($client_info)) {
            \SearchJet\API\SearchJetRemoteClient::fetchClientInfo($api_key, true);
            // Re-read after attempt — persistClientInfo may have run inside fetchClientInfo callers
            // but we also need to handle the case where it didn't
            $client_info = get_option('searchjet_client_info', []);
            if (empty($client_info)) {
                // Try explicit persist as fallback
                $info = \SearchJet\API\SearchJetRemoteClient::fetchClientInfo($api_key, false);
                if (is_array($info)) {
                    update_option('searchjet_client_info', $info);
                    $client_info = $info;
                }
            }
        }
        $is_connected = !empty($client_info) && !empty($api_key);

        ?>
        <div class="searchjet-admin-wrap min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
            <div class="bg-gradient-to-r from-[#152A53] to-[#2563eb] text-white px-8 py-6 shadow-lg">
                <div class="max-w-6xl mx-auto flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl text-white mb-1"><?php esc_html_e('Settings', 'searchjet-instant-search'); ?></h1>
                        <p class="text-blue-100 text-xl"><?php esc_html_e('Configure your SearchJet connection and preferences', 'searchjet-instant-search'); ?></p>
                    </div>
                    <div class="flex items-center gap-3 bg-white/10 px-4 py-2 rounded-lg border border-white/20">
                        <span class="w-2.5 h-2.5 rounded-full <?php echo $is_connected ? 'bg-green-400' : 'bg-amber-400'; ?>"></span>
                        <strong class="text-sm text-white"><?php echo $is_connected ? esc_html__('Connected', 'searchjet-instant-search') : esc_html__('Not Connected', 'searchjet-instant-search'); ?></strong>
                    </div>
                </div>
            </div>

            <div class="searchjet-content max-w-6xl mx-auto">

                <?php settings_errors('searchjet_settings'); ?>

                <?php if (!$is_connected): ?>

                <!-- Onboarding flow for new users -->
                <div class="searchjet-card" style="border:2px solid #bfdbfe;background:linear-gradient(135deg,#f0f7ff,#fff);">
                    <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:240px;">
                            <h2 style="margin:0 0 4px;font-size:22px;color:#152A53;"><?php esc_html_e('Connect Your Site', 'searchjet-instant-search'); ?></h2>
                            <p style="margin:0 0 20px;color:#6c757d;font-size:14px;"><?php esc_html_e('Follow these steps to enable AI-powered instant search.', 'searchjet-instant-search'); ?></p>

                            <div style="display:flex;flex-direction:column;gap:12px;">
                                <div style="display:flex;align-items:flex-start;gap:14px;padding:14px 16px;background:#fff;border-radius:10px;border:1px solid #e9ecef;">
                                    <div style="width:32px;height:32px;border-radius:50%;background:#2563eb;color:white;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0;">1</div>
                                    <div>
                                        <strong style="display:block;font-size:14px;color:#1e293b;"><?php esc_html_e('Create a SearchJet Account', 'searchjet-instant-search'); ?></strong>
                                        <span style="font-size:13px;color:#6c757d;"><?php esc_html_e('Sign up free at SearchJet — no credit card required.', 'searchjet-instant-search'); ?></span>
                                        <div style="margin-top:8px;">
                                            <a href="https://app.searchjetengine.com/register" target="_blank" rel="noopener noreferrer" class="searchjet-btn searchjet-btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
                                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/settings-external-link-white.svg'); ?>" alt="" width="16" height="16" style="display:block;">
                                                <?php esc_html_e('Sign Up Free', 'searchjet-instant-search'); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div style="display:flex;align-items:flex-start;gap:14px;padding:14px 16px;background:#fff;border-radius:10px;border:1px solid #e9ecef;">
                                    <div style="width:32px;height:32px;border-radius:50%;background:#667eea;color:white;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0;">2</div>
                                    <div>
                                        <strong style="display:block;font-size:14px;color:#1e293b;"><?php esc_html_e('Get Your API Key', 'searchjet-instant-search'); ?></strong>
                                        <span style="font-size:13px;color:#6c757d;"><?php esc_html_e('After signing in, copy your API key from the SearchJet dashboard.', 'searchjet-instant-search'); ?></span>
                                        <div style="margin-top:8px;">
                                            <a href="https://app.searchjetengine.com/dashboard" target="_blank" rel="noopener noreferrer" class="searchjet-btn searchjet-btn-secondary" style="display:inline-flex;align-items:center;gap:6px;">
                                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/settings-lock-gray.svg'); ?>" alt="" width="16" height="16" style="display:block;">
                                                <?php esc_html_e('Go to Dashboard', 'searchjet-instant-search'); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div style="display:flex;align-items:flex-start;gap:14px;padding:14px 16px;background:#e8f4fd;border-radius:10px;border:1px solid #bfdbfe;">
                                    <div style="width:32px;height:32px;border-radius:50%;background:#28a745;color:white;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0;">3</div>
                                    <div style="flex:1;">
                                        <strong style="display:block;font-size:14px;color:#1e293b;"><?php esc_html_e('Paste Your API Key Below', 'searchjet-instant-search'); ?></strong>
                                        <span style="font-size:13px;color:#6c757d;display:block;margin-bottom:12px;"><?php esc_html_e('Enter the key and click Save Settings to activate.', 'searchjet-instant-search'); ?></span>

                                        <form method="post" action="options.php" autocomplete="off">
                                            <?php settings_fields('searchjet_settings'); ?>
                                            <input type="hidden" name="searchjet_disable_search_mode" value="0">
                                            <div class="searchjet-api-key-group" style="max-width:480px;">
                                                <input type="password"
                                                       id="searchjet_hosted_api_key"
                                                       name="searchjet_hosted_api_key"
                                                       value="<?php echo esc_attr($api_key); ?>"
                                                       class="searchjet-input"
                                                       placeholder="<?php esc_attr_e('Paste your API key here', 'searchjet-instant-search'); ?>">
                                                <span class="searchjet-api-key-toggle">
                                                    <img class="searchjet-eye-icon searchjet-eye-open" src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/settings-eye-open.svg'); ?>" alt="" width="18" height="18" style="display:block;">
                                                    <img class="searchjet-eye-icon searchjet-eye-closed" src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/settings-eye-closed.svg'); ?>" alt="" width="18" height="18" style="display:none;">
                                                </span>
                                            </div>
                                            <button type="submit" class="searchjet-btn searchjet-btn-primary" style="margin-top:12px;display:inline-flex;align-items:center;gap:6px;">
                                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/settings-save-white.svg'); ?>" alt="" width="16" height="16" style="display:block;">
                                                <?php esc_html_e('Save Settings & Connect', 'searchjet-instant-search'); ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div style="flex-shrink:0;width:180px;text-align:center;padding-top:20px;">
                            <div style="background:#e8f4fd;border-radius:14px;padding:20px 16px;">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/settings-search-blue.svg'); ?>" alt="" width="48" height="48" style="margin:0 auto 10px;display:block;">
                                <strong style="display:block;font-size:14px;color:#152A53;"><?php esc_html_e('Free Tier', 'searchjet-instant-search'); ?></strong>
                                <span style="display:block;font-size:12px;color:#6c757d;margin-bottom:8px;"><?php esc_html_e('1,000 searches/mo', 'searchjet-instant-search'); ?></span>
                                <a href="https://searchjetengine.com/pricing" target="_blank" rel="noopener noreferrer" style="font-size:12px;color:#2563eb;font-weight:500;"><?php esc_html_e('Compare Plans →', 'searchjet-instant-search'); ?></a>
                            </div>
                            <div style="margin-top:12px;background:#fff;border:1px solid #e9ecef;border-radius:10px;padding:12px;">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/settings-file-indigo.svg'); ?>" alt="" width="20" height="20" style="margin:0 auto 6px;display:block;">
                                <span style="font-size:11px;color:#6c757d;"><?php esc_html_e('Need help? Use the chat button', 'searchjet-instant-search'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php else: ?>

                <!-- Connected state -->
                <div class="searchjet-card" style="border:2px solid #d4edda;background:linear-gradient(135deg,#f0fdf4,#fff);margin-bottom:24px;">
                    <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:200px;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/settings-check-green.svg'); ?>" alt="" width="24" height="24" style="display:block;">
                                <h2 style="margin:0;font-size:18px;color:#155724;"><?php esc_html_e('Connected Successfully!', 'searchjet-instant-search'); ?></h2>
                            </div>
                            <p style="margin:0 0 16px;color:#6c757d;font-size:14px;"><?php esc_html_e('Your site is linked to SearchJet. Here\'s what to do next:', 'searchjet-instant-search'); ?></p>
                            <div style="display:flex;flex-direction:column;gap:10px;">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-indexing')); ?>" class="searchjet-btn searchjet-btn-primary" style="display:inline-flex;align-items:center;gap:8px;width:fit-content;">
                                    <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-refresh-white.svg'); ?>" alt="" width="18" height="18" style="display:block;">
                                    <?php esc_html_e('Index Your Content', 'searchjet-instant-search'); ?>
                                    <span style="font-size:12px;opacity:0.8;"><?php esc_html_e('(required to show search results)', 'searchjet-instant-search'); ?></span>
                                </a>
                                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-search-ui')); ?>" class="searchjet-btn searchjet-btn-secondary" style="display:inline-flex;align-items:center;gap:6px;">
                                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-sliders-gray.svg'); ?>" alt="" width="16" height="16" style="display:block;">
                                        <?php esc_html_e('Customize Search UI', 'searchjet-instant-search'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-analytics')); ?>" class="searchjet-btn searchjet-btn-secondary" style="display:inline-flex;align-items:center;gap:6px;">
                                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/automation-chart-gray.svg'); ?>" alt="" width="16" height="16" style="display:block;">
                                        <?php esc_html_e('View Analytics', 'searchjet-instant-search'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div style="flex-shrink:0;width:140px;text-align:center;padding-top:8px;">
                            <div style="background:#d4edda;border-radius:50%;width:80px;height:80px;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/settings-check-green.svg'); ?>" alt="" width="40" height="40" style="display:block;">
                            </div>
                            <strong style="display:block;font-size:13px;color:#155724;"><?php esc_html_e('Step 4: Index', 'searchjet-instant-search'); ?></strong>
                            <span style="font-size:12px;color:#6c757d;"><?php esc_html_e('Last step to go live', 'searchjet-instant-search'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Settings form -->
                <form method="post" action="options.php" autocomplete="off">
                    <?php settings_fields('searchjet_settings'); ?>

                    <div class="searchjet-card">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
                            <div style="width:10px;height:10px;border-radius:50%;background:#28a745;"></div>
                            <h2 style="margin:0;font-size:18px;"><?php esc_html_e('API Connection', 'searchjet-instant-search'); ?></h2>
                            <span style="font-size:12px;padding:2px 10px;border-radius:10px;background:#d4edda;color:#155724;font-weight:500;"><?php esc_html_e('Connected', 'searchjet-instant-search'); ?></span>
                        </div>

                        <div class="searchjet-form-group">
                            <label for="searchjet_hosted_api_key"><?php esc_html_e('SearchJet API Key', 'searchjet-instant-search'); ?></label>
                            <div class="searchjet-api-key-group">
                                <input type="password"
                                       id="searchjet_hosted_api_key"
                                       name="searchjet_hosted_api_key"
                                       value="<?php echo esc_attr($api_key); ?>"
                                       class="searchjet-input"
                                       placeholder="<?php esc_attr_e('Enter your API key from SearchJet dashboard', 'searchjet-instant-search'); ?>">
                                <span class="searchjet-api-key-toggle">
                                    <img class="searchjet-eye-icon searchjet-eye-open" src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/settings-eye-open.svg'); ?>" alt="" width="18" height="18" style="display:block;">
                                    <img class="searchjet-eye-icon searchjet-eye-closed" src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/settings-eye-closed.svg'); ?>" alt="" width="18" height="18" style="display:none;">
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="searchjet-card">
                        <h2><?php esc_html_e('Advanced Settings', 'searchjet-instant-search'); ?></h2>
                        <div class="searchjet-form-group">
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                <input type="hidden" name="searchjet_disable_search_mode" value="0">
                                <input type="checkbox"
                                       name="searchjet_disable_search_mode"
                                       value="1"
                                       <?php checked(get_option('searchjet_disable_search_mode'), 1); ?>
                                       style="width:18px;height:18px;">
                                <div>
                                    <strong style="display:block;font-size:14px;color:#1e293b;"><?php esc_html_e('Disable Search Override', 'searchjet-instant-search'); ?></strong>
                                    <span style="font-size:13px;color:#6c757d;"><?php esc_html_e('Disable SearchJet and use WordPress default search.', 'searchjet-instant-search'); ?></span>
                                </div>
                            </label>
                        </div>
                        <div class="searchjet-form-group" style="margin-top:20px;">
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                <input type="hidden" name="searchjet_delete_data_on_uninstall" value="0">
                                <input type="checkbox"
                                       name="searchjet_delete_data_on_uninstall"
                                       value="1"
                                       <?php checked(get_option('searchjet_delete_data_on_uninstall'), 1); ?>
                                       style="width:18px;height:18px;">
                                <div>
                                    <strong style="display:block;font-size:14px;color:#1e293b;"><?php esc_html_e('Delete Data on Uninstall', 'searchjet-instant-search'); ?></strong>
                                    <span style="font-size:13px;color:#6c757d;"><?php esc_html_e('When deleting the plugin, also delete all SearchJet data. Leave off to keep your data.', 'searchjet-instant-search'); ?></span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div style="margin-top:32px;">
                        <button type="submit" class="searchjet-btn searchjet-btn-primary searchjet-btn-lg" style="display:inline-flex;align-items:center;gap:8px;">
                            <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/settings-save-white.svg'); ?>" alt="" width="18" height="18" style="display:block;">
                            <?php esc_html_e('Save Settings', 'searchjet-instant-search'); ?>
                        </button>
                    </div>
                </form>

                <?php endif; ?>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toggle = document.querySelector('.searchjet-api-key-toggle');
            var input = document.getElementById('searchjet_hosted_api_key');
            if (toggle && input) {
                toggle.addEventListener('click', function() {
                    var isOpen = input.type === 'text';
                    input.type = isOpen ? 'password' : 'text';
                    toggle.querySelector('.searchjet-eye-open').style.display = isOpen ? 'block' : 'none';
                    toggle.querySelector('.searchjet-eye-closed').style.display = isOpen ? 'none' : 'block';
                });
            }

            var wizardKey = localStorage.getItem('searchjet_wizard_api_key');
            if (wizardKey && input) {
                input.value = wizardKey;
                input.type = 'text';
                localStorage.removeItem('searchjet_wizard_api_key');
                setTimeout(function() {
                    input.type = 'password';
                }, 3000);
                var notice = document.createElement('div');
                notice.className = 'notice notice-success is-dismissible';
                notice.innerHTML = '<p><strong>' + '<?php echo esc_js(__('SearchJet:', 'searchjet-instant-search')); ?>' + '</strong> ' + '<?php echo esc_js(__('API key added from setup wizard. Click Save Settings to activate.', 'searchjet-instant-search')); ?>' + '</p>';
                var form = document.querySelector('.searchjet-content');
                if (form) {
                    form.insertBefore(notice, form.firstChild);
                    setTimeout(function() {
                        notice.style.transition = 'opacity 0.5s';
                        notice.style.opacity = '0';
                        setTimeout(function() { if (notice.parentNode) notice.parentNode.removeChild(notice); }, 500);
                    }, 6000);
                }
                input.focus();
                input.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
        </script>
        <?php
    }
}
