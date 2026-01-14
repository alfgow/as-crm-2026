<?php
// scripts/inspect_one.php
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
    $stmt = $pdo->query("SELECT * FROM usuarios2 LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        print_r(array_keys($row));
    } else {
        echo "Table is empty.\n";
    }
} catch (\Throwable $e) { echo $e->getMessage(); }
