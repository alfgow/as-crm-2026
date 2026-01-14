<?php
// scripts/setup_tables.php

require __DIR__ . '/../src/Core/Env.php';
// Autoloader logic
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});
use App\Core\Database;
\App\Core\Env::load(__DIR__ . '/../.env');
$config = require __DIR__ . '/../config/config.php';

try {
    $db = new Database($config['db']);
    $pdo = $db->pdo();
    
    echo "--- Creating tables ---\n";
    
    // usuarios_refresh_tokens
    echo "Creating usuarios_refresh_tokens... ";
    $sql1 = "CREATE TABLE IF NOT EXISTS `usuarios_refresh_tokens` (
      `id` bigint unsigned NOT NULL AUTO_INCREMENT,
      `user_id` int NOT NULL,
      `jti` varchar(36) NOT NULL,
      `token_hash` varchar(255) NOT NULL,
      `expires_at` datetime NOT NULL,
      `revoked_at` datetime DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_user_jti` (`user_id`, `jti`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql1);
    echo "OK\n";

    // api_logs
    echo "Creating api_logs... ";
    $sql2 = "CREATE TABLE IF NOT EXISTS `api_logs` (
      `id` bigint unsigned NOT NULL AUTO_INCREMENT,
      `request_id` varchar(64) NOT NULL,
      `method` varchar(10) NOT NULL,
      `path` varchar(255) NOT NULL,
      `status_code` int NOT NULL,
      `ip_address` varchar(45) NOT NULL,
      `user_agent` varchar(255) NOT NULL,
      `occurred_at` datetime NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql2);
    echo "OK\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
