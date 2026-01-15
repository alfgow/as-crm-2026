<?php
require __DIR__ . '/../src/Core/Env.php';
spl_autoload_register(function ($class) {
    // Basic autoloader
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
    
    // Search for potential "N/A" records
    $sql = "SELECT id, nombre_inquilino, email FROM inquilinos 
            WHERE nombre_inquilino LIKE '%No aplica%' 
               OR nombre_inquilino LIKE '%N/A%'
               OR nombre_inquilino LIKE '%General%'
               OR nombre_inquilino LIKE '%Sin%'";
    
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($rows) . " matches:\n";
    foreach ($rows as $r) {
        echo "ID: " . $r['id'] . " | Name: " . $r['nombre_inquilino'] . " | Email: " . $r['email'] . "\n";
    }

} catch (\Throwable $e) { echo $e->getMessage(); }
