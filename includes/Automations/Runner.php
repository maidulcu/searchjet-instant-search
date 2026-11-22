<?php
/**
 * SearchJet – Automations Daily Runner
 */

namespace SearchJet\Automations;

if (!defined('ABSPATH')) {
    exit;
}

class Runner
{
    /**
     * Hook names
     */
    const CRON_HOOK = 'searchjet_daily_automations';

    /**
     * Boot the runner: schedule cron and register callbacks.
     */
    public static function init(): void
    {
        // Register our custom schedule (once daily).
        add_filter('cron_schedules', [self::class, 'addDailySchedule']);

        // Ensure the event is scheduled.
        add_action('init', [self::class, 'ensureScheduled']);

        // Handle the cron event.
        add_action(self::CRON_HOOK, [self::class, 'runDaily']);

        // Allow manual trigger from admin (for testing).
        add_action('admin_post_searchjet_run_automations_now', [self::class, 'handleManualRun']);
    }

    /**
     * Add a once-daily schedule if missing.
     */
    public static function addDailySchedule(array $schedules): array
    {
        if (!isset($schedules['searchjet_daily'])) {
            $schedules['searchjet_daily'] = [
                'interval' => \DAY_IN_SECONDS,
                'display'  => __('SearchJet – Daily', 'searchjet-instant-search'),
            ];
        }
        return $schedules;
    }

    /**
     * Make sure our cron event is scheduled when automations are enabled.
     */
    public static function ensureScheduled(): void
    {
        if (defined('SEARCHJET_DISABLE_AUTOMATIONS') && SEARCHJET_DISABLE_AUTOMATIONS) {
            self::clearSchedule();
            return;
        }
        $enabled = (int) get_option('searchjet_automations_enabled', 0);
        if ($enabled !== 1) {
            self::clearSchedule();
            return;
        }
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            // Schedule to run at next 3:15am site time to avoid peak hours.
            $ts = self::nextTime(3, 15);
            wp_schedule_event($ts, 'searchjet_daily', self::CRON_HOOK);
        }
    }

    /**
     * Clear any scheduled events for this hook.
     */
    public static function clearSchedule(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Calculate next timestamp for a given hour/minute today or tomorrow.
     */
    protected static function nextTime(int $hour, int $minute): int
    {
        $now     = current_time('timestamp');
        $today   = gmdate('Y-m-d', $now + (get_option('gmt_offset') * HOUR_IN_SECONDS));
        $target  = strtotime("$today $hour:$minute:00") - (get_option('gmt_offset') * \HOUR_IN_SECONDS);
        if ($target <= $now) {
            $target += \DAY_IN_SECONDS;
        }
        return $target;
    }

    /**
     * Cron entrypoint – runs the enabled daily automations.
     */
    public static function runDaily(): void
    {
        if (defined('SEARCHJET_DISABLE_AUTOMATIONS') && SEARCHJET_DISABLE_AUTOMATIONS) {
            return;
        }
        $enabled = (int) get_option('searchjet_automations_enabled', 0);
        if ($enabled !== 1) {
            return;
        }

        // Ensure registry and events are ready.
        if (class_exists(Registry::class)) {
            Registry::init();
        }
        if (class_exists(Events::class)) {
            Events::init();
        }

        $filters = get_option('searchjet_automation_filters', null);
        $filters = is_array($filters) ? $filters : null; // null => default enable all

        // Run Daily Health Snapshot
        if (self::isAllowed('daily_health_snapshot', $filters) && method_exists(Events::class, 'dailyHealthSnapshot')) {
            try {
                Events::dailyHealthSnapshot();
            } catch (\Throwable $e) {
                if (class_exists(Logger::class)) {
                    Logger::log('error', 'dailyHealthSnapshot failed', ['e' => $e->getMessage()]);
                }
            }
        }

        // Run Index Summary
        if (self::isAllowed('index_summary', $filters) && method_exists(Events::class, 'indexCompleted')) {
            try {
                // Get document count for the summary
                $doc_count = (int) get_option('searchjet_document_count', 0);
                Events::indexCompleted([
                    'added'   => $doc_count,
                    'updated' => 0,
                    'skipped' => 0,
                    'source'  => 'daily_cron',
                ]);
            } catch (\Throwable $e) {
                if (class_exists(Logger::class)) {
                    Logger::log('error', 'indexCompleted failed', ['e' => $e->getMessage()]);
                }
            }
        }
    }

    /**
     * Manual runner (Admin → Send test / Run now button)
     */
    public static function handleManualRun(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'searchjet-instant-search'));
        }
        check_admin_referer('searchjet_run_automations');
        self::runDaily();
        wp_safe_redirect(add_query_arg(['page' => 'searchjet-automations', 'ran' => '1'], admin_url('admin.php')));
        exit;
    }

    /**
     * Check if an automation id is allowed by the saved filters.
     *  - $filters === null  => treat as "all allowed" (never saved state)
     *  - empty array        => none allowed
     */
    protected static function isAllowed(string $id, ?array $filters): bool
    {
        if ($filters === null) {
            return true;
        }
        return in_array(sanitize_key($id), array_map('sanitize_key', $filters), true);
    }
}

// Auto‑boot when loaded.
Runner::init();