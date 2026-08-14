<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * [PHP Pro] Flash Message Helper
 * Handles temporary session-based messages (Success/Error/Warning).
 */
final class Flash
{
    private const SESSION_KEY = '_flash_messages';

    private static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Set a flash message
     */
    public static function set(string $type, string $message): void
    {
        self::start();
        $_SESSION[self::SESSION_KEY][$type] = $message;
    }

    public static function success(string $message): void
    {
        self::set('success', $message);
    }

    public static function error(string $message): void
    {
        self::set('danger', $message);
    }

    /**
     * Get and clear all flash messages
     * 
     * @return array<string, string>
     */
    public static function getMessages(): array
    {
        self::start();
        $messages = $_SESSION[self::SESSION_KEY] ?? [];
        unset($_SESSION[self::SESSION_KEY]);
        return $messages;
    }

    /**
     * Check if there are messages
     */
    public static function hasMessages(): bool
    {
        self::start();
        return !empty($_SESSION[self::SESSION_KEY]);
    }

    /**
     * Render messages as Bootstrap Alerts
     */
    public static function render(): void
    {
        $messages = self::getMessages();
        foreach ($messages as $type => $message) {
            echo '<div class="alert alert-' . $type . ' alert-dismissible fade show shadow-sm border-0" role="alert" style="border-radius:15px;">
                    <i class="bi ' . ($type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill') . ' me-2"></i>
                    ' . htmlspecialchars($message) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
    }
}
