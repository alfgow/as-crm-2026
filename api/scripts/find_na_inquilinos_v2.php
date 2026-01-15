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
    
    // Search stricter
    $sql = "SELECT id, nombre_inquilino FROM inquilinos 
            WHERE nombre_inquilino LIKE '%Aplica%' 
            LIMIT 10";
    
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "--- Search 'Aplica' ---\n";
    foreach ($rows as $r) {
        echo "ID: " . $r['id'] . " | Name: " . $r['nombre_inquilino'] . "\n";
    }

    // Check ID 0?
    $stmt0 = $pdo->query("SELECT id FROM inquilinos WHERE id = 0");
    $row0 = $stmt0->fetch();
    echo "--- ID 0 Exists? ---\n";
    var_dump($row0);

} catch (\Throwable $e) { echo $e->getMessage(); }
