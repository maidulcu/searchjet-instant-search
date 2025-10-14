<?php
/**
 * SearchJet Automations - Events (v1.1.0)
 *
 * Normalizes internal signals into automation-ready payloads, provides
 * a small rolling cache for burst detection, and dispatches webhooks.
 */

namespace SearchJet\Automations;

if (!defined('ABSPATH')) {
    exit;
}

final class Events
{
    /** Rolling window (seconds) for zero-result burst detection (default 10m) */
    private const ZR_DEFAULT_WINDOW    = 600;
    /** Number of zero-result hits in window to trigger burst (default 5) */
    private const ZR_DEFAULT_THRESHOLD = 5;
    /** Cooldown to avoid duplicate bursts (seconds) */
    private const ZR_COOLDOWN          = 300;

    /** Transient keys */
    private const ZR_ROLLUP_KEY        = 'searchjet_zr_rollup';
    private const ZR_LAST_DISPATCH_KEY = 'searchjet_zr_last_dispatch';

    /**
     * Initialize internal state if needed.
     * Intentionally minimal for v1.1.0.
     */
    public static function init(): void
    {
        // Reserved for future use (e.g., warm caches, preload configs).
    }

    /**
     * Handle "index completed" summary and dispatch index.summary webhook.
     *
     * @param array $summary Expected keys: added, updated, skipped, duration(optional), source(optional)
     */
    public static function indexCompleted(array $summary): void
    {
        try {
            $added   = isset($summary['added'])   ? (int) $summary['added']   : 0;
            $updated = isset($summary['updated']) ? (int) $summary['updated'] : 0;
            $skipped = isset($summary['skipped']) ? (int) $summary['skipped'] : 0;

            $payload = DataContracts::indexSummary($added, $updated, $skipped);
            // Add optional metadata inside payload if present.
            if (!empty($summary['duration']) || !empty($summary['source'])) {
                $payload['payload']['meta'] = [
                    'duration' => $summary['duration'] ?? null,
                    'source'   => $summary['source']   ?? null,
                ];
            }

            $res = Webhooks::dispatch('index.summary', $payload);

            Logger::log('info', 'index_completed dispatched', [
                'summary'  => $summary,
                'dispatch' => $res,
            ]);
        } catch (\Throwable $e) {
            Logger::log('error', 'index_completed failed', [
                'error'   => $e->getMessage(),
                'summary' => $summary,
            ]);
        }
    }

    /**
     * Record a zero-result query (global burst logic across *all* queries).
     * Triggers a webhook when ≥ N events occur within M minutes.
     *
     * @param string $query The user query string
     * @param array  $meta  Optional diagnostics (ua, ref, ip, total, etc.)
     */
    public static function recordZeroResult(string $query, array $meta = []): void
    {
        $q = trim(mb_strtolower($query));
        if ($q === '') {
            return;
        }

        // Read runtime-configurable window/threshold (with sane defaults)
        $window    = (int) get_option('searchjet_zr_window', self::ZR_DEFAULT_WINDOW);
        if ($window <= 0) { $window = self::ZR_DEFAULT_WINDOW; }
        $threshold = (int) get_option('searchjet_zr_threshold', self::ZR_DEFAULT_THRESHOLD);
        if ($threshold <= 0) { $threshold = self::ZR_DEFAULT_THRESHOLD; }

        $now = time();

        // Load existing rollup (array of ['ts'=>int,'q'=>string])
        $rollup = get_transient(self::ZR_ROLLUP_KEY);
        if (!is_array($rollup)) {
            $rollup = [];
        }

        // Prune outside window
        $rollup = array_values(array_filter($rollup, static function ($row) use ($now, $window) {
            return is_array($row) && isset($row['ts']) && ($now - (int) $row['ts']) <= $window;
        }));

        // Append this event (cap to last 200 to keep memory small)
        $rollup[] = [
            'ts' => $now,
            'q'  => mb_substr($q, 0, 200),
        ];
        if (count($rollup) > 200) {
            $rollup = array_slice($rollup, -200);
        }

        // Persist with window-based TTL
        set_transient(self::ZR_ROLLUP_KEY, $rollup, $window);

        // Count + sample top queries
        $total   = count($rollup);
        $freq    = [];
        foreach ($rollup as $row) {
            $k = $row['q'];
            $freq[$k] = ($freq[$k] ?? 0) + 1;
        }
        arsort($freq);
        $samples = [];
        foreach (array_slice($freq, 0, 5, true) as $k => $c) {
            $samples[] = ['query' => $k, 'count' => (int) $c];
        }

        // Only dispatch when we cross the threshold AND not in cooldown
        if ($total >= $threshold) {
            $last = (int) get_transient(self::ZR_LAST_DISPATCH_KEY);
            if ($last === 0 || ($now - $last) >= self::ZR_COOLDOWN) {
                try {
                    $payload = DataContracts::zeroResultEvent('*', $total, $samples);
                    // Attach diagnostic metadata if present
                    if (!empty($meta)) {
                        $payload['payload']['meta'] = $meta;
                    }
                    $payload['payload']['window']    = $window;
                    $payload['payload']['threshold'] = $threshold;
                    $payload['payload']['unique_queries'] = count($freq);

                    $res = Webhooks::dispatch('search.zero_result_burst', $payload);

                    Logger::log('warning', 'search.zero_result_burst dispatched', [
                        'total'    => $total,
                        'samples'  => $samples,
                        'dispatch' => $res,
                    ]);
                } catch (\Throwable $e) {
                    Logger::log('error', 'search.zero_result_burst failed', [
                        'error' => $e->getMessage(),
                        'total' => $total,
                    ]);
                } finally {
                    // Cooldown to prevent flapping
                    set_transient(self::ZR_LAST_DISPATCH_KEY, $now, self::ZR_COOLDOWN);
                }
            }
        }
    }

    /**
     * @deprecated Use recordZeroResult() instead. Kept for backward compatibility.
     */
    public static function trackZeroResult(string $query): void
    {
        self::recordZeroResult($query);
    }

    /**
     * Daily health snapshot helper. Sends health.daily_snapshot webhook.
     *
     * @return array Result of webhook dispatch.
     */
    public static function dailyHealthSnapshot(): array
    {
        $snapshot = [
            'total_searches' => null,
            'zero_results'   => null,
            'top_queries'    => [],
        ];

        $payload = DataContracts::searchSnapshot($snapshot);
        $res     = Webhooks::dispatch('health.daily_snapshot', $payload);

        Logger::log('info', 'daily_health_snapshot dispatched', [
            'dispatch' => $res,
        ]);

        return $res;
    }
}