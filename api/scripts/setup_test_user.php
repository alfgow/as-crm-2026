<?php
// scripts/setup_test_user.php

require __DIR__ . '/../src/Core/Env.php';

// Autoloader manual para scripts
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

    $email = 'test@arrendamientoseguro.app';
    $password = 'password123';
    $hash = password_hash($password, PASSWORD_BCRYPT);

    echo "--- Configurando Usuario de Prueba ---\n";
    echo "Email: $email\n";
    echo "Password: $password\n";

    // Verificar si existe
    $stmt = $pdo->prepare("SELECT id FROM usuarios2 WHERE mail_usuario = ?");
    $stmt->execute([$email]);
    $exists = $stmt->fetch();

    if ($exists) {
        // Actualizar password
        $sql = "UPDATE usuarios2 SET password = ?, nombre_usuario = 'Test', apellidos_usuario = 'User', tipo_usuario = 1, usuario = 'testuser', corto_usuario = 'TU' WHERE mail_usuario = ?";
        $pdo->prepare($sql)->execute([$hash, $email]);
        echo "Usuario actualizado correctamente.\n";
    } else {
        // Crear
        $sql = "INSERT INTO usuarios2 (nombre_usuario, apellidos_usuario, usuario, mail_usuario, password, tipo_usuario, corto_usuario) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute(['Test', 'User', 'testuser', $email, $hash, 1, 'TU']);
        echo "Usuario creado correctamente.\n";
    }

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
