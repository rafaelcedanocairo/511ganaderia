<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/conf.php';
$cfg = $config = require __DIR__ . '/conf.php';
$db = $cfg['db'];
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $db['name'], $db['charset']);
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
try { $pdo = new PDO($dsn, $db['user'], $db['pass'], $options); } catch (Throwable $e) { echo json_encode(['success'=>false,'error'=>'DB connection error']); exit; }

$tipo = $_GET['tipo'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$conditions = [];
$params = [];
if ($tipo !== '') { $conditions[] = 'e.tipo = :tipo'; $params['tipo'] = $tipo; }
if ($from !== '') { $conditions[] = 'e.fecha >= :from'; $params['from'] = $from; }
if ($to !== '') { $conditions[] = 'e.fecha <= :to'; $params['to'] = $to; }

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$sql = "SELECT e.id, a.ficha_no, a.nombre, e.fecha, e.tipo
        FROM events e
        JOIN animals a ON a.id = e.animal_id
        {$where}
        ORDER BY e.fecha DESC
        LIMIT 500";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll();
    echo json_encode(['success' => true, 'events' => $events]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

