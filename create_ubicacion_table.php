<?php
// Script para crear tabla `ubicacion` e insertar valores iniciales.
// Ejecútalo con: php create_ubicacion_table.php

$config = require __DIR__ . '/conf.php';
$db = $config['db'];

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $db['host'],
    $db['name'],
    $db['charset']
);

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], $options);
} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

$sql = "CREATE TABLE IF NOT EXISTS `ubicacion` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(255) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$pdo->exec($sql);

$ubicaciones = [
    'Finca Central',
    'Potrero Norte',
    'Establo 3'
];

$insert = $pdo->prepare('INSERT IGNORE INTO ubicacion (nombre) VALUES (:nombre)');
foreach ($ubicaciones as $u) {
    $insert->execute(['nombre' => $u]);
}

echo "Tabla 'ubicacion' creada (si no existía) y ubicaciones insertadas (si no existían).\n";
