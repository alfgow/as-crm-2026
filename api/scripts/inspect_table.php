<?php
// scripts/inspect_table.php

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

// Cargar Env
\App\Core\Env::load(__DIR__ . '/../.env');

$config = require __DIR__ . '/../config/config.php';

try {
    $db = new Database($config['db']);
    $pdo = $db->pdo();

    echo "--- Columnas de usuarios2 ---\n";
    $stmt = $pdo->query("DESCRIBE usuarios2");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $names = array_column($columns, 'Field');
    echo "Columns: " . implode(', ', $names) . "\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
