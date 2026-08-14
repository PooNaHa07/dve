<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * CSRF Protection Helper
 * [Security Improvement]
 */
final class CsrfHelper
{
    /**
     * Generate or retrieve a CSRF token for the current session
     */
    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Validate the provided token against the session token
     */
    public static function validate(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Output a hidden CSRF input field for forms
     */
    public static function echoInput(): void
    {
        echo '<input type="hidden" name="csrf_token" value="' . self::getToken() . '">';
    }
}
