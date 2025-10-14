<?php
namespace SearchJet\Admin;

use SearchJet\Automations\Registry as AutomationsRegistry;

class AutomationPage
{
    public static function init(): void
    {
        add_action('admin_init', [self::class, 'registerSettings']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);

        // simple webhook ping
        add_action('wp_ajax_searchjet_automation_test_webhook', [self::class, 'ajaxTestWebhook']);
    }


    public static function registerSettings(): void
    {
        register_setting('searchjet_automations', 'searchjet_automations_enabled', [
            'type'              => 'boolean',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]);

        register_setting('searchjet_automations', 'searchjet_webhook_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ]);

        register_setting('searchjet_automations', 'searchjet_webhook_secret', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        // Provider selection and provider-specific URLs
        register_setting('searchjet_automations', 'searchjet_webhook_provider', [
            'type'              => 'string',
            'sanitize_callback' => function ($v) {
                $v = sanitize_key((string) $v);
                return in_array($v, ['custom','n8n','zapier'], true) ? $v : 'custom';
            },
            'default'           => 'custom',
        ]);

        register_setting('searchjet_automations', 'searchjet_webhook_url_n8n', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ]);

        register_setting('searchjet_automations', 'searchjet_webhook_url_zapier', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ]);

        // list of enabled built-ins; empty = all on
        register_setting('searchjet_automations', 'searchjet_automation_filters', [
            'type'              => 'array',
            'sanitize_callback' => function ($v) {
                $v = is_array($v) ? $v : [];
                $v = array_map('sanitize_key', $v);
                $v = array_values(array_filter($v, static fn($k) => $k !== '__none__' && $k !== ''));
                $v = array_values(array_unique($v));
                return $v;
            },
            'default'           => [],
        ]);
    }

    public static function enqueueAssets(string $hook): void
    {
        if (strpos((string) $hook, 'searchjet-automations') === false && (empty($_GET['page']) || $_GET['page'] !== 'searchjet-automations')) {
            return;
        }

        wp_enqueue_style(
            'searchjet-admin-automations',
            plugin_dir_url(dirname(__DIR__)) . 'assets/css/automations.css',
            [],
            '1.1.0'
        );
        wp_enqueue_script(
            'searchjet-admin-automations',
            plugin_dir_url(dirname(__DIR__)) . 'assets/js/automations.js',
            ['jquery'],
            '1.1.0',
            true
        );
        wp_localize_script('searchjet-admin-automations', 'SearchJetAutomations', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('searchjet_automation_nonce'),
        ]);
    }

    public static function ajaxTestWebhook(): void
    {
        check_ajax_referer('searchjet_automation_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'searchjet-instant-search')]);
        }

        $url    = trim((string) get_option('searchjet_webhook_url', ''));
        $secret = trim((string) get_option('searchjet_webhook_secret', ''));

        if ($url === '') {
            wp_send_json_error(['message' => __('Webhook URL is empty.', 'searchjet-instant-search')]);
        }

        $payload = [
            'site'    => home_url('/'),
            'event'   => 'searchjet.test',
            'payload' => ['message' => 'Webhook test from SearchJet WP'],
            'ts'      => time(),
        ];
        $body = wp_json_encode($payload);

        $headers = [
            'Content-Type'          => 'application/json',
            'X-SearchJet-Timestamp' => (string) $payload['ts'],
        ];
        if ($secret !== '') {
            $headers['X-SearchJet-Signature'] =
                'sha256=' . hash_hmac('sha256', $body . '|' . $payload['ts'], $secret);
        }

        $resp = wp_remote_post($url, ['timeout' => 10, 'headers' => $headers, 'body' => $body]);

        if (is_wp_error($resp)) {
            wp_send_json_error(['message' => $resp->get_error_message()]);
        }
        wp_send_json_success(['code' => wp_remote_retrieve_response_code($resp)]);
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'searchjet-instant-search'));
        }

        $enabled        = (int) get_option('searchjet_automations_enabled', 0);
        $webhook_url    = (string) get_option('searchjet_webhook_url', '');
        $webhook_secret = (string) get_option('searchjet_webhook_secret', '');
        $provider       = (string) get_option('searchjet_webhook_provider', 'custom');
        $url_n8n        = (string) get_option('searchjet_webhook_url_n8n', '');
        $url_zapier     = (string) get_option('searchjet_webhook_url_zapier', '');
        $filters_raw = get_option('searchjet_automation_filters', null);
        $filters     = is_array($filters_raw) ? $filters_raw : [];
        $all_by_default = is_null($filters_raw);

        if (class_exists(AutomationsRegistry::class)) {
            AutomationsRegistry::init();
        }
        $builtin = class_exists(AutomationsRegistry::class) ? AutomationsRegistry::all() : [];
        ?>
        <div class="searchjet-admin-wrap">
            <div class="searchjet-header">
                <h1><?php _e('Automations', 'searchjet'); ?></h1>
                <div class="subtitle"><?php _e('Intelligent search optimization and automated improvements', 'searchjet'); ?></div>
            </div>

            <div class="searchjet-content">
                <div class="searchjet-alert searchjet-alert-info">
                    <div>
                        <strong><?php _e('Smart Automations', 'searchjet'); ?></strong><br>
                        <?php _e('Trigger actions via webhooks when specific search events occur, such as zero-result query bursts.', 'searchjet'); ?>
                    </div>
                </div>

                <form action="options.php" method="post">
                    <?php settings_fields('searchjet_automations'); ?>
                    
                    <div class="searchjet-card">
                        <h2><?php _e('General Settings', 'searchjet'); ?></h2>
                        <div class="searchjet-form-group">
                            <label>
                                <input type="checkbox" name="searchjet_automations_enabled" value="1" <?php checked($enabled, 1); ?>>
                                <?php _e('Enable Automations', 'searchjet'); ?>
                            </label>
                            <div class="description"><?php _e('Turn on background triggers and webhook dispatch.', 'searchjet'); ?></div>
                        </div>
                    </div>

                    <div class="searchjet-card">
                        <h2><?php _e('Webhook Configuration', 'searchjet'); ?></h2>
                        
                        <div class="searchjet-form-group">
                            <label><?php _e('Provider', 'searchjet'); ?></label>
                            <div class="searchjet-radio-group">
                                <label>
                                    <input type="radio" name="searchjet_webhook_provider" value="custom" <?php checked($provider, 'custom'); ?>>
                                    <?php _e('Custom Webhook', 'searchjet'); ?>
                                </label>
                                <label>
                                    <input type="radio" name="searchjet_webhook_provider" value="n8n" <?php checked($provider, 'n8n'); ?>>
                                    <?php _e('n8n', 'searchjet'); ?>
                                </label>
                                <label>
                                    <input type="radio" name="searchjet_webhook_provider" value="zapier" <?php checked($provider, 'zapier'); ?>>
                                    <?php _e('Zapier', 'searchjet'); ?>
                                </label>
                            </div>
                            <div class="description"><?php _e('Choose your automation provider.', 'searchjet'); ?></div>
                        </div>

                        <div class="sj-provider sj-provider-custom searchjet-form-group">
                            <label for="searchjet_webhook_url"><?php _e('Custom Webhook URL', 'searchjet'); ?></label>
                            <input type="url" class="searchjet-input" id="searchjet_webhook_url" name="searchjet_webhook_url" value="<?php echo esc_attr($webhook_url); ?>" placeholder="https://hooks.example.com/...">
                        </div>

                        <div class="sj-provider sj-provider-n8n searchjet-form-group">
                            <label for="searchjet_webhook_url_n8n"><?php _e('n8n Webhook URL', 'searchjet'); ?></label>
                            <input type="url" class="searchjet-input" id="searchjet_webhook_url_n8n" name="searchjet_webhook_url_n8n" value="<?php echo esc_attr($url_n8n); ?>" placeholder="https://n8n.example.com/webhook/...">
                        </div>

                        <div class="sj-provider sj-provider-zapier searchjet-form-group">
                            <label for="searchjet_webhook_url_zapier"><?php _e('Zapier Webhook URL', 'searchjet'); ?></label>
                            <input type="url" class="searchjet-input" id="searchjet_webhook_url_zapier" name="searchjet_webhook_url_zapier" value="<?php echo esc_attr($url_zapier); ?>" placeholder="https://hooks.zapier.com/hooks/catch/...">
                        </div>

                        <div class="searchjet-form-group">
                            <label for="searchjet_webhook_secret"><?php _e('Webhook Secret (HMAC)', 'searchjet'); ?></label>
                            <input type="text" class="searchjet-input" id="searchjet_webhook_secret" name="searchjet_webhook_secret" value="<?php echo esc_attr($webhook_secret); ?>">
                            <div class="description"><?php _e('If set, requests include X-SearchJet-Signature for verification.', 'searchjet'); ?></div>
                        </div>
                    </div>

                    <?php if (!empty($builtin)) : ?>
                    <div class="searchjet-card">
                        <h2><?php _e('Available Automations', 'searchjet'); ?></h2>
                        <input type="hidden" name="searchjet_automation_filters[]" value="__none__">
                        <div class="searchjet-automation-list">
                            <?php foreach ($builtin as $arrKey => $def) :
                                $defId = isset($def['id']) ? sanitize_key($def['id']) : sanitize_key(is_string($arrKey) ? $arrKey : (string) $arrKey);
                                $checked = $all_by_default ? true : in_array($defId, $filters, true); ?>
                                <div class="searchjet-automation-item">
                                    <label>
                                        <input type="checkbox" name="searchjet_automation_filters[]" value="<?php echo esc_attr($defId); ?>" <?php checked($checked); ?>>
                                        <strong><?php echo esc_html($def['label'] ?? $defId); ?></strong>
                                    </label>
                                    <div class="description"><?php echo esc_html($def['description'] ?? ''); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="description"><?php _e('Uncheck to disable specific automations.', 'searchjet'); ?></div>
                    </div>
                    <?php endif; ?>

                    <div style="margin-top: 32px;">
                        <button type="submit" class="searchjet-btn searchjet-btn-primary searchjet-btn-lg">
                            <?php _e('Save Settings', 'searchjet'); ?>
                        </button>
                    </div>
                </form>

                <div class="searchjet-card" style="margin-top: 32px;">
                    <h2><?php _e('Connection Test', 'searchjet'); ?></h2>
                    <p><?php _e('Test your webhook configuration by sending a test event.', 'searchjet'); ?></p>
                    <button class="searchjet-btn searchjet-btn-secondary" id="searchjet-automation-test">
                        <?php _e('Send Test Event', 'searchjet'); ?>
                    </button>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('searchjet-automation-test');
            if (!btn) return;
            
            function toggleProviderRows() {
                const val = document.querySelector('input[name="searchjet_webhook_provider"]:checked')?.value || 'custom';
                document.querySelectorAll('.sj-provider').forEach(el => el.style.display = 'none');
                document.querySelectorAll('.sj-provider-' + val).forEach(el => el.style.display = 'block');
            }
            
            document.querySelectorAll('input[name="searchjet_webhook_provider"]').forEach(r => {
                r.addEventListener('change', toggleProviderRows);
            });
            toggleProviderRows();
            
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                btn.disabled = true;
                const originalText = btn.textContent;
                btn.innerHTML = '<span class="searchjet-loading"></span> Testing...';
                
                jQuery.post(ajaxurl, {
                    action: 'searchjet_automation_test_webhook',
                    _ajax_nonce: SearchJetAutomations.nonce
                }).done(function(resp) {
                    const message = resp && resp.success 
                        ? 'Test successful! Response code: ' + (resp.data?.code || 'Unknown')
                        : 'Test failed: ' + (resp.data?.message || 'Unknown error');
                    alert(message);
                }).always(function() {
                    btn.disabled = false;
                    btn.textContent = originalText;
                });
            });
        });
        </script>
        <?php
    }
}