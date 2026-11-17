<?php

declare(strict_types=1);

namespace SmartSave;

use PDO;

// Verbindung zur SQLite-Datenbank (eine Datei, kein Server noetig) und Tabellenaufbau.
final class Database
{
    public static function defaultPath(): string
    {
        return __DIR__ . '/../data/smartsave.sqlite';
    }

    /** @param string $path Dateipfad oder ':memory:' (fuer Tests) */
    public static function connect(string $path): PDO
    {
        if ($path !== ':memory:') {
            $directory = dirname($path);
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
        }

        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        self::createSchema($pdo);

        return $pdo;
    }

    public static function createSchema(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                price_per_kwh REAL NOT NULL,
                old_monthly_cost REAL NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                months_elapsed INTEGER NOT NULL DEFAULT 0
            )'
        );
        self::addMissingColumns($pdo, 'users', ['months_elapsed' => 'INTEGER NOT NULL DEFAULT 0']);
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS bookings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                product TEXT NOT NULL,
                start_month INTEGER NOT NULL,
                monthly_price REAL NOT NULL
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_bookings_user ON bookings(user_id)');
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS rooms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                name TEXT NOT NULL,
                width INTEGER NOT NULL DEFAULT 1
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rooms_user ON rooms(user_id)');
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS devices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                room_id INTEGER NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
                name TEXT NOT NULL,
                wattage REAL NOT NULL,
                hours_per_day REAL NOT NULL,
                is_on INTEGER NOT NULL DEFAULT 1,
                category TEXT NOT NULL DEFAULT \'sonstiges\',
                off_time TEXT,
                on_time TEXT
            )'
        );
        self::addMissingColumns($pdo, 'devices', [
            'category' => "TEXT NOT NULL DEFAULT 'sonstiges'",
            'off_time' => 'TEXT',
            'on_time' => 'TEXT',
        ]);
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_devices_user ON devices(user_id)');
    }

    /**
     * Aeltere Datenbanken bekommen neue Spalten nachtraeglich. Tabellen- und Spaltennamen
     * stehen fest im Code, nie aus Nutzereingaben.
     *
     * @param array<string, string> $columns Spaltenname => Definition
     */
    private static function addMissingColumns(PDO $pdo, string $table, array $columns): void
    {
        $existing = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
        foreach ($columns as $name => $definition) {
            if (!in_array($name, $existing, true)) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $name $definition");
            }
        }
    }
}
