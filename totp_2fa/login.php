<?php
/**
 * login.php – con 2FA TOTP (Google Authenticator)
 */
session_start();
require_once __DIR__ . '/totp_helper.php';

$error = '';
$host  = '10.20.26.150'; $db = 'academia'; $user = 'webuser'; $pass = 'manager';
$pdo   = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario  = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT id_empleado,usuario,password,nombre,rol,totp_secret FROM __TABLE__ WHERE usuario=?");
    $stmt->execute([$usuario]);
    $emp = $stmt->fetch();

    if ($emp && $password === $emp['password']) {
        // Solo los administradores requieren 2FA
        if ($emp['rol'] === 'administrador') {
            $_SESSION['totp_pre_id']      = $emp['id_empleado'];
            $_SESSION['totp_pre_nombre']  = $emp['nombre'];
            $_SESSION['totp_pre_usuario'] = $emp['usuario'];
            $_SESSION['totp_pre_rol']     = $emp['rol'];
            $_SESSION['totp_pre_secret']  = $emp['totp_secret'];

            if (empty($emp['totp_secret'])) {
                $_SESSION['totp_setup_id']      = $emp['id_empleado'];
                $_SESSION['totp_setup_nombre']  = $emp['nombre'];
                $_SESSION['totp_setup_usuario'] = $emp['usuario'];
                $_SESSION['totp_setup_rol']     = $emp['rol'];
                header("Location: /empleados/setup_totp.php"); exit;
            } else {
                header("Location: /empleados/verify_totp.php"); exit;
            }
        } else {
            // Usuarios no administradores: acceso directo sin 2FA
            $_SESSION['empleado']    = $emp['nombre'];
            $_SESSION['id_empleado'] = $emp['id_empleado'];
            $_SESSION['rol']         = $emp['rol'];
            header("Location: /empleados/administracion.php"); exit;
        }
    } else {
        $error = 'Usuario o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Login – Sistema SDL</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',sans-serif}
body{background:linear-gradient(135deg,#1e3c72,#2a5298);height:100vh;display:flex;align-items:center;justify-content:center}
.card{background:#fff;color:#333;width:340px;padding:40px;border-radius:15px;box-shadow:0 15px 35px rgba(0,0,0,.3);text-align:center}
h2{color:#1e3c72;margin-bottom:25px}
input{width:100%;padding:12px;margin-bottom:15px;border-radius:8px;border:1px solid #ccc;font-size:14px}
button{width:100%;padding:12px;border:none;border-radius:8px;background:#2a5298;color:#fff;font-weight:700;cursor:pointer;transition:.2s}
button:hover{background:#1e3c72}
.error{margin-top:14px;color:red;font-size:14px}
.title{position:fixed;top:30px;width:100%;text-align:center;font-size:26px;font-weight:600;color:#fff}
</style>
</head>
<body>
<div class="title">Sistema de Gestión SDL</div>
<div class="card">
  <h2>Área de Empleados</h2>
  <form method="POST">
    <input type="text"     name="usuario"  placeholder="Usuario"    required>
    <input type="password" name="password" placeholder="Contraseña" required>
    <button type="submit">Iniciar sesión</button>
  </form>
  <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
</div>
</body>
</html>
