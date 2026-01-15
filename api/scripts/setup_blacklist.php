<?php
require __DIR__ . '/../src/Core/Env.php';
// Autoloader manual
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
    
    // Create blacklist table
    $sql = "CREATE TABLE IF NOT EXISTS usuarios_access_token_blacklist (
        id INT AUTO_INCREMENT PRIMARY KEY,
        jti VARCHAR(36) NOT NULL,
        user_id INT NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_jti (jti),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "Table usuarios_access_token_blacklist created successfully.";

} catch (\Throwable $e) { echo $e->getMessage(); }
