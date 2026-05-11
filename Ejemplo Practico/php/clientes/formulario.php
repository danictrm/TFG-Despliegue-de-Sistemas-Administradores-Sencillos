<?php
$mensaje = "";
$solicitudes_cliente = [];
$solicitudes_pendientes = [];

// datos de conexión
$host = '10.20.26.150';
$db   = 'academia';
$user = 'webuser';
$pass = 'manager';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // cargar asignaturas disponibles
    $stmt = $pdo->query("SELECT id_asignatura, nombre FROM asignaturas");
    $asignaturas = $stmt->fetchAll();

    /* =================================
       REGISTRO DE SOLICITUD DE CLASE
    ================================= */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $nombre_completo = trim($_POST['nombre_completo'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $asignatura_id = intval($_POST['asignatura'] ?? 0);
        $fecha_clase = trim($_POST['fecha_clase'] ?? '');

        if ($nombre_completo === '' || $telefono === '' || $email === '' || $asignatura_id === 0 || $fecha_clase === '') {
            $mensaje = "Todos los campos son obligatorios ❌";
        } elseif (!preg_match('/^[0-9]{9}$/', $telefono)) {
            $mensaje = "El teléfono debe tener exactamente 9 números ❌";
        } else {
            // buscar si ya existe el cliente
            $stmt = $pdo->prepare("SELECT id_cliente FROM clientes WHERE nombre = ?");
            $stmt->execute([$nombre_completo]);
            $cliente = $stmt->fetch();

            if ($cliente) {
                $id_cliente = $cliente['id_cliente'];
            } else {
                // insertar cliente nuevo
                $stmt = $pdo->prepare("INSERT INTO clientes (nombre, telefono, email) VALUES (?, ?, ?)");
                $stmt->execute([$nombre_completo, $telefono, $email]);
                $id_cliente = $pdo->lastInsertId();
            }

            // asignar profesor según la asignatura
            $profesor_map = [
                1 => 1,
                2 => 1,
                3 => 2,
                4 => 3
            ];

            if (!isset($profesor_map[$asignatura_id])) {
                $mensaje = "No se pudo asignar un profesor para esta asignatura ❌";
            } else {
                $profesor = $profesor_map[$asignatura_id];

                $stmt = $pdo->prepare("
                    INSERT INTO solicitudes_clases
                    (id_cliente, id_empleado, id_asignatura, fecha, estado)
                    VALUES (?, ?, ?, ?, 'pendiente')
                ");

                $stmt->execute([
                    $id_cliente,
                    $profesor,
                    $asignatura_id,
                    $fecha_clase
                ]);

                $mensaje = "Solicitud de clase registrada correctamente ✅";
            }
        }
    }

    /* =================================
       CONSULTAR SOLICITUDES PENDIENTES
    ================================= */
    if (isset($_GET['buscar_pendientes'])) {

        $nombre_pendiente = trim($_GET['buscar_pendientes']);

        $stmt = $pdo->prepare("
            SELECT
                c.nombre,
                a.nombre AS asignatura,
                sc.fecha
            FROM solicitudes_clases sc
            JOIN clientes c
                ON sc.id_cliente = c.id_cliente
            JOIN asignaturas a
                ON sc.id_asignatura = a.id_asignatura
            WHERE
                c.nombre LIKE ?
                AND sc.estado = 'pendiente'
            ORDER BY sc.fecha ASC
        ");

        $stmt->execute(["%$nombre_pendiente%"]);
        $solicitudes_pendientes = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    $mensaje = "Error de conexión: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Área Clientes - Sistema de Gestión SDL</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../css/estiloAzul.css">
<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<script>
async function buscarCliente() {
    const nombre = document.getElementById('nombre_completo').value;
    if(nombre.length < 2) return;
    const res = await fetch('../php_apoyo/buscar_cliente.php?nombre=' + encodeURIComponent(nombre));
    const data = await res.json();
    if(data) {
        document.getElementById('telefono').value = data.telefono;
        document.getElementById('email').value = data.email;
        document.getElementById('telefono').readOnly = true;
        document.getElementById('email').readOnly = true;
    } else {
        document.getElementById('telefono').value = '';
        document.getElementById('email').value = '';
        document.getElementById('telefono').readOnly = false;
        document.getElementById('email').readOnly = false;
    }
}
</script>

</head>
<body>

<header>
<h1>Sistema de Gestión SDL</h1>
<nav>
<a href="/index.php">Inicio</a>
<a href="/clientes/formulario.php">Área Clientes</a>
<a href="/empleados/administracion.php">Área Empleados</a>
</nav>
</header>

<main>

<div class="hero">
<h2>Área de Clientes</h2>
<p>Registro y gestión de clases</p>
</div>

<div class="container">

<!-- CARD FORMULARIO -->
<div class="card">
<h2><i class="fas fa-user-plus"></i> Solicitud de clase</h2>

<form method="POST">
<input type="text" name="nombre_completo" id="nombre_completo" placeholder="Nombre Completo" required onkeyup="buscarCliente()">
<input type="text" name="telefono" id="telefono" placeholder="Teléfono" required pattern="[0-9]{9}" maxlength="9" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
<input type="email" name="email" id="email" placeholder="Correo Electrónico" required>

<select name="asignatura" required>
<option value="">Seleccione una Asignatura</option>
<?php foreach($asignaturas as $a): ?>
<option value="<?php echo $a['id_asignatura']; ?>">
<?php echo $a['nombre']; ?>
</option>
<?php endforeach; ?>
</select>

<label for="fecha_clase" style="margin-bottom:5px;">Fecha de la Clase:</label>
<input type="date" name="fecha_clase" id="fecha_clase" required>

<button type="submit" class="btn btn-primary">Guardar Solicitud</button>
</form>

<?php if (!empty($mensaje)): ?>
<div class="mensaje"><?php echo $mensaje; ?></div>
<?php endif; ?>
</div>

<!-- CARD CONSULTA DE SOLICITUDES PENDIENTES -->
<div class="card">
<h2><i class="fas fa-list"></i> Solicitudes pendientes</h2>

<form method="GET">
<input type="text" name="buscar_pendientes" placeholder="Buscar por nombre">
<button type="submit" class="btn btn-secondary">Buscar</button>
</form>

<?php if(!empty($solicitudes_pendientes)): ?>
<table>
<thead>
<tr>
<th>Cliente</th>
<th>Asignatura</th>
<th>Fecha</th>
</tr>
</thead>
<tbody>
<?php foreach($solicitudes_pendientes as $sp): ?>
<tr>
<td><?php echo htmlspecialchars($sp['nombre']); ?></td>
<td><?php echo htmlspecialchars($sp['asignatura']); ?></td>
<td><?php echo htmlspecialchars($sp['fecha']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php elseif(isset($_GET['buscar_pendientes'])): ?>
<p>No se encontraron solicitudes pendientes.</p>
<?php endif; ?>
</div>

</div>
</main>
</body>
</html>

