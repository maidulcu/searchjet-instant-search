<?php
/**
 * SearchJet – AI Merchandising Agent (MVP)
 *
 * Purpose: Provide a lightweight, rule-based merchandising planner that can
 * reorder search results and emit a simple plan (pin/boost/demote) without
 * any external AI dependency. This is meant to be called via the AI Router
 * action `merchandising_plan` and surfaced in the Agents UI.
 */

namespace SearchJet\AI\Agents;

if (!defined('ABSPATH')) {
    exit;
}

class MerchandisingAgent
{
    /**
     * Return default config for the agent.
     */
    public static function defaultConfig(): array
    {
        return [
            // Hard pin items to the top (IDs)
            'pin_ids'          => [],
            // Demote items (IDs)
            'demote_ids'       => [],
            // Boost categories (strings; exact match in item categories[])
            'boost_categories' => [],
            // Demote categories
            'demote_categories'=> [],
            // Prefer items on sale
            'sale_first'       => true,
            // Demote out-of-stock items
            'demote_oos'       => true,
            // Optional price filters
            'price_min'        => null, // float|int|null
            'price_max'        => null, // float|int|null
            // Simple stock threshold (if provided, lightly boost above this)
            'min_stock'        => null, // int|null
            // Scoring weights (advanced)
            'weights' => [
                'sale_bonus'         => 15.0,
                'oos_penalty'        => -25.0,
                'category_boost'     => 8.0,
                'category_penalty'   => -8.0,
                'price_outside_pen'  => -12.0,
                'stock_bonus'        => 6.0,
                'explicit_demote'    => -30.0,
                'explicit_pin'       => 1000.0,
            ],
            // How many items to return in preview (UI tester)
            'preview_limit'    => 20,
        ];
    }

    /**
     * Router action entrypoint. Payload shape:
     * {
     *   results: [ { id, title, type, price, regular_price, sale_price, stock_status, categories:[], ... } ],
     *   config: { ...overrides... }
     * }
     */
    public static function action(array $payload): array
    {
        $results = isset($payload['results']) && is_array($payload['results']) ? $payload['results'] : [];
        $config  = array_replace_recursive(self::defaultConfig(), is_array($payload['config'] ?? null) ? $payload['config'] : []);

        [$norm, $meta] = self::normalizeResults($results);
        if (empty($norm)) {
            return [
                'ok' => false,
                'action' => 'merchandising_plan',
                'error' => 'no_results',
                'meta'  => $meta,
            ];
        }

        $plan = [
            'pins'   => array_values(array_unique(array_map('intval', $config['pin_ids'] ?? []))),
            'demote' => array_values(array_unique(array_map('intval', $config['demote_ids'] ?? []))),
            'boost_categories'  => array_values($config['boost_categories'] ?? []),
            'demote_categories' => array_values($config['demote_categories'] ?? []),
            'sale_first'  => (bool) $config['sale_first'],
            'demote_oos'  => (bool) $config['demote_oos'],
            'price_min'   => self::numOrNull($config['price_min']),
            'price_max'   => self::numOrNull($config['price_max']),
            'min_stock'   => isset($config['min_stock']) ? (int) $config['min_stock'] : null,
        ];

        $scored = self::scoreAndOrder($norm, $plan, $config['weights']);
        $preview = array_slice($scored, 0, (int) $config['preview_limit']);

        return [
            'ok'     => true,
            'action' => 'merchandising_plan',
            'result' => [
                'plan'    => $plan,
                'ordered' => $preview,
                'total'   => count($scored),
            ],
            'meta'   => $meta,
        ];
    }

    /**
     * Normalize incoming results to a consistent shape.
     * Returns [array $items, array $meta].
     */
    protected static function normalizeResults(array $results): array
    {
        $out  = [];
        $meta = [ 'seen_fields' => [] ];
        foreach ($results as $r) {
            $id   = isset($r['id']) ? (int) $r['id'] : null;
            $type = isset($r['type']) ? (string) $r['type'] : '';
            if (!$id) { continue; }

            $price = self::toNumber($r['price'] ?? ($r['sale_price'] ?? ($r['regular_price'] ?? null)));
            $cats  = [];
            if (isset($r['categories'])) {
                if (is_array($r['categories'])) { $cats = array_values(array_filter(array_map('strval', $r['categories']))); }
                elseif (is_string($r['categories'])) { $cats = array_values(array_filter(array_map('trim', explode(',', $r['categories'])))); }
            }
            $stockStatus = isset($r['stock_status']) ? (string) $r['stock_status'] : '';
            $stockQty    = isset($r['stock_quantity']) ? (int) $r['stock_quantity'] : null;
            $onSale      = self::toNumber($r['sale_price'] ?? null) !== null && self::toNumber($r['regular_price'] ?? null) !== null && (float)$r['sale_price'] < (float)($r['regular_price']);

            $item = [
                'id'            => $id,
                'title'         => isset($r['title']) ? (string) $r['title'] : '',
                'type'          => $type,
                'price'         => $price,
                'regular_price' => self::toNumber($r['regular_price'] ?? null),
                'sale_price'    => self::toNumber($r['sale_price'] ?? null),
                'on_sale'       => (bool) $onSale,
                'stock_status'  => $stockStatus,
                'stock_qty'     => $stockQty,
                'categories'    => $cats,
                'url'           => isset($r['url']) ? (string) $r['url'] : '',
                'thumbnail'     => isset($r['thumbnail']) ? (string) $r['thumbnail'] : '',
            ];
            $meta['seen_fields'] = array_unique(array_merge($meta['seen_fields'], array_keys($r)));
            $out[] = $item;
        }
        return [$out, $meta];
    }

    /**
     * Score and order items according to plan and weights.
     */
    protected static function scoreAndOrder(array $items, array $plan, array $weights): array
    {
        $pins   = array_map('intval', $plan['pins'] ?? []);
        $demote = array_map('intval', $plan['demote'] ?? []);
        $boostC = array_map('strval', $plan['boost_categories'] ?? []);
        $penC   = array_map('strval', $plan['demote_categories'] ?? []);

        $scored = [];
        foreach ($items as $it) {
            $score = 0.0;

            // Explicit pin/demote
            if (in_array($it['id'], $pins, true)) {
                $score += (float)$weights['explicit_pin'];
            }
            if (in_array($it['id'], $demote, true)) {
                $score += (float)$weights['explicit_demote'];
            }

            // Sale bonus
            if (!empty($plan['sale_first']) && !empty($it['on_sale'])) {
                $score += (float)$weights['sale_bonus'];
            }

            // OOS penalty
            if (!empty($plan['demote_oos']) && isset($it['stock_status']) && $it['stock_status'] === 'outofstock') {
                $score += (float)$weights['oos_penalty'];
            }

            // Category boosts/penalties
            if (!empty($it['categories'])) {
                foreach ($it['categories'] as $c) {
                    if (in_array($c, $boostC, true)) { $score += (float)$weights['category_boost']; }
                    if (in_array($c, $penC, true))   { $score += (float)$weights['category_penalty']; }
                }
            }

            // Price window penalty
            $p = $it['price'];
            if ($p !== null) {
                if ($plan['price_min'] !== null && $p < (float)$plan['price_min']) {
                    $score += (float)$weights['price_outside_pen'];
                }
                if ($plan['price_max'] !== null && $p > (float)$plan['price_max']) {
                    $score += (float)$weights['price_outside_pen'];
                }
            }

            // Stock bonus above threshold
            if ($plan['min_stock'] !== null && isset($it['stock_qty']) && is_int($it['stock_qty']) && $it['stock_qty'] >= (int)$plan['min_stock']) {
                $score += (float)$weights['stock_bonus'];
            }

            $it['_score'] = $score;
            $scored[] = $it;
        }

        // Sort by score desc, then keep original relative order (stable)
        usort($scored, function($a, $b){
            if ($a['_score'] === $b['_score']) { return 0; }
            return ($a['_score'] > $b['_score']) ? -1 : 1;
        });

        // Ensure hard pins stay at the very top preserving user order
        if (!empty($pins)) {
            $byId = [];
            foreach ($scored as $row) { $byId[$row['id']] = $row; }
            $top = [];
            foreach ($pins as $pid) {
                if (isset($byId[$pid])) { $top[] = $byId[$pid]; unset($byId[$pid]); }
            }
            $scored = array_values(array_merge($top, array_values($byId)));
        }

        return $scored;
    }

    protected static function toNumber($v): ?float
    {
        if ($v === null || $v === '') { return null; }
        if (is_numeric($v)) { return (float)$v; }
        // strip non-numeric
        $f = preg_replace('/[^0-9\.-]/', '', (string)$v);
        return ($f === '' || !is_numeric($f)) ? null : (float)$f;
    }

    protected static function numOrNull($v): ?float
    {
        return is_numeric($v) ? (float)$v : null;
    }
}

/**
 * Register this agent with the AI Router and AgentRegistry via filters.
 */
add_filter('searchjet/ai/actions', function(array $actions){
    $actions[] = [
        'id'       => 'merchandising_plan',
        'label'    => __('Merchandising Plan', 'searchjet-instant-search'),
        'callback' => [MerchandisingAgent::class, 'action'],
    ];
    return $actions;
});

add_filter('searchjet/ai/agents', function(array $agents){
    $agents[] = [
        'id'          => 'merchandising_plan',
        'name'        => __('Merchandising Planner', 'searchjet-instant-search'),
        'description' => __('Reorder search results with pins, boosts, price windows, and stock rules.', 'searchjet-instant-search'),
        'action'      => 'merchandising_plan',
        'defaults'    => MerchandisingAgent::defaultConfig(),
    ];
    return $agents;
});
