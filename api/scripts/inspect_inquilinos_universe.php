<?php
// scripts/inspect_inquilinos_universe.php
require __DIR__ . '/../src/Core/Env.php';
spl_autoload_register(function ($class) {
    // ... basic autoloader ...
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
    
    $tables = [
        'inquilinos',
        'inquilinos_direccion',
        'inquilinos_trabajo',
        'inquilinos_fiador',
        'inquilinos_historial_vivienda',
        'inquilinos_archivos',
        'inquilinos_validaciones'
    ];

    $output = "";

    foreach ($tables as $table) {
        $output .= "--- Table: $table ---\n";
        try {
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $col) {
                $output .= $col['Field'] . " (" . $col['Type'] . ")\n";
            }
        } catch (\Throwable $t) {
            $output .= "Error describing $table: " . $t->getMessage() . "\n";
        }
        $output .= "\n";
    }
    
    file_put_contents('schema_inquilinos.txt', $output);
    echo "Dumped to schema_inquilinos.txt";

} catch (\Throwable $e) { echo $e->getMessage(); }
