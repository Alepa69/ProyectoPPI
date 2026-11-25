<?php
require_once __DIR__ . '/config/sesion.php';
require_once __DIR__ . '/includes/formularios.php';

function esPeticionJson(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return stripos($accept, 'application/json') !== false
        || strcasecmp($xhr, 'XMLHttpRequest') === 0;
}

function responderJson(int $codigoHttp, array $payload): void
{
    http_response_code($codigoHttp);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Normaliza horas en formato HH:MM o HH:MM:SS y valida rango 00:00:00 - 23:59:59.
 */
function normalizarHora(string $valor): ?string
{
    $valor = trim($valor);
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $valor, $m)) {
        return null;
    }
    return sprintf('%s:%s:%s', $m[1], $m[2], $m[3] ?? '00');
}

/**
 * Procesa URLs externas (prioridad por convencion actual) y archivos en $_FILES['imagenes'].
 *
 * @param string[] $errores
 * @param string[] $archivosSubidos Rutas absolutas creadas para rollback si falla la BD
 * @return string[] Rutas relativas o URLs listas para insertar en alojamiento_imagenes
 */
function procesarImagenesPublicacion(array &$errores, array &$archivosSubidos): array
{
    $imagenes = [];

    // 1. Convencion principal actual del proyecto: URLs externas (imagenes_url[] o fotos)
    $urlsRaw = $_POST['imagenes_url'] ?? [];
    if (!is_array($urlsRaw)) {
        $urlsRaw = [$urlsRaw];
    }
    if (empty(array_filter($urlsRaw, static fn($u) => trim((string)$u) !== '')) && !empty($_POST['foto'])) {
        $urlsRaw = [$_POST['foto']];
    }

    foreach ($urlsRaw as $url) {
        $url = trim((string)$url);
        if ($url === '') {
            continue;
        }
        $esUrlValida = filter_var($url, FILTER_VALIDATE_URL) !== false
            && preg_match('#^https?://#i', $url) === 1;
        if (!$esUrlValida) {
            $errores[] = "La URL de imagen no es valida: {$url}";
            continue;
        }
        $imagenes[] = $url;
    }

    // 2. Subida multiple de archivos en $_FILES['imagenes']
    if (isset($_FILES['imagenes']) && is_array($_FILES['imagenes']['name'] ?? null)) {
        $nombres = $_FILES['imagenes']['name'];
        $tmps = $_FILES['imagenes']['tmp_name'];
        $codigosError = $_FILES['imagenes']['error'];
        $pesos = $_FILES['imagenes']['size'];

        $mimePermitidos = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];
        $pesoMaximoBytes = 5 * 1024 * 1024;
        $dirDestino = __DIR__ . '/uploads/alojamientos';

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        foreach ($nombres as $i => $nombreOriginal) {
            $codigo = (int)($codigosError[$i] ?? UPLOAD_ERR_NO_FILE);
            if ($codigo === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($codigo !== UPLOAD_ERR_OK) {
                $errores[] = "Error al subir el archivo {$nombreOriginal}.";
                continue;
            }

            $peso = (int)($pesos[$i] ?? 0);
            if ($peso <= 0 || $peso > $pesoMaximoBytes) {
                $errores[] = "La imagen {$nombreOriginal} excede el limite permitido de 5 MB.";
                continue;
            }

            $tmpPath = (string)($tmps[$i] ?? '');
            $mime = $tmpPath !== '' && is_uploaded_file($tmpPath) ? $finfo->file($tmpPath) : false;
            if (!$mime || !isset($mimePermitidos[$mime])) {
                $errores[] = "Formato no permitido en {$nombreOriginal}. Usa JPG, PNG o WEBP.";
                continue;
            }

            if (!is_dir($dirDestino) && !mkdir($dirDestino, 0755, true) && !is_dir($dirDestino)) {
                $errores[] = 'No se pudo preparar el directorio /uploads/alojamientos/.';
                break;
            }

            $nombreSeguro = bin2hex(random_bytes(12)) . '.' . $mimePermitidos[$mime];
            $rutaAbsoluta = $dirDestino . '/' . $nombreSeguro;

            if (!move_uploaded_file($tmpPath, $rutaAbsoluta)) {
                $errores[] = "No se pudo guardar la imagen {$nombreOriginal}.";
                continue;
            }

            $archivosSubidos[] = $rutaAbsoluta;
            $imagenes[] = 'uploads/alojamientos/' . $nombreSeguro;
        }
    }

    return $imagenes;
}

/**
 * Ejecuta la validacion e insercion transaccional del alojamiento, imagenes y amenidades.
  */
function ejecutarPublicacionAlojamiento(mysqli $conexion, int $idAnfitrion): array
{
    $errores = [];
    $archivosSubidos = [];

    // Campos principales
    $idTipo = isset($_POST['id_tipo']) ? (int)$_POST['id_tipo'] : 0;
    $tipoEspacio = trim((string)($_POST['tipo_espacio'] ?? 'alojamiento_entero'));
    $tiposEspacioValidos = ['alojamiento_entero', 'habitacion_privada', 'habitacion_compartida'];

    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));

    // Ubicacion
    $departamento = trim((string)($_POST['departamento'] ?? ''));
    $municipio = trim((string)($_POST['municipio'] ?? ''));
    $ciudad = trim((string)($_POST['ciudad'] ?? ''));
    if ($ciudad === '' && $municipio !== '') {
        $ciudad = $municipio;
    } elseif ($municipio === '' && $ciudad !== '') {
        $municipio = $ciudad;
    }
    $direccion = trim((string)($_POST['direccion'] ?? ''));
    $referenciasRaw = trim((string)($_POST['referencias'] ?? ''));
    $referencias = $referenciasRaw !== '' ? $referenciasRaw : null;

    $latRaw = trim((string)($_POST['latitud'] ?? ''));
    $lngRaw = trim((string)($_POST['longitud'] ?? ''));
    $latitud = $latRaw !== '' ? (float)$latRaw : null;
    $longitud = $lngRaw !== '' ? (float)$lngRaw : null;

    // Capacidad (con respaldo para campo heredado 'capacidad')
    $capacidadPersonas = (isset($_POST['capacidad_personas']) && $_POST['capacidad_personas'] !== '')
        ? (int)$_POST['capacidad_personas']
        : (int)($_POST['capacidad'] ?? 0);
    $numHabitaciones = (isset($_POST['num_habitaciones']) && $_POST['num_habitaciones'] !== '')
        ? (int)$_POST['num_habitaciones']
        : -1;
    $numCamas = (isset($_POST['num_camas']) && $_POST['num_camas'] !== '')
        ? (int)$_POST['num_camas']
        : 0;
    $numBanos = (isset($_POST['num_banos']) && $_POST['num_banos'] !== '')
        ? (float)$_POST['num_banos']
        : 0.0;

    // Reglas y logistica
    $horaCheckin = normalizarHora((string)($_POST['hora_checkin'] ?? '15:00'));
    $horaCheckout = normalizarHora((string)($_POST['hora_checkout'] ?? '11:00'));
    $estanciaMinima = (isset($_POST['estancia_minima']) && $_POST['estancia_minima'] !== '')
        ? (int)$_POST['estancia_minima']
        : 1;
    $permiteMascotas = !empty($_POST['permite_mascotas']) ? 1 : 0;
    $permiteFumar = !empty($_POST['permite_fumar']) ? 1 : 0;
    $permiteEventos = !empty($_POST['permite_eventos']) ? 1 : 0;
    $reglasRaw = trim((string)($_POST['reglas_adicionales'] ?? ''));
    $reglasAdicionales = $reglasRaw !== '' ? $reglasRaw : null;

    // Tarifas (con respaldo para campo heredado 'precio')
    $precioNoche = (isset($_POST['precio_noche']) && $_POST['precio_noche'] !== '')
        ? (float)$_POST['precio_noche']
        : (float)($_POST['precio'] ?? 0.0);
    $tarifaLimpieza = (isset($_POST['tarifa_limpieza']) && $_POST['tarifa_limpieza'] !== '')
        ? (float)$_POST['tarifa_limpieza']
        : 0.0;

    // Validaciones de negocio
    if ($titulo === '' || mb_strlen($titulo) > 150) {
        $errores[] = 'El titulo es obligatorio y debe tener maximo 150 caracteres.';
    }
    if ($idTipo <= 0) {
        $errores[] = 'Selecciona un tipo de alojamiento valido.';
    }
    if (!in_array($tipoEspacio, $tiposEspacioValidos, true)) {
        $errores[] = 'Selecciona un tipo de espacio valido.';
    }
    if ($descripcion === '') {
        $errores[] = 'La descripcion es obligatoria.';
    }
    if ($departamento === '' || $ciudad === '') {
        $errores[] = 'El departamento y la ciudad/municipio son obligatorios.';
    }
    if ($direccion === '') {
        $errores[] = 'La direccion es obligatoria.';
    }
    if ($latitud !== null && (!is_numeric($latRaw) || $latitud < -90 || $latitud > 90)) {
        $errores[] = 'La latitud debe estar entre -90 y 90.';
    }
    if ($longitud !== null && (!is_numeric($lngRaw) || $longitud < -180 || $longitud > 180)) {
        $errores[] = 'La longitud debe estar entre -180 y 180.';
    }
    if ($capacidadPersonas < 1) {
        $errores[] = 'La capacidad minima debe ser de al menos 1 persona.';
    }
    if ($numHabitaciones < 0) {
        $errores[] = 'El numero de habitaciones debe ser 0 o mayor.';
    }
    if ($numCamas < 1) {
        $errores[] = 'Debe haber al menos 1 cama.';
    }
    if ($numBanos < 0.5) {
        $errores[] = 'El numero de banos debe ser de al menos 0.5.';
    }
    if ($horaCheckin === null || $horaCheckout === null) {
        $errores[] = 'Los horarios de check-in y check-out deben tener formato HH:MM.';
    }
    if ($estanciaMinima < 1) {
        $errores[] = 'La estancia minima debe ser de al menos 1 noche.';
    }
    if ($precioNoche <= 0) {
        $errores[] = 'El precio por noche debe ser mayor a 0.';
    }
    if ($tarifaLimpieza < 0) {
        $errores[] = 'La tarifa de limpieza no puede ser negativa.';
    }

    // Procesar imagenes y validar al menos 1
    $imagenes = procesarImagenesPublicacion($errores, $archivosSubidos);
    if (empty($imagenes)) {
        $errores[] = 'Debes agregar al menos 1 imagen del alojamiento.';
    }

    $indicePortada = isset($_POST['imagen_portada']) ? (int)$_POST['imagen_portada'] : 0;
    if ($indicePortada < 0 || $indicePortada >= count($imagenes)) {
        $indicePortada = 0;
    }

    // Amenidades seleccionadas
    $amenidadesRaw = $_POST['amenidades'] ?? [];
    $amenidadesIds = [];
    if (is_array($amenidadesRaw)) {
        foreach ($amenidadesRaw as $idAm) {
            $idEntero = (int)$idAm;
            if ($idEntero > 0) {
                $amenidadesIds[$idEntero] = $idEntero;
            }
        }
        $amenidadesIds = array_values($amenidadesIds);
    }

    if (!empty($errores)) {
        foreach ($archivosSubidos as $ruta) {
            if (is_file($ruta)) {
                @unlink($ruta);
            }
        }
        return ['ok' => false, 'errores' => $errores];
    }

    $enTransaccion = false;
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conexion->begin_transaction();
        $enTransaccion = true;

        // 1. Insertar en alojamientos (manteniendo columnas heredadas sincronizadas)
        $sqlAlojamiento = "
            INSERT INTO alojamientos (
                id_anfitrion, id_tipo, tipo_espacio, titulo, descripcion,
                pais, departamento, municipio, ciudad, direccion, referencias,
                latitud, longitud, capacidad, capacidad_personas, num_habitaciones,
                num_camas, num_banos, hora_checkin, hora_checkout, estancia_minima,
                permite_mascotas, permite_fumar, permite_eventos, reglas_adicionales,
                precio_persona_noche, precio_noche, tarifa_limpieza, estado
            ) VALUES (
                ?, ?, ?, ?, ?,
                'El Salvador', ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, 'publicado'
            )
        ";
        $stmtAloj = $conexion->prepare($sqlAlojamiento);
        $stmtAloj->bind_param(
            'iissssssssddiiiidssiiiisddd',
            $idAnfitrion,
            $idTipo,
            $tipoEspacio,
            $titulo,
            $descripcion,
            $departamento,
            $municipio,
            $ciudad,
            $direccion,
            $referencias,
            $latitud,
            $longitud,
            $capacidadPersonas,
            $capacidadPersonas,
            $numHabitaciones,
            $numCamas,
            $numBanos,
            $horaCheckin,
            $horaCheckout,
            $estanciaMinima,
            $permiteMascotas,
            $permiteFumar,
            $permiteEventos,
            $reglasAdicionales,
            $precioNoche,
            $precioNoche,
            $tarifaLimpieza
        );
        $stmtAloj->execute();
        $idAlojamiento = (int)$stmtAloj->insert_id;
        $stmtAloj->close();

        // 2. Insertar imagenes en alojamiento_imagenes con portada y orden
        $stmtImg = $conexion->prepare(
            'INSERT INTO alojamiento_imagenes (alojamiento_id, ruta_o_url, es_portada, orden) VALUES (?, ?, ?, ?)'
        );
        foreach ($imagenes as $idx => $rutaOUrl) {
            $esPortada = ($idx === $indicePortada) ? 1 : 0;
            $orden = $idx + 1;
            $stmtImg->bind_param('isii', $idAlojamiento, $rutaOUrl, $esPortada, $orden);
            $stmtImg->execute();
        }
        $stmtImg->close();

        // Sincronizacion opcional en fotos_alojamiento (posicion 1 = portada, max 8)
        $stmtFotoLegacy = $conexion->prepare(
            'INSERT INTO fotos_alojamiento (id_alojamiento, ruta_archivo, posicion) VALUES (?, ?, ?)'
        );
        $ordenadasLegacy = [$imagenes[$indicePortada]];
        foreach ($imagenes as $idx => $rutaOUrl) {
            if ($idx !== $indicePortada) {
                $ordenadasLegacy[] = $rutaOUrl;
            }
        }
        $posLegacy = 1;
        foreach ($ordenadasLegacy as $rutaLegacy) {
            if ($posLegacy > 8) {
                break;
            }
            if (strlen($rutaLegacy) <= 255) {
                $stmtFotoLegacy->bind_param('isi', $idAlojamiento, $rutaLegacy, $posLegacy);
                $stmtFotoLegacy->execute();
                $posLegacy++;
            }
        }
        $stmtFotoLegacy->close();

        // 3. Insertar amenidades seleccionadas (N:M)
        if (!empty($amenidadesIds)) {
            $stmtAmenidad = $conexion->prepare(
                'INSERT INTO alojamiento_amenidades (alojamiento_id, amenidad_id) VALUES (?, ?)'
            );
            foreach ($amenidadesIds as $amenidadId) {
                $stmtAmenidad->bind_param('ii', $idAlojamiento, $amenidadId);
                $stmtAmenidad->execute();
            }
            $stmtAmenidad->close();
        }

        $conexion->commit();
        $enTransaccion = false;

        return [
            'ok' => true,
            'id_alojamiento' => $idAlojamiento,
            'mensaje' => 'Alojamiento publicado correctamente.'
        ];
    } catch (Throwable $e) {
        if ($enTransaccion) {
            $conexion->rollback();
        }
        foreach ($archivosSubidos as $ruta) {
            if (is_file($ruta)) {
                @unlink($ruta);
            }
        }

        $codigo = $e->getCode();
        $msg = ($codigo === 1452)
            ? 'Uno de los catalogos seleccionados (tipo o amenidad) no existe.'
            : 'Error al guardar el alojamiento en la base de datos.';

        return ['ok' => false, 'errores' => [$msg]];
    }
}

// Punto de entrada segun ejecucion directa a inclusion desde publicar_casa.php
$esEntradaDirecta = basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'procesar_publicacion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $desdePanel = ($_POST['desde'] ?? '') === 'anfitrion' || ($_SESSION['modo'] ?? '') === 'anfitrion';
    $destino = $desdePanel ? 'mis_alojamientos.php' : 'index.php?publicado=1';
    $resultado = tokenFormularioValido()
        ? ejecutarPublicacionAlojamiento($conexion, (int) $_SESSION['id_usuario'])
        : ['ok' => false, 'errores' => ['Recarga la página y vuelve a publicar.']];

    if (esPeticionJson()) {
        if ($resultado['ok']) {
            responderJson(201, [
                'ok' => true,
                'id_alojamiento' => $resultado['id_alojamiento'],
                'mensaje' => $resultado['mensaje'],
                'redirect' => $destino
            ]);
        }
        responderJson(422, [
            'ok' => false,
            'error' => $resultado['errores'][0] ?? 'Datos invalidos.',
            'errores' => $resultado['errores']
        ]);
    }

    if ($resultado['ok']) {
        header('Location: ' . $destino, true, 303);
        exit;
    }

    $mensajeError = implode(' ', $resultado['errores']);
    if ($esEntradaDirecta) {
        $_SESSION['error_publicacion'] = $mensajeError;
        header('Location: publicar_casa.php' . ($desdePanel ? '?desde=anfitrion' : ''), true, 303);
        exit;
    }
} elseif ($esEntradaDirecta) {
    if (esPeticionJson()) {
        responderJson(405, ['ok' => false, 'error' => 'Metodo no permitido.']);
    }
    header('Location: publicar_casa.php');
    exit;
}
