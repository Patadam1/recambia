<?php
/**
 * Copia este archivo como app/config.php y rellena los datos de Hostinger.
 * app/config.php NO se sube a ningún repositorio: contiene contraseñas.
 */
return [
    // Dirección pública de la web, sin barra final
    'site_url' => 'https://recambia.es',

    // Base de datos MySQL (Hostinger → Bases de datos → MySQL)
    'db' => [
        'host' => 'localhost',
        'name' => 'u000000000_recambia',
        'user' => 'u000000000_recambia',
        'pass' => 'CAMBIA_ESTA_CONTRASEÑA',
    ],

    // Correos
    'mail_from'      => 'hola@recambia.es',   // buzón creado en Hostinger
    'mail_from_name' => 'Recambia',
    'mail_owner'     => 'hola@recambia.es',   // dónde llegan los avisos de solicitud nueva

    // Textos que salen en los correos
    'plazo_horas' => '24',

    // Seguridad
    'ip_salt'         => 'CAMBIA_ESTO_POR_UNA_FRASE_LARGA_Y_ALEATORIA',
    'max_por_hora_ip' => 5,
    'min_segundos'    => 4,   // un humano tarda más que esto en rellenar el formulario

    // Fotos
    'max_fotos' => 3,
    'max_mb'    => 8,
];
