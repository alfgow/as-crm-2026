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
    
    $ids = [40, 292];
    $placeholders = implode(',', $ids);
    
    $sql = "SELECT id, nombre_inquilino, tipo, email FROM inquilinos WHERE id IN ($placeholders)";
    
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rows as $r) {
        echo "ID: " . $r['id'] . " | Name: " . $r['nombre_inquilino'] . " | Tipo: " . $r['tipo'] . "\n";
    }

} catch (\Throwable $e) { echo $e->getMessage(); }
