<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/conf.php';
$cfg = $config = require __DIR__ . '/conf.php';
$db = $cfg['db'];
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $db['name'], $db['charset']);
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
try { $pdo = new PDO($dsn, $db['user'], $db['pass'], $options); } catch (Throwable $e) { echo json_encode(['success'=>false,'error'=>'DB connection error']); exit; }

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$tipo = $_POST['tipo'] ?? '';
$fecha = $_POST['fecha'] ?? '';
$comentario = $_POST['comentario'] ?? null;

if (!$id) { echo json_encode(['success'=>false,'error'=>'ID inválido']); exit; }
try {
    $stmt = $pdo->prepare('UPDATE events SET tipo = :tipo, fecha = :fecha, comentario = :comentario WHERE id = :id');
    $stmt->execute(['tipo' => $tipo, 'fecha' => $fecha ?: null, 'comentario' => $comentario, 'id' => $id]);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
