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
} catch (PDOException $exception) {
    $error = 'No se pudo conectar con la base de datos: ' . $exception->getMessage();
}

$success = null;
$errors = [];

require_once __DIR__ . '/ficha_funcs.php';
require_once __DIR__ . '/servicios.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $action = post_value('action');

    if ($action === 'save_animal') {
        handle_save_animal($pdo, $errors, $success);
    }

    if ($action === 'add_event') {
        handle_add_event($pdo, $errors, $success);
    }
}

$animals = [];
$inventory = [];

if (empty($error)) {
    $animals = $pdo->query('SELECT id, ficha_no, nombre, estado FROM animals ORDER BY id DESC')->fetchAll();

    $filters = [
        'estado' => $_GET['estado'] ?? '',
        'raza' => $_GET['raza'] ?? '',
        'edad_min' => $_GET['edad_min'] ?? '',
        'edad_max' => $_GET['edad_max'] ?? '',
    ];

    $conditions = [];
    $params = [];

    if ($filters['estado'] !== '') {
        $conditions[] = 'estado = :estado';
        $params['estado'] = $filters['estado'];
    }

    if ($filters['raza'] !== '') {
        $conditions[] = 'raza = :raza';
        $params['raza'] = $filters['raza'];
    }

    if ($filters['edad_min'] !== '') {
        $conditions[] = 'TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) >= :edad_min';
        $params['edad_min'] = (int) $filters['edad_min'];
    }

    if ($filters['edad_max'] !== '') {
        $conditions[] = 'TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) <= :edad_max';
        $params['edad_max'] = (int) $filters['edad_max'];
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $stmt = $pdo->prepare(
        "SELECT ficha_no, nombre, raza, estado, fecha_nacimiento
         FROM animals
         {$where}
         ORDER BY estado, raza, nombre"
    );

    $stmt->execute($params);
    $inventory = $stmt->fetchAll();
}

// si se pasa animal_id por GET, cargar datos para editar
$editingAnimal = null;
if (empty($error) && isset($_GET['animal_id']) && $_GET['animal_id'] !== '') {
    $aid = (int) $_GET['animal_id'];
    $stmt = $pdo->prepare('SELECT * FROM animals WHERE id = :id');
    $stmt->execute(['id' => $aid]);
    $editingAnimal = $stmt->fetch();
}

// `h()` is defined in ficha_funcs.php
// lista de animales activos para el modal
$activeAnimals = array_values(array_filter($animals, function ($a) {
    return ($a['estado'] ?? '') === 'activo';
}));
// cargar razas desde la tabla `raza` si existe (valor será el nombre)
$razas = [];
try {
    if (empty($error)) {
        $stmtR = $pdo->query('SELECT nombre FROM raza ORDER BY nombre');
        $razas = $stmtR->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $razas = [];
}
// cargar ubicaciones desde la tabla `ubicacion` si existe
$ubicaciones = [];
try {
    if (empty($error)) {
        $stmtU = $pdo->query('SELECT nombre FROM ubicacion ORDER BY nombre');
        $ubicaciones = $stmtU->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $ubicaciones = [];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>511 Ganadería - Ficha Animal</title>
    <style>
        :root {
            color-scheme: light;
            font-family: "Segoe UI", system-ui, sans-serif;
        }

        body {
            margin: 0;
            background: #f3f5f7;
            color: #1f2933;
        }

        header {
            position: relative;
            color: #fff;
            padding: 48px 16px 64px;
            text-align: center;
            overflow: visible;
            /* wallpaper image with subtle gradient overlay */
            background-image: linear-gradient(rgba(27,88,38,0.55), rgba(121,178,74,0.25)), url('img/wallpaper.png');
            background-size: cover;
            background-position: center center;
        }

        /* logo positioned above the background */
        #header-logo {
            position: absolute;
            left: 50%;
            top: 6px;
            transform: translateX(-50%);
            width: 220px;
            max-width: 40%;
            height: auto;
            z-index: 2;
        }

        header h1 { position: relative; z-index: 1; margin-top: 48px; }

        main {
            max-width: 1100px;
            margin: 24px auto;
            padding: 0 16px 40px;
            display: grid;
            gap: 24px;
        }

        .card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }

        /* evitar que los inputs salgan fuera de sus contenedores */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        h2 {
            margin-top: 0;
        }

        form {
            display: grid;
            gap: 16px;
        }

        .grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }

        label {
            font-weight: 600;
            display: block;
            margin-bottom: 8px;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #cbd2d9;
            font-size: 0.95rem;
            background: #fff;
        }

        textarea {
            min-height: 90px;
        }

        button {
            background: #215a36;
            color: #fff;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            justify-self: start;
        }

        .notice {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .notice.success {
            background: #e7f6ec;
            color: #1f6b3b;
        }

        .notice.error {
            background: #fdecea;
            color: #b42318;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        th,
        td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        .tag {
            background: #e2f0e7;
            color: #215a36;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 0.8rem;
            display: inline-block;
        }

        /* forzar botones a comportamiento inline y evitar que ocupen todo el ancho */
        button {
            display: inline-block;
            width: auto;
        }

        /* estilos específicos para el modal/table para evitar superposición */
        #animalModal table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        #animalModal td {
            vertical-align: middle;
            word-break: break-word;
        }

        #animalModal .select-animal,
        #animalModal a {
            display: inline-block;
            margin: 0 6px 0 0;
        }

        /* icon styles */
        #animalModal .icon {
            width: 18px;
            height: 18px;
            vertical-align: middle;
            display: inline-block;
            margin-right: 6px;
        }

        /* botón agregar servicio verde (consistente con botones de la app) - estilo igual que button */
        .add-service-btn {
            background: #215a36;
            color: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            border: none;
            cursor: pointer;
            line-height: 1;
            vertical-align: middle;
        }

        .add-service-btn:hover {
            background: #1e4f2f;
        }

        /* acciones (botones) en cabecera del card */
        .actions { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
        .actions .add-service-btn, .actions button { padding:10px 14px; line-height:1; min-height:0; }
        @media (max-width:420px) {
            .actions .add-service-btn, .actions button { padding:8px 10px; font-size:0.95rem; }
        }

        /* columnas: ficha pequeña, nombre dominante, acciones a la derecha */
        #animalModal table th:nth-child(1),
        #animalModal table td:nth-child(1) {
            width: 12%;
            max-width: 120px;
            font-size: 0.9rem;
        }

        #animalModal table th:nth-child(2),
        #animalModal table td:nth-child(2) {
            width: 40%;
            font-size: 0.98rem;
        }

        #animalModal table th:nth-child(3),
        #animalModal table td:nth-child(3) {
            width: 26%;
            text-align: right;
        }

        /* asegurar que los botones estén alineados a la derecha y no rompan la línea */
        #animalModal td:nth-child(3) .select-animal,
        #animalModal td:nth-child(3) .add-service-btn {
            vertical-align: middle;
            margin-left: 6px;
        }

        @media (max-width: 720px) {
            header {
                padding: 20px 12px;
            }

            .card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <header>
        <img id="header-logo" src="img/borrar.png" width="200px" alt="511 Ganaderia logo">
        <h1>Ficha Animal</h1>
    </header>

    <main>
        <?php if (!empty($error)): ?>
            <div class="card notice error">
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="card notice success">
                <?= h($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="card notice error">
                <ul>
                    <?php foreach ($errors as $message): ?>
                        <li><?= h($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <section class="card">
            <h2>Buscar ficha / servicios</h2>
            <p>Pulse <strong>Buscar Ficha</strong> para seleccionar un animal activo.</p>
            <div class="actions">
                <button type="button" id="openModalBtn">Buscar Ficha</button>
                <a class="add-service-btn" href="stats.php" style="margin-left:8px;">Estadísticas</a>
                
                
                    <a class="add-service-btn" href="stats_servicios.php" style="margin-left:8px;">Estadísticas servicios</a>
                <?php if (isset($editingAnimal) && $editingAnimal): ?>
                    <a class="add-service-btn" href="agregar_servicio.php?animal_id=<?= h((string) $editingAnimal['id']) ?>">Agregar servicio</a>
                    <a href="index.php" style="text-decoration:none;color:#215a36;font-weight:600;margin-left:8px;">Limpiar</a>
                <?php endif; ?>
            </div>

            <!-- Modal -->
            <div id="animalModal"
                style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);align-items:center;justify-content:center;padding:20px;z-index:9999;">
                <div
                    style="background:#fff;border-radius:10px;max-width:900px;width:100%;max-height:80vh;overflow:auto;padding:18px;box-shadow:0 8px 30px rgba(0,0,0,0.2);">
                    <div style="display:flex;justify-content:space-between;align-items:center;position:relative;">
                        <h3>Seleccionar animal</h3>
                        <button id="closeModalBtn"
                            style="background:transparent;border:none;font-size:18px;cursor:pointer;">✕</button>
                        <button id="closeModalX" aria-label="Cerrar" title="Cerrar"
                            style="position:absolute;right:12px;top:12px;background:transparent;border:none;font-size:20px;cursor:pointer;">✕</button>
                    </div>
                    <input id="animalSearchInput" type="text" placeholder="Buscar por número de ficha o nombre"
                        style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;margin:10px 0;">

                    <div id="animalList">
                        <?php if (empty($activeAnimals)): ?>
                            <p>No hay animales activos.</p>
                        <?php else: ?>
                            <table style="width:100%;border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;">Ficha</th>
                                        <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;">Nombre</th>
                                        <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;">Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activeAnimals as $a): ?>
                                        <tr class="animal-row" data-ficha="<?= h($a['ficha_no']) ?>"
                                            data-nombre="<?= h($a['nombre']) ?>">
                                            <td style="padding:8px;border-bottom:1px solid #f3f4f6;"><?= h($a['ficha_no']) ?>
                                            </td>
                                            <td style="padding:8px;border-bottom:1px solid #f3f4f6;"><?= h($a['nombre']) ?></td>
                                            <td style="padding:8px;border-bottom:1px solid #f3f4f6;">
                                                <button class="select-animal" data-id="<?= h((string) $a['id']) ?>"
                                                    style="margin-right:8px;background:#a7d3b7;" aria-label="Seleccionar animal"
                                                    title="Seleccionar">
                                                    <img src="img/soga.png" alt="">
                                                </button>
                                                <a class="add-service-btn" href="agregar_servicio.php?animal_id=<?= h((string) $a['id']) ?>" aria-label="Agregar servicio" title="Agregar servicio" style="background:#a7d3b7">
                                                    <img src="img/servicio32.png" alt="">
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <script>
                // Esperar a que el DOM esté cargado antes de atar handlers
                document.addEventListener('DOMContentLoaded', function () {
                    (function () {
                        var modal = document.getElementById('animalModal');
                        var openBtn = document.getElementById('openModalBtn');
                        var closeBtn = document.getElementById('closeModalBtn');
                        var searchInput = document.getElementById('animalSearchInput');
                        var rows = Array.prototype.slice.call(document.querySelectorAll('.animal-row'));

                        if (openBtn) openBtn.addEventListener('click', function () { modal.style.display = 'flex'; searchInput.focus(); });
                        if (closeBtn) closeBtn.addEventListener('click', function () { modal.style.display = 'none'; });
                        var closeX = document.getElementById('closeModalX');
                        if (closeX) closeX.addEventListener('click', function () { modal.style.display = 'none'; });

                        if (searchInput) {
                            searchInput.addEventListener('input', function () {
                                var q = this.value.trim().toLowerCase();
                                rows.forEach(function (r) {
                                    var ficha = (r.getAttribute('data-ficha') || '').toLowerCase();
                                    var nombre = (r.getAttribute('data-nombre') || '').toLowerCase();
                                    var visible = q === '' || ficha.indexOf(q) !== -1 || nombre.indexOf(q) !== -1;
                                    r.style.display = visible ? '' : 'none';
                                });
                            });
                        }

                        document.querySelectorAll('.select-animal').forEach(function (btn) {
                            btn.addEventListener('click', function () {
                                var id = this.getAttribute('data-id');
                                window.location = 'index.php?animal_id=' + encodeURIComponent(id);
                            });
                        });

                        // añadir ubicación vía AJAX
                        var addUb = document.getElementById('addUbBtn');
                        if (addUb) {
                            addUb.addEventListener('click', function () {
                                var ubicElem = document.getElementById('ubicacion');
                                if (!ubicElem) return;
                                var current = '';
                                if (ubicElem.tagName.toLowerCase() === 'select') {
                                    current = prompt('Nueva ubicación:');
                                } else {
                                    current = ubicElem.value.trim();
                                    if (!current) current = prompt('Nueva ubicación:');
                                }
                                if (!current) return;
                                var data = new URLSearchParams();
                                data.append('nombre', current);
                                fetch('api_add_ubicacion.php', { method: 'POST', body: data })
                                    .then(function (r) { return r.json(); })
                                    .then(function (json) {
                                        if (!json || !json.success) { alert(json && json.error ? json.error : 'Error agregando ubicación'); return; }
                                        // reconstruir select
                                        var parent = ubicElem.parentNode;
                                        var sel = document.createElement('select');
                                        sel.id = 'ubicacion'; sel.name = 'ubicacion';
                                        var o0 = document.createElement('option'); o0.value = ''; o0.text = 'Selecciona una ubicación'; sel.appendChild(o0);
                                        json.ubicaciones.forEach(function (u) { var o = document.createElement('option'); o.value = u; o.text = u; if (u === current) o.selected = true; sel.appendChild(o); });
                                        parent.replaceChild(sel, ubicElem);
                                    }).catch(function () { alert('Error de red'); });
                            });
                        }
                    })();
                });
            </script>
        </section>

        <section class="card">
            <h2>Ficha animal</h2>
            <form method="post">
                <input type="hidden" name="action" value="save_animal">
                <?php if (isset($editingAnimal) && $editingAnimal): ?>
                    <input type="hidden" name="id" value="<?= h((string) $editingAnimal['id']) ?>">
                <?php endif; ?>
                <div class="grid">
                    <div>
                        <label for="ficha_no">No ficha</label>
                        <input id="ficha_no" name="ficha_no" required
                            value="<?= h($editingAnimal['ficha_no'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="nombre">Nombre</label>
                        <input id="nombre" name="nombre" required value="<?= h($editingAnimal['nombre'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="fecha_nacimiento">Fecha de nacimiento</label>
                        <input id="fecha_nacimiento" type="date" name="fecha_nacimiento"
                            value="<?= h($editingAnimal['fecha_nacimiento'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="peso">Peso (kg)</label>
                        <input id="peso" type="number" step="0.01" name="peso"
                            value="<?= h($editingAnimal['peso'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="ubicacion">Ubicación</label>
                        <?php if (!empty($ubicaciones)): ?>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <select id="ubicacion" name="ubicacion">
                                    <option value="">Selecciona una ubicación</option>
                                    <?php foreach ($ubicaciones as $u): ?>
                                        <option value="<?= h($u['nombre']) ?>" <?php if (($editingAnimal['ubicacion'] ?? '') === $u['nombre'])
                                              echo 'selected'; ?>><?= h($u['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" id="addUbBtn"
                                    style="padding:8px 10px;border-radius:8px;background:#64748b;color:#fff;border:none;cursor:pointer;">+</button>
                            </div>
                        <?php else: ?>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input id="ubicacion" name="ubicacion" placeholder="Finca, potrero, etc."
                                    value="<?= h($editingAnimal['ubicacion'] ?? '') ?>">
                                <button type="button" id="addUbBtn"
                                    style="padding:8px 10px;border-radius:8px;background:#64748b;color:#fff;border:none;cursor:pointer;">+</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label for="padre">Padre</label>
                        <input id="padre" name="padre" value="<?= h($editingAnimal['padre'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="madre">Madre</label>
                        <input id="madre" name="madre" value="<?= h($editingAnimal['madre'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="salud">Salud</label>
                        <select id="salud" name="salud">
                            <option value="sano" <?php if (($editingAnimal['salud'] ?? '') === 'sano')
                                echo 'selected'; ?>>Sano</option>
                            <option value="enfermo" <?php if (($editingAnimal['salud'] ?? '') === 'enfermo')
                                echo 'selected'; ?>>Enfermo</option>
                        </select>
                    </div>
                    <div>
                        <label for="estado">Estado actual</label>
                        <select id="estado" name="estado">
                            <option value="activo" <?php if (($editingAnimal['estado'] ?? '') === 'activo')
                                echo 'selected'; ?>>Activo</option>
                            <option value="vendido" <?php if (($editingAnimal['estado'] ?? '') === 'vendido')
                                echo 'selected'; ?>>Vendido</option>
                            <option value="muerto" <?php if (($editingAnimal['estado'] ?? '') === 'muerto')
                                echo 'selected'; ?>>Muerto</option>
                        </select>
                    </div>
                    <div>
                        <label for="color">Color</label>
                        <input id="color" name="color" value="<?= h($editingAnimal['color'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="raza_select">Raza</label>
                        <?php if (!empty($razas)): ?>
                            <?php
                            $editingRaza = $editingAnimal['raza'] ?? '';
                            $editingRazaExists = false;
                            foreach ($razas as $rcheck) {
                                if ($rcheck['nombre'] === $editingRaza) {
                                    $editingRazaExists = true;
                                    break;
                                }
                            }
                            ?>
                            <select id="raza_select" name="raza_select">
                                <option value="">Selecciona una raza</option>
                                <?php foreach ($razas as $r): ?>
                                    <option value="<?= h($r['nombre']) ?>" <?php if ($editingRazaExists && $editingRaza === $r['nombre'])
                                          echo 'selected'; ?>><?= h($r['nombre']) ?></option>
                                <?php endforeach; ?>
                                <option value="__otra__" <?php if ($editingRaza !== '' && !$editingRazaExists)
                                    echo 'selected'; ?>>Otra...</option>
                            </select>
                            <input id="raza_custom" name="raza_custom" placeholder="Escribe la raza"
                                style="display:none;margin-top:8px;"
                                value="<?= h((!$editingRazaExists ? $editingRaza : '')) ?>">
                        <?php else: ?>
                            <input id="raza_custom" name="raza_custom" value="<?= h($editingAnimal['raza'] ?? '') ?>"
                                placeholder="Escribe la raza">
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit">Guardar ficha</button>
            </form>
        </section>
        <script>
            (function () {
                var sel = document.getElementById('raza_select');
                var custom = document.getElementById('raza_custom');
                if (!sel || !custom) return;
                function update() {
                    if (sel.value === '__otra__') {
                        custom.style.display = '';
                        try { custom.focus(); } catch (e) { }
                    } else {
                        // ocultar solo si está vacío; si tiene texto, mantenerlo visible
                        if (!custom.value || custom.value.trim() === '') {
                            custom.style.display = 'none';
                        }
                    }
                }
                // mostrar campo custom si al cargar viene seleccionado 'Otra' o ya existe valor personalizado
                if (sel.value === '__otra__' || (custom.value && custom.value.trim() !== '')) {
                    custom.style.display = '';
                } else {
                    custom.style.display = 'none';
                }
                sel.addEventListener('change', update);
            })();
        </script>
    </main>
</body>

</html>