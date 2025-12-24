<?php
$config = require __DIR__ . '/conf.php';
$db = $config['db'];

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $db['name'], $db['charset']);
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
try { $pdo = new PDO($dsn, $db['user'], $db['pass'], $options); } catch (Throwable $e) { $error = 'DB error'; }

    $events = [];
$filters = ['tipo' => $_GET['tipo'] ?? '', 'from' => $_GET['from'] ?? '', 'to' => $_GET['to'] ?? ''];
$conditions = [];
$params = [];
if ($filters['tipo'] !== '') { $conditions[] = 'e.tipo = :tipo'; $params['tipo'] = $filters['tipo']; }
if ($filters['from'] !== '') { $conditions[] = 'e.fecha >= :from'; $params['from'] = $filters['from']; }
if ($filters['to'] !== '') { $conditions[] = 'e.fecha <= :to'; $params['to'] = $filters['to']; }
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

if (empty($error)) {
    $sql = "SELECT e.id, e.animal_id, a.ficha_no, a.nombre, e.fecha, e.tipo FROM events e JOIN animals a ON a.id = e.animal_id {$where} ORDER BY e.fecha DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll();
    $total = count($events);
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Estadísticas servicios</title>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;background:#f3f5f7;color:#1f2933}
        .wrap{max-width:1100px;margin:24px auto;padding:0 12px}
        .card{background:#fff;padding:18px;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.06)}
        table{width:100%;border-collapse:collapse}
        th,td{padding:8px;border-bottom:1px solid #eee;text-align:left}
        button{background:#215a36;color:#fff;border:none;padding:8px 12px;border-radius:6px}
    </style>
</head>
<body>
    <div class="wrap">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h2>Estadísticas de servicios</h2>
            <a href="index.php" style="text-decoration:none;color:#215a36;font-weight:600;">Volver</a>
        </div>
        <div class="card">
            <form method="get" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
                <div><label>Tipo</label><select name="tipo"><option value="">Todos</option><option value="mantenimiento">Mantenimiento</option><option value="vacunas">Vacunas</option><option value="revision_veterinaria">Revisión veterinaria</option><option value="venta">Venta</option></select></div>
                <div><label>Desde</label><input type="date" name="from"></div>
                <div><label>Hasta</label><input type="date" name="to"></div>
                <div><button type="submit">Filtrar</button></div>
            </form>
            <div style="margin-top:12px;overflow:auto;">
                <?php if (!empty($events)) : ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                        <div><strong>Resultados: <?= (int)$total ?></strong></div>
                        <div><a href="index.php" style="text-decoration:none;color:#215a36;font-weight:600;">Volver</a></div>
                    </div>
                    <table>
                        <thead><tr><th>Ficha</th><th>Nombre</th><th>Fecha</th><th>Tipo</th><th>Acciones</th></tr></thead>
                        <tbody>
                        <?php foreach ($events as $e) : ?>
                            <tr data-event-id="<?= (int)$e['id'] ?>">
                                <td><?= htmlspecialchars($e['ficha_no']) ?></td>
                                <td><?= htmlspecialchars($e['nombre']) ?></td>
                                <td class="cell-fecha"><?= htmlspecialchars($e['fecha']) ?></td>
                                <td class="cell-tipo"><?= htmlspecialchars($e['tipo']) ?></td>
                                <td><a href="agregar_servicio.php?animal_id=<?= (int)$e['animal_id'] ?>&event_id=<?= (int)$e['id'] ?>" class="viewServiceLink" data-id="<?= (int)$e['id'] ?>">Ver</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" style="text-align:right;font-weight:600;">Total servicios: <?= (int)$total ?></td>
                            </tr>
                        </tfoot>
                    </table>
                <?php else: ?>
                    <p>No se encontraron servicios.</p>
                <?php endif; ?>
            </div>

            
        </div>
    </div>
</body>
</html>
