<?php
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
    echo "Error de conexión: " . $e->getMessage();
    exit(1);
}

require_once __DIR__ . '/ficha_funcs.php';

$razas = [];
try {
    $stmtR = $pdo->query('SELECT nombre FROM raza ORDER BY nombre');
    $razas = $stmtR->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $razas = [];
}

// filtros desde GET
$f_raza = $_GET['raza'] ?? 'all';
$f_estado = $_GET['estado'] ?? 'all';
$f_salud = $_GET['salud'] ?? 'all';
$show_detail = isset($_GET['detail']) && $_GET['detail'] === '1';

$where = [];
$params = [];
if ($f_raza !== 'all') {
    $where[] = 'raza = :raza';
    $params['raza'] = $f_raza;
}
if ($f_estado !== 'all') {
    $where[] = 'estado = :estado';
    $params['estado'] = $f_estado;
}
if ($f_salud !== 'all') {
    $where[] = 'salud = :salud';
    $params['salud'] = $f_salud;
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// resumen por raza + estado
$sql_res = "SELECT COALESCE(raza, '') AS raza, COALESCE(estado, '') AS estado, COUNT(*) AS cantidad
    FROM animals
    {$where_sql}
    GROUP BY raza, estado
    ORDER BY raza, estado";
$stmt = $pdo->prepare($sql_res);
$stmt->execute($params);
$res_by_raza_estado = $stmt->fetchAll();

// resumen por salud
$sql_salud = "SELECT COALESCE(salud, '') AS salud, COUNT(*) AS cantidad
    FROM animals
    {$where_sql}
    GROUP BY salud
    ORDER BY salud";
$stmt2 = $pdo->prepare($sql_salud);
$stmt2->execute($params);
$res_by_salud = $stmt2->fetchAll();

// detalle de animales si se solicita
$detail_rows = [];
if ($show_detail) {
    $sql_det = "SELECT id, ficha_no, nombre, raza, estado, salud, fecha_nacimiento FROM animals {$where_sql} ORDER BY estado, raza, nombre";
    $stmt3 = $pdo->prepare($sql_det);
    $stmt3->execute($params);
    $detail_rows = $stmt3->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Estadísticas - 511 Ganadería</title>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;background:#f3f5f7;color:#1f2933;margin:0;padding:20px}
        .card{background:#fff;padding:18px;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.06);max-width:1100px;margin:12px auto}
        label{font-weight:600;display:block;margin-bottom:6px}
        select,input{padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;width:100%}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
        table{width:100%;border-collapse:collapse;margin-top:12px}
        th,td{padding:8px;border-bottom:1px solid #e6eef3;text-align:left}
        .controls{display:flex;gap:12px;align-items:center;margin-top:12px}
        .btn{background:#215a36;color:#fff;padding:10px 14px;border-radius:8px;text-decoration:none;display:inline-block;border:none;cursor:pointer}
    </style>
</head>
<body>
    <div class="card">
        <h2>Estadísticas</h2>
        <form method="get">
            <div class="grid">
                <div>
                    <label for="raza">Raza</label>
                    <select id="raza" name="raza">
                        <option value="all" <?php if ($f_raza === 'all') echo 'selected'; ?>>Todos</option>
                        <?php foreach ($razas as $r): ?>
                            <option value="<?= h($r['nombre']) ?>" <?php if ($f_raza === $r['nombre']) echo 'selected'; ?>><?= h($r['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="estado">Estado</label>
                    <select id="estado" name="estado">
                        <option value="all" <?php if ($f_estado === 'all') echo 'selected'; ?>>Todos</option>
                        <option value="activo" <?php if ($f_estado === 'activo') echo 'selected'; ?>>Activo</option>
                        <option value="vendido" <?php if ($f_estado === 'vendido') echo 'selected'; ?>>Vendido</option>
                        <option value="muerto" <?php if ($f_estado === 'muerto') echo 'selected'; ?>>Muerto</option>
                    </select>
                </div>
                <div>
                    <label for="salud">Salud</label>
                    <select id="salud" name="salud">
                        <option value="all" <?php if ($f_salud === 'all') echo 'selected'; ?>>Todos</option>
                        <option value="sano" <?php if ($f_salud === 'sano') echo 'selected'; ?>>Sano</option>
                        <option value="enfermo" <?php if ($f_salud === 'enfermo') echo 'selected'; ?>>Enfermo</option>
                    </select>
                </div>
                <div>
                    <label for="detail">Detalle</label>
                    <select id="detail" name="detail">
                        <option value="0" <?php if (!$show_detail) echo 'selected'; ?>>Resumen</option>
                        <option value="1" <?php if ($show_detail) echo 'selected'; ?>>Mostrar detalle</option>
                    </select>
                </div>
            </div>
            <div class="controls">
                <button class="btn" type="submit">Aplicar</button>
                <a class="btn" href="index.php" style="background:#6b7280">Volver</a>
            </div>
        </form>

        <h3>Resumen por raza y estado</h3>
        <table>
            <thead>
                <tr><th>Raza</th><th>Estado</th><th>Cantidad</th></tr>
            </thead>
            <tbody>
                <?php if (empty($res_by_raza_estado)): ?>
                    <tr><td colspan="3">No hay datos.</td></tr>
                <?php else: ?>
                    <?php foreach ($res_by_raza_estado as $row): ?>
                        <tr>
                            <td><?= h($row['raza'] !== '' ? $row['raza'] : '(sin especificar)') ?></td>
                            <td><?= h($row['estado'] !== '' ? $row['estado'] : '(sin especificar)') ?></td>
                            <td><?= h((string)$row['cantidad']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <h3>Resumen por salud</h3>
        <table>
            <thead><tr><th>Salud</th><th>Cantidad</th></tr></thead>
            <tbody>
                <?php if (empty($res_by_salud)): ?>
                    <tr><td colspan="2">No hay datos.</td></tr>
                <?php else: ?>
                    <?php foreach ($res_by_salud as $s): ?>
                        <tr>
                            <td><?= h($s['salud'] !== '' ? $s['salud'] : '(sin especificar)') ?></td>
                            <td><?= h((string)$s['cantidad']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($show_detail): ?>
            <h3>Detalle de animales</h3>
            <table>
                <thead><tr><th>Ficha</th><th>Nombre</th><th>Raza</th><th>Estado</th><th>Salud</th><th>Fecha Nac.</th></tr></thead>
                <tbody>
                    <?php if (empty($detail_rows)): ?>
                        <tr><td colspan="6">No hay animales con esas condiciones.</td></tr>
                    <?php else: ?>
                        <?php foreach ($detail_rows as $d): ?>
                            <tr>
                                <td><?= h($d['ficha_no']) ?></td>
                                <td><?= h($d['nombre']) ?></td>
                                <td><?= h($d['raza']) ?></td>
                                <td><?= h($d['estado']) ?></td>
                                <td><?= h($d['salud']) ?></td>
                                <td><?= h($d['fecha_nacimiento']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>
</body>
</html>

