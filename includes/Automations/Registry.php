<?php

namespace SearchJet\Automations;

class Registry
{
    /**
     * @var array<string, array>
     */
    protected static $items = [];

    /**
     * Register an automation definition.
     *
     * @param array $definition
     * @return void
     */
    public static function register(array $definition): void
    {
        if (isset($definition['id'])) {
            self::$items[$definition['id']] = $definition;
        }
    }

    /**
     * Get all registered automation definitions.
     *
     * @return array
     */
    public static function all(): array
    {
        return array_values(self::$items);
    }

    /**
     * Get a specific automation definition by ID.
     *
     * @param string $id
     * @return array|null
     */
    public static function get(string $id): ?array
    {
        return self::$items[$id] ?? null;
    }

    /**
     * Initialize built-in automations.
     *
     * @return void
     */
    public static function init(): void
    {
        // Only register once
        if (!empty(self::$items)) {
            return;
        }
        self::register([
            'id' => 'daily_health_snapshot',
            'label' => 'Daily Health Snapshot',
            'description' => 'Sends a daily summary of search health metrics.',
            'callback' => 'SearchJet\Automations\Events::dailyHealthSnapshot',
        ]);
        self::register([
            'id' => 'zero_result_burst',
            'label' => 'Zero Result Burst',
            'description' => 'Triggers when a burst of zero-result searches is detected.',
            'callback' => 'SearchJet\Automations\Events::zeroResultBurst',
        ]);
        self::register([
            'id' => 'index_summary',
            'label' => 'Index Summary',
            'description' => 'Provides a summary of the search index.',
            'callback' => 'SearchJet\Automations\Events::indexSummary',
        ]);
    }
}
