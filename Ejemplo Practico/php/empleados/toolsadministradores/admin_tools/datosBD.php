<?php
session_start();
if(!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administrador'){
    die("acceso no autorizado");
}

$nombre_empleado = $_SESSION['empleado'];

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
} catch (\PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// PAGINACIÓN
$por_pagina = 10;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$offset = ($pagina - 1) * $por_pagina;

// FILTROS
$alumno_filtro = $_GET['alumno'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

// WHERE dinámico
$where = [];
$params = [];

if ($alumno_filtro !== '') {
    $where[] = "cli.nombre LIKE ?";
    $params[] = "%$alumno_filtro%";
}
if ($fecha_inicio !== '') {
    $where[] = "c.fecha >= ?";
    $params[] = $fecha_inicio;
}
if ($fecha_fin !== '') {
    $where[] = "c.fecha <= ?";
    $params[] = $fecha_fin;
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// TOTAL
$stmt_total = $pdo->prepare("
    SELECT COUNT(*)
    FROM clases c
    JOIN clientes cli ON c.id_cliente = cli.id_cliente
    $where_sql
");
$stmt_total->execute($params);
$total_paginas = ceil($stmt_total->fetchColumn() / $por_pagina);

// CONSULTA
$stmt = $pdo->prepare("
    SELECT cli.nombre alumno, e.nombre profesor, a.nombre asignatura, c.fecha
    FROM clases c
    JOIN clientes cli ON c.id_cliente = cli.id_cliente
    JOIN empleados e ON c.id_empleado = e.id_empleado
    JOIN asignaturas a ON c.id_asignatura = a.id_asignatura
    $where_sql
    ORDER BY c.fecha ASC
    LIMIT $por_pagina OFFSET $offset
");
$stmt->execute($params);

// EVENTOS CALENDARIO
$eventos = [];
$res = $pdo->query("
    SELECT c.fecha, cli.nombre alumno, a.nombre asignatura
    FROM clases c
    JOIN clientes cli ON c.id_cliente = cli.id_cliente
    JOIN asignaturas a ON c.id_asignatura = a.id_asignatura
    WHERE c.fecha >= CURDATE()
");
while ($r = $res->fetch()) {
    $eventos[] = [
        'title' => $r['alumno'].' - '.$r['asignatura'],
        'start' => $r['fecha']
    ];
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
<span>Bienvenido <?= htmlspecialchars($nombre_empleado) ?></span>
<a href="/index.php">Inicio</a>
<a href="/clientes/formulario.php">Área Clientes</a>
<a href="/empleados/administracion.php">Área Empleados</a>
<a href="/empleados/logout.php">Cerrar sesión</a>
</nav>
</header>

<div class="container">

<!-- CLASES -->
<div class="card">
<h2>Clases Registradas</h2>

<form method="get" class="form-filtro">
<input type="text" name="alumno" placeholder="Alumno" value="<?= $alumno_filtro ?>">
<input type="date" name="fecha_inicio" value="<?= $fecha_inicio ?>">
<input type="date" name="fecha_fin" value="<?= $fecha_fin ?>">
<button>Filtrar</button>
</form>

<table>
<tr><th>Alumno</th><th>Profesor</th><th>Asignatura</th><th>Fecha</th></tr>
<?php while($r=$stmt->fetch()): ?>
<tr>
<td><?= $r['alumno'] ?></td>
<td><?= $r['profesor'] ?></td>
<td><?= $r['asignatura'] ?></td>
<td><?= $r['fecha'] ?></td>
</tr>
<?php endwhile; ?>
</table>

<div>
<?php
for($i=1;$i<=$total_paginas;$i++){
$url="?pagina=$i";
if($alumno_filtro) $url.="&alumno=$alumno_filtro";
if($fecha_inicio) $url.="&fecha_inicio=$fecha_inicio";
if($fecha_fin) $url.="&fecha_fin=$fecha_fin";
echo $i==$pagina?"<strong>$i</strong> ":"<a href='$url'>$i</a> ";
}
?>
</div>
</div>

<!-- ALUMNOS POR PROFESOR -->
<div class="card">
<h2>Alumnos por Profesor</h2>
<?php
$r=$pdo->query("SELECT e.nombre,COUNT(DISTINCT c.id_cliente) alumnos FROM clases c JOIN empleados e ON c.id_empleado=e.id_empleado GROUP BY e.id_empleado");
echo "<table><tr><th>Profesor</th><th>Alumnos</th></tr>";
while($d=$r->fetch()) echo "<tr><td>{$d['nombre']}</td><td>{$d['alumnos']}</td></tr>";
echo "</table>";
?>
</div>

<!-- ASIGNATURAS -->
<div class="card">
<h2>Clases por Asignatura</h2>
<?php
$r=$pdo->query("SELECT a.nombre,COUNT(*) total FROM clases c JOIN asignaturas a ON c.id_asignatura=a.id_asignatura GROUP BY a.id_asignatura");
echo "<table><tr><th>Asignatura</th><th>Total</th></tr>";
while($d=$r->fetch()) echo "<tr><td>{$d['nombre']}</td><td>{$d['total']}</td></tr>";
echo "</table>";
?>
</div>

<!-- NOMINAS -->
<div class="card">
<h2>Nóminas</h2>
<?php
$r=$pdo->query("SELECT e.nombre,COUNT(*) clases,COUNT(*)*10 salario FROM clases c JOIN empleados e ON c.id_empleado=e.id_empleado WHERE MONTH(c.fecha)=MONTH(CURDATE()) GROUP BY e.id_empleado");
echo "<table><tr><th>Profesor</th><th>Clases</th><th>€</th></tr>";
while($d=$r->fetch()) echo "<tr><td>{$d['nombre']}</td><td>{$d['clases']}</td><td>{$d['salario']}</td></tr>";
echo "</table>";
?>
</div>

<!-- CALENDARIO -->
<div class="card">
<h2>Próximas clases</h2>
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
