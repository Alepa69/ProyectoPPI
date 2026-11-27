<?php
// Copia este archivo como config/correo.local.php y coloca tus datos reales.
// El archivo correo.local.php está en .gitignore para no subir la API key a Git.
return [
    // 'simulado' muestra el código en pantalla para probar en local sin internet ni API key.
    // 'brevo' envía el correo real usando cURL y la API HTTP de Brevo (sin librerías externas).
    'modo' => 'simulado',

    // Datos para modo 'brevo' (https://www.brevo.com/)
    'api_key' => 'PON_AQUI_TU_API_KEY_DE_BREVO',
    'remitente_correo' => 'tu_correo_verificado@gmail.com',
    'remitente_nombre' => 'HALOJA',
];

//para el host

// 'modo' => 'brevo'

  
// 'api_key' => 'TU_API_KEY'

  
// 'remitente_correo' => 'tu_correo_verificado@gmail.com'
