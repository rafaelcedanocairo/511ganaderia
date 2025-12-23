<?php
$config = require __DIR__ . '/config.php';
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

function post_value(string $key): string
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $action = post_value('action');

    if ($action === 'add_animal') {
        $fichaNo = post_value('ficha_no');
        $nombre = post_value('nombre');

        if ($fichaNo === '' || $nombre === '') {
            $errors[] = 'La ficha y el nombre son obligatorios.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare(
                'INSERT INTO animals
                    (ficha_no, nombre, fecha_nacimiento, peso, ubicacion, padre, madre, salud, estado, color, raza)
                VALUES
                    (:ficha_no, :nombre, :fecha_nacimiento, :peso, :ubicacion, :padre, :madre, :salud, :estado, :color, :raza)'
            );

            $stmt->execute([
                'ficha_no' => $fichaNo,
                'nombre' => $nombre,
                'fecha_nacimiento' => post_value('fecha_nacimiento') ?: null,
                'peso' => post_value('peso') ?: null,
                'ubicacion' => post_value('ubicacion') ?: null,
                'padre' => post_value('padre') ?: null,
                'madre' => post_value('madre') ?: null,
                'salud' => post_value('salud') ?: 'sano',
                'estado' => post_value('estado') ?: 'activo',
                'color' => post_value('color') ?: null,
                'raza' => post_value('raza') ?: null,
            ]);

            $success = 'Animal registrado correctamente.';
        }
    }

    if ($action === 'add_event') {
        $animalId = post_value('animal_id');
        $tipo = post_value('tipo');
        $fecha = post_value('fecha') ?: date('Y-m-d');

        if ($animalId === '' || $tipo === '') {
            $errors[] = 'Selecciona un animal y el tipo de evento.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare(
                'INSERT INTO events (animal_id, tipo, comentario, fecha)
                 VALUES (:animal_id, :tipo, :comentario, :fecha)'
            );

            $stmt->execute([
                'animal_id' => $animalId,
                'tipo' => $tipo,
                'comentario' => post_value('comentario') ?: null,
                'fecha' => $fecha,
            ]);

            $success = 'Evento registrado correctamente.';
        }
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

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganadería - Ficha Animal</title>
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
            background: #215a36;
            color: #fff;
            padding: 24px 16px;
            text-align: center;
        }
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
        input, select, textarea {
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
        th, td {
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
        <h1>Ficha Animal - Sistema Ganadero</h1>
        <p>Acceso rápido desde celular, tablet o computador.</p>
    </header>

    <main>
        <?php if (!empty($error)) : ?>
            <div class="card notice error">
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success) : ?>
            <div class="card notice success">
                <?= h($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($errors) : ?>
            <div class="card notice error">
                <ul>
                    <?php foreach ($errors as $message) : ?>
                        <li><?= h($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <section class="card">
            <h2>Ficha animal</h2>
            <form method="post">
                <input type="hidden" name="action" value="add_animal">
                <div class="grid">
                    <div>
                        <label for="ficha_no">No ficha</label>
                        <input id="ficha_no" name="ficha_no" required>
                    </div>
                    <div>
                        <label for="nombre">Nombre</label>
                        <input id="nombre" name="nombre" required>
                    </div>
                    <div>
                        <label for="fecha_nacimiento">Fecha de nacimiento</label>
                        <input id="fecha_nacimiento" type="date" name="fecha_nacimiento">
                    </div>
                    <div>
                        <label for="peso">Peso (kg)</label>
                        <input id="peso" type="number" step="0.01" name="peso">
                    </div>
                    <div>
                        <label for="ubicacion">Ubicación</label>
                        <input id="ubicacion" name="ubicacion" placeholder="Finca, potrero, etc.">
                    </div>
                    <div>
                        <label for="padre">Padre</label>
                        <input id="padre" name="padre">
                    </div>
                    <div>
                        <label for="madre">Madre</label>
                        <input id="madre" name="madre">
                    </div>
                    <div>
                        <label for="salud">Salud</label>
                        <select id="salud" name="salud">
                            <option value="sano">Sano</option>
                            <option value="enfermo">Enfermo</option>
                        </select>
                    </div>
                    <div>
                        <label for="estado">Estado actual</label>
                        <select id="estado" name="estado">
                            <option value="activo">Activo</option>
                            <option value="vendido">Vendido</option>
                            <option value="muerto">Muerto</option>
                        </select>
                    </div>
                    <div>
                        <label for="color">Color</label>
                        <input id="color" name="color">
                    </div>
                    <div>
                        <label for="raza">Raza</label>
                        <input id="raza" name="raza">
                    </div>
                </div>
                <button type="submit">Guardar ficha</button>
            </form>
        </section>

        <section class="card">
            <h2>Eventos o servicios</h2>
            <form method="post">
                <input type="hidden" name="action" value="add_event">
                <div class="grid">
                    <div>
                        <label for="animal_id">Animal</label>
                        <select id="animal_id" name="animal_id" required>
                            <option value="">Selecciona un animal</option>
                            <?php foreach ($animals as $animal) : ?>
                                <option value="<?= h((string) $animal['id']) ?>">
                                    <?= h($animal['ficha_no']) ?> - <?= h($animal['nombre']) ?>
                                    (<?= h($animal['estado']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="tipo">Tipo de evento</label>
                        <select id="tipo" name="tipo" required>
                            <option value="mantenimiento">Mantenimiento</option>
                            <option value="palpitaciones">Palpitaciones</option>
                            <option value="parto">Parto</option>
                            <option value="bano">Baño</option>
                            <option value="vacunas">Vacunas</option>
                            <option value="revision_veterinaria">Revisión veterinaria</option>
                            <option value="venta">Venta</option>
                        </select>
                    </div>
                    <div>
                        <label for="fecha">Fecha</label>
                        <input id="fecha" type="date" name="fecha" value="<?= h(date('Y-m-d')) ?>">
                    </div>
                </div>
                <div>
                    <label for="comentario">Comentario</label>
                    <textarea id="comentario" name="comentario" placeholder="Detalle del evento"></textarea>
                </div>
                <button type="submit">Registrar evento</button>
            </form>
        </section>

        <section class="card">
            <h2>Inventario y reportes rápidos</h2>
            <form method="get">
                <div class="grid">
                    <div>
                        <label for="estado_filter">Estado</label>
                        <select id="estado_filter" name="estado">
                            <option value="">Todos</option>
                            <option value="activo" <?= ($filters['estado'] ?? '') === 'activo' ? 'selected' : '' ?>>Activo</option>
                            <option value="vendido" <?= ($filters['estado'] ?? '') === 'vendido' ? 'selected' : '' ?>>Vendido</option>
                            <option value="muerto" <?= ($filters['estado'] ?? '') === 'muerto' ? 'selected' : '' ?>>Muerto</option>
                        </select>
                    </div>
                    <div>
                        <label for="raza_filter">Raza</label>
                        <input id="raza_filter" name="raza" value="<?= h($filters['raza'] ?? '') ?>" placeholder="Ej: Brahman">
                    </div>
                    <div>
                        <label for="edad_min">Edad mínima (años)</label>
                        <input id="edad_min" type="number" name="edad_min" value="<?= h($filters['edad_min'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="edad_max">Edad máxima (años)</label>
                        <input id="edad_max" type="number" name="edad_max" value="<?= h($filters['edad_max'] ?? '') ?>">
                    </div>
                </div>
                <button type="submit">Filtrar inventario</button>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Ficha</th>
                        <th>Nombre</th>
                        <th>Raza</th>
                        <th>Estado</th>
                        <th>Fecha nacimiento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$inventory) : ?>
                        <tr>
                            <td colspan="5">Sin resultados. Registra animales para visualizar el inventario.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($inventory as $row) : ?>
                            <tr>
                                <td><?= h($row['ficha_no']) ?></td>
                                <td><?= h($row['nombre']) ?></td>
                                <td><?= h($row['raza']) ?></td>
                                <td><span class="tag"><?= h($row['estado']) ?></span></td>
                                <td><?= h($row['fecha_nacimiento']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>