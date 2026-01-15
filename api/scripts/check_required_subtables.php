<?php
require __DIR__ . '/../src/Core/Env.php';

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
    
    $tables = ['inquilinos_direccion', 'inquilinos_trabajo'];
    
    foreach ($tables as $table) {
        echo "--- $table ---\n";
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($columns as $col) {
            if ($col['Null'] === 'NO' && $col['Default'] === null && $col['Extra'] !== 'auto_increment') {
                echo "REQUIRED: " . $col['Field'] . "\n";
            }
        }
        echo "\n";
    }
} catch (\Throwable $e) { echo $e->getMessage(); }
