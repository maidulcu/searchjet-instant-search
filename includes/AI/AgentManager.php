<?php
/**
 * SearchJet – AI Agent Manager (Admin UI + wiring)
 */

namespace SearchJet\AI;

if (!defined('ABSPATH')) {
    exit;
}

class AgentManager
{
    public const PAGE_SLUG = 'searchjet-agents';

    public static function init(): void
    {
        // Admin menu + save handler
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_post_searchjet_save_agents', [self::class, 'handle_save']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    /**
     * Add the Agents sub‑menu under the SearchJet parent.
     */
    public static function register_menu(): void
    {
        $parent = apply_filters('searchjet/admin_parent_slug', 'searchjet');

        add_submenu_page(
            $parent,
            __('AI Agents', 'searchjet-instant-search'),
            __('AI Agents', 'searchjet-instant-search'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render']
        );
    }

    /** Save enabled agent ids. */
    public static function handle_save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'searchjet-instant-search'));
        }
        check_admin_referer('searchjet_agents_save');

        $enabled = isset($_POST['enabled']) && is_array($_POST['enabled']) ? array_map('sanitize_key', $_POST['enabled']) : [];
        $ok = AgentRegistry::replaceEnabled($enabled);

        $redirect = add_query_arg([
            'page'   => self::PAGE_SLUG,
            'updated' => $ok ? '1' : '0',
        ], admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    /** Enqueue tiny inline JS for dispatch tester (only on our page). */
    public static function enqueue_assets($hook): void
    {
        if ($hook !== 'searchjet_page_' . self::PAGE_SLUG) {
            return;
        }
        // Provide REST nonce to JS
        wp_add_inline_script('jquery-core', 'window.searchjetRestNonce = ' . wp_json_encode(wp_create_nonce('wp_rest')) . ';', 'before');
    }

    /** Render the admin page. */
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to view this page.', 'searchjet-instant-search'));
        }

        $agents  = AgentRegistry::all();
        $enabled = AgentRegistry::enabledIds();
        $restUrl = esc_url_raw( rest_url('searchjet/v1/ai/dispatch') );
        ?>
        <div class="wrap searchjet-agents-page">
            <h1><?php echo esc_html__('SearchJet – AI Agents', 'searchjet-instant-search'); ?></h1>

            <?php if (isset($_GET['updated'])): ?>
                <?php if ($_GET['updated'] === '1'): ?>
                    <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Agents updated.', 'searchjet-instant-search'); ?></p></div>
                <?php else: ?>
                    <div class="notice notice-error is-dismissible"><p><?php echo esc_html__('Failed to update agents.', 'searchjet-instant-search'); ?></p></div>
                <?php endif; ?>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:24px;">
                <?php wp_nonce_field('searchjet_agents_save'); ?>
                <input type="hidden" name="action" value="searchjet_save_agents" />

                <h2><?php echo esc_html__('Available Agents', 'searchjet-instant-search'); ?></h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th style="width:60px;">&nbsp;</th>
                            <th><?php echo esc_html__('Agent', 'searchjet-instant-search'); ?></th>
                            <th><?php echo esc_html__('Description', 'searchjet-instant-search'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($agents)): ?>
                            <tr><td colspan="3"><?php echo esc_html__('No agents registered.', 'searchjet-instant-search'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($agents as $id => $a): ?>
                                <tr>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="enabled[]" value="<?php echo esc_attr($id); ?>" <?php checked(in_array($id, $enabled, true)); ?> />
                                        </label>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($a['name'] ?? $id); ?></strong>
                                        <div style="color:#666; font-size:12px;">ID: <code><?php echo esc_html($id); ?></code> · Action: <code><?php echo esc_html($a['action'] ?? ''); ?></code></div>
                                    </td>
                                    <td><?php echo esc_html($a['description'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <p>
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Save Changes', 'searchjet-instant-search'); ?></button>
                </p>
            </form>

            <hr/>

            <h2><?php echo esc_html__('Quick Tester', 'searchjet-instant-search'); ?></h2>
            <p><?php echo esc_html__('Run an agent locally to validate behavior. Only enabled agents will run.', 'searchjet-instant-search'); ?></p>

            <div id="searchjet-agent-tester" style="max-width:820px;">
                <div style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
                    <select id="sj-agent-id">
                        <?php foreach ($agents as $id => $a): ?>
                            <option value="<?php echo esc_attr($id); ?>" <?php selected(in_array($id, $enabled, true)); ?>><?php echo esc_html($a['name'] ?? $id); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="button" id="sj-agent-run"><?php echo esc_html__('Run', 'searchjet-instant-search'); ?></button>
                </div>
                <textarea id="sj-agent-payload" rows="4" style="width:100%;" placeholder='{"text":"Paste text here"}'></textarea>
                <pre id="sj-agent-result" style="background:#f6f7f7; padding:12px; margin-top:8px; overflow:auto;"></pre>
            </div>

            <script>
            (function(){
                const runBtn = document.getElementById('sj-agent-run');
                const sel    = document.getElementById('sj-agent-id');
                const ta     = document.getElementById('sj-agent-payload');
                const out    = document.getElementById('sj-agent-result');
                const nonce  = (window.searchjetRestNonce||'');
                const url    = <?php echo wp_json_encode($restUrl); ?>;

                function parseJSONSafe(txt){
                    try { return txt ? JSON.parse(txt) : {}; } catch(e){ return {}; }
                }
                function show(obj){ out.textContent = JSON.stringify(obj, null, 2); }

                runBtn.addEventListener('click', function(e){
                    e.preventDefault();
                    const action  = sel.value;
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
                    .catch(err => show({ ok:false, error: String(err) }));
                });
            })();
            </script>
        </div>
        <?php
    }
}

// Bootstrap
AgentManager::init();