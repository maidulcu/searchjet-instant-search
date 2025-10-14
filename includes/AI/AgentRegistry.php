<?php
/**
 * SearchJet – AI Agent Registry (MVP)
 *
 * Lightweight in-memory registry for local AI-style agents built on top of
 * SearchJet\AI\Router actions. Agents are simple descriptors mapping a UI card
 * to a Router action and optional default payload. Enable/disable state is
 * stored in WP options for persistence.
 */

namespace SearchJet\AI;

if (!defined('ABSPATH')) {
    exit;
}

class AgentRegistry
{
    /** @var array<string,array> */
    protected static array $items = [];

    /** Option keys */
    public const OPT_ENABLED = 'searchjet_agents_enabled'; // array of ids

    /** Boot: register built-ins and allow extensions via filters. */
    public static function init(): void
    {
        // Register built-in agents
        self::register([
            'id'          => 'recommend_attributes',
            'name'        => __('Recommend Search Attributes', 'searchjet-instant-search'),
            'description' => __('Suggests SearchJet attributes (and Woo fields if active) for optimal autocomplete and product cards.', 'searchjet-instant-search'),
            'action'      => 'recommend_attributes',
            'defaults'    => ['includeWoo' => class_exists('WooCommerce')],
        ]);

        self::register([
            'id'          => 'summarize_text',
            'name'        => __('Summarize Text', 'searchjet-instant-search'),
            'description' => __('Creates a short summary from longer text (first N sentences heuristic).', 'searchjet-instant-search'),
            'action'      => 'summarize_text',
            'defaults'    => ['max_sentences' => 2],
        ]);

        self::register([
            'id'          => 'extract_keywords',
            'name'        => __('Extract Keywords', 'searchjet-instant-search'),
            'description' => __('Picks frequent keywords after removing stopwords; handy for synonyms and boosters.', 'searchjet-instant-search'),
            'action'      => 'extract_keywords',
            'defaults'    => ['max' => 15],
        ]);

        /**
         * Allow external code to append/modify agents.
         * Each item should match the schema used in self::register().
         */
        $extra = apply_filters('searchjet/ai/agents', []);
        if (is_array($extra)) {
            foreach ($extra as $def) {
                if (is_array($def)) {
                    self::register($def);
                }
            }
        }
    }

    /**
     * Register or overwrite an agent definition.
     *
     * Required keys: id, name, action
     * Optional: description, defaults (array)
     */
    public static function register(array $def): void
    {
        $id   = isset($def['id']) ? sanitize_key($def['id']) : '';
        $name = isset($def['name']) ? (string) $def['name'] : '';
        $act  = isset($def['action']) ? (string) $def['action'] : '';

        if ($id === '' || $name === '' || $act === '') {
            return; // invalid
        }

        self::$items[$id] = [
            'id'          => $id,
            'name'        => $name,
            'description' => isset($def['description']) ? (string) $def['description'] : '',
            'action'      => $act,
            'defaults'    => isset($def['defaults']) && is_array($def['defaults']) ? $def['defaults'] : [],
        ];
    }

    /** Return all agents, annotated with enabled state. */
    public static function all(): array
    {
        $enabled = self::enabledIds();
        $out = [];
        foreach (self::$items as $id => $it) {
            $it['enabled'] = in_array($id, $enabled, true);
            $out[$id] = $it;
        }
        return $out;
    }

    /** Get a single agent by id (with enabled flag). */
    public static function get(string $id): ?array
    {
        $id = sanitize_key($id);
        if (!isset(self::$items[$id])) {
            return null;
        }
        $it = self::$items[$id];
        $it['enabled'] = in_array($id, self::enabledIds(), true);
        return $it;
    }

    /** Enable/disable an agent id and persist the set. */
    public static function setEnabled(string $id, bool $enabled): bool
    {
        $id = sanitize_key($id);
        $all = self::enabledIds();
        $had = in_array($id, $all, true);
        if ($enabled && !$had) {
            $all[] = $id;
        } elseif (!$enabled && $had) {
            $all = array_values(array_diff($all, [$id]));
        }
        return update_option(self::OPT_ENABLED, $all, false);
    }

    /** Replace the full enabled set. */
    public static function replaceEnabled(array $ids): bool
    {
        $clean = [];
        foreach ($ids as $id) {
            $id = sanitize_key((string) $id);
            if ($id !== '') { $clean[] = $id; }
        }
        $clean = array_values(array_unique($clean));
        return update_option(self::OPT_ENABLED, $clean, false);
    }

    /** Read enabled ids from options. */
    public static function enabledIds(): array
    {
        $val = get_option(self::OPT_ENABLED, []);
        return is_array($val) ? array_values(array_map('sanitize_key', $val)) : [];
    }

    /**
     * Run an agent by id with an optional payload. Returns Router dispatch
     * result or an error array.
     */
    public static function run(string $id, array $payload = []): array
    {
        $agent = self::get($id);
        if (!$agent) {
            return ['ok' => false, 'error' => 'unknown_agent'];
        }
        if (empty($agent['enabled'])) {
            return ['ok' => false, 'error' => 'agent_disabled'];
        }
        $payload = array_merge($agent['defaults'] ?? [], $payload);
        return Router::dispatch((string) $agent['action'], $payload);
    }
}

// Auto-register on load
AgentRegistry::init();