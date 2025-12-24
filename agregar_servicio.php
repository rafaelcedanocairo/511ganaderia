<?php
session_start();

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
} catch (PDOException $exception) {
    $error = 'No se pudo conectar con la base de datos: ' . $exception->getMessage();
}

require_once __DIR__ . '/ficha_funcs.php';
require_once __DIR__ . '/servicios.php';

$errors = [];
$success = null;

// Obtener animal_id y event_id desde GET, POST o sesión
$animalId = $_GET['animal_id'] ?? $_POST['animal_id'] ?? $_SESSION['selected_animal'] ?? null;
$eventId = $_GET['event_id'] ?? $_POST['event_id'] ?? null;

if (isset($_GET['animal_id'])) {
    $_SESSION['selected_animal'] = $_GET['animal_id'];
}

// Si no se proporcionó animal_id pero sí event_id, cargar el evento y obtener animal_id
if (($animalId === null || $animalId === '') && $eventId) {
    $stmtEv = $pdo->prepare('SELECT id, animal_id, tipo, fecha, comentario FROM events WHERE id = :id');
    $stmtEv->execute(['id' => (int)$eventId]);
    $ev = $stmtEv->fetch();
    if ($ev) {
        $animalId = $ev['animal_id'];
    } else {
        header('Location: index.php');
        exit;
    }
}

if (!$animalId) {
    header('Location: index.php');
    exit;
}

// validar que el animal exista
$stmt = $pdo->prepare('SELECT id, ficha_no, nombre FROM animals WHERE id = :id');
$stmt->execute(['id' => $animalId]);
$animal = $stmt->fetch();
if (!$animal) {
    header('Location: index.php');
    exit;
}

// si se solicita editar un evento (event_id), cargar sus datos para prellenar el formulario
$editingEvent = null;
if ($eventId) {
    $stmtEv = $pdo->prepare('SELECT id, animal_id, tipo, fecha, comentario FROM events WHERE id = :id');
    $stmtEv->execute(['id' => (int)$eventId]);
    $editingEvent = $stmtEv->fetch();
    if ($editingEvent && (string)$editingEvent['animal_id'] !== (string)$animalId) {
        // evento no pertenece a este animal → redirigir
        header('Location: index.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    // si llega event_id en POST, actualizar; si no, crear
    if (!empty($_POST['event_id'])) {
        handle_update_event($pdo, $errors, $success);
    } else {
        handle_add_event($pdo, $errors, $success);
    }
    if ($success && empty($errors)) {
        // limpiar selección en sesión
        unset($_SESSION['selected_animal']);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar servicio - 511 Ganadería</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; background:#f3f5f7; color:#1f2933; }
        .container { max-width:900px; margin:30px auto; padding:0 12px; }
        .card { background:#fff; padding:20px; border-radius:10px; box-shadow:0 6px 18px rgba(0,0,0,0.06); overflow:auto; }
        label { display:block; font-weight:600; margin-top:12px; }
        input, select, textarea { width:100%; padding:10px; border-radius:6px; border:1px solid #d1d5db; box-sizing:border-box; max-width:100%; }
        textarea { min-height:100px; }
        button { background:#215a36; color:#fff; padding:10px 14px; border-radius:8px; border:none; margin-top:14px; }
        .notice { padding:10px; border-radius:6px; margin-bottom:12px; }
        .success { background:#e7f6ec; color:#1f6b3b; }
        .error { background:#fdecea; color:#b42318; }
        .row { display:flex; gap:12px; align-items:center; }
        .row .col { flex:1; }
        .stats-btn { background:#1e88e5; margin-left:8px; }
        /* simple modal */
        .modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:9999; }
        .modal { background:#fff; border-radius:8px; max-width:1000px; width:100%; max-height:80vh; overflow:auto; padding:16px; }
        table { width:100%; border-collapse:collapse; margin-top:12px; }
        th, td { padding:8px; border-bottom:1px solid #eee; text-align:left; }
        .small { font-size:0.9rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <h2 style="margin:0;">Agregar servicio a <?= h($animal['ficha_no']) ?> - <?= h($animal['nombre']) ?></h2>
                
            </div>

            <?php if ($success) : ?>
                <div class="notice success"><?= h($success) ?></div>
                <p><a href="index.php">Volver al inicio</a></p>
            <?php else: ?>

                <?php if ($errors) : ?>
                    <div class="notice error">
                        <ul>
                            <?php foreach ($errors as $m) : ?>
                                <li><?= h($m) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="animal_id" value="<?= h((string)$animal['id']) ?>">
                    <?php if (!empty($editingEvent)) : ?>
                        <input type="hidden" name="event_id" value="<?= h((string)$editingEvent['id']) ?>">
                    <?php endif; ?>
                    <?php
                        $tipoVal = $editingEvent['tipo'] ?? '';
                        $fechaVal = $editingEvent['fecha'] ?? date('Y-m-d');
                        $comentVal = $editingEvent['comentario'] ?? '';
                        $options = ['mantenimiento'=>'Mantenimiento','palpitaciones'=>'Palpitaciones','parto'=>'Parto','bano'=>'Baño','vacunas'=>'Vacunas','revision_veterinaria'=>'Revisión veterinaria','venta'=>'Venta'];
                    ?>
                    <label for="tipo">Tipo de evento</label>
                    <select id="tipo" name="tipo" required>
                        <?php foreach ($options as $val => $label) : ?>
                            <option value="<?= h($val) ?>" <?php if ($tipoVal === $val) echo 'selected'; ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="fecha">Fecha</label>
                    <input id="fecha" type="date" name="fecha" value="<?= h($fechaVal) ?>">

                    <label for="comentario">Comentario</label>
                    <textarea id="comentario" name="comentario" placeholder="Detalle del evento"><?= h($comentVal) ?></textarea>

                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <button type="submit"><?= !empty($editingEvent) ? 'Guardar cambios' : 'Guardar servicio' ?></button>
                        <a class="stats-btn" href="stats_servicios.php" style="text-decoration:none;color:#fff;padding:10px 14px;border-radius:8px;display:inline-block;">Ver estadísticas</a>
                    </div>
                </form>

                <p style="margin-top:12px;"><a href="index.php">Cancelar</a></p>
            <?php endif; ?>
        </div>
    </div>
        <!-- Stats modal -->
        <div id="servicesStatsModal" class="modal-backdrop">
            <div class="modal">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 style="margin:0;">Estadísticas de servicios</h3>
                    <button id="closeStatsBtn" style="background:transparent;border:none;font-size:20px;cursor:pointer;">✕</button>
                </div>
                <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:end;">
                    <div style="min-width:220px;">
                        <label for="filter_tipo">Tipo</label>
                        <select id="filter_tipo">
                            <option value="">Todos</option>
                            <option value="mantenimiento">Mantenimiento</option>
                            <option value="palpitaciones">Palpitaciones</option>
                            <option value="parto">Parto</option>
                            <option value="bano">Baño</option>
                            <option value="vacunas">Vacunas</option>
                            <option value="revision_veterinaria">Revisión veterinaria</option>
                            <option value="venta">Venta</option>
                        </select>
                    </div>
                    <div style="min-width:160px;">
                        <label for="filter_from">Desde</label>
                        <input id="filter_from" type="date">
                    </div>
                    <div style="min-width:160px;">
                        <label for="filter_to">Hasta</label>
                        <input id="filter_to" type="date">
                    </div>
                    <div style="margin-left:auto;">
                        <button id="searchServicesBtn">Buscar</button>
                    </div>
                </div>
                <div id="servicesResults" class="small"></div>
            </div>
        </div>

        <!-- Service detail modal (view/edit) -->
        <div id="serviceDetailModal" class="modal-backdrop">
            <div class="modal">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 id="detailTitle" style="margin:0;">Detalle servicio</h3>
                    <button id="closeDetailBtn" style="background:transparent;border:none;font-size:20px;cursor:pointer;">✕</button>
                </div>
                <form id="detailForm" style="margin-top:12px;">
                    <input type="hidden" id="detail_id" name="id">
                    <div class="row"><div class="col"><label for="detail_tipo">Tipo</label><select id="detail_tipo" name="tipo"><option value="mantenimiento">Mantenimiento</option><option value="palpitaciones">Palpitaciones</option><option value="parto">Parto</option><option value="bano">Baño</option><option value="vacunas">Vacunas</option><option value="revision_veterinaria">Revisión veterinaria</option><option value="venta">Venta</option></select></div>
                    <div class="col"><label for="detail_fecha">Fecha</label><input id="detail_fecha" name="fecha" type="date"></div></div>
                    <label for="detail_comentario">Comentario</label>
                    <textarea id="detail_comentario" name="comentario"></textarea>
                    <div style="display:flex;gap:8px;align-items:center;margin-top:8px;">
                        <button type="button" id="saveDetailBtn">Guardar cambios</button>
                        <button type="button" id="closeDetailBtn2" style="background:#6b7280;">Cerrar</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function(){
                function openModal(id){ document.getElementById(id).style.display = 'flex'; }
                function closeModal(id){ document.getElementById(id).style.display = 'none'; }

                var openStats = document.getElementById('openStatsBtn');
                var openStatsInline = document.getElementById('openStatsBtnInline');
                if (openStats) openStats.addEventListener('click', function(){ openModal('servicesStatsModal'); });
                if (openStatsInline) openStatsInline.addEventListener('click', function(){ openModal('servicesStatsModal'); });
                var closeStats = document.getElementById('closeStatsBtn'); if (closeStats) closeStats.addEventListener('click', function(){ closeModal('servicesStatsModal'); });

                var searchBtn = document.getElementById('searchServicesBtn');
                var results = document.getElementById('servicesResults');
                function renderTable(events){
                    if (!events || !events.length) { results.innerHTML = '<p>No se encontraron servicios.</p>'; return; }
                    var html = '<table><thead><tr><th>Ficha</th><th>Nombre</th><th>Fecha</th><th>Tipo</th><th>Acciones</th></tr></thead><tbody>';
                    events.forEach(function(ev){
                        html += '<tr>'+
                            '<td>'+ (ev.ficha_no || '') +'</td>'+
                            '<td>'+ (ev.nombre || '') +'</td>'+
                            '<td>'+ (ev.fecha || '') +'</td>'+
                            '<td>'+ (ev.tipo || '') +'</td>'+
                            '<td><button class="viewServiceBtn" data-id="'+ev.id+'">Ver</button></td>'+
                            '</tr>';
                    });
                    html += '</tbody></table>';
                    results.innerHTML = html;
                    document.querySelectorAll('.viewServiceBtn').forEach(function(b){ b.addEventListener('click', function(){ var id = this.getAttribute('data-id'); fetch('/agregar_servicio_api_get.php?id='+encodeURIComponent(id)).then(function(r){return r.json();}).then(function(j){ if (!j || !j.success){ alert(j && j.error ? j.error : 'Error'); return; } var ev=j.event; document.getElementById('detail_id').value=ev.id; document.getElementById('detail_tipo').value=ev.tipo; document.getElementById('detail_fecha').value=ev.fecha; document.getElementById('detail_comentario').value=ev.comentario || ''; openModal('serviceDetailModal'); }).catch(function(){ alert('Error de red'); }); });
                }

                if (searchBtn) searchBtn.addEventListener('click', function(){
                    var tipo = document.getElementById('filter_tipo').value;
                    var from = document.getElementById('filter_from').value;
                    var to = document.getElementById('filter_to').value;
                    var params = new URLSearchParams(); if (tipo) params.append('tipo', tipo); if (from) params.append('from', from); if (to) params.append('to', to);
                    fetch('/agregar_servicio_api_list.php?'+params.toString()).then(function(r){ return r.json(); }).then(function(j){ if (!j || !j.success){ results.innerHTML = '<p>Error cargando datos</p>'; return; } renderTable(j.events); }).catch(function(){ results.innerHTML = '<p>Error de red</p>'; });
                });

                // detail modal handlers
                var closeDetail = document.getElementById('closeDetailBtn'); if (closeDetail) closeDetail.addEventListener('click', function(){ closeModal('serviceDetailModal'); });
                var closeDetail2 = document.getElementById('closeDetailBtn2'); if (closeDetail2) closeDetail2.addEventListener('click', function(){ closeModal('serviceDetailModal'); });
                var saveDetail = document.getElementById('saveDetailBtn'); if (saveDetail) saveDetail.addEventListener('click', function(){
                    var id = document.getElementById('detail_id').value;
                    var tipo = document.getElementById('detail_tipo').value;
                    var fecha = document.getElementById('detail_fecha').value;
                    var comentario = document.getElementById('detail_comentario').value;
                    var data = new URLSearchParams(); data.append('id', id); data.append('tipo', tipo); data.append('fecha', fecha); data.append('comentario', comentario);
                    fetch('/agregar_servicio_api_update.php', { method:'POST', body: data }).then(function(r){ return r.json(); }).then(function(j){ if (!j || !j.success){ alert(j && j.error ? j.error : 'Error guardando'); return; } alert('Guardado'); closeModal('serviceDetailModal'); document.getElementById('searchServicesBtn').click(); }).catch(function(){ alert('Error de red'); });
                });
            });
        </script>
</body>
</html>
