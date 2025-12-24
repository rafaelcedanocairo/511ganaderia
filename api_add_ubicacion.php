<?php
header('Content-Type: application/json; charset=utf-8');
try {
    $config = require __DIR__ . '/conf.php';
    $db = $config['db'];

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $db['name'], $db['charset']);
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    $pdo = new PDO($dsn, $db['user'], $db['pass'], $options);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'DB connection error']);
    exit;
}

$nombre = isset($_POST['nombre']) ? trim((string)$_POST['nombre']) : '';
if ($nombre === '') {
    echo json_encode(['success' => false, 'error' => 'Nombre vacío']);
    exit;
}

try {
    $ins = $pdo->prepare('INSERT IGNORE INTO ubicacion (nombre) VALUES (:nombre)');
    $ins->execute(['nombre' => $nombre]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'No se pudo insertar (tabla ausente?)']);
    exit;
}

try {
    $stmt = $pdo->query('SELECT nombre FROM ubicacion ORDER BY nombre');
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $rows = [];
}

echo json_encode(['success' => true, 'ubicaciones' => $rows]);
