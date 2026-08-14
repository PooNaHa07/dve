<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * [PHP Pro] Authentication & Authorization Helper
 * Centralizes login checks and role-based access control.
 */
final class Auth
{
    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Check if the user is logged in
     */
    public static function check(): bool
    {
        self::startSession();
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get the current user ID
     */
    public static function id(): ?int
    {
        self::startSession();
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    /**
     * Get the current user role
     */
    public static function role(): ?string
    {
        self::startSession();
        return $_SESSION['role'] ?? null;
    }

    /**
     * Enforce login, redirect if not authenticated
     */
    public static function protect(): void
    {
        if (!self::check()) {
            $root = (str_contains($_SERVER['PHP_SELF'], '/admin/') || 
                     str_contains($_SERVER['PHP_SELF'], '/staff/') || 
                     str_contains($_SERVER['PHP_SELF'], '/teacher/') || 
                     str_contains($_SERVER['PHP_SELF'], '/student/')) ? '../' : '';
            
            header("Location: {$root}login.php");
            exit;
        }
    }

    /**
     * Enforce specific roles
     * 
     * @param array<int, string> $allowedRoles
     */
    public static function guard(array $allowedRoles): void
    {
        self::protect();
        $currentRole = self::role();

        if ($currentRole === null || !in_array($currentRole, $allowedRoles, true)) {
            $root = (str_contains($_SERVER['PHP_SELF'], '/admin/') || 
                     str_contains($_SERVER['PHP_SELF'], '/staff/') || 
                     str_contains($_SERVER['PHP_SELF'], '/teacher/') || 
                     str_contains($_SERVER['PHP_SELF'], '/student/')) ? '../' : '';
            
            header("Location: {$root}login.php?error=unauthorized");
            exit;
        }
    }

    /**
     * Get all data stored in the current user's session
     * 
     * @return array<string, mixed>
     */
    public static function user(): array
    {
        self::startSession();
        /** @var array<string, mixed> $data */
        $data = $_SESSION;
        return $data;
    }
}
