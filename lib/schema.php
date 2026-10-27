<?php
function schema_statements(string $driver): array
{
    if ($driver === 'mysql') {
        $end = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
        return [
            "CREATE TABLE IF NOT EXISTS settings (`key` VARCHAR(100) PRIMARY KEY, value TEXT NOT NULL)" . $end,
            "CREATE TABLE IF NOT EXISTS servers (id VARCHAR(80) PRIMARY KEY, type CHAR(1) NOT NULL CHECK(type IN ('A','B')), title VARCHAR(200) NOT NULL, domain VARCHAR(2048) NOT NULL, api_path VARCHAR(256) NOT NULL DEFAULT '/bigbluebutton/api/', secret TEXT NOT NULL, login TEXT NOT NULL, password TEXT NOT NULL, enabled TINYINT NOT NULL DEFAULT 1)" . $end,
            "CREATE TABLE IF NOT EXISTS users (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(100) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, name VARCHAR(200) NOT NULL, role VARCHAR(10) NOT NULL CHECK(role IN ('admin','viewer')), enabled TINYINT NOT NULL DEFAULT 1, all_servers TINYINT NOT NULL DEFAULT 0, auth_version BIGINT NOT NULL DEFAULT 1)" . $end,
            'CREATE TABLE IF NOT EXISTS user_servers (user_id BIGINT UNSIGNED NOT NULL, server_id VARCHAR(80) NOT NULL, PRIMARY KEY(user_id,server_id), FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY(server_id) REFERENCES servers(id) ON DELETE CASCADE)' . $end,
            'CREATE TABLE IF NOT EXISTS audit (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor VARCHAR(100) NOT NULL, action VARCHAR(100) NOT NULL, target VARCHAR(200) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)' . $end,
            'CREATE TABLE IF NOT EXISTS login_attempts (bucket CHAR(64) PRIMARY KEY, attempts INT NOT NULL, expires BIGINT NOT NULL)' . $end,
            'CREATE TABLE IF NOT EXISTS app_lock (id INT PRIMARY KEY)' . $end,
            'INSERT IGNORE INTO app_lock(id) VALUES(1)',
        ];
    }
    return [
        'CREATE TABLE IF NOT EXISTS settings (`key` TEXT PRIMARY KEY, value TEXT NOT NULL)',
        "CREATE TABLE IF NOT EXISTS servers (id TEXT PRIMARY KEY, type TEXT NOT NULL CHECK(type IN ('A','B')), title TEXT NOT NULL, domain TEXT NOT NULL, api_path TEXT NOT NULL DEFAULT '/bigbluebutton/api/', secret TEXT NOT NULL DEFAULT '', login TEXT NOT NULL DEFAULT '', password TEXT NOT NULL DEFAULT '', enabled INTEGER NOT NULL DEFAULT 1)",
        "CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, name TEXT NOT NULL, role TEXT NOT NULL CHECK(role IN ('admin','viewer')), enabled INTEGER NOT NULL DEFAULT 1, all_servers INTEGER NOT NULL DEFAULT 0, auth_version INTEGER NOT NULL DEFAULT 1)",
        'CREATE TABLE IF NOT EXISTS user_servers (user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, server_id TEXT NOT NULL REFERENCES servers(id) ON DELETE CASCADE, PRIMARY KEY(user_id,server_id))',
        "CREATE TABLE IF NOT EXISTS audit (id INTEGER PRIMARY KEY, actor TEXT NOT NULL, action TEXT NOT NULL, target TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ','now')))",
        'CREATE TABLE IF NOT EXISTS login_attempts (bucket TEXT PRIMARY KEY, attempts INTEGER NOT NULL, expires INTEGER NOT NULL)',
    ];
}
