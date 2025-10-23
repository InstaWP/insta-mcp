<?php

namespace InstaWP\MCP\PHP\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Simple WordPress Logger
 *
 * Logs to WordPress debug.log using error_log()
 */
class WordPressLogger extends AbstractLogger
{
    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = []): void
    {
        // Format the log message
        $logMessage = sprintf(
            '[%s] %s: %s',
            strtoupper($level),
            'InstaMCP',
            $message
        );

        // Add context if provided
        if (!empty($context)) {
            $logMessage .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }

        // Log to WordPress debug.log
        error_log($logMessage);
    }
}
