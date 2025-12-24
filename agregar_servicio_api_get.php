<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/conf.php';
$cfg = $config = require __DIR__ . '/conf.php';
$db = $cfg['db'];
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $db['name'], $db['charset']);
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
try { $pdo = new PDO($dsn, $db['user'], $db['pass'], $options); } catch (Throwable $e) { echo json_encode(['success'=>false,'error'=>'DB connection error']); exit; }

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) { echo json_encode(['success'=>false,'error'=>'ID inválido']); exit; }

try {
    $stmt = $pdo->prepare('SELECT e.id, e.animal_id, a.ficha_no, a.nombre, e.fecha, e.tipo, e.comentario FROM events e JOIN animals a ON a.id = e.animal_id WHERE e.id = :id');
    $stmt->execute(['id' => $id]);
    $ev = $stmt->fetch();
    if (!$ev) { echo json_encode(['success'=>false,'error'=>'No encontrado']); exit; }
    echo json_encode(['success'=>true, 'event' => $ev]);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
