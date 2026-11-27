<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/imagenes.php';

/**
 * Valida hora en formato HH:MM o HH:MM:SS y la devuelve como HH:MM:SS.
 */
function normalizarHora($valor)
{
    $valor = trim((string) $valor);
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $valor, $m)) {
        return null;
    }
    return $m[1] . ':' . $m[2] . ':' . ($m[3] ?? '00');
}

/**
 * Procesa URLs de imágenes y archivos subidos desde $_FILES['imagenes'].
 */
function procesarImagenesPublicacion(&$errores, &$archivosSubidos)
{
    $imagenes = [];

    // 1. Imágenes por URL
    $urlsRaw = $_POST['imagenes_url'] ?? [];
    if (!is_array($urlsRaw)) {
        $urlsRaw = [$urlsRaw];
    }

    foreach ($urlsRaw as $url) {
        $url = trim((string) $url);
        if ($url === '') {
            continue;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url) || strlen($url) > 500) {
            $errores[] = "La URL de imagen no es válida o supera los 500 caracteres.";
            continue;
        }
        $imagenes[] = $url;
    }

    // 2. Archivos subidos desde el dispositivo
    if (isset($_FILES['imagenes']) && is_array($_FILES['imagenes']['name'] ?? null)) {
        $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];
        $pesoMaximo = 5 * 1024 * 1024; // 5 MB
        $carpetaDestino = __DIR__ . '/../uploads/alojamientos';

        $total = count($_FILES['imagenes']['name']);
        for ($i = 0; $i < $total; $i++) {
            $errorArchivo = $_FILES['imagenes']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($errorArchivo === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $nombreOriginal = $_FILES['imagenes']['name'][$i] ?? 'archivo';
            if ($errorArchivo !== UPLOAD_ERR_OK) {
                $errores[] = "Error al subir el archivo {$nombreOriginal}.";
                continue;
            }

            $peso = (int) ($_FILES['imagenes']['size'][$i] ?? 0);
            if ($peso <= 0 || $peso > $pesoMaximo) {
                $errores[] = "La imagen {$nombreOriginal} supera el límite de 5 MB.";
                continue;
            }

            $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
            if (!in_array($ext, $extensionesPermitidas, true)) {
                $errores[] = "Formato no permitido en {$nombreOriginal}. Usa JPG, PNG o WEBP.";
                continue;
            }

            $tmpPath = $_FILES['imagenes']['tmp_name'][$i] ?? '';
            if ($tmpPath === '' || @getimagesize($tmpPath) === false) {
                $errores[] = "El archivo {$nombreOriginal} no es una imagen válida.";
                continue;
            }

            $resultadoImg = optimizarYGuardarImagen($tmpPath, $carpetaDestino, 'aloj_', $ext, 1280, 1280, 82);
            if (!$resultadoImg['ok']) {
                $errores[] = "No se pudo guardar la imagen {$nombreOriginal}: " . $resultadoImg['error'];
                continue;
            }

            $nombreNuevo = $resultadoImg['archivo'];
            $rutaAbsoluta = $carpetaDestino . '/' . $nombreNuevo;

            $archivosSubidos[] = $rutaAbsoluta;
            $imagenes[] = 'uploads/alojamientos/' . $nombreNuevo;
        }
    }

    return $imagenes;
}

// Si alguien entra directo por GET, regresar al formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: publicar_casa.php');
    exit;
}

verificar_csrf();

$idAnfitrion = (int) $_SESSION['id_usuario'];
$errores = [];
$archivosSubidos = [];

// Datos básicos
$idTipo = (int) ($_POST['id_tipo'] ?? 0);
$tipoEspacio = trim($_POST['tipo_espacio'] ?? 'alojamiento_entero');
$tiposEspacioValidos = ['alojamiento_entero', 'habitacion_privada', 'habitacion_compartida'];
$titulo = trim($_POST['titulo'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');

// Ubicación
$departamento = trim($_POST['departamento'] ?? '');
$ciudad = trim($_POST['ciudad'] ?? '');
$municipio = trim($_POST['municipio'] ?? '');
if ($municipio === '') {
    $municipio = $ciudad;
}
if ($ciudad === '') {
    $ciudad = $municipio;
}
$direccion = trim($_POST['direccion'] ?? '');
$referenciasRaw = trim($_POST['referencias'] ?? '');
$referencias = $referenciasRaw !== '' ? $referenciasRaw : null;

$latRaw = trim($_POST['latitud'] ?? '');
$lngRaw = trim($_POST['longitud'] ?? '');
$latitud = ($latRaw !== '' && is_numeric($latRaw)) ? (float) $latRaw : null;
$longitud = ($lngRaw !== '' && is_numeric($lngRaw)) ? (float) $lngRaw : null;

// Capacidad y distribución
$capacidadPersonas = (int) ($_POST['capacidad_personas'] ?? 0);
$numHabitaciones = (int) ($_POST['num_habitaciones'] ?? -1);
$numCamas = (int) ($_POST['num_camas'] ?? 0);
$numBanos = (float) ($_POST['num_banos'] ?? 0);

// Reglas y horarios
$horaCheckin = normalizarHora($_POST['hora_checkin'] ?? '15:00');
$horaCheckout = normalizarHora($_POST['hora_checkout'] ?? '11:00');
$estanciaMinima = (int) ($_POST['estancia_minima'] ?? 1);
$permiteMascotas = !empty($_POST['permite_mascotas']) ? 1 : 0;
$permiteFumar = !empty($_POST['permite_fumar']) ? 1 : 0;
$permiteEventos = !empty($_POST['permite_eventos']) ? 1 : 0;
$reglasRaw = trim($_POST['reglas_adicionales'] ?? '');
$reglasAdicionales = $reglasRaw !== '' ? $reglasRaw : null;

// Tarifas
$precioNoche = (float) ($_POST['precio_noche'] ?? 0);
$tarifaLimpieza = (float) ($_POST['tarifa_limpieza'] ?? 0);

// Validaciones
if ($titulo === '' || mb_strlen($titulo) > 150) {
    $errores[] = 'El título es obligatorio (máximo 150 caracteres).';
}
if ($idTipo <= 0) {
    $errores[] = 'Selecciona un tipo de propiedad válido.';
} else {
    $stmtTipo = $conexion->prepare("SELECT id_tipo FROM tipos_alojamiento WHERE id_tipo = ? LIMIT 1");
    $stmtTipo->bind_param('i', $idTipo);
    $stmtTipo->execute();
    if ($stmtTipo->get_result()->num_rows === 0) {
        $errores[] = 'El tipo de propiedad seleccionado no existe.';
    }
    $stmtTipo->close();
}
if (!in_array($tipoEspacio, $tiposEspacioValidos, true)) {
    $errores[] = 'Selecciona un tipo de espacio válido.';
}
if ($descripcion === '') {
    $errores[] = 'La descripción es obligatoria.';
}
if ($departamento === '' || $ciudad === '' || $direccion === '') {
    $errores[] = 'Departamento, ciudad/municipio y dirección son obligatorios.';
} elseif (mb_strlen($departamento) > 100 || mb_strlen($ciudad) > 100 || mb_strlen($direccion) > 255) {
    $errores[] = 'Departamento/ciudad (máx. 100) o dirección (máx. 255) superan el límite permitido.';
}
if ($referencias !== null && mb_strlen($referencias) > 500) {
    $errores[] = 'Las referencias de llegada deben tener máximo 500 caracteres.';
}
if ($reglasAdicionales !== null && mb_strlen($reglasAdicionales) > 2000) {
    $errores[] = 'Las reglas adicionales deben tener máximo 2000 caracteres.';
}
if ($latitud === null || $longitud === null || $latitud < -90 || $latitud > 90 || $longitud < -180 || $longitud > 180) {
    $errores[] = 'Debes marcar la ubicación exacta en el mapa.';
}
if ($capacidadPersonas < 1 || $capacidadPersonas > 50 || $numHabitaciones < 0 || $numHabitaciones > 30 || $numCamas < 1 || $numCamas > 50 || $numBanos < 0.5 || $numBanos > 30) {
    $errores[] = 'Verifica que capacidad, habitaciones, camas y baños estén dentro de los límites permitidos.';
}
if ($horaCheckin === null || $horaCheckout === null || $estanciaMinima < 1 || $estanciaMinima > 365) {
    $errores[] = 'Verifica los horarios y la estancia mínima (1 a 365 noches).';
}
if ($precioNoche <= 0 || $precioNoche > 99999 || $tarifaLimpieza < 0 || $tarifaLimpieza > 99999) {
    $errores[] = 'El precio debe ser mayor a $0 y las tarifas no pueden superar $99,999.';
}

// Imágenes (mínimo 1, máximo 8)
$imagenes = procesarImagenesPublicacion($errores, $archivosSubidos);
if (count($imagenes) < 1) {
    $errores[] = 'Debes agregar al menos 1 imagen del alojamiento.';
} elseif (count($imagenes) > 8) {
    $errores[] = 'Puedes subir un máximo de 8 imágenes por alojamiento.';
}

// Amenidades seleccionadas (validar que existan en catálogo)
$amenidadesIds = [];
if (!empty($_POST['amenidades']) && is_array($_POST['amenidades'])) {
    $resAmValidas = $conexion->query("SELECT id FROM amenidades");
    $amValidas = $resAmValidas ? array_map('intval', array_column($resAmValidas->fetch_all(MYSQLI_ASSOC), 'id')) : [];
    foreach ($_POST['amenidades'] as $idAm) {
        $idEntero = (int) $idAm;
        if ($idEntero > 0 && in_array($idEntero, $amValidas, true)) {
            $amenidadesIds[] = $idEntero;
        }
    }
    $amenidadesIds = array_unique($amenidadesIds);
}

// Métodos de pago informativos seleccionados (validar que existan en catálogo)
$metodosPagoIds = [];
if (!empty($_POST['metodos_pago']) && is_array($_POST['metodos_pago'])) {
    $resMpValidos = $conexion->query("SELECT id_metodo_pago FROM metodos_pago");
    $mpValidos = $resMpValidos ? array_map('intval', array_column($resMpValidos->fetch_all(MYSQLI_ASSOC), 'id_metodo_pago')) : [];
    foreach ($_POST['metodos_pago'] as $idMp) {
        $idEntero = (int) $idMp;
        if ($idEntero > 0 && in_array($idEntero, $mpValidos, true)) {
            $metodosPagoIds[] = $idEntero;
        }
    }
    $metodosPagoIds = array_unique($metodosPagoIds);
}

if (empty($metodosPagoIds)) {
    $errores[] = 'Selecciona al menos un método de pago informativo que aceptas.';
}

// Verificar que el usuario esté activo
$stmtEstado = $conexion->prepare("SELECT estado FROM usuarios WHERE id_usuario = ?");
$stmtEstado->bind_param('i', $idAnfitrion);
$stmtEstado->execute();
$filaUsuario = $stmtEstado->get_result()->fetch_assoc();
if (!$filaUsuario || $filaUsuario['estado'] !== 'activo') {
    $errores[] = 'Tu cuenta no está activa para publicar alojamientos.';
}

if (!empty($errores)) {
    foreach ($archivosSubidos as $ruta) {
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }
    $_SESSION['error_publicacion'] = implode(' ', $errores);
    header('Location: publicar_casa.php');
    exit;
}

// Guardar en base de datos dentro de una transacción
$conexion->begin_transaction();
try {
    $sql = "INSERT INTO alojamientos (
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
            )";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param(
        'iissssssssddiiiidssiiiisddd',
        $idAnfitrion, $idTipo, $tipoEspacio, $titulo, $descripcion,
        $departamento, $municipio, $ciudad, $direccion, $referencias,
        $latitud, $longitud, $capacidadPersonas, $capacidadPersonas, $numHabitaciones,
        $numCamas, $numBanos, $horaCheckin, $horaCheckout, $estanciaMinima,
        $permiteMascotas, $permiteFumar, $permiteEventos, $reglasAdicionales,
        $precioNoche, $precioNoche, $tarifaLimpieza
    );
    $stmt->execute();
    $idAlojamiento = (int) $stmt->insert_id;

    // Guardar imágenes en alojamiento_imagenes y fotos_alojamiento
    $stmtImg = $conexion->prepare("INSERT INTO alojamiento_imagenes (alojamiento_id, ruta_o_url, es_portada, orden) VALUES (?, ?, ?, ?)");
    $stmtFotoLegacy = $conexion->prepare("INSERT INTO fotos_alojamiento (id_alojamiento, ruta_archivo, posicion) VALUES (?, ?, ?)");

    foreach ($imagenes as $idx => $rutaOUrl) {
        $esPortada = ($idx === 0) ? 1 : 0;
        $orden = $idx + 1;
        $stmtImg->bind_param('isii', $idAlojamiento, $rutaOUrl, $esPortada, $orden);
        $stmtImg->execute();

        if ($orden <= 8 && strlen($rutaOUrl) <= 255) {
            $stmtFotoLegacy->bind_param('isi', $idAlojamiento, $rutaOUrl, $orden);
            $stmtFotoLegacy->execute();
        }
    }

    // Guardar amenidades
    if (!empty($amenidadesIds)) {
        $stmtAm = $conexion->prepare("INSERT INTO alojamiento_amenidades (alojamiento_id, amenidad_id) VALUES (?, ?)");
        foreach ($amenidadesIds as $amId) {
            $stmtAm->bind_param('ii', $idAlojamiento, $amId);
            $stmtAm->execute();
        }
    }

    // Guardar métodos de pago informativos
    $stmtMp = $conexion->prepare("INSERT INTO alojamiento_metodo_pago (id_alojamiento, id_metodo_pago) VALUES (?, ?)");
    foreach ($metodosPagoIds as $mpId) {
        $stmtMp->bind_param('ii', $idAlojamiento, $mpId);
        $stmtMp->execute();
    }

    $conexion->commit();
    $_SESSION['flash'] = ['tipo' => 'success', 'texto' => '¡Alojamiento publicado correctamente!'];
    header('Location: ../mis_alojamientos.php');
    exit;
} catch (Throwable $e) {
    $conexion->rollback();
    foreach ($archivosSubidos as $ruta) {
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }
    $_SESSION['error_publicacion'] = 'Ocurrió un error al guardar el alojamiento en la base de datos.';
    header('Location: publicar_casa.php');
    exit;
}
