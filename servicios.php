<?php
// Depende de post_value() y h() definidas en ficha_funcs.php
function handle_add_event(PDO $pdo, array &$errors, ?string &$success): void
{
    $animalId = post_value('animal_id');
    $tipo = post_value('tipo');
    $fecha = post_value('fecha') ?: date('Y-m-d');

    // validar animal id
    if ($animalId === '' || !ctype_digit((string)$animalId) || (int)$animalId <= 0) {
        $errors[] = 'Animal inválido. Vuelve a seleccionar la ficha.';
        return;
    }

    // validar tipo
    $allowed = ['mantenimiento','palpitaciones','parto','bano','vacunas','revision_veterinaria','venta'];
    if ($tipo === '' || !in_array($tipo, $allowed, true)) {
        $errors[] = 'Selecciona un tipo de evento válido.';
        return;
    }

    // validar fecha simple (YYYY-MM-DD)
    if ($fecha !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $errors[] = 'Fecha inválida.';
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO events (animal_id, tipo, comentario, fecha)
                 VALUES (:animal_id, :tipo, :comentario, :fecha)'
    );

    $stmt->execute([
        'animal_id' => (int)$animalId,
        'tipo' => $tipo,
        'comentario' => post_value('comentario') ?: null,
        'fecha' => $fecha ?: null,
    ]);

    $success = 'Evento registrado correctamente.';
}

function handle_update_event(PDO $pdo, array &$errors, ?string &$success): void
{
    $id = isset($_POST['event_id']) ? trim((string)$_POST['event_id']) : '';
    $tipo = post_value('tipo');
    $fecha = post_value('fecha') ?: date('Y-m-d');
    $comentario = post_value('comentario') ?: null;

    if ($id === '' || !ctype_digit($id) || (int)$id <= 0) {
        $errors[] = 'ID de evento inválido.';
        return;
    }

    $allowed = ['mantenimiento','palpitaciones','parto','bano','vacunas','revision_veterinaria','venta'];
    if ($tipo === '' || !in_array($tipo, $allowed, true)) {
        $errors[] = 'Selecciona un tipo de evento válido.';
        return;
    }

    if ($fecha !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $errors[] = 'Fecha inválida.';
        return;
    }

    try {
        $stmt = $pdo->prepare('UPDATE events SET tipo = :tipo, fecha = :fecha, comentario = :comentario WHERE id = :id');
        $stmt->execute(['tipo' => $tipo, 'fecha' => $fecha ?: null, 'comentario' => $comentario, 'id' => (int)$id]);
        $success = 'Evento actualizado correctamente.';
    } catch (Throwable $e) {
        $errors[] = 'Error actualizando el evento.';
    }
}
