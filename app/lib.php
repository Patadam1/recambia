<?php
declare(strict_types=1);

/** Utilidades compartidas: configuración, base de datos y correos. */

function config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . '/config.php';
        if (!is_file($file)) {
            throw new RuntimeException('Falta app/config.php (copia app/config.example.php).');
        }
        $cfg = require $file;
    }
    return $cfg;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config()['db'];
        $pdo = new PDO(
            'mysql:host=' . $c['host'] . ';dbname=' . $c['name'] . ';charset=utf8mb4',
            $c['user'],
            $c['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
    return $pdo;
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Envía un correo HTML con versión de texto. Devuelve true si el servidor lo aceptó. */
function send_mail(string $to, string $subject, string $html, string $text, ?string $replyTo = null): bool
{
    $c = config();
    $boundary = 'b' . bin2hex(random_bytes(12));
    $fromName = mb_encode_mimeheader($c['mail_from_name'], 'UTF-8', 'Q');

    $headers = [
        'MIME-Version: 1.0',
        'From: ' . $fromName . ' <' . $c['mail_from'] . '>',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: Recambia',
    ];
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    $body = "--$boundary\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text)) . "\r\n"
        . "--$boundary\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html)) . "\r\n"
        . "--$boundary--";

    return @mail(
        $to,
        mb_encode_mimeheader($subject, 'UTF-8', 'B'),
        $body,
        implode("\r\n", $headers),
        '-f' . $c['mail_from']
    );
}

/** Descripción legible del coche. */
function coche_texto(array $s): string
{
    $partes = array_filter([$s['marca'] ?? '', $s['modelo'] ?? '', $s['motor'] ?? '']);
    $txt = implode(' ', $partes);
    if (!empty($s['anio'])) {
        $txt .= ($txt ? ' · ' : '') . $s['anio'];
    }
    return $txt;
}

function matricula_bonita(?string $m): string
{
    $m = (string) $m;
    return preg_match('/^(\d{4})([A-Z]{3})$/', $m, $x) ? $x[1] . ' ' . $x[2] : $m;
}

/** Plantilla común de correo (tablas e estilos en línea para que se vea bien en Gmail/Outlook). */
function mail_layout(string $ref, string $inner, string $pie): string
{
    return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
        . '<body style="margin:0;padding:0;background:#E9E6E0;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#E9E6E0;"><tr><td align="center" style="padding:24px 12px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#FFFFFF;border-radius:14px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#16181B;">'
        . '<tr><td style="background:#16181B;padding:20px 28px;">'
        . '<table role="presentation" width="100%"><tr>'
        . '<td style="font-size:22px;font-weight:bold;color:#FFFFFF;letter-spacing:-0.5px;">Recambia</td>'
        . '<td align="right" style="font-family:Consolas,Menlo,monospace;font-size:14px;font-weight:bold;color:#FFC21A;">#' . e($ref) . '</td>'
        . '</tr></table></td></tr>'
        . '<tr><td style="padding:32px 28px;font-size:16px;line-height:1.6;">' . $inner . '</td></tr>'
        . '<tr><td style="background:#F5F3EF;padding:16px 28px;font-size:12px;line-height:1.6;color:#565A61;">' . $pie . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function fila(string $k, string $v, bool $mono = false): string
{
    $font = $mono ? 'font-family:Consolas,Menlo,monospace;' : '';
    return '<tr><td style="padding:10px 14px;border-top:1px solid #E2DED6;color:#565A61;">' . e($k) . '</td>'
        . '<td align="right" style="padding:10px 14px;border-top:1px solid #E2DED6;font-weight:bold;' . $font . '">' . e($v) . '</td></tr>';
}

/** Correo de confirmación para el cliente. */
function mail_cliente(array $s, string $ref): array
{
    $c = config();
    $nombre = $s['nombre'];
    $coche = coche_texto($s);
    $mat = matricula_bonita($s['matricula'] ?? '');
    $refPieza = $s['referencia'] ?? '';
    $pieza = $s['pieza'] !== '' ? mb_strimwidth($s['pieza'], 0, 160, '…') : '';
    if ($refPieza !== '') {
        $paraCoche = ' de la pieza con referencia <strong>' . e($refPieza) . '</strong>';
    } else {
        $paraCoche = $coche ? ' para tu <strong>' . e($coche) . '</strong>' : ($mat ? ' para el coche con matrícula <strong>' . e($mat) . '</strong>' : '');
    }

    $tabla = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #E2DED6;border-radius:10px;font-size:15px;margin:22px 0;">'
        . '<tr><td colspan="2" style="padding:10px 14px;background:#F5F3EF;font-size:12px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#565A61;">Resumen de tu solicitud</td></tr>'
        . ($refPieza !== '' ? fila('Referencia', $refPieza, true) : '')
        . (!empty($s['fabricante']) ? fila('Marca de la pieza', $s['fabricante']) : '')
        . ($mat ? fila('Matrícula', $mat, true) : '')
        . ($coche ? fila('Coche', $coche) : '')
        . ($pieza !== '' ? fila('Pieza', $pieza) : '')
        . fila('Envío a', 'CP ' . $s['cp'])
        . '</table>';

    $aviso = '';
    if ($refPieza === '' && !$mat && empty($s['bastidor'])) {
        $aviso = '<p style="margin:0 0 18px;padding:12px 14px;background:#FFF4CC;border-radius:8px;font-size:15px;">'
            . '<strong>Un favor:</strong> si puedes, responde a este correo con la matrícula o el nº de bastidor (está en la ficha técnica). Así nos aseguramos de que la pieza encaja.</p>';
    }

    $pasos = '';
    $lista = [
        'Te enviamos el presupuesto por correo, con precio cerrado y el envío incluido.',
        'Si te encaja, pagas por transferencia o Bizum.',
        'La recibes en casa, normalmente en 24–72 h en península.',
    ];
    foreach ($lista as $i => $t) {
        $pasos .= '<tr><td width="36" valign="top" style="padding:6px 0;"><div style="width:26px;height:26px;line-height:26px;border-radius:13px;background:#16181B;color:#FFC21A;text-align:center;font-weight:bold;font-size:13px;">' . ($i + 1) . '</div></td>'
            . '<td style="padding:8px 0;font-size:15px;">' . e($t) . '</td></tr>';
    }

    $inner = '<h1 style="margin:0 0 14px;font-size:28px;line-height:1.15;">¡Hola, ' . e($nombre) . '! Ya estamos buscando tu pieza.</h1>'
        . '<p style="margin:0 0 4px;">Hemos recibido tu solicitud' . $paraCoche . '. Vamos a comprobar la referencia exacta y te enviaremos el presupuesto en menos de <strong>' . e($c['plazo_horas']) . ' horas</strong>.</p>'
        . $tabla . $aviso
        . '<p style="margin:0 0 6px;font-weight:bold;">¿Qué pasa ahora?</p>'
        . '<table role="presentation" cellspacing="0" cellpadding="0">' . $pasos . '</table>'
        . '<p style="margin:22px 0 0;color:#565A61;font-size:15px;">¿Algo no está bien? Responde a este correo y lo corregimos.</p>'
        . '<p style="margin:14px 0 0;font-weight:bold;">El equipo de Recambia</p>';

    $pie = '[RAZÓN SOCIAL O NOMBRE DEL TITULAR] · NIF [NIF] · [DIRECCIÓN].<br>Recibes este correo porque has pedido un presupuesto en ' . e(preg_replace('#^https?://#', '', $c['site_url'])) . '.';

    $text = "¡Hola, $nombre! Ya estamos buscando tu pieza.\n\n"
        . "Solicitud: #$ref\n"
        . ($refPieza !== '' ? "Referencia: $refPieza\n" : '')
        . (!empty($s['fabricante']) ? "Marca de la pieza: {$s['fabricante']}\n" : '')
        . ($mat ? "Matrícula: $mat\n" : '')
        . ($coche ? "Coche: $coche\n" : '')
        . ($pieza !== '' ? "Pieza: $pieza\n" : '')
        . "Envío a: CP {$s['cp']}\n\n"
        . "Te enviaremos el presupuesto en menos de {$c['plazo_horas']} horas.\n"
        . "Si algo no está bien, responde a este correo.\n\nEl equipo de Recambia";

    return ['Hemos recibido tu solicitud #' . $ref, mail_layout($ref, $inner, $pie), $text];
}

/** Aviso interno para el dueño. Responder a este correo contesta directamente al cliente. */
function mail_dueno(array $s, string $ref, array $fotos): array
{
    $c = config();
    $coche = coche_texto($s);
    $mat = matricula_bonita($s['matricula'] ?? '');
    $refPieza = $s['referencia'] ?? '';
    $piezaTxt = $s['pieza'] !== '' ? $s['pieza'] : '(Sin descripción: el cliente ha dado la referencia)';

    $tabla = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #E2DED6;border-radius:10px;font-size:15px;margin:0 0 20px;">'
        . ($refPieza !== '' ? fila('Referencia', $refPieza, true) : '')
        . (!empty($s['fabricante']) ? fila('Marca de la pieza', $s['fabricante']) : '')
        . ($mat ? fila('Matrícula', $mat, true) : '')
        . (!empty($s['bastidor']) ? fila('Bastidor', $s['bastidor'], true) : '')
        . ($coche ? fila('Coche', $coche) : '')
        . fila('Nombre', $s['nombre'])
        . fila('Email', $s['email'])
        . fila('Teléfono', $s['telefono'])
        . fila('Código postal', $s['cp'])
        . '</table>';

    $fotosHtml = '';
    if ($fotos) {
        $fotosHtml = '<p style="margin:0 0 8px;font-weight:bold;">Fotos</p><p style="margin:0 0 20px;">';
        foreach ($fotos as $i => $f) {
            $url = rtrim($c['site_url'], '/') . '/uploads/' . $f;
            $fotosHtml .= '<a href="' . e($url) . '" style="color:#16181B;">Foto ' . ($i + 1) . '</a>&nbsp;&nbsp; ';
        }
        $fotosHtml .= '</p>';
    }

    $titulo = $refPieza !== '' ? 'Solicitud nueva · por referencia' : 'Solicitud nueva · por matrícula';
    $inner = '<h1 style="margin:0 0 16px;font-size:24px;">' . e($titulo) . '</h1>'
        . ($refPieza !== '' ? '<p style="margin:0 0 20px;padding:14px;background:#16181B;color:#FFC21A;border-radius:8px;font-family:Consolas,Menlo,monospace;font-size:20px;font-weight:bold;letter-spacing:1px;">REF ' . e($refPieza) . '</p>' : '')
        . '<p style="margin:0 0 8px;font-weight:bold;">Pieza</p>'
        . '<p style="margin:0 0 20px;padding:12px 14px;background:#F5F3EF;border-radius:8px;white-space:pre-line;">' . e($piezaTxt) . '</p>'
        . $tabla . $fotosHtml
        . '<p style="margin:0;font-size:14px;color:#565A61;">Pulsa <strong>Responder</strong> para contestar directamente a ' . e($s['nombre']) . ' con el presupuesto.</p>';

    $text = "Solicitud nueva #$ref\n\n"
        . ($refPieza !== '' ? "Referencia: $refPieza\n" : '')
        . (!empty($s['fabricante']) ? "Marca de la pieza: {$s['fabricante']}\n" : '')
        . "Pieza:\n$piezaTxt\n\n"
        . ($mat ? "Matrícula: $mat\n" : '')
        . (!empty($s['bastidor']) ? "Bastidor: {$s['bastidor']}\n" : '')
        . ($coche ? "Coche: $coche\n" : '')
        . "Nombre: {$s['nombre']}\nEmail: {$s['email']}\nTeléfono: {$s['telefono']}\nCP: {$s['cp']}\n";
    foreach ($fotos as $i => $f) {
        $text .= 'Foto ' . ($i + 1) . ': ' . rtrim($c['site_url'], '/') . '/uploads/' . $f . "\n";
    }

    $asunto = 'Solicitud #' . $ref . ' · '
        . ($refPieza !== '' ? 'REF ' . $refPieza : mb_strimwidth($s['pieza'], 0, 50, '…'))
        . ($mat ? ' · ' . $mat : '');
    return [$asunto, mail_layout($ref, $inner, 'Aviso interno de la web de Recambia.'), $text];
}
