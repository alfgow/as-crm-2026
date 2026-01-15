<?php
// scripts/inspect_arrendadores.php
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
    $stmt = $pdo->query("DESCRIBE arrendadores");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $out = "";
    foreach ($columns as $col) {
        $out .= $col['Field'] . " (Type: " . $col['Type'] . ", Null: " . $col['Null'] . ", Default: " . ($col['Default']??'NULL') . ", Extra: " . $col['Extra'] . ")\n";
    }
    file_put_contents('schema_arrendadores.txt', $out);
    echo "Dumped to schema_arrendadores.txt";

} catch (\Throwable $e) { echo $e->getMessage(); }
