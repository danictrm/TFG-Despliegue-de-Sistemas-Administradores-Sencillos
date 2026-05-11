<?php
/**
 * setup_totp.php
 * El empleado visita esta página UNA VEZ para vincular Google Authenticator.
 * Requiere estar autenticado con usuario+contraseña (sesión parcial).
 */
session_start();
require_once __DIR__ . '/totp_helper.php';

// Conexión BD
$host = '10.20.26.150'; $db = 'academia'; $user = 'webuser'; $pass = 'manager';
$pdo  = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass,
               [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Solo accesible desde el flujo de login parcial
if (empty($_SESSION['totp_setup_id'])) {
    header("Location: /empleados/login.php"); exit;
}

$id_empleado = $_SESSION['totp_setup_id'];
$nombre      = $_SESSION['totp_setup_nombre'];
$usuario     = $_SESSION['totp_setup_usuario'];

// Generar secret si no existe aún en esta sesión
if (empty($_SESSION['totp_nuevo_secret'])) {
    $_SESSION['totp_nuevo_secret'] = totp_generar_secret();
}
$secret  = $_SESSION['totp_nuevo_secret'];
$otpauth = totp_otpauth($secret, $usuario);
$qr_url  = totp_qr_url($otpauth);

$error = '';

// Verificar que el empleado escaneó bien el QR antes de guardar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo'] ?? '');
    if (totp_verificar($secret, $codigo)) {
        // Guardar secret en BD
        $pdo->prepare("UPDATE __TABLE__ SET totp_secret=? WHERE id_empleado=?")
            ->execute([$secret, $id_empleado]);

        // Sesión completa
        $_SESSION['empleado']    = $nombre;
        $_SESSION['id_empleado'] = $id_empleado;
        $_SESSION['rol']         = $_SESSION['totp_setup_rol'];
        unset($_SESSION['totp_setup_id'], $_SESSION['totp_setup_nombre'],
              $_SESSION['totp_setup_usuario'], $_SESSION['totp_setup_rol'],
              $_SESSION['totp_nuevo_secret']);

        header("Location: /empleados/administracion.php"); exit;
    } else {
        $error = 'Código incorrecto. Asegúrate de haber escaneado el QR y prueba de nuevo.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Configura tu autenticador – SDL</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',sans-serif}
body{background:linear-gradient(135deg,#1e3c72,#2a5298);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;color:#333;width:100%;max-width:400px;padding:40px;border-radius:16px;box-shadow:0 20px 40px rgba(0,0,0,.3);text-align:center}
h2{color:#1e3c72;margin-bottom:8px}
.sub{color:#666;font-size:14px;margin-bottom:24px;line-height:1.5}
.steps{text-align:left;background:#f4f6fb;border-radius:10px;padding:16px 20px;margin-bottom:20px;font-size:14px;color:#444;line-height:2}
.qr{border:3px solid #e8edf5;border-radius:12px;padding:10px;margin:0 auto 20px;display:inline-block}
.secret{font-family:monospace;font-size:13px;background:#f4f6fb;padding:8px 12px;border-radius:6px;color:#1e3c72;letter-spacing:2px;margin-bottom:20px;word-break:break-all}
input{width:100%;padding:14px;border-radius:8px;border:2px solid #2a5298;font-size:22px;font-weight:700;letter-spacing:10px;text-align:center;color:#1e3c72;outline:none;margin-bottom:14px}
button{width:100%;padding:12px;border:none;border-radius:8px;background:#2a5298;color:#fff;font-weight:700;font-size:15px;cursor:pointer;transition:.2s}
button:hover{background:#1e3c72}
.error{margin-top:12px;color:#c0392b;font-size:14px}
.title{position:fixed;top:30px;width:100%;text-align:center;font-size:22px;font-weight:600;color:#fff}
</style>
</head>
<body>
<div class="title">Sistema de Gestión SDL</div>
<div class="card">
  <h2>Configura tu autenticador</h2>
  <p class="sub">Hola <strong><?= htmlspecialchars($nombre) ?></strong>, esta configuración se hace <strong>una sola vez</strong>.</p>

  <div class="steps">
    <b>1.</b> Instala <b>Google Authenticator</b> o <b>Authy</b> en tu móvil<br>
    <b>2.</b> Escanea el código QR de abajo<br>
    <b>3.</b> Introduce el código de 6 dígitos que aparece
  </div>

  <div class="qr">
    <img src="<?= htmlspecialchars($qr_url) ?>" width="180" height="180" alt="QR TOTP">
  </div>

  <p style="font-size:12px;color:#999;margin-bottom:6px">¿No puedes escanear? Introduce este código manualmente:</p>
  <div class="secret"><?= htmlspecialchars($secret) ?></div>

  <form method="POST">
    <input type="text" name="codigo" placeholder="000000" maxlength="6"
           inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" autofocus required>
    <button type="submit">Verificar y activar</button>
  </form>

  <?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>
</div>
<script>
  document.querySelector('input').addEventListener('input', function(){
    this.value = this.value.replace(/\D/g,'').slice(0,6);
  });
</script>
</body>
</html>
