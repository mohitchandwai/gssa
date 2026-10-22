<?php
/**
 * PDO connection helper. Include config.php before this file.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    if (DB_DRIVER === 'sqlite') {
        $path = defined('DB_SQLITE_PATH') ? DB_SQLITE_PATH : (__DIR__ . '/../data/gsaa.sqlite');
        $dsn = 'sqlite:' . $path;
        $pdo = new PDO($dsn);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $port = defined('DB_PORT') && DB_PORT ? DB_PORT : 3306;
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, $port, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}
