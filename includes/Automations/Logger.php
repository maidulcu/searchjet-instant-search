<?php

namespace SearchJet\Automations;

class Logger
{
    public static function log($level, $message, array $context = [])
    {
        $level = strtoupper($level);
        $contextJson = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $logMessage = "[searchjet.automation] {$level}: {$message} | context({$contextJson})";
        error_log($logMessage);
    }
}
