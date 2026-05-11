<?php

session_start();
if(!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administrador'){
    die("acceso no autorizado");
}
$nombre_empleado = $_SESSION['empleado'];
// función para estado de servicios normales usando systemctl
function servicio_estado($nombre) {
    $estado = trim(shell_exec("systemctl is-active $nombre 2>/dev/null"));
    return $estado === "active";
}

// función para comprobar si UFW está realmente levantado
function ufw_levantado() {
    $status = trim(shell_exec("sudo ufw status 2>/dev/null"));
    return strpos($status, 'Status: active') !== false;
}

// función para obtener puertos abiertos en UFW
// función para obtener puertos permitidos en ufw
function ufw_puertos() {
    $output = shell_exec("sudo ufw status 2>/dev/null");
    $puertos = [];

    $lineas = explode("\n", $output);

    foreach ($lineas as $linea) {
        $linea = trim($linea);

        // ignorar cabeceras y separadores
        if ($linea === "" ||
            strpos($linea,'Status') === 0 ||
            strpos($linea,'To ') === 0 ||
            strpos($linea,'--') === 0) {
            continue;
        }

        // dividir columnas
        $partes = preg_split('/\s+/', $linea);

        if (count($partes) < 2) {
            continue;
        }

        $accion = strtoupper($partes[1]);

        // solo guardar si está permitido
        if ($accion === "ALLOW") {

            // quitar (v6) si existe
            $puerto = str_replace('(v6)','',$partes[0]);
            $puerto = trim($puerto);

            $puertos[] = $puerto;
        }
    }

    return array_unique($puertos);
}
// función para comprobar el estado de MySQL en el servidor remoto
function estado_mysql_remoto($host, $user, $pass, $db) {
    $charset = 'utf8mb4';
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        return true; // Conexión exitosa
    } catch (PDOException $e) {
        return false; // Fallo en la conexión
    }
}

// lista de servicios a monitorear
$servicios = [
    'apache2' => 'Servicio Web',
    'mysql' => 'Base de Datos (Servidor externo)',
    'postfix' => 'Servicio de Correo',
    'ufw' => 'Firewall UFW',
    'ssh' => 'SSH',
    'webmin' => 'Webmin'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Monitorización de Servicios - SDL</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="refresh" content="3">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../../../css/monitorizacion.css">

</head>
<body>
<header>
<h1>Sistema de Gestión SDL</h1>
<nav>
<span>Bienvenido <?php echo htmlspecialchars($nombre_empleado); ?></span>
	<a href="/index.php">Inicio</a>  
        <a href="/clientes/formulario.php">Área Clientes</a>
        <a href="/empleados/administracion.php">Área Empleados</a>
        <a href="/empleados/logout.php">Cerrar sesión</a>
</nav>
</header>

<main>
<div class="hero">
<h2><i class="fas fa-network-wired"></i> Monitorización de Servicios</h2>
<p>Estado actual de los servicios internos del sistema</p>
</div>

<div class="container">

<?php foreach($servicios as $svc=>$nombre): ?>
<div class="service-box">

<h3>
<?php
switch($svc){
    case 'apache2': echo '<i class="fas fa-globe"></i>'; break;
    case 'mysql': echo '<i class="fas fa-database"></i>'; break;
    case 'postfix': echo '<i class="fas fa-envelope"></i>'; break;
    case 'ufw': echo '<i class="fas fa-shield-alt"></i>'; break;
    case 'ssh': echo '<i class="fas fa-terminal"></i>'; break;
    case 'webmin': echo '<i class="fas fa-cogs"></i>'; break;
}
echo " $nombre";
?>
</h3>

<p class="<?php
    if ($svc === 'ufw') {
        echo ufw_levantado() ? 'status-ok':'status-down';
    } elseif ($svc === 'mysql') {
        echo estado_mysql_remoto('10.20.26.150', 'webuser', 'manager', 'academia') ? 'status-ok' : 'status-down';
    } else {
        echo servicio_estado($svc)?'status-ok':'status-down';
    }
?>">
<?php
if ($svc === 'ufw') {
    if (ufw_levantado()) {
        echo "✔ Firewall activo";

        $puertos = ufw_puertos();
        if (!empty($puertos)) {
            echo '<div class="puertos">Puertos abiertos: '.implode(', ', $puertos).'</div>';
        }
    } else {
        echo "✖ Firewall inactivo";
    }
} elseif ($svc === 'mysql') {
    echo estado_mysql_remoto('10.20.26.150', 'webuser', 'manager', 'academia') ? '✔ Base de Datos operativa' : '✖ Base de Datos no disponible';
} else {
    echo servicio_estado($svc) ? '✔ Operativo' : '✖ No disponible';
}
?>
</p>

</div>
<?php endforeach; ?>

</div>
</main>

<footer>Sistema de Gestión SDL © <?php echo date("Y"); ?></footer>

</body>
</html>
