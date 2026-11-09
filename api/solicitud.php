<?php
declare(strict_types=1);

/**
 * Recibe una solicitud de pieza, la guarda y envía los dos correos.
 * Responde JSON si la petición viene del formulario con JavaScript;
 * si no, redirige a gracias.html.
 */

require __DIR__ . '/../app/lib.php';

$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

function reply(bool $ok, array $extra = [], int $code = 200): never
{
    global $wantsJson;
    if ($wantsJson) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => $ok] + $extra, JSON_UNESCAPED_UNICODE);
    } else {
        $dest = $ok ? '../gracias.html?ref=' . rawurlencode($extra['ref'] ?? '') : '../gracias.html?error=1';
        header('Location: ' . $dest, true, 303);
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    reply(false, ['message' => 'Método no permitido.'], 405);
}

try {
    $cfg = config();
} catch (Throwable $e) {
    error_log('[recambia] ' . $e->getMessage());
    reply(false, ['message' => 'El servicio no está disponible ahora mismo. Escríbenos a hola@recambia.es.'], 500);
}

// ---------- Anti-spam ----------
// Campo trampa: los bots lo rellenan. Respondemos "ok" para no darles pistas.
if (trim($_POST['web'] ?? '') !== '') {
    reply(true, ['ref' => 'SOL-0000']);
}
$t = (int) ($_POST['t'] ?? 0);
if ($t > 0 && (microtime(true) * 1000 - $t) < $cfg['min_segundos'] * 1000) {
    reply(false, ['message' => 'Has ido demasiado rápido. Revisa los datos y vuelve a enviarlo.'], 429);
}

// ---------- Lectura y limpieza ----------
function campo(string $k, int $max = 255): string
{
    $v = trim((string) ($_POST[$k] ?? ''));
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr($v, 0, $max);
}

$s = [
    'modo'       => campo('modo', 12) === 'referencia' ? 'referencia' : 'matricula',
    'referencia' => mb_strtoupper(trim(preg_replace('/\s+/u', ' ', campo('referencia', 40)) ?? '')),
    'fabricante' => campo('fabricante', 60),
    'matricula' => strtoupper(preg_replace('/[\s\-·.]/u', '', campo('matricula', 20)) ?? ''),
    'bastidor'  => strtoupper(preg_replace('/\s/', '', campo('bastidor', 30)) ?? ''),
    'marca'     => campo('marca', 60),
    'modelo'    => campo('modelo', 80),
    'anio'      => campo('anio', 4),
    'motor'     => campo('motor', 60),
    'pieza'     => campo('pieza', 1000),
    'nombre'    => campo('nombre', 80),
    'email'     => strtolower(campo('email', 120)),
    'telefono'  => campo('telefono', 20),
    'cp'        => campo('cp', 5),
];

// ---------- Validación (la misma que en el navegador) ----------
$err = [];
$plateOk = preg_match('/^\d{4}[BCDFGHJKLMNPRSTVWXYZ]{3}$/', $s['matricula'])
        || preg_match('/^[A-Z]{1,2}\d{4,6}[A-Z]{0,2}$/', $s['matricula']);

$porReferencia = $s['modo'] === 'referencia';

if ($porReferencia) {
    // Camino "tengo la referencia": la referencia es obligatoria; la matrícula, opcional
    if (mb_strlen($s['referencia']) < 3 || !preg_match('/^[A-Z0-9][A-Z0-9 .\-\/]*$/', $s['referencia'])) {
        $err['referencia'] = 'Escribe la referencia tal como aparece en la pieza.';
    }
    if ($s['matricula'] !== '' && !$plateOk) {
        $err['matricula_ref'] = 'Esa matrícula no parece válida. Revísala o déjala en blanco.';
    }
    $s['marca'] = $s['modelo'] = $s['anio'] = $s['motor'] = $s['bastidor'] = '';
} else {
    $s['referencia'] = $s['fabricante'] = '';
}

if (!$porReferencia && $s['matricula'] !== '' && !$plateOk) {
    $err['matricula'] = 'Esa matrícula no parece válida. Revísala o elige tu coche a mano.';
}
if (!$porReferencia && $s['matricula'] === '') {
    if ($s['marca'] === '' || $s['modelo'] === '') {
        $err['matricula'] = 'Escribe la matrícula o elige marca, modelo y año.';
    }
    $y = (int) $s['anio'];
    if ($s['anio'] !== '' && ($y < 1950 || $y > (int) date('Y') + 1)) {
        $err['anio'] = 'Escribe un año válido.';
    }
}
if ($s['bastidor'] !== '' && !preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $s['bastidor'])) {
    $err['bastidor'] = 'El bastidor tiene 17 caracteres.';
}
if (!$porReferencia && mb_strlen($s['pieza']) < 3) {
    $err['pieza'] = 'Cuéntanos qué pieza necesitas.';
}
if (mb_strlen($s['nombre']) < 2) {
    $err['nombre'] = 'Escribe tu nombre.';
}
if (!filter_var($s['email'], FILTER_VALIDATE_EMAIL)) {
    $err['email'] = 'Revisa el email; parece que le falta algo.';
}
$tel = preg_replace('/^(\+34|0034)/', '', preg_replace('/[^\d+]/', '', $s['telefono']) ?? '') ?? '';
if (!preg_match('/^[6789]\d{8}$/', $tel)) {
    $err['telefono'] = 'Escribe un teléfono de 9 cifras.';
}
$s['telefono'] = $tel;
if (!preg_match('/^(0[1-9]|[1-4]\d|5[0-2])\d{3}$/', $s['cp'])) {
    $err['cp'] = 'Escribe un código postal de 5 cifras.';
}
if (($_POST['privacidad'] ?? '') !== '1') {
    $err['privacidad'] = 'Necesitamos que aceptes la política de privacidad para enviarte el presupuesto.';
}

if ($err) {
    reply(false, ['errors' => $err, 'message' => 'Revisa los campos marcados.'], 422);
}

// ---------- Límite de envíos por IP ----------
$ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . $cfg['ip_salt']);
try {
    $q = db()->prepare('SELECT COUNT(*) FROM solicitudes WHERE ip_hash = ? AND creada > (NOW() - INTERVAL 1 HOUR)');
    $q->execute([$ipHash]);
    if ((int) $q->fetchColumn() >= $cfg['max_por_hora_ip']) {
        reply(false, ['message' => 'Has enviado varias solicitudes seguidas. Espera un rato o escríbenos a ' . $cfg['mail_owner'] . '.'], 429);
    }
} catch (Throwable $e) {
    error_log('[recambia] BD: ' . $e->getMessage());
    reply(false, ['message' => 'No hemos podido guardar tu solicitud. Inténtalo en unos minutos o escríbenos a ' . $cfg['mail_owner'] . '.'], 500);
}

// ---------- Fotos ----------
$fotos = [];
if (!empty($_FILES['fotos']) && is_array($_FILES['fotos']['name'])) {
    $permitidos = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        'image/heic' => 'heic', 'image/heif' => 'heif',
    ];
    $dirRel = date('Y/m');
    $dirAbs = __DIR__ . '/../uploads/' . $dirRel;
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $total = min(count($_FILES['fotos']['name']), (int) $cfg['max_fotos']);

    for ($i = 0; $i < $total; $i++) {
        if ($_FILES['fotos']['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($_FILES['fotos']['error'][$i] !== UPLOAD_ERR_OK
            || $_FILES['fotos']['size'][$i] > $cfg['max_mb'] * 1024 * 1024) {
            reply(false, ['errors' => ['fotos' => 'Máximo ' . $cfg['max_fotos'] . ' fotos de ' . $cfg['max_mb'] . ' MB cada una.']], 422);
        }
        $tmp = $_FILES['fotos']['tmp_name'][$i];
        $mime = $finfo->file($tmp) ?: '';
        if (!isset($permitidos[$mime])) {
            reply(false, ['errors' => ['fotos' => 'Solo admitimos fotos JPG, PNG, WEBP o HEIC.']], 422);
        }
        if (!is_dir($dirAbs) && !mkdir($dirAbs, 0755, true) && !is_dir($dirAbs)) {
            error_log('[recambia] No se pudo crear ' . $dirAbs);
            break;
        }
        $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $permitidos[$mime];
        if (move_uploaded_file($tmp, $dirAbs . '/' . $nombreArchivo)) {
            $fotos[] = $dirRel . '/' . $nombreArchivo;
        }
    }
}

// ---------- Guardar ----------
try {
    $ins = db()->prepare(
        'INSERT INTO solicitudes
            (modo, referencia, fabricante, matricula, bastidor, marca, modelo, anio, motor, pieza, fotos, nombre, email, telefono, cp, ip_hash, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([
        $s['modo'],
        $s['referencia'] ?: null,
        $s['fabricante'] ?: null,
        $s['matricula'] ?: null,
        $s['bastidor'] ?: null,
        $s['marca'] ?: null,
        $s['modelo'] ?: null,
        $s['anio'] !== '' ? (int) $s['anio'] : null,
        $s['motor'] ?: null,
        $s['pieza'],
        $fotos ? json_encode($fotos) : null,
        $s['nombre'],
        $s['email'],
        $s['telefono'],
        $s['cp'],
        $ipHash,
        mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
    $id = (int) db()->lastInsertId();
} catch (Throwable $e) {
    error_log('[recambia] BD: ' . $e->getMessage());
    reply(false, ['message' => 'No hemos podido guardar tu solicitud. Inténtalo en unos minutos o escríbenos a ' . $cfg['mail_owner'] . '.'], 500);
}

$ref = 'SOL-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);

// ---------- Correos ----------
[$asC, $htmlC, $txtC] = mail_cliente($s, $ref);
[$asD, $htmlD, $txtD] = mail_dueno($s, $ref, $fotos);

$okCliente = send_mail($s['email'], $asC, $htmlC, $txtC, $cfg['mail_owner']);
$okDueno = send_mail($cfg['mail_owner'], $asD, $htmlD, $txtD, $s['email']);

if (!$okCliente || !$okDueno) {
    error_log("[recambia] Fallo de correo en $ref (cliente: " . (int) $okCliente . ', dueño: ' . (int) $okDueno . ')');
}
try {
    db()->prepare('UPDATE solicitudes SET correo_ok = ? WHERE id = ?')->execute([(int) ($okCliente && $okDueno), $id]);
} catch (Throwable $e) {
    // No es crítico.
}

reply(true, ['ref' => $ref]);
