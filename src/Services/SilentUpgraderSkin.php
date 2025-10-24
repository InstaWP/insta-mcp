<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Services;

/**
 * Silent Upgrader Skin
 *
 * Completely silent upgrader skin that suppresses all output
 * Used for plugin/theme installations via MCP to avoid output interference
 */
class SilentUpgraderSkin extends \WP_Upgrader_Skin
{
    /**
     * Constructor - suppress all output
     */
    public function __construct($args = [])
    {
        parent::__construct($args);

        // Mark header/footer as done to prevent output
        $this->done_header = true;
        $this->done_footer = true;
    }

    /**
     * Suppress feedback messages
     */
    public function feedback($string, ...$args): void
    {
        // Do nothing - suppress all feedback
    }

    /**
     * Suppress header output
     */
    public function header(): void
    {
        // Do nothing
    }

    /**
     * Suppress footer output
     */
    public function footer(): void
    {
        // Do nothing
    }

    /**
     * Suppress error output
     */
    public function error($errors): void
    {
        // Store errors but don't output them
        if (is_wp_error($errors)) {
            $this->errors = $errors;
        }
    }

    /**
     * Suppress before output
     */
    public function before(): void
    {
        // Do nothing
    }

    /**
     * Suppress after output
     */
    public function after(): void
    {
        // Do nothing
    }

    /**
     * Suppress decrement update count
     */
    public function decrement_update_count($type): void
    {
        // Do nothing
    }

    /**
     * Suppress bulk header
     */
    public function bulk_header(): void
    {
        // Do nothing
    }

    /**
     * Suppress bulk footer
     */
    public function bulk_footer(): void
    {
        // Do nothing
    }
}
