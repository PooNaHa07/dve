<?php

declare(strict_types=1);

namespace App\Helpers;

use mysqli;
use mysqli_result;
use mysqli_stmt;
use Exception;

/**
 * [PHP Pro] Centralized Database Wrapper
 * Singleton pattern for managing MySQLi connection safely.
 */
final class Database
{
    private static ?mysqli $connection = null;

    /**
     * Get the database connection (Singleton)
     */
    public static function getConnection(): mysqli
    {
        if (self::$connection === null) {
            require_once __DIR__ . '/../../includes/configdb.php';
            
            /** @var mysqli $conn Variable from configdb.php */
            global $conn;

            if (!$conn instanceof mysqli) {
                throw new Exception('Database connection not initialized. Check configdb.php.');
            }

            self::$connection = $conn;
            self::$connection->set_charset('utf8mb4');
        }

        return self::$connection;
    }

    /**
     * Execute a query with prepared statements
     * 
     * @param string $sql
     * @param array<int, mixed> $params
     * @return mysqli_stmt
     */
    public static function query(string $sql, array $params = []): mysqli_stmt
    {
        $db = self::getConnection();
        $stmt = $db->prepare($sql);

        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $db->error);
        }

        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) $types .= 'i';
                elseif (is_float($param)) $types .= 'd';
                elseif (is_string($param)) $types .= 's';
                else $types .= 'b';
            }
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            throw new Exception("Execution failed: " . $stmt->error);
        }

        return $stmt;
    }

    /**
     * Fetch all rows from a query
     * 
     * @param string $sql
     * @param array<int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::query($sql, $params);
        $result = $stmt->get_result();
        /** @var array<int, array<string, mixed>> $data */
        $data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $data;
    }

    /**
     * Fetch a single row
     * 
     * @param string $sql
     * @param array<int, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);
        $result = $stmt->get_result();
        /** @var array<string, mixed>|null|false $data */
        $data = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return ($data === false) ? null : $data;
    }

    /**
     * Get the last inserted ID
     */
    public static function lastInsertId(): int|string
    {
        return self::getConnection()->insert_id;
    }
}
