<?php
function post_value(string $key): string
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function handle_add_animal(PDO $pdo, array &$errors, ?string &$success): void
{
    $fichaNo = post_value('ficha_no');
    $nombre = post_value('nombre');

    if ($fichaNo === '' || $nombre === '') {
        $errors[] = 'La ficha y el nombre son obligatorios.';
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO animals
                    (ficha_no, nombre, fecha_nacimiento, peso, ubicacion, padre, madre, salud, estado, color, raza)
                VALUES
                    (:ficha_no, :nombre, :fecha_nacimiento, :peso, :ubicacion, :padre, :madre, :salud, :estado, :color, :raza)'
    );

    // si se proporcionó una ubicación, intentar insertarla en la tabla `ubicacion` (si no existe)
    $ubic = post_value('ubicacion');
    if ($ubic !== '') {
        try {
            $insU = $pdo->prepare('INSERT IGNORE INTO ubicacion (nombre) VALUES (:nombre)');
            $insU->execute(['nombre' => $ubic]);
        } catch (Throwable $e) {
            // no bloquear el guardado del animal si la tabla no existe o hay error
        }
    }

    $stmt->execute([
        'ficha_no' => $fichaNo,
        'nombre' => $nombre,
        'fecha_nacimiento' => post_value('fecha_nacimiento') ?: null,
        'peso' => post_value('peso') ?: null,
            'ubicacion' => $ubic ?: null,
        'padre' => post_value('padre') ?: null,
        'madre' => post_value('madre') ?: null,
        'salud' => post_value('salud') ?: 'sano',
        'estado' => post_value('estado') ?: 'activo',
        'color' => post_value('color') ?: null,
        'raza' => post_value('raza_custom') ?: post_value('raza_select') ?: post_value('raza') ?: null,
    ]);

    $success = 'Animal registrado correctamente.';
}

function handle_save_animal(PDO $pdo, array &$errors, ?string &$success): void
{
    $id = post_value('id');
    $fichaNo = post_value('ficha_no');
    $nombre = post_value('nombre');

    if ($fichaNo === '' || $nombre === '') {
        $errors[] = 'La ficha y el nombre son obligatorios.';
        return;
    }

    if ($id !== '') {
        $stmt = $pdo->prepare(
            'UPDATE animals SET
                ficha_no = :ficha_no,
                nombre = :nombre,
                fecha_nacimiento = :fecha_nacimiento,
                peso = :peso,
                ubicacion = :ubicacion,
                padre = :padre,
                madre = :madre,
                salud = :salud,
                estado = :estado,
                color = :color,
                raza = :raza
             WHERE id = :id'
        );

        // si se proporcionó una ubicación, intentar insertarla en la tabla `ubicacion` (si no existe)
        $ubic = post_value('ubicacion');
        if ($ubic !== '') {
            try {
                $insU = $pdo->prepare('INSERT IGNORE INTO ubicacion (nombre) VALUES (:nombre)');
                $insU->execute(['nombre' => $ubic]);
            } catch (Throwable $e) {
                // no bloquear la actualización si la tabla no existe o hay error
            }
        }

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
            'raza' => post_value('raza_custom') ?: post_value('raza_select') ?: post_value('raza') ?: null,
            'id' => $id,
        ]);

        $success = 'Ficha actualizada correctamente.';
        return;
    }

    // si no hay id, crear nuevo
    handle_add_animal($pdo, $errors, $success);
}
