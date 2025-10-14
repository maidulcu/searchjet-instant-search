<?php
namespace SearchJet\Admin;

use SearchJet\AI\AgentRegistry;

class AgentsPage
{
    public static function init(): void
    {
        add_action('admin_init', [self::class, 'registerSettings']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    public static function enqueueAssets($hook): void
    {
        if (strpos($hook, 'searchjet-agents') === false) {
            return;
        }

        wp_enqueue_style(
            'searchjet-admin-enhanced',
            plugin_dir_url(dirname(__DIR__)) . 'assets/css/admin-style-enhanced.css',
            [],
            '1.0.0'
        );
    }

    public static function registerSettings(): void
    {
        register_setting('searchjet_agents', 'searchjet_agents_enabled', [
            'type'              => 'boolean',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]);

        // Which agents are enabled (per‑agent toggles)
        register_setting('searchjet_agents', 'searchjet_agents_enabled_ids', [
            'type'              => 'array',
            'sanitize_callback' => function ($value) {
                if (!is_array($value)) { return []; }
                $out = [];
                foreach ($value as $k) {
                    $out[] = sanitize_key((string) $k);
                }
                // de-duplicate
                return array_values(array_unique($out));
            },
            'default'           => [],
        ]);

        register_setting('searchjet_agents', 'searchjet_agents_notes', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => '',
        ]);
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'searchjet-instant-search'));
        }

        $enabled = (int) get_option('searchjet_agents_enabled', 0);
        $notes   = (string) get_option('searchjet_agents_notes', '');
        $tab     = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'overview';

        $page_url = admin_url('admin.php?page=searchjet-agents');
        $nav = function (string $key) use ($page_url) {
            return esc_url(add_query_arg(['tab' => $key], $page_url));
        };

        $agents = [];
        if (class_exists(AgentRegistry::class)) {
            $agents = AgentRegistry::all();
        }
        $enabledIds = (array) get_option('searchjet_agents_enabled_ids', []);
        if (empty($enabledIds) && class_exists(AgentRegistry::class) && method_exists(AgentRegistry::class, 'enabledIds')) {
            $enabledIds = (array) AgentRegistry::enabledIds();
        }
        $restUrl = esc_url_raw( rest_url('searchjet/v1/ai/dispatch') );
        $restNonce = wp_create_nonce('wp_rest');

        ?>
        <div class="searchjet-admin-wrap">
            <div class="searchjet-header">
                <h1><?php _e('AI Agents', 'searchjet'); ?></h1>
                <div class="subtitle"><?php _e('Intelligent search optimization powered by AI agents', 'searchjet'); ?></div>
            </div>

            <div class="searchjet-content">
                <div class="searchjet-nav">
                    <div class="nav-tab-wrapper">
                        <a href="<?php echo $nav('overview'); ?>" class="nav-tab <?php echo $tab === 'overview' ? 'nav-tab-active' : ''; ?>"><?php _e('Overview', 'searchjet'); ?></a>
                        <a href="<?php echo $nav('agents'); ?>" class="nav-tab <?php echo $tab === 'agents' ? 'nav-tab-active' : ''; ?>"><?php _e('Agents', 'searchjet'); ?></a>
                        <a href="<?php echo $nav('settings'); ?>" class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>"><?php _e('Settings', 'searchjet'); ?></a>
                        <a href="<?php echo $nav('debug'); ?>" class="nav-tab <?php echo $tab === 'debug' ? 'nav-tab-active' : ''; ?>"><?php _e('Debug', 'searchjet'); ?></a>
                    </div>
                </div>

                <?php if ($tab === 'overview') : ?>
                    <div class="searchjet-alert searchjet-alert-info">
                        <div>
                            <strong><?php _e('AI Agents Preview', 'searchjet'); ?></strong><br>
                            <?php _e('Agents analyze search patterns to suggest improvements like synonyms, filters, and content gaps.', 'searchjet'); ?>
                        </div>
                    </div>

                    <div class="searchjet-grid searchjet-grid-2">
                        <div class="searchjet-card">
                            <h2><?php _e('Smart Analysis', 'searchjet'); ?></h2>
                            <p><?php _e('AI agents continuously monitor search analytics and index state to identify optimization opportunities.', 'searchjet'); ?></p>
                            <div class="searchjet-feature-list">
                                <div class="searchjet-feature-item">
                                    <span class="searchjet-feature-icon">🧠</span>
                                    <span><?php _e('Pattern recognition', 'searchjet'); ?></span>
                                </div>
                                <div class="searchjet-feature-item">
                                    <span class="searchjet-feature-icon">📝</span>
                                    <span><?php _e('Content gap analysis', 'searchjet'); ?></span>
                                </div>
                                <div class="searchjet-feature-item">
                                    <span class="searchjet-feature-icon">🔗</span>
                                    <span><?php _e('Synonym suggestions', 'searchjet'); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="searchjet-card">
                            <h2><?php _e('Automated Improvements', 'searchjet'); ?></h2>
                            <p><?php _e('Agents can trigger automations you approve to continuously improve search performance.', 'searchjet'); ?></p>
                            <div class="searchjet-feature-list">
                                <div class="searchjet-feature-item">
                                    <span class="searchjet-feature-icon">⚡</span>
                                    <span><?php _e('Real-time optimization', 'searchjet'); ?></span>
                                </div>
                                <div class="searchjet-feature-item">
                                    <span class="searchjet-feature-icon">🎯</span>
                                    <span><?php _e('Targeted improvements', 'searchjet'); ?></span>
                                </div>
                                <div class="searchjet-feature-item">
                                    <span class="searchjet-feature-icon">📊</span>
                                    <span><?php _e('Performance tracking', 'searchjet'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="searchjet-card">
                        <h2><?php _e('Status', 'searchjet'); ?></h2>
                        <?php if (!$enabled): ?>
                            <div class="searchjet-alert searchjet-alert-warning">
                                <div>
                                    <strong><?php _e('Agents Disabled', 'searchjet'); ?></strong><br>
                                    <?php _e('Enable AI agents in the Settings tab to start receiving intelligent optimization suggestions.', 'searchjet'); ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="searchjet-alert searchjet-alert-success">
                                <div>
                                    <strong><?php _e('Agents Active', 'searchjet'); ?></strong><br>
                                    <?php _e('AI agents are monitoring your search patterns and will provide suggestions based on user behavior.', 'searchjet'); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div style="margin-top: 20px;">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-automations')); ?>" class="searchjet-btn searchjet-btn-primary">
                                <?php _e('Configure Automations', 'searchjet'); ?>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-analytics')); ?>" class="searchjet-btn searchjet-btn-secondary" style="margin-left: 12px;">
                                <?php _e('View Analytics', 'searchjet'); ?>
                            </a>
                        </div>
                    </div>

                <?php elseif ($tab === 'agents') : ?>
                    <div class="searchjet-card">
                        <h2><?php _e('Available Agents', 'searchjet'); ?></h2>
                        <p><?php _e('Local agents run inside WordPress without external AI calls. Connect to external workflows via Automations if needed.', 'searchjet'); ?></p>

                        <form action="options.php" method="post">
                            <?php settings_fields('searchjet_agents'); ?>
                            
                            <?php if (empty($agents)) : ?>
                                <div class="searchjet-empty-state">
                                    <h3><?php _e('No Agents Available', 'searchjet'); ?></h3>
                                    <p><?php _e('No AI agents are currently registered in the system.', 'searchjet'); ?></p>
                                </div>
                            <?php else: ?>
                                <div class="searchjet-agents-list">
                                    <?php foreach ($agents as $id => $a): ?>
                                        <div class="searchjet-agent-item">
                                            <div class="searchjet-agent-header">
                                                <div class="searchjet-agent-info">
                                                    <strong><?php echo esc_html($a['name'] ?? $id); ?></strong>
                                                    <code><?php echo esc_html($id); ?></code>
                                                </div>
                                                <label class="searchjet-agent-toggle">
                                                    <input type="checkbox" name="searchjet_agents_enabled_ids[]" value="<?php echo esc_attr($id); ?>" <?php checked(in_array($id, (array)$enabledIds, true)); ?>>
                                                    <?php _e('Enable', 'searchjet'); ?>
                                                </label>
                                            </div>
                                            <div class="searchjet-agent-description">
                                                <?php echo esc_html($a['description'] ?? 'No description available.'); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div style="margin-top: 24px;">
                                <button type="submit" class="searchjet-btn searchjet-btn-primary">
                                    <?php _e('Save Agent Settings', 'searchjet'); ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php if (!empty($agents)): ?>
                    <div class="searchjet-card">
                        <h2><?php _e('Agent Tester', 'searchjet'); ?></h2>
                        <p><?php _e('Test agents with custom JSON payloads. Only enabled agents will execute.', 'searchjet'); ?></p>
                        
                        <div class="searchjet-agent-tester">
                            <div class="searchjet-form-group">
                                <label for="sj-agent-id"><?php _e('Select Agent', 'searchjet'); ?></label>
                                <select id="sj-agent-id" class="searchjet-select">
                                    <?php foreach ($agents as $id => $a): ?>
                                        <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($a['name'] ?? $id); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="searchjet-form-group">
                                <label for="sj-agent-payload"><?php _e('Test Payload (JSON)', 'searchjet'); ?></label>
                                <textarea id="sj-agent-payload" class="searchjet-textarea" rows="4" placeholder='{"text":"Enter test data here"}'></textarea>
                            </div>
                            
                            <button class="searchjet-btn searchjet-btn-secondary" id="sj-agent-run">
                                <?php _e('Run Test', 'searchjet'); ?>
                            </button>
                            
                            <div class="searchjet-form-group" style="margin-top: 20px;">
                                <label><?php _e('Result', 'searchjet'); ?></label>
                                <pre id="sj-agent-result" class="searchjet-code-output"></pre>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php elseif ($tab === 'settings') : ?>
                    <form action="options.php" method="post">
                        <?php settings_fields('searchjet_agents'); ?>
                        
                        <div class="searchjet-card">
                            <h2><?php _e('General Settings', 'searchjet'); ?></h2>
                            
                            <div class="searchjet-form-group">
                                <label>
                                    <input type="checkbox" name="searchjet_agents_enabled" value="1" <?php checked($enabled, 1); ?>>
                                    <?php _e('Enable AI Agents (Preview)', 'searchjet'); ?>
                                </label>
                                <div class="description"><?php _e('Turn on the agent framework. No external calls are made until configured in Automations.', 'searchjet'); ?></div>
                            </div>
                            
                            <div class="searchjet-form-group">
                                <label for="searchjet_agents_notes"><?php _e('Agent Instructions', 'searchjet'); ?></label>
                                <textarea id="searchjet_agents_notes" name="searchjet_agents_notes" class="searchjet-textarea" rows="5" placeholder="<?php echo esc_attr__('e.g., Focus on product search improvements; avoid changes during peak hours', 'searchjet'); ?>"><?php echo esc_textarea($notes); ?></textarea>
                                <div class="description"><?php _e('Provide guidance on how agents should behave for your specific site and requirements.', 'searchjet'); ?></div>
                            </div>
                        </div>

                        <div style="margin-top: 32px;">
                            <button type="submit" class="searchjet-btn searchjet-btn-primary searchjet-btn-lg">
                                <?php _e('Save Settings', 'searchjet'); ?>
                            </button>
                        </div>
                    </form>

                <?php else : /* debug */ ?>
                    <div class="searchjet-card">
                        <h2><?php _e('System Information', 'searchjet'); ?></h2>
                        
                        <div class="searchjet-table-container">
                            <table class="searchjet-table">
                                <tbody>
                                    <tr><td><?php _e('Site URL', 'searchjet'); ?></td><td><?php echo esc_html(home_url('/')); ?></td></tr>
                                    <tr><td><?php _e('WordPress Version', 'searchjet'); ?></td><td><?php echo esc_html(get_bloginfo('version')); ?></td></tr>
                                    <tr><td><?php _e('PHP Version', 'searchjet'); ?></td><td><?php echo esc_html(PHP_VERSION); ?></td></tr>
                                    <tr><td><?php _e('AI Agents Status', 'searchjet'); ?></td><td><span class="searchjet-status <?php echo $enabled ? 'searchjet-status-connected' : 'searchjet-status-disconnected'; ?>"><?php echo $enabled ? __('Enabled', 'searchjet') : __('Disabled', 'searchjet'); ?></span></td></tr>
                                    <tr><td><?php _e('Automations Status', 'searchjet'); ?></td><td><span class="searchjet-status <?php echo get_option('searchjet_automations_enabled') ? 'searchjet-status-connected' : 'searchjet-status-disconnected'; ?>"><?php echo get_option('searchjet_automations_enabled') ? __('Enabled', 'searchjet') : __('Disabled', 'searchjet'); ?></span></td></tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div style="margin-top: 24px;">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-automations')); ?>" class="searchjet-btn searchjet-btn-primary">
                                <?php _e('Configure Automations', 'searchjet'); ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const runBtn = document.getElementById('sj-agent-run');
            const sel = document.getElementById('sj-agent-id');
            const ta = document.getElementById('sj-agent-payload');
            const out = document.getElementById('sj-agent-result');
            
            if (!runBtn || !sel || !ta || !out) return;
            
            const nonce = <?php echo wp_json_encode($restNonce); ?>;
            const url = <?php echo wp_json_encode($restUrl); ?>;

            function parseJSONSafe(txt) {
                try { return txt ? JSON.parse(txt) : {}; } catch(e) { return {}; }
            }
            
            function show(obj) { 
                out.textContent = JSON.stringify(obj, null, 2); 
            }

            runBtn.addEventListener('click', function(e) {
                e.preventDefault();
                runBtn.disabled = true;
                const originalText = runBtn.textContent;
                runBtn.innerHTML = '<span class="searchjet-loading"></span> Testing...';
                
                const action = sel.value;
                const payload = parseJSONSafe(ta.value);
                
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    body: JSON.stringify({ action, payload })
                })
                .then(r => r.json().catch(() => ({})))
                .then(show)
                .catch(err => show({ ok: false, error: String(err) }))
                .finally(() => {
                    runBtn.disabled = false;
                    runBtn.textContent = originalText;
                });
            });
        });
        </script>
        <?php
    }
}