<?php

namespace SearchJet\Automations;

class Schedules
{
    const CRON_HOOK = 'searchjet_daily_automations';

    public static function init()
    {
        add_filter('cron_schedules', [self::class, 'addSchedules']);
        add_action('init', [self::class, 'ensureScheduled']);
        add_action(self::CRON_HOOK, [self::class, 'runDaily']);
    }

    public static function addSchedules($schedules)
    {
        $schedules['searchjet_daily'] = [
            'interval' => 86400,
            'display'  => __('Once Daily at 3:15am'),
        ];
        return $schedules;
    }

    public static function ensureScheduled()
    {
        $enabled = get_option('searchjet_automations_enabled', false);
        if ($enabled) {
            if (!wp_next_scheduled(self::CRON_HOOK)) {
                wp_schedule_event(self::nextDailyTimestamp(), 'searchjet_daily', self::CRON_HOOK);
            }
        } else {
            $timestamp = wp_next_scheduled(self::CRON_HOOK);
            if ($timestamp) {
                wp_unschedule_event($timestamp, self::CRON_HOOK);
            }
        }
    }

    public static function runDaily()
    {
        // Placeholder for automation runner logic.
    }

    public static function nextDailyTimestamp()
    {
        $timestamp = strtotime('today 3:15am');
        if ($timestamp <= time()) {
            $timestamp = strtotime('tomorrow 3:15am');
        }
        return $timestamp;
    }
}
