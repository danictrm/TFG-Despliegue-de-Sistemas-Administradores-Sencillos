<?php
session_start();

$nombre_empleado = $_SESSION['empleado'];
$rol_empleado = $_SESSION['rol'] ?? 'profesor'; // por defecto profesor

$host = '10.20.26.150';
$db   = 'academia';
$user = 'webuser';
$pass = 'manager';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    $eventos = [];

    if ($rol_empleado === 'administrador') {
        // admin: todas las clases
        $res = $pdo->prepare("
            SELECT c.fecha, cli.nombre AS alumno, a.nombre AS asignatura
            FROM clases c
            JOIN clientes cli ON c.id_cliente = cli.id_cliente
            JOIN asignaturas a ON c.id_asignatura = a.id_asignatura
            WHERE c.fecha >= CURDATE()
            ORDER BY c.fecha ASC
        ");
        $res->execute();
    } else {
        // profesor: solo sus clases
        $stmt = $pdo->prepare("SELECT id_empleado FROM empleados WHERE nombre = ?");
        $stmt->execute([$nombre_empleado]);
        $empleado = $stmt->fetch();

        if (!$empleado) {
            die("Empleado no encontrado.");
        }

        $id_empleado = $empleado['id_empleado'];

        $res = $pdo->prepare("
            SELECT c.fecha, cli.nombre AS alumno, a.nombre AS asignatura
            FROM clases c
            JOIN clientes cli ON c.id_cliente = cli.id_cliente
            JOIN asignaturas a ON c.id_asignatura = a.id_asignatura
            WHERE c.fecha >= CURDATE() AND c.id_empleado = ?
            ORDER BY c.fecha ASC
        ");
        $res->execute([$id_empleado]);
    }

    while ($r = $res->fetch()) {
        $eventos[] = [
            'title' => $r['alumno'].' - '.$r['asignatura'],
            'start' => $r['fecha']
        ];
    }

} catch (\PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Sistema de Gestion SDL</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- FULLCALENDAR -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<link rel="stylesheet" href="../../../../css/datosBD.css">

</head>

<body>

<header>
<h1>Sistema de Gestion SDL</h1>
<nav>
<span>Bienvenido <?= htmlspecialchars($nombre_empleado) ?> (<?= htmlspecialchars($rol_empleado) ?>)</span>
<a href="/index.php">Inicio</a>
<a href="/clientes/formulario.php">Área Clientes</a>
<a href="/empleados/administracion.php">Área Empleados</a>
<a href="/empleados/logout.php">Cerrar sesión</a>
</nav>
</header>

<div class="container">

<!-- CALENDARIO -->
<div class="card">
<h2><i class="fas fa-calendar-days"></i> Próximas clases</h2>
<div id="calendar"></div>
</div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView:'dayGridMonth',
        locale:'es',
        height:500,
        events: <?= json_encode($eventos) ?>
    }).render();
});
</script>

</body>
</html>
