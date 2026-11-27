<?php

/**
 * Redimensiona y comprime una imagen subida (JPG, PNG o WEBP) usando GD.
 * Si la extensión GD no está habilitada en el servidor, guarda el archivo original sin fallar.
 *
 * @return array{ok: bool, archivo: string, error: string}
 */
function optimizarYGuardarImagen(
    string $tmpPath,
    string $carpetaDestino,
    string $prefijoNombre,
    string $extOriginal,
    int $maxAncho = 1280,
    int $maxAlto = 1280,
    int $calidad = 82
): array {
    $info = @getimagesize($tmpPath);
    if ($info === false) {
        return ['ok' => false, 'archivo' => '', 'error' => 'El archivo no es una imagen válida.'];
    }

    if (!is_dir($carpetaDestino)) {
        mkdir($carpetaDestino, 0755, true);
    }

    $extOriginal = strtolower($extOriginal);
    $nombreBase = uniqid($prefijoNombre, true);

    // Respaldo seguro si GD aún no está activo en PHP
    if (!function_exists('imagecreatetruecolor')) {
        $nombreArchivo = $nombreBase . '.' . $extOriginal;
        $rutaAbsoluta = rtrim($carpetaDestino, '/\\') . DIRECTORY_SEPARATOR . $nombreArchivo;
        $guardado = is_uploaded_file($tmpPath)
            ? move_uploaded_file($tmpPath, $rutaAbsoluta)
            : @copy($tmpPath, $rutaAbsoluta);

        if (!$guardado) {
            return ['ok' => false, 'archivo' => '', 'error' => 'No se pudo guardar la imagen en el servidor.'];
        }

        return ['ok' => true, 'archivo' => $nombreArchivo, 'error' => ''];
    }

    [$anchoOrig, $altoOrig, $tipoImg] = $info;
    $origen = null;

    if ($tipoImg === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) {
        $origen = @imagecreatefromjpeg($tmpPath);
        // Corregir rotación automática de fotos tomadas desde celular si EXIF está disponible
        if ($origen && function_exists('exif_read_data')) {
            $exif = @exif_read_data($tmpPath);
            $orientacion = (int) ($exif['Orientation'] ?? 1);
            if ($orientacion === 3) {
                $origen = imagerotate($origen, 180, 0);
            } elseif ($orientacion === 6) {
                $origen = imagerotate($origen, -90, 0);
                [$anchoOrig, $altoOrig] = [$altoOrig, $anchoOrig];
            } elseif ($orientacion === 8) {
                $origen = imagerotate($origen, 90, 0);
                [$anchoOrig, $altoOrig] = [$altoOrig, $anchoOrig];
            }
        }
    } elseif ($tipoImg === IMAGETYPE_PNG && function_exists('imagecreatefrompng')) {
        $origen = @imagecreatefrompng($tmpPath);
    } elseif ($tipoImg === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
        $origen = @imagecreatefromwebp($tmpPath);
    }

    if (!$origen) {
        return ['ok' => false, 'archivo' => '', 'error' => 'No se pudo procesar el formato de la imagen.'];
    }

    // Calcular dimensiones máximas manteniendo proporción (sin agrandar imágenes pequeñas)
    $escala = min($maxAncho / max(1, $anchoOrig), $maxAlto / max(1, $altoOrig), 1);
    $nuevoAncho = max(1, (int) round($anchoOrig * $escala));
    $nuevoAlto = max(1, (int) round($altoOrig * $escala));

    $lienzo = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
    imagealphablending($lienzo, false);
    imagesavealpha($lienzo, true);
    $transparente = imagecolorallocatealpha($lienzo, 0, 0, 0, 127);
    imagefilledrectangle($lienzo, 0, 0, $nuevoAncho, $nuevoAlto, $transparente);

    imagecopyresampled($lienzo, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $anchoOrig, $altoOrig);
    imagedestroy($origen);

    // Priorizar WebP por menor peso; si no está compilado en GD, usar JPEG o PNG comprimido
    $guardado = false;
    $extFinal = $extOriginal;

    if (function_exists('imagewebp')) {
        $extFinal = 'webp';
        $nombreArchivo = $nombreBase . '.' . $extFinal;
        $rutaAbsoluta = rtrim($carpetaDestino, '/\\') . DIRECTORY_SEPARATOR . $nombreArchivo;
        $guardado = @imagewebp($lienzo, $rutaAbsoluta, $calidad);
    } elseif ($tipoImg === IMAGETYPE_PNG) {
        $extFinal = 'png';
        $nombreArchivo = $nombreBase . '.' . $extFinal;
        $rutaAbsoluta = rtrim($carpetaDestino, '/\\') . DIRECTORY_SEPARATOR . $nombreArchivo;
        $guardado = @imagepng($lienzo, $rutaAbsoluta, 6);
    } else {
        $extFinal = 'jpg';
        $nombreArchivo = $nombreBase . '.' . $extFinal;
        $rutaAbsoluta = rtrim($carpetaDestino, '/\\') . DIRECTORY_SEPARATOR . $nombreArchivo;
        $guardado = @imagejpeg($lienzo, $rutaAbsoluta, $calidad);
    }

    imagedestroy($lienzo);

    if (!$guardado) {
        return ['ok' => false, 'archivo' => '', 'error' => 'No se pudo guardar la imagen optimizada.'];
    }

    return ['ok' => true, 'archivo' => $nombreArchivo, 'error' => ''];
}
