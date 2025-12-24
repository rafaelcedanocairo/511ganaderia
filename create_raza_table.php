<?php
// Script para crear tabla `raza` e insertar valores iniciales.
// Ejecútalo con: php create_raza_table.php

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

$sql = "CREATE TABLE IF NOT EXISTS `raza` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(255) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$pdo->exec($sql);

$razas = [
    'Simmental',
    'Symbrah',
    'Gyr',
    'Guzerat',
    'Brahman',
    'Brangus',
    'Pardo Suizo',
    'Holstein',
    'Jersey',
    'Gyrolando',
    '7 Colores'
];

$insert = $pdo->prepare('INSERT IGNORE INTO raza (nombre) VALUES (:nombre)');
foreach ($razas as $r) {
    $insert->execute(['nombre' => $r]);
}

echo "Tabla 'raza' creada (si no existía) y razas insertadas (si no existían).\n";
