<?php
/**
 * verify_totp.php – Segundo factor: código de Google Authenticator
 */
session_start();
require_once __DIR__ . '/totp_helper.php';

if (empty($_SESSION['totp_pre_id']) || empty($_SESSION['totp_pre_secret'])) {
    header("Location: /empleados/login.php"); exit;
}

$error    = '';
$intentos = &$_SESSION['totp_intentos'];
$intentos = $intentos ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo'] ?? '');
    $intentos++;

    if ($intentos > 5) {
        session_destroy();
        header("Location: /empleados/login.php?error=limite"); exit;
    }

    if (totp_verificar($_SESSION['totp_pre_secret'], $codigo)) {
        $_SESSION['empleado']    = $_SESSION['totp_pre_nombre'];
        $_SESSION['id_empleado'] = $_SESSION['totp_pre_id'];
        $_SESSION['rol']         = $_SESSION['totp_pre_rol'];
        unset($_SESSION['totp_pre_id'], $_SESSION['totp_pre_nombre'],
              $_SESSION['totp_pre_usuario'], $_SESSION['totp_pre_rol'],
              $_SESSION['totp_pre_secret'], $_SESSION['totp_intentos']);
        header("Location: /empleados/administracion.php"); exit;
    } else {
        $error = 'Código incorrecto. Intentos restantes: ' . (5 - $intentos) . '.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Verificación – Sistema SDL</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',sans-serif}
body{background:linear-gradient(135deg,#1e3c72,#2a5298);height:100vh;display:flex;align-items:center;justify-content:center}
.card{background:#fff;color:#333;width:360px;padding:40px;border-radius:15px;box-shadow:0 15px 35px rgba(0,0,0,.3);text-align:center}
h2{color:#1e3c72;margin-bottom:10px}
.sub{font-size:14px;color:#666;margin-bottom:24px;line-height:1.5}
.icono{font-size:44px;margin-bottom:12px}
input{width:100%;padding:16px;border-radius:8px;border:2px solid #2a5298;font-size:26px;font-weight:700;letter-spacing:12px;text-align:center;color:#1e3c72;outline:none;margin-bottom:14px}
button{width:100%;padding:12px;border:none;border-radius:8px;background:#2a5298;color:#fff;font-weight:700;font-size:15px;cursor:pointer;transition:.2s}
button:hover{background:#1e3c72}
.error{margin-top:14px;color:red;font-size:14px}
.volver{display:inline-block;margin-top:14px;font-size:12px;color:#aaa;text-decoration:none}
.volver:hover{color:#555}
.title{position:fixed;top:30px;width:100%;text-align:center;font-size:26px;font-weight:600;color:#fff}
</style>
</head>
<body>
<div class="title">Sistema de Gestión SDL</div>
<div class="card">
  <div class="icono">📱</div>
  <h2>Verificación en dos pasos</h2>
  <p class="sub">Introduce el código de 6 dígitos de tu app <strong>Google Authenticator</strong> o <strong>Authy</strong>.</p>
  <form method="POST">
    <input type="text" name="codigo" placeholder="000000" maxlength="6"
           inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" autofocus required>
    <button type="submit">Verificar</button>
  </form>
  <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <a href="/empleados/login.php" class="volver">← Volver al login</a>
</div>
<script>
  document.querySelector('input').addEventListener('input', function(){
    this.value = this.value.replace(/\D/g,'').slice(0,6);
  });
</script>
</body>
</html>
