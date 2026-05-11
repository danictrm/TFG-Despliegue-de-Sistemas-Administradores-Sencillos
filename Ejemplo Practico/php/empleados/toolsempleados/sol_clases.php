<?php
session_start();

// validar sesión
if (!isset($_SESSION['empleado']) || !isset($_SESSION['id_empleado'])) {
    header("Location: login.php");
    exit();
}

$nombre_empleado = $_SESSION['empleado'];
$id_empleado = $_SESSION['id_empleado'];
$mensaje = "";

// conexión
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

    // ============================
    // PROCESAR ACCIONES
    // ============================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_solicitud'], $_POST['accion'])) {

        $id = intval($_POST['id_solicitud']);
        $estado = $_POST['accion'] === 'aprobada' ? 'aprobada' : 'rechazada';

        $pdo->beginTransaction();

        try {

            // si se aprueba → crear clase
            if ($estado === 'aprobada') {

                // obtener datos solicitud
                $stmt = $pdo->prepare("
                    SELECT id_cliente, id_asignatura, fecha
                    FROM solicitudes_clases
                    WHERE id_solicitud = ?
                ");
                $stmt->execute([$id]);
                $solicitud = $stmt->fetch();

                if ($solicitud) {

                    // evitar duplicados
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) 
                        FROM clases 
                        WHERE id_cliente = ? AND id_asignatura = ? AND fecha = ?
                    ");
                    $stmt->execute([
                        $solicitud['id_cliente'],
                        $solicitud['id_asignatura'],
                        $solicitud['fecha']
                    ]);

                    if ($stmt->fetchColumn() == 0) {

                        // 🔥 OBTENER PROFESOR DE LA ASIGNATURA
                        $stmt = $pdo->prepare("
                            SELECT id_empleado 
                            FROM asignaturas 
                            WHERE id_asignatura = ?
                        ");
                        $stmt->execute([$solicitud['id_asignatura']]);
                        $asignatura = $stmt->fetch();

                        if (!$asignatura) {
                            throw new Exception("Asignatura no encontrada");
                        }

                        $profesor_asignado = $asignatura['id_empleado'];

                        // insertar clase con el profesor correcto
                        $stmt = $pdo->prepare("
                            INSERT INTO clases (id_empleado, id_cliente, id_asignatura, fecha)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $profesor_asignado,
                            $solicitud['id_cliente'],
                            $solicitud['id_asignatura'],
                            $solicitud['fecha']
                        ]);
                    }
                }
            }

            // actualizar estado solicitud
            $stmt = $pdo->prepare("
                UPDATE solicitudes_clases 
                SET estado = ? 
                WHERE id_solicitud = ?
            ");
            $stmt->execute([$estado, $id]);

            $pdo->commit();
            $mensaje = "Solicitud procesada correctamente ✅";

        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = "Error al procesar la solicitud ❌";
        }
    }

    // ============================
    // FILTRO
    // ============================
    $filtro = trim($_GET['filtro'] ?? '');

    if ($filtro !== '') {
        $stmt = $pdo->prepare("
            SELECT sc.id_solicitud, c.nombre, c.telefono, c.email, a.nombre AS asignatura, sc.fecha, sc.estado
            FROM solicitudes_clases sc
            JOIN clientes c ON sc.id_cliente = c.id_cliente
            JOIN asignaturas a ON sc.id_asignatura = a.id_asignatura
            WHERE c.nombre LIKE ?
            ORDER BY sc.created_at DESC
        ");
        $stmt->execute(["%$filtro%"]);
    } else {
        $stmt = $pdo->query("
            SELECT sc.id_solicitud, c.nombre, c.telefono, c.email, a.nombre AS asignatura, sc.fecha, sc.estado
            FROM solicitudes_clases sc
            JOIN clientes c ON sc.id_cliente = c.id_cliente
            JOIN asignaturas a ON sc.id_asignatura = a.id_asignatura
            ORDER BY sc.created_at DESC
        ");
    }

    $solicitudes = $stmt->fetchAll();

} catch (PDOException $e) {
    $mensaje = "Error de conexión: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Solicitudes de Clases - Sistema de Gestión SDL</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/css/estiloAzul.css">

<style>
body.empleados .card {
    max-width: 1300px;
    width: 100%;
}
body.empleados .container {
    justify-content: center;
}
.table-container {
    width: 100%;
    overflow-x: auto;
}
</style>

</head>

<body class="empleados">

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
    <h2><i class="fas fa-list"></i> Solicitudes de Clases</h2>
    <p>Revise y gestione las solicitudes de los clientes</p>
</div>

<div class="container">

    <div class="card">

        <?php if(!empty($mensaje)): ?>
            <div class="mensaje"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <form method="GET" class="filtro">
            <input type="text" name="filtro" placeholder="Buscar por nombre" value="<?php echo htmlspecialchars($filtro); ?>">
            <button type="submit"><i class="fas fa-search"></i> Buscar</button>
        </form>

        <div class="table-container">
            <table>
                <tr>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Asignatura</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>

                <?php foreach($solicitudes as $s): ?>
                <tr>
                    <td><?php echo htmlspecialchars($s['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($s['telefono']); ?></td>
                    <td><?php echo htmlspecialchars($s['email']); ?></td>
                    <td><?php echo htmlspecialchars($s['asignatura']); ?></td>
                    <td><?php echo htmlspecialchars($s['fecha']); ?></td>
                    <td><?php echo ucfirst($s['estado']); ?></td>
                    <td>
                        <?php if($s['estado'] === 'pendiente'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id_solicitud" value="<?php echo $s['id_solicitud']; ?>">
                                
                                <button type="submit" name="accion" value="aprobada" class="aprobar">
                                    <i class="fas fa-check"></i>
                                </button>

                                <button type="submit" name="accion" value="rechazada" class="rechazar">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>

            </table>
        </div>

    </div>

</div>

</main>

<footer>
    Sistema de Gestión SDL © <?php echo date("Y"); ?>
</footer>

</body>
</html>
