<?php

namespace SearchJet\Automations;

/**
 * Class DataContracts
 *
 * Standardizes payload formats for automations.
 */
class DataContracts
{
    public static function searchSnapshot(array $a): array
    {
        return [
            'site'    => get_site_url(),
            'event'   => 'search_snapshot',
            'payload' => $a,
            'ts'      => time(),
        ];
    }

    public static function zeroResultEvent(string $q, int $c, array $samples = []): array
    {
        return [
            'site'    => get_site_url(),
            'event'   => 'zero_result_burst',
            'payload' => [
                'query'        => $q,
                'count'        => $c,
                'sample_terms' => $samples,
            ],
            'ts'      => time(),
        ];
    }

    public static function indexSummary(int $added, int $updated, int $skipped): array
    {
        return [
            'site'    => get_site_url(),
            'event'   => 'index_summary',
            'payload' => [
                'added'   => $added,
                'updated' => $updated,
                'skipped' => $skipped,
            ],
            'ts'      => time(),
        ];
    }
}
