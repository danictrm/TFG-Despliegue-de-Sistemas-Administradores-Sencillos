<?php
session_start();

// validar que el usuario esté logueado (profesor o admin)
if (!isset($_SESSION['empleado'])) {
    header("Location: /empleados/login.php");
    exit();
}

$nombre_empleado = $_SESSION['empleado'];
$id_empleado = $_SESSION['id_empleado'];

/* datos de conexión */
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
} catch (PDOException $e) {
    die("error de conexión a la base de datos");
}

if(isset($_POST['tipo'])){
    $tipo = $_POST['tipo'] === 'entrada' ? 'entrada' : 'salida';
    $stmt = $pdo->prepare("INSERT INTO fichajes (id_empleado, tipo) VALUES (?, ?)");
    $stmt->execute([$id_empleado, $tipo]);

    // redirigir para evitar reenvío al recargar
    header("Location: fichaje.php");
    exit();
}
// obtener últimos fichajes
$stmt = $pdo->prepare("SELECT f.tipo, f.fecha, e.nombre 
                       FROM fichajes f 
                       JOIN empleados e ON e.id_empleado=f.id_empleado
                       ORDER BY f.fecha DESC 
                       LIMIT 10");
$stmt->execute();
$ultimos_fichajes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- iconos -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../../../css/estiloAzul.css">
<style>
	button { width:100%; padding:15px; background-color:#2a5298; color:white; border:none; border-radius:8px; font-size:16px; font-weight:bold; cursor:pointer; margin-bottom:15px; transition:0.3s; }
	button:hover { background-color:#1e3c72; }
</style>


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
    <h2><i class="fas fa-id-card"></i> Fichaje de Empleados</h2>
    <p>Registro de entrada y salida del personal</p>
</div>

<div class="container">
    <div class="card">
        <form method="POST">
            <button type="submit" name="tipo" value="entrada"><i class="fas fa-sign-in-alt"></i> Registrar Entrada</button>
            <button type="submit" name="tipo" value="salida"><i class="fas fa-sign-out-alt"></i> Registrar Salida</button>
        </form>

        <div class="log-box">
            <p><strong>Últimos movimientos:</strong></p>
            <?php foreach($ultimos_fichajes as $f): ?>
                <p>- <?php echo htmlspecialchars($f['nombre']); ?>: <?php echo ucfirst($f['tipo']); ?> <?php echo date("H:i", strtotime($f['fecha'])); ?></p>
            <?php endforeach; ?>
        </div>
    </div>
</div>

</main>

<footer>
    Sistema de Gestión SDL © <?php echo date("Y"); ?>
</footer>

</body>
</html>
