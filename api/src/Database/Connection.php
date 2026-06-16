<?php

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

class Connection
{
    private static ?PDO $instance = null;

    private function __construct()
    {
    }

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $port = $_ENV['DB_PORT'] ?? '5432';
            $db = $_ENV['DB_DATABASE'] ?? 'oms';
            $user = $_ENV['DB_USERNAME'] ?? 'user';
            $pass = $_ENV['DB_PASSWORD'] ?? 'secret';

            try {
                self::$instance = new PDO(
                    "pgsql:host={$host};port={$port};dbname={$db}",
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );
            } catch (PDOException $e) {
                throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
            }
        }

        return self::$instance;
    }
}
