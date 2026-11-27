<?php

/**
 * Envía un código de verificación de 6 dígitos usando cURL y la API de Brevo (sin dependencias externas),
 * o en modo 'simulado' cuando está configurado así para pruebas locales.
 *
 * @return array{ok: bool, simulado: bool, mensaje: string}
 */
function enviarCodigoVerificacion(string $correoDestino, string $nombreDestino, string $codigo): array
{
    $rutaConfig = __DIR__ . '/correo.local.php';
    if (!file_exists($rutaConfig)) {
        return [
            'ok' => false,
            'simulado' => false,
            'mensaje' => 'Falta configurar config/correo.local.php para enviar códigos de verificación.'
        ];
    }

    $configCorreo = require $rutaConfig;
    $modo = $configCorreo['modo'] ?? 'simulado';

    if ($modo === 'simulado') {
        return [
            'ok' => true,
            'simulado' => true,
            'mensaje' => 'Modo de prueba local activo: código generado sin envío SMTP/API.'
        ];
    }

    if ($modo !== 'brevo') {
        return [
            'ok' => false,
            'simulado' => false,
            'mensaje' => 'Modo de correo no válido en config/correo.local.php.'
        ];
    }

    $apiKey = trim((string) ($configCorreo['api_key'] ?? ''));
    $remitenteCorreo = trim((string) ($configCorreo['remitente_correo'] ?? ''));
    $remitenteNombre = trim((string) ($configCorreo['remitente_nombre'] ?? 'HALOJA'));

    if ($apiKey === '' || $remitenteCorreo === '') {
        return [
            'ok' => false,
            'simulado' => false,
            'mensaje' => 'Falta completar api_key o remitente_correo en config/correo.local.php.'
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'simulado' => false,
            'mensaje' => 'La extensión cURL de PHP no está disponible en el servidor.'
        ];
    }

    $nombreSeguro = htmlspecialchars($nombreDestino, ENT_QUOTES, 'UTF-8');
    $codigoSeguro = htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8');

    $payload = [
        'sender' => [
            'name'  => $remitenteNombre !== '' ? $remitenteNombre : 'HALOJA',
            'email' => $remitenteCorreo,
        ],
        'to' => [
            [
                'email' => $correoDestino,
                'name'  => $nombreDestino,
            ],
        ],
        'subject' => 'Tu código de verificación - HALOJA',
        'htmlContent' =>
            '<div style="font-family: Arial, sans-serif; max-width: 520px; margin: 0 auto; padding: 20px;">' .
                '<h2 style="color: #1b4332; margin-bottom: 12px;">Verifica tu correo en HALOJA</h2>' .
                '<p>Hola <strong>' . $nombreSeguro . '</strong>,</p>' .
                '<p>Ingresa este código para completar tu registro:</p>' .
                '<div style="font-size: 30px; font-weight: bold; letter-spacing: 6px; padding: 14px 20px; background: #f1f5f2; border-radius: 8px; text-align: center; color: #1b4332; margin: 16px 0;">' .
                    $codigoSeguro .
                '</div>' .
                '<p style="color: #555; font-size: 14px;">Este código vence en 10 minutos. Si no intentaste crear una cuenta, puedes ignorar este correo.</p>' .
            '</div>',
        'textContent' => "Hola {$nombreDestino},\n\nTu código de verificación en HALOJA es: {$codigo}\nEste código vence en 10 minutos.",
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);

    $respuesta = curl_exec($ch);
    $errorCurl = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($respuesta === false || $errorCurl !== '') {
        return [
            'ok' => false,
            'simulado' => false,
            'mensaje' => 'No se pudo conectar con el servicio de correo. Intenta nuevamente más tarde.'
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            'ok' => false,
            'simulado' => false,
            'mensaje' => 'El servicio de correo rechazó el envío (HTTP ' . $httpCode . '). Revisa tu API key y remitente verificado.'
        ];
    }

    return [
        'ok' => true,
        'simulado' => false,
        'mensaje' => 'Código enviado correctamente al correo.'
    ];
}
