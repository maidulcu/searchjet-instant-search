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
        if ($hook !== 'searchjet_page_searchjet-agents') {
            return;
        }

        wp_enqueue_style(
            'searchjet-admin-tw',
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
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'searchjet-instant-search'));
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
        $restUrl = esc_url( rest_url('searchjet/v1/ai/dispatch') );
        $restNonce = wp_create_nonce('wp_rest');

        $enabledCount = count($enabledIds);
        $totalCount = count($agents);
        $categories = array_unique(array_map(fn($a) => $a['category'] ?? 'general', $agents));

        ?>
        <div class="searchjet-admin-wrap min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
            <div class="bg-gradient-to-r from-[#152A53] to-[#2563eb] text-white px-8 py-6 shadow-lg">
                <div class="max-w-6xl mx-auto flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl text-white mb-1"><?php esc_html_e('AI Agents', 'searchjet-instant-search'); ?></h1>
                        <p class="text-blue-100 text-xl"><?php esc_html_e('Intelligent search optimization powered by AI agents', 'searchjet-instant-search'); ?></p>
                    </div>
                    <div class="flex items-center gap-2 bg-white px-4 py-2 rounded-lg shadow-lg">
                        <span class="w-2 h-2 <?php echo $enabled ? 'bg-green-500' : 'bg-yellow-500'; ?> rounded-full"></span>
                        <strong class="text-sm text-[#152A53]"><?php echo $enabled ? esc_html__('Enabled', 'searchjet-instant-search') : esc_html__('Disabled', 'searchjet-instant-search'); ?></strong>
                    </div>
                </div>
            </div>

            <div class="searchjet-content max-w-6xl mx-auto">

                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-automations')); ?>" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/agents-activity-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;margin-right:6px;">
                        <?php esc_html_e('Automations', 'searchjet-instant-search'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-task-history')); ?>" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/bulk-clock-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;margin-right:6px;">
                        <?php esc_html_e('Task History', 'searchjet-instant-search'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-analytics')); ?>" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/automation-chart-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;margin-right:6px;">
                        <?php esc_html_e('Analytics', 'searchjet-instant-search'); ?>
                    </a>
                </div>
                <div class="searchjet-nav">
                    <div class="nav-tab-wrapper">
                        <a href="<?php echo esc_url(add_query_arg(['tab' => 'overview'], $page_url)); ?>" class="nav-tab <?php echo $tab === 'overview' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Overview', 'searchjet-instant-search'); ?></a>
                        <a href="<?php echo esc_url(add_query_arg(['tab' => 'agents'], $page_url)); ?>" class="nav-tab <?php echo $tab === 'agents' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Agents', 'searchjet-instant-search'); ?></a>
                        <a href="<?php echo esc_url(add_query_arg(['tab' => 'settings'], $page_url)); ?>" class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Settings', 'searchjet-instant-search'); ?></a>
                        <a href="<?php echo esc_url(add_query_arg(['tab' => 'debug'], $page_url)); ?>" class="nav-tab <?php echo $tab === 'debug' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Debug', 'searchjet-instant-search'); ?></a>
                    </div>
                </div>

                <?php if ($tab === 'overview') : ?>
                    <div class="searchjet-grid searchjet-grid-3">
                        <div class="searchjet-stat-card">
                            <div class="searchjet-stat-number"><?php echo (int) $totalCount; ?></div>
                            <div class="searchjet-stat-label"><?php esc_html_e('Total Agents', 'searchjet-instant-search'); ?></div>
                        </div>
                        <div class="searchjet-stat-card">
                            <div class="searchjet-stat-number" style="color: #28a745;"><?php echo (int) $enabledCount; ?></div>
                            <div class="searchjet-stat-label"><?php esc_html_e('Active', 'searchjet-instant-search'); ?></div>
                        </div>
                        <div class="searchjet-stat-card">
                            <div class="searchjet-stat-number" style="color: #667eea;"><?php echo (int) count($categories); ?></div>
                            <div class="searchjet-stat-label"><?php esc_html_e('Categories', 'searchjet-instant-search'); ?></div>
                        </div>
                    </div>

                    <div class="searchjet-grid searchjet-grid-2">
                        <div class="searchjet-card">
                            <h2><?php esc_html_e('Smart Analysis', 'searchjet-instant-search'); ?></h2>
                            <p><?php esc_html_e('AI agents continuously monitor search analytics and index state to identify optimization opportunities.', 'searchjet-instant-search'); ?></p>
                            <div class="searchjet-feature-list">
                                <div class="searchjet-feature-item">
                                    <span class="searchjet-agent-category-icon analysis" style="width:32px;height:32px;font-size:14px;">
                                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/agents-search-purple.svg'); ?>" alt="" width="16" height="16" style="display:block;">
                                    </span>
                                    <span><?php esc_html_e('Pattern recognition', 'searchjet-instant-search'); ?></span>
                                </div>
                                <div class="searchjet-feature-item">
                                    <span class="searchjet-agent-category-icon content" style="width:32px;height:32px;font-size:14px;">
                                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/agents-file-amber.svg'); ?>" alt="" width="16" height="16" style="display:block;">
                                    </span>
                                    <span><?php esc_html_e('Content gap analysis', 'searchjet-instant-search'); ?></span>
                                </div>
                                <div class="searchjet-feature-item">
                                    <span class="searchjet-agent-category-icon configuration" style="width:32px;height:32px;font-size:14px;">
                                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/agents-code-green.svg'); ?>" alt="" width="16" height="16" style="display:block;">
                                    </span>
                                    <span><?php esc_html_e('Synonym suggestions', 'searchjet-instant-search'); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="searchjet-card">
                            <h2><?php esc_html_e('Quick Start', 'searchjet-instant-search'); ?></h2>
                            <ul class="searchjet-quickstart-list">
                                <li class="searchjet-quickstart-item">
                                    <span class="searchjet-quickstart-num">1</span>
                                    <span class="searchjet-quickstart-text"><?php echo wp_kses_post(__('<strong>Enable agents</strong> in the Settings tab to activate the framework.', 'searchjet-instant-search')); ?></span>
                                </li>
                                <li class="searchjet-quickstart-item">
                                    <span class="searchjet-quickstart-num">2</span>
                                    <span class="searchjet-quickstart-text"><?php echo wp_kses_post(__('<strong>Select agents</strong> you want to use in the Agents tab.', 'searchjet-instant-search')); ?></span>
                                </li>
                                <li class="searchjet-quickstart-item">
                                    <span class="searchjet-quickstart-num">3</span>
                                    <span class="searchjet-quickstart-text"><?php echo wp_kses_post(__('<strong>Configure automations</strong> to let agents run on schedule or trigger events.', 'searchjet-instant-search')); ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="searchjet-card">
                        <h2><?php esc_html_e('Status', 'searchjet-instant-search'); ?></h2>
                        <?php if (!$enabled): ?>
                            <div class="searchjet-alert searchjet-alert-warning">
                                <div>
                                    <strong><?php esc_html_e('Agents Disabled', 'searchjet-instant-search'); ?></strong><br>
                                    <?php esc_html_e('Enable AI agents in the Settings tab to start receiving intelligent optimization suggestions.', 'searchjet-instant-search'); ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="searchjet-alert searchjet-alert-success">
                                <div>
                                    <strong><?php esc_html_e('Agents Active', 'searchjet-instant-search'); ?></strong><br>
                                    <?php printf(esc_html__(' %d of %d agents enabled and monitoring search patterns.', 'searchjet-instant-search'), (int) $enabledCount, (int) $totalCount); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div style="margin-top: 20px;">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-automations')); ?>" class="searchjet-btn searchjet-btn-primary">
                                <?php esc_html_e('Configure Automations', 'searchjet-instant-search'); ?>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-analytics')); ?>" class="searchjet-btn searchjet-btn-secondary" style="margin-left: 12px;">
                                <?php esc_html_e('View Analytics', 'searchjet-instant-search'); ?>
                            </a>
                        </div>
                    </div>

                <?php elseif ($tab === 'agents') : ?>
                    <div class="searchjet-card">
                        <h2><?php esc_html_e('Available Agents', 'searchjet-instant-search'); ?></h2>
                        <p><?php esc_html_e('Local agents run inside WordPress without external AI calls. Connect to external workflows via Automations if needed.', 'searchjet-instant-search'); ?></p>

                        <form action="options.php" method="post">
                            <?php settings_fields('searchjet_agents'); ?>
                            
                            <?php if (empty($agents)) : ?>
                                <div class="searchjet-empty-state">
                                    <h3><?php esc_html_e('No Agents Available', 'searchjet-instant-search'); ?></h3>
                                    <p><?php esc_html_e('No AI agents are currently registered in the system.', 'searchjet-instant-search'); ?></p>
                                </div>
                            <?php else: ?>
                                <div class="searchjet-agents-list">
                                    <?php foreach ($agents as $id => $a): 
                                        $isEnabled = in_array($id, (array)$enabledIds, true);
                                        $category = $a['category'] ?? 'general';
                                    ?>
                                        <div class="searchjet-agent-item <?php echo $isEnabled ? 'searchjet-agent-item-enabled' : 'searchjet-agent-item-disabled'; ?>">
                                            <div class="searchjet-agent-header">
                                                <div class="searchjet-agent-info">
                                                    <strong><?php echo esc_html($a['name'] ?? $id); ?></strong>
                                                    <code><?php echo esc_html($id); ?></code>
                                                </div>
                                                <div class="searchjet-agent-badges">
                                                    <span class="searchjet-badge searchjet-badge-<?php echo esc_attr($category); ?>"><?php echo esc_html(ucfirst($category)); ?></span>
                                                    <span class="searchjet-badge <?php echo $isEnabled ? 'searchjet-badge-success' : 'searchjet-badge-muted'; ?>">
                                                        <?php echo $isEnabled ? esc_html__('Active', 'searchjet-instant-search') : esc_html__('Inactive', 'searchjet-instant-search'); ?>
                                                    </span>
                                                    <label class="searchjet-toggle-option">
                                                        <input type="checkbox" name="searchjet_agents_enabled_ids[]" value="<?php echo esc_attr($id); ?>" <?php checked($isEnabled); ?>>
                                                        <span class="searchjet-toggle-slider"></span>
                                                    </label>
                                                </div>
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
                                    <?php esc_html_e('Save Agent Settings', 'searchjet-instant-search'); ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php if (!empty($agents)): ?>
                    <div class="searchjet-card">
                        <h2><?php esc_html_e('Agent Tester', 'searchjet-instant-search'); ?></h2>
                        <p><?php esc_html_e('Test agents with custom JSON payloads. Only enabled agents will execute.', 'searchjet-instant-search'); ?></p>
                        
                        <div class="searchjet-agent-tester">
                            <div class="searchjet-form-group">
                                <label for="sj-agent-id"><?php esc_html_e('Select Agent', 'searchjet-instant-search'); ?></label>
                                <select id="sj-agent-id" class="searchjet-select">
                                    <?php foreach ($agents as $id => $a): ?>
                                        <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($a['name'] ?? $id); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="searchjet-form-group">
                                <label for="sj-agent-payload"><?php esc_html_e('Test Payload (JSON)', 'searchjet-instant-search'); ?></label>
                                <textarea id="sj-agent-payload" class="searchjet-textarea searchjet-textarea-dark" rows="4" placeholder='{"text":"Enter test data here"}'></textarea>
                            </div>
                            
                            <div style="display: flex; gap: 8px;">
                                <button class="searchjet-btn searchjet-btn-secondary" id="sj-agent-run">
                                    <?php esc_html_e('Run Test', 'searchjet-instant-search'); ?>
                                </button>
                                <button class="searchjet-btn searchjet-btn-secondary" id="sj-agent-clear" type="button">
                                    <?php esc_html_e('Clear', 'searchjet-instant-search'); ?>
                                </button>
                            </div>
                            
                            <div class="searchjet-form-group" style="margin-top: 20px;">
                                <label><?php esc_html_e('Result', 'searchjet-instant-search'); ?></label>
                                <div id="sj-agent-result" class="searchjet-result-panel"></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php elseif ($tab === 'settings') : ?>
                    <form action="options.php" method="post">
                        <?php settings_fields('searchjet_agents'); ?>
                        
                        <div class="searchjet-card">
                            <h2><?php esc_html_e('General Settings', 'searchjet-instant-search'); ?></h2>
                            
                            <div class="searchjet-form-group">
                                <label class="searchjet-toggle-option">
                                    <input type="checkbox" name="searchjet_agents_enabled" value="1" <?php checked($enabled, 1); ?>>
                                    <span class="searchjet-toggle-slider"></span>
                                    <span class="searchjet-toggle-label"><?php esc_html_e('Enable AI Agents (Preview)', 'searchjet-instant-search'); ?></span>
                                </label>
                                <div class="description" style="margin-top: 8px;"><?php esc_html_e('Turn on the agent framework. No external calls are made until configured in Automations.', 'searchjet-instant-search'); ?></div>
                            </div>
                            
                            <div class="searchjet-form-group">
                                <label for="searchjet_agents_notes"><?php esc_html_e('Agent Instructions', 'searchjet-instant-search'); ?></label>
                                <textarea id="searchjet_agents_notes" name="searchjet_agents_notes" class="searchjet-textarea" rows="5" placeholder="<?php echo esc_attr__('e.g., Focus on product search improvements; avoid changes during peak hours', 'searchjet-instant-search'); ?>"><?php echo esc_textarea($notes); ?></textarea>
                                <div class="description"><?php esc_html_e('Provide guidance on how agents should behave for your specific site and requirements. Use one instruction per line.', 'searchjet-instant-search'); ?></div>
                            </div>
                        </div>

                        <div style="margin-top: 32px;">
                            <button type="submit" class="searchjet-btn searchjet-btn-primary searchjet-btn-lg">
                                <?php esc_html_e('Save Settings', 'searchjet-instant-search'); ?>
                            </button>
                        </div>
                    </form>

                <?php else : /* debug */ ?>
                    <div class="searchjet-card">
                        <h2><?php esc_html_e('System Information', 'searchjet-instant-search'); ?></h2>
                        
                        <div style="margin-bottom: 16px;">
                            <button class="searchjet-copy-btn" id="sj-debug-copy" type="button">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/agents-copy-gray.svg'); ?>" alt="" width="14" height="14" style="display:block;">
                                <?php esc_html_e('Copy to Clipboard', 'searchjet-instant-search'); ?>
                            </button>
                        </div>

                        <div class="searchjet-table-container" id="sj-debug-info">
                            <table class="searchjet-table">
                                <tbody>
                                    <tr><td><?php esc_html_e('Site URL', 'searchjet-instant-search'); ?></td><td><?php echo esc_html(home_url('/')); ?></td></tr>
                                    <tr><td><?php esc_html_e('WordPress Version', 'searchjet-instant-search'); ?></td><td><span class="searchjet-debug-badge"><?php echo esc_html(get_bloginfo('version')); ?></span></td></tr>
                                    <tr><td><?php esc_html_e('PHP Version', 'searchjet-instant-search'); ?></td><td><span class="searchjet-debug-badge"><?php echo esc_html(PHP_VERSION); ?></span></td></tr>
                                    <tr><td><?php esc_html_e('AI Agents Status', 'searchjet-instant-search'); ?></td><td><span class="searchjet-status <?php echo $enabled ? 'searchjet-status-connected' : 'searchjet-status-disconnected'; ?>"><?php echo $enabled ? esc_html__('Enabled', 'searchjet-instant-search') : esc_html__('Disabled', 'searchjet-instant-search'); ?></span></td></tr>
                                    <tr><td><?php esc_html_e('Automations Status', 'searchjet-instant-search'); ?></td><td><span class="searchjet-status <?php echo esc_attr(get_option('searchjet_automations_enabled')) ? 'searchjet-status-connected' : 'searchjet-status-disconnected'; ?>"><?php echo get_option('searchjet_automations_enabled') ? esc_html__('Enabled', 'searchjet-instant-search') : esc_html__('Disabled', 'searchjet-instant-search'); ?></span></td></tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div style="margin-top: 24px;">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-automations')); ?>" class="searchjet-btn searchjet-btn-primary">
                                <?php esc_html_e('Configure Automations', 'searchjet-instant-search'); ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const runBtn = document.getElementById('sj-agent-run');
            const clearBtn = document.getElementById('sj-agent-clear');
            const sel = document.getElementById('sj-agent-id');
            const ta = document.getElementById('sj-agent-payload');
            const out = document.getElementById('sj-agent-result');
            const copyBtn = document.getElementById('sj-debug-copy');
            
            if (runBtn && sel && ta && out) {
                const nonce = <?php echo wp_json_encode($restNonce); ?>;
                const url = <?php echo wp_json_encode($restUrl); ?>;

                function parseJSONSafe(txt) {
                    try { 
                        if (!txt || !txt.trim()) return { error: 'empty' }; 
                        return JSON.parse(txt); 
                    } catch(e) { 
                        return { error: 'Invalid JSON: ' + e.message }; 
                    }
                }

                function show(obj, isError) { 
                    out.textContent = JSON.stringify(obj, null, 2);
                    out.className = 'searchjet-result-panel ' + (isError ? 'searchjet-result-error' : 'searchjet-result-success');
                }

                runBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    runBtn.disabled = true;
                    const originalText = runBtn.textContent;
                    runBtn.innerHTML = '<span class="searchjet-loading"></span> Testing...';
                    
                    const action = sel.value;
                    const payload = parseJSONSafe(ta.value);
                    
                    if (payload.error) {
                        show({ ok: false, error: payload.error }, true);
                        runBtn.disabled = false;
                        runBtn.textContent = originalText;
                        return;
                    }
                    
                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': nonce
                        },
                        body: JSON.stringify({ action, payload })
                    })
                    .then(r => r.json().catch(() => ({})))
                    .then(resp => show(resp, !resp || resp.success === false))
                    .catch(err => show({ ok: false, error: String(err) }, true))
                    .finally(() => {
                        runBtn.disabled = false;
                        runBtn.textContent = originalText;
                    });
                });

                if (clearBtn) {
                    clearBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        ta.value = '';
                        out.textContent = '';
                        out.className = 'searchjet-result-panel';
                    });
                }
            }

            if (copyBtn) {
                copyBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const info = document.getElementById('sj-debug-info');
                    if (!info) return;
                    const text = info.innerText;
                    navigator.clipboard.writeText(text).then(function() {
                        copyBtn.classList.add('copied');
                        copyBtn.textContent = 'Copied!';
                        setTimeout(function() {
                            copyBtn.classList.remove('copied');
                            copyBtn.textContent = 'Copy to Clipboard';
                        }, 2000);
                    });
                });
            }
        });
        </script>
        <?php
    }
}