<?php
session_start();

// ==========================================
// 1. CONFIGURACIÓN DE SEGURIDAD (LOGIN)
// ==========================================
$PASSWORD_SECRETA = "SDLAdmin2026*"; 

if (isset($_POST['login_submit'])) {
    if ($_POST['password'] === $PASSWORD_SECRETA) {
        $_SESSION['autenticado'] = true;
        header("Location: index.php");
        exit();
    } else {
        $error_login = "Contraseña incorrecta.";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Pantalla de bloqueo si no está logueado
if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Login - SDL Control Center</title>
        <style>
            body { background: #121212; color: #e0e0e0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .login-box { background: #1e1e1e; padding: 40px; border-radius: 8px; border: 1px solid #333; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.5); width: 300px; }
            input[type="password"] { padding: 12px; width: 100%; box-sizing: border-box; margin: 20px 0; background: #2d2d2d; color: white; border: 1px solid #555; border-radius: 4px; }
            input[type="password"]:focus { outline: none; border-color: #3a86ff; }
            button { background: #3a86ff; color: white; border: none; padding: 12px; cursor: pointer; border-radius: 4px; width: 100%; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
            button:hover { background: #2171ec; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2 style="color: #3a86ff; margin-top:0;">🔒 Acceso Restringido</h2>
            <p style="color: #9e9e9e; font-size: 14px;">SDL Control Center</p>
            <?php if(isset($error_login)) echo "<div style='color: #cf6679; background: rgba(207,102,121,0.1); padding: 10px; border-radius: 4px; margin-top:15px; border-left: 4px solid #cf6679;'>$error_login</div>"; ?>
            <form method="POST">
                <input type="password" name="password" placeholder="Introduce tu contraseña" required autofocus>
                <button type="submit" name="login_submit">Entrar al Panel</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit(); // Detiene la carga del código restante
}
// ==========================================


$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// 1. Backup manual
if (isset($_POST['ejecutar_alpha'])) {
    $salida = shell_exec("sudo /usr/local/bin/backup_inteligente.sh 2>&1");
    $_SESSION['mensaje'] = "<div class='alert alert-success'>[ OK ] Tarea completada con éxito. Se sincronizaron los cambios y se empaquetó la copia.</div><pre>$salida</pre>";
    header("Location: ?page=dashboard");
    exit();
}

// 2. Programar Cron
if (isset($_POST['programar_cron'])) {
    $h = str_pad(intval($_POST['hora']), 2, "0", STR_PAD_LEFT);
    $m = str_pad(intval($_POST['minuto']), 2, "0", STR_PAD_LEFT);
    $dias_cron = !empty($_POST['dias']) ? implode(",", $_POST['dias']) : "*";
    $cron_job = "$m $h * * $dias_cron root /usr/local/bin/backup_inteligente.sh\\n";
    shell_exec("echo \"$cron_job\" | sudo /usr/bin/tee /etc/cron.d/rsnapshot_web");
    $_SESSION['mensaje_cron'] = "<div class='alert alert-success'>Configuración inyectada en el demonio Cron (Modo incremental inteligente).</div>";
    header("Location: ?page=dashboard");
    exit();
}

// 3. Borrar Cron
if (isset($_POST['borrar_cron'])) {
    shell_exec("sudo /usr/bin/rm -f /etc/cron.d/rsnapshot_web 2>/dev/null");
    shell_exec("echo -n '' | sudo /usr/bin/tee /etc/cron.d/rsnapshot_web >/dev/null 2>&1");
    $_SESSION['mensaje_cron'] = "<div class='alert alert-danger'>Tarea programada eliminada del sistema.</div>";
    header("Location: ?page=dashboard");
    exit();
}

// 4. Eliminar máquina
if (isset($_POST['eliminar_maquina'])) {
    $ip_target     = escapeshellcmd($_POST['ip_target']);
    $modulo_target = escapeshellcmd($_POST['modulo_target']);
    $nombre_target = escapeshellcmd($_POST['nombre_target']);

    shell_exec("sudo sed -i \"\\@^backup.*rsync://$ip_target/$modulo_target/.*$nombre_target@d\" /etc/rsnapshot.conf");

    $_SESSION['mensaje_setup'] = "<div class='alert alert-danger'>Entrada $ip_target / $modulo_target → $nombre_target eliminada correctamente.</div>";
    header("Location: ?page=setup");
    exit();
}

// 5. Editar IP
if (isset($_POST['editar_ip'])) {
    $old_ip = escapeshellcmd($_POST['old_ip']);
    $new_ip = escapeshellcmd($_POST['new_ip']);

    shell_exec("sudo sed -i \"s|rsync://$old_ip/|rsync://$new_ip/|g\" /etc/rsnapshot.conf");

    $_SESSION['mensaje_setup'] = "<div class='alert alert-info'>IP actualizada correctamente de $old_ip a $new_ip (todos sus módulos).</div>";
    header("Location: ?page=setup");
    exit();
}


// 6. Registrar nueva máquina
if (isset($_POST['run_setup'])) {
    // Eliminamos la variable $rol
    $comando = "sudo /usr/local/bin/setup_rsnapshot.sh";
    $comando .= " " . escapeshellarg("añadir");

    $num = isset($_POST['num_vms']) ? (int)$_POST['num_vms'] : 0;
    for ($i = 1; $i <= $num; $i++) {
        $ip_vm    = isset($_POST["vm_ip_$i"])     ? escapeshellcmd($_POST["vm_ip_$i"])     : "";
        $modulo_vm= isset($_POST["vm_modulo_$i"]) ? escapeshellcmd($_POST["vm_modulo_$i"]) : "";
        $nombre_vm= isset($_POST["vm_nombre_$i"]) ? escapeshellcmd($_POST["vm_nombre_$i"]) : "";
        
        if (!empty($ip_vm) && !empty($modulo_vm) && !empty($nombre_vm)) {
            $comando .= " " . escapeshellarg($ip_vm . ':' . $modulo_vm . ':' . $nombre_vm);
        }
    }

    $salida_setup = shell_exec("$comando 2>&1");
    $_SESSION['mensaje_setup'] = "<div class='alert alert-success'>Asistente ejecutado correctamente.</div><pre>$salida_setup</pre>";
    header("Location: ?page=setup");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SDL Control Center</title>
    <style>
        :root {
            --bg-color: #121212; --panel-bg: #1e1e1e; --text-main: #e0e0e0; --text-muted: #9e9e9e;
            --border-color: #333333; --accent-gray: #2c2c2c; --primary: #3a86ff; --primary-hover: #2171ec;
            --danger: #cf6679; --danger-hover: #b94f61; --success: #03dac6; --input-bg: #2d2d2d;
        }
        body { font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--bg-color); color: var(--text-main); margin: 0; padding: 40px 20px; }
        .container { max-width: 900px; margin: auto; }
        .header { margin-bottom: 20px; padding-bottom: 15px; display: flex; align-items: center; justify-content: space-between; }
        .header h1 { margin: 0; font-size: 28px; color: #ffffff; font-weight: 600; letter-spacing: 1px; }
        .nav-tabs { display: flex; border-bottom: 2px solid var(--border-color); margin-bottom: 25px; }
        .nav-link { padding: 12px 20px; color: var(--text-muted); text-decoration: none; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; font-size: 14px; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.3s; }
        .nav-link:hover { color: var(--text-main); }
        .nav-link.active { color: var(--primary); border-bottom: 2px solid var(--primary); }
        .status-badge { background-color: rgba(3, 218, 198, 0.15); color: var(--success); padding: 5px 12px; border-radius: 4px; border: 1px solid var(--success); font-size: 12px; font-weight: bold; letter-spacing: 1px; }
        .box { background: var(--panel-bg); padding: 25px; border-radius: 6px; box-shadow: 0 4px 15px rgba(0,0,0,0.5); margin-bottom: 25px; border: 1px solid var(--border-color); }
        .box h3 { margin-top: 0; margin-bottom: 20px; color: var(--primary); font-size: 18px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
        .btn { background: var(--primary); color: white; border: none; padding: 10px 20px; font-size: 14px; font-weight: 600; border-radius: 4px; cursor: pointer; transition: all 0.2s ease; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn:hover { background: var(--primary-hover); box-shadow: 0 0 10px rgba(58,134,255,0.4); }
        .btn-red { background: transparent; color: var(--danger); border: 1px solid var(--danger); }
        .btn-red:hover { background: rgba(207,102,121,0.1); color: var(--danger); }
        .btn-small { padding: 5px 10px; font-size: 12px; border-radius: 3px; }
        label { color: #cccccc; font-size: 14px; display: block; margin-bottom: 8px; }
        input[type="number"], input[type="text"], select { padding: 10px 12px; font-size: 14px; width: 100%; box-sizing: border-box; background-color: var(--input-bg); border: 1px solid var(--border-color); border-radius: 4px; margin-bottom: 20px; color: white; font-family: inherit; }
        input:focus, select:focus { outline: none; border-color: var(--primary); }
        .form-row { display: flex; gap: 20px; }
        .form-col { flex: 1; }
        .dias-semana { display: flex; flex-wrap: wrap; gap: 10px; margin: 10px 0 20px 0; }
        .dia-checkbox { display: block; position: relative; cursor: pointer; font-size: 13px; user-select: none; color: var(--text-muted); background: var(--input-bg); padding: 8px 15px 8px 35px; border-radius: 4px; border: 1px solid var(--border-color); transition: all 0.2s; }
        .dia-checkbox:hover { background: #383838; color: white; }
        .dia-checkbox input { position: absolute; opacity: 0; cursor: pointer; height: 0; width: 0; }
        .checkmark { position: absolute; top: 8px; left: 10px; height: 16px; width: 16px; background-color: #222; border: 1px solid #555; border-radius: 3px; }
        .dia-checkbox input:checked ~ .checkmark { background-color: var(--primary); border-color: var(--primary); }
        .dia-checkbox input:checked ~ .checkmark:after { content: ""; position: absolute; display: block; left: 5px; top: 2px; width: 4px; height: 8px; border: solid white; border-width: 0 2px 2px 0; transform: rotate(45deg); }
        .progress-wrapper { display: none; margin-top: 15px; }
        .progress-container { width: 100%; background-color: var(--input-bg); border-radius: 4px; overflow: hidden; height: 15px; border: 1px solid var(--border-color); }
        .progress-bar { height: 100%; width: 100%; background-color: var(--primary); background-image: linear-gradient(45deg, rgba(255,255,255,.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.15) 50%, rgba(255,255,255,.15) 75%, transparent 75%, transparent); background-size: 40px 40px; animation: progress-animation 1s linear infinite; }
        @keyframes progress-animation { 0% { background-position: 40px 0; } 100% { background-position: 0 0; } }
        .loading-text { font-size: 13px; color: var(--primary); margin-top: 10px; font-weight: 500; }
        pre { background: #0d0d0d; color: #00ff00; padding: 15px; border-radius: 4px; overflow-x: auto; font-family: 'Consolas', 'Courier New', monospace; font-size: 13px; border: 1px solid #333; line-height: 1.5; }
        .alert { padding: 12px 15px; border-radius: 4px; margin-bottom: 15px; font-size: 14px; border-left: 4px solid; }
        .alert-success { background-color: rgba(3,218,198,0.1); color: var(--success); border-color: var(--success); }
        .alert-danger { background-color: rgba(207,102,121,0.1); color: var(--danger); border-color: var(--danger); }
        .alert-info { background-color: rgba(58,134,255,0.1); color: var(--primary); border-color: var(--primary); }
        code { background: #000; padding: 3px 6px; border-radius: 3px; border: 1px solid #444; color: #ffb86c; font-family: monospace; }
        .btn-group { display: flex; gap: 10px; margin-top: 20px; }
        .status-box { background: rgba(0,0,0,0.2); padding: 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid var(--border-color); border-left: 3px solid; }
        .vm-card { background: var(--input-bg); padding: 15px; border-radius: 4px; margin-bottom: 15px; border: 1px dashed #555; }
        .modulo-tag { display: inline-block; background: rgba(255,184,108,0.15); color: #ffb86c; border: 1px solid #ffb86c44; padding: 2px 8px; border-radius: 3px; font-family: monospace; font-size: 12px; }
        
        .logout-btn { color: #cf6679; text-decoration: none; font-size: 13px; font-weight: bold; border: 1px solid rgba(207,102,121,0.5); padding: 4px 10px; border-radius: 4px; transition: all 0.2s; }
        .logout-btn:hover { background: rgba(207,102,121,0.1); }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>SDL Control Center</h1>
        <div>
            <span class="status-badge" style="margin-right: 15px;">SRV-DEBIAN-11</span>
            <a href="?logout=1" class="logout-btn">SALIR ⏏</a>
        </div>
    </div>

    <div class="nav-tabs">
        <a href="?page=dashboard" class="nav-link <?php echo ($page == 'dashboard') ? 'active' : ''; ?>">Panel de Control</a>
        <a href="?page=setup" class="nav-link <?php echo ($page == 'setup') ? 'active' : ''; ?>">Setup Wizard</a>
    </div>

    <?php if ($page == 'dashboard'): ?>

        <div class="box">
            <h3>Ejecución Manual</h3>
            <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">Fuerza la ejecución inmediata de una copia de seguridad tipo Alpha en Modo Incremental.</p>
            <form method="POST" id="form-backup">
                <button type="submit" name="ejecutar_alpha" class="btn" id="btn-ejecutar">
                    <span style="font-size: 14px; margin-right: 5px;">⟳</span> Iniciar Sincronización
                </button>
                <div class="progress-wrapper" id="loading-zone">
                    <div class="progress-container"><div class="progress-bar"></div></div>
                    <div class="loading-text">⚡ Sincronizando datos con los nodos y rotando copias... por favor espere.</div>
                </div>
            </form>
            <?php
            if (isset($_SESSION['mensaje'])) {
                echo "<div style='margin-top:20px;'>" . $_SESSION['mensaje'] . "</div>";
                unset($_SESSION['mensaje']);
            }
            ?>
        </div>

        <div class="box">
            <h3>Programación de Tareas Automáticas</h3>
            <?php
            if (isset($_SESSION['mensaje_cron'])) {
                echo $_SESSION['mensaje_cron'];
                unset($_SESSION['mensaje_cron']);
            }
            $estado_cron = trim((string)shell_exec("cat /etc/cron.d/rsnapshot_web 2>/dev/null"));
            $borde_color = !empty($estado_cron) ? "var(--success)" : "var(--border-color)";
            ?>
            <div class="status-box" style="border-left-color: <?php echo $borde_color; ?>;">
                <b style="color: #fff;">Estado del demonio:</b>
                <?php
                if (!empty($estado_cron)) {
                    echo "<span style='color: var(--success); font-weight: bold;'>Activo</span><br>";
                    echo "<span style='font-size:13px; color: var(--text-muted);'>Regla inyectada: <code>" . $estado_cron . "</code></span>";
                } else {
                    echo "<span style='color: var(--text-muted); font-weight: bold;'>Inactivo</span>";
                }
                ?>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-col" style="max-width: 150px;">
                        <label>Hora (0-23):</label>
                        <input type="number" name="hora" min="0" max="23" required value="02" style="width: 100%;">
                    </div>
                    <div class="form-col" style="max-width: 150px;">
                        <label>Minuto (0-59):</label>
                        <input type="number" name="minuto" min="0" max="59" required value="00" style="width: 100%;">
                    </div>
                </div>
                <label>Días de actividad <small style="font-weight:normal; color:#666;">(Dejar vacío para ejecución diaria)</small></label>
                <div class="dias-semana">
                    <label class="dia-checkbox">Lunes<input type="checkbox" name="dias[]" value="1"><span class="checkmark"></span></label>
                    <label class="dia-checkbox">Martes<input type="checkbox" name="dias[]" value="2"><span class="checkmark"></span></label>
                    <label class="dia-checkbox">Miércoles<input type="checkbox" name="dias[]" value="3"><span class="checkmark"></span></label>
                    <label class="dia-checkbox">Jueves<input type="checkbox" name="dias[]" value="4"><span class="checkmark"></span></label>
                    <label class="dia-checkbox">Viernes<input type="checkbox" name="dias[]" value="5"><span class="checkmark"></span></label>
                    <label class="dia-checkbox">Sábado<input type="checkbox" name="dias[]" value="6"><span class="checkmark"></span></label>
                    <label class="dia-checkbox">Domingo<input type="checkbox" name="dias[]" value="0"><span class="checkmark"></span></label>
                </div>
                <div class="btn-group">
                    <button type="submit" name="programar_cron" class="btn">Aplicar Regla</button>
                    <button type="submit" name="borrar_cron" class="btn btn-red" formnovalidate>Desactivar Regla</button>
                </div>
            </form>
        </div>

        <div class="box">
            <h3>Repositorio de Datos</h3>
            <p style="color: var(--text-muted); font-size: 14px;">Estructura de ficheros en <code>/copias_seguridad/</code></p>
            <?php
            $copias = shell_exec("sudo ls -lh /copias_seguridad/ 2>&1");
            if ($copias && strpos($copias, 'No such file') === false) {
                echo "<pre>$copias</pre>";
            } else {
                echo "<div class='alert alert-info'>El directorio de almacenamiento se encuentra vacío o inaccesible.</div>";
            }
            ?>
        </div>

        <script>
            document.getElementById('form-backup').addEventListener('submit', function () {
                document.getElementById('btn-ejecutar').style.display = 'none';
                document.getElementById('loading-zone').style.display = 'block';
            });
        </script>

    <?php elseif ($page == 'setup'): ?>

        <div class="box">
            <h3>Registrar Nueva Máquina a Respaldar</h3>
            <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">
                Gestiona las máquinas virtuales registradas en la configuración de rsnapshot del servidor central.
            </p>

            <?php
            if (isset($_SESSION['mensaje_setup'])) {
                echo $_SESSION['mensaje_setup'];
                unset($_SESSION['mensaje_setup']);
            }
            ?>

            <?php
            $fichero_conf = '/etc/rsnapshot.conf';
            if (file_exists($fichero_conf)) {
                $lineas = file($fichero_conf);
                $maquinas_actuales = [];

                foreach ($lineas as $linea) {
                    if (preg_match('/^backup\s+rsync:\/\/([^\/]+)\/([^\/]+)\/\s+(\S+)/', trim($linea), $coincidencias)) {
                        $maquinas_actuales[] = [
                            'ip'     => trim($coincidencias[1]),
                            'modulo' => trim($coincidencias[2]),
                            'nombre' => trim($coincidencias[3])
                        ];
                    }
                }

                if (count($maquinas_actuales) > 0) {
                    echo "<div class='box' style='background:rgba(0,0,0,0.2); border:1px solid var(--primary); margin-bottom:30px; padding:15px;'>";
                    echo "<h4 style='margin-top:0; color:var(--primary); font-size:15px;'>🖥️ Nodos (VMs) actualmente en rotación</h4>";
                    echo "<table style='width:100%; border-collapse:collapse; font-size:14px; text-align:left;'>";
                    echo "<tr style='border-bottom:1px solid var(--border-color); color:var(--text-muted);'>
                            <th style='padding:8px 5px;'>IP Origen</th>
                            <th style='padding:8px 5px;'>Módulo Rsync</th>
                            <th style='padding:8px 5px;'>Carpeta Destino</th>
                            <th style='padding:8px 5px; width:80px;'>Acciones</th>
                          </tr>";

                    foreach ($maquinas_actuales as $maq) {
                        echo "<tr style='border-bottom:1px dashed #333;'>
                                <td style='padding:8px 5px;'>
                                    <form method='POST' style='display:flex; gap:5px; align-items:center; margin:0;'>
                                        <input type='hidden' name='old_ip' value='{$maq['ip']}'>
                                        <input type='text' name='new_ip' value='{$maq['ip']}' required
                                            style='width:120px; padding:5px; margin:0; height:30px; font-family:monospace;
                                                   background:var(--input-bg); color:#03dac6; border:1px solid #555; border-radius:3px;'>
                                        <button type='submit' name='editar_ip' class='btn btn-small' style='height:30px;'>Guardar</button>
                                    </form>
                                </td>
                                <td style='padding:8px 5px;'><span class='modulo-tag'>{$maq['modulo']}</span></td>
                                <td style='padding:8px 5px; color:#e0e0e0;'>{$maq['nombre']}</td>
                                <td style='padding:8px 5px;'>
                                    <form method='POST' style='margin:0;'
                                          onsubmit='return confirm(\"¿Eliminar {$maq['ip']} / {$maq['modulo']}?\");'>
                                        <input type='hidden' name='ip_target'     value='{$maq['ip']}'>
                                        <input type='hidden' name='modulo_target' value='{$maq['modulo']}'>
                                        <input type='hidden' name='nombre_target' value='{$maq['nombre']}'>
                                        <button type='submit' name='eliminar_maquina'
                                                class='btn btn-red btn-small' style='height:30px;'>Eliminar</button>
                                    </form>
                                </td>
                              </tr>";
                    }

                    echo "</table>";
                    echo "</div>";
                } else {
                    echo "<div class='alert alert-info' style='margin-bottom:25px;'>No hay ninguna máquina registrada todavía en <code>/etc/rsnapshot.conf</code>.</div>";
                }
            }
            ?>

            <form method="POST" id="setup-form" action="index.php">
                <input type="hidden" name="rol_maquina" value="servidor">

                <p style="color: var(--primary); font-size: 14px; margin-bottom: 15px;">
                    Añadir nuevas máquinas a la configuración actual:
                </p>

                <label>¿Cuántas máquinas virtuales vas a añadir AHORA?</label>
                <input type="number" name="num_vms" id="num_vms" min="1" max="20"
                       placeholder="Nº de VMs" required oninput="generarVMs()">

                <div id="vms_container" style="margin-top: 15px;"></div>

                <div class="btn-group">
                    <button type="submit" name="run_setup" id="btn-submit" class="btn" style="display:none;">
                        ⚡ Desplegar Configuración
                    </button>
                </div>
            </form>
        </div>

        <script>
            function generarVMs() {
                var num = parseInt(document.getElementById('num_vms').value);
                var container = document.getElementById('vms_container');
                container.innerHTML = '';

                document.getElementById('btn-submit').style.display = (num > 0) ? 'block' : 'none';

                for (var i = 1; i <= num; i++) {
                    var html = `
                    <div class="vm-card">
                        <label style="color:var(--primary); font-weight:bold;">Datos de la Máquina ${i}</label>
                        <div class="form-row" style="margin-top:10px;">
                            <div class="form-col" style="flex: 1.2;">
                                <label>IP de la VM:</label>
                                <input type="text" name="vm_ip_${i}" placeholder="192.168.1.X" required>
                            </div>
                            <div class="form-col" style="flex: 1;">
                                <label>Módulo Rsync Remoto:</label>
                                <input type="text" name="vm_modulo_${i}" placeholder="Ej: web1, mysql1" required>
                            </div>
                            <div class="form-col" style="flex: 1.5;">
                                <label>Nombre de carpeta destino:</label>
                                <input type="text" name="vm_nombre_${i}" placeholder="Ej: Maquina${i}" required>
                            </div>
                        </div>
                    </div>`;
                    container.innerHTML += html;
                }
            }
        </script>

    <?php endif; ?>
</div>
</body>
</html>