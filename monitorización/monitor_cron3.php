<?php
/**
 * monitor_cron.php
 * Ejecutar con cron cada minuto:
 *   * * * * * /usr/bin/php /var/www/html/monitor_cron.php >> /var/www/html/monitor_cron.log 2>&1
 */

define('BOT_TOKEN',    '8522801732:AAGlyCjjUOSKJdj3_RmUlszfMtznZaZrrP8');
define('CHAT_ID',      '5647461703');
define('ESTADOS_FILE', __DIR__ . '/estados.json');

// ── Funciones de Telegram ────────────────────────────────────────────────────

function telegram_alerta($texto) {
    $url  = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    $data = ['chat_id' => CHAT_ID, 'text' => $texto];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "[" . date('H:i:s') . "] Error cURL Telegram: $error\n";
    } else {
        echo "[" . date('H:i:s') . "] Telegram enviado: $texto\n";
    }
}

function cargar_estados() {
    if (!file_exists(ESTADOS_FILE)) return [];
    return json_decode(file_get_contents(ESTADOS_FILE), true) ?: [];
}

function guardar_estados($estados) {
    file_put_contents(ESTADOS_FILE, json_encode($estados));
}

// ── Funciones de comprobación ────────────────────────────────────────────────

function servicio_estado($nombre) {
    $estado = trim(shell_exec("systemctl is-active $nombre 2>/dev/null"));
    return $estado === "active";
}

// Lee /etc/ufw/ufw.conf directamente, sin necesitar sudo ni tty
function ufw_levantado() {
    $conf = @file_get_contents('/etc/ufw/ufw.conf');
    if ($conf === false) return false;
    return strpos($conf, 'ENABLED=yes') !== false;
}

function estado_mysql_remoto($host, $user, $pass, $db) {
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 3,
    ];
    try {
        new PDO($dsn, $user, $pass, $options);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// ── Lista de servicios ───────────────────────────────────────────────────────

$servicios = [
    'apache2' => 'Servicio Web',
    'mysql'   => 'Base de Datos (Servidor externo)',
    'postfix' => 'Servicio de Correo',
    'ufw'     => 'Firewall UFW',
    'ssh'     => 'SSH',
    'webmin'  => 'Webmin',
];

// ── Comprobar y notificar ────────────────────────────────────────────────────

$prev = cargar_estados();
$curr = [];

foreach ($servicios as $svc => $nombre) {
    $activo = false;
    try {
        if ($svc === 'ufw') {
            $activo = ufw_levantado();
        } elseif ($svc === 'mysql') {
            $activo = estado_mysql_remoto('10.20.26.150', 'webuser', 'manager', 'academia');
        } else {
            $activo = servicio_estado($svc);
        }
    } catch (Throwable $e) {
        $activo = false;
    }

    $curr[$svc] = $activo ? 'activo' : 'inactivo';

    echo "[" . date('H:i:s') . "] $nombre: " . $curr[$svc] . "\n";

    if (!$activo) {
        if (!isset($prev[$svc]) || $prev[$svc] === 'activo') {
            telegram_alerta("⚠️ $nombre CAÍDO — " . date('H:i:s'));
        }
    } else {
        if (isset($prev[$svc]) && $prev[$svc] === 'inactivo') {
            telegram_alerta("✅ $nombre RECUPERADO — " . date('H:i:s'));
        }
    }
}

guardar_estados($curr);
echo "[" . date('H:i:s') . "] Estados guardados.\n";
