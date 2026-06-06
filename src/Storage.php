<?php
namespace JarirAhmed\ServerStats;

use PDO;

class Storage {
    private $pdo;

    public function __construct() {
        $host = '127.0.0.1';
        $port = '3307';
        $db   = 'server_stats';
        $user = 'root';
        $pass = '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;port=$port;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
            $this->pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` ");
            $this->pdo->exec("USE `$db` ");
            $this->createTables();
        } catch (\PDOException $e) {
            // Fallback to SQLite if MySQL fails
            $this->pdo = new PDO('sqlite:' . __DIR__ . '/../stats.db');
            $this->createTables();
        }
    }

    private function createTables() {
        try {
            // Try MySQL syntax first
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS metrics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                value FLOAT NOT NULL,
                labels JSON DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;");
        } catch (\PDOException $e) {
            // Fallback to SQLite syntax
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS metrics (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                value REAL NOT NULL,
                labels TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );");
        }
    }

    public function save(string $name, float $value, array $labels = []) {
        $stmt = $this->pdo->prepare("INSERT INTO metrics (name, value, labels) VALUES (?, ?, ?)");
        $stmt->execute([$name, $value, json_encode($labels)]);
    }

    public function getLatest() {
        $stmt = $this->pdo->query("SELECT * FROM metrics ORDER BY created_at DESC LIMIT 50");
        return $stmt->fetchAll();
    }
}
