<?php
// scripts/simulate_production.php

// 1. Simular entorno de Producción (IONOS)
// En prod, el DocumentRoot es .../public, por lo tanto el script es /index.php
// y la URL solicitada NO tiene /as-crm-2026/api/public

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/v1/health'; 
$_SERVER['SCRIPT_NAME'] = '/index.php'; // Esto es clave: Apache ve index.php en la raiz del dominio
$_SERVER['HTTP_HOST'] = 'crm.arrendamientoseguro.app';

// Resetear variables que podrían interferir en CLI
unset($_SERVER['argv']);
unset($_SERVER['argc']);

echo "--- Iniciando Simulación de Producción ---\n";
echo "URL Simulada: https://crm.arrendamientoseguro.app/api/v1/health\n";
echo "Contexto: DocumentRoot apuntando a /public\n\n";

// 2. Cargar la App (igual que index.php)
require __DIR__ . '/../src/Core/Env.php';

// Autoplader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

use App\Core\App;
use App\Core\Request;
use App\Core\Response;

// Cargar Env
\App\Core\Env::load(__DIR__ . '/../.env');
// Forzar entorno prod para la prueba si se quiere, o dejar local
// putenv('APP_ENV=production'); 

$config = require __DIR__ . '/../config/config.php';

// 3. Ejecutar
$request = new Request();
$response = new Response();

// Mockear la salida json para verla en terminal
// (Response class hace echo, capturamos con buffer si queremos, o dejamos salir)

try {
    $app = new App($config);
    $app->handle($request, $response);
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage();
}
echo "\n--- Fin de Simulación ---\n";
