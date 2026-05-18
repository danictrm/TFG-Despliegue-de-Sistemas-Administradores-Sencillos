<?php
session_start();

/* comprobar sesión */
if (!isset($_SESSION['empleado'])) {
    header("Location: /empleados/login.php");
    exit();
}

$nombre_empleado = $_SESSION['empleado'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Área de Empleados - Sistema de Gestión SDL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/estiloAzul.css">

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
    <h2>Área de Empleados</h2>
    <p>Seleccione una de las herramientas disponibles</p>
</div>

<div class="container">
    <div class="panels">
	<div class="panel">
            <i class="fas fa-chalkboard-teacher"></i>
            <h3>Solicitudes de Clases</h3>
            <p>Revisar y aprobar las solicitudes de clases pendientes de los clientes.</p>
            <a href="/empleados/toolsempleados/sol_clases.php">Acceder</a>
        </div>

	<div class="panel">
            <i class="fas fa-id-card"></i>
            <h3>Fichaje</h3>
            <p>Registro de entrada y salida de empleados.</p>
            <a href="/empleados/toolsempleados/fichaje.php">Acceder</a>
        </div>
	<div class="panel">
            <i class="fas fa-calendar"></i>
            <h3>Calendario</h3>
            <p>Calendario de clases</p>
            <a href="/empleados/toolsempleados/calendario.php">Acceder</a>
        </div>

<?php if(isset($_SESSION['rol']) && $_SESSION['rol'] === 'administrador'): ?>
        <div class="panel">
            <i class="fas fa-network-wired"></i>
            <h3>Monitorización de Servicios</h3>
            <p>Estado de servicios internos, procesos y recursos.</p>
            <a href="/empleados/toolsadministradores/monitorizacion.php">Acceder</a>
        </div>
<?php endif; ?>
<?php if(isset($_SESSION['rol']) && $_SESSION['rol'] === 'administrador'): ?>
        <div class="panel">
            <i class="fas fa-server"></i>
            <h3>Estado del Servidor</h3>
            <p>Información del servidor, carga y disponibilidad.</p>
            <a href="/empleados/toolsadministradores/estado_servidor.php">Acceder</a>
        </div>
<?php endif; ?>

<?php if(isset($_SESSION['rol']) && $_SESSION['rol'] === 'administrador'): ?>
        <div class="panel">
            <i class="fas fa-user-shield"></i>
            <h3>Administrador</h3>
            <p>Gestión avanzada de usuarios y herramientas internas.</p>
            <a href="/empleados/toolsadministradores/admin_tools.php">Acceder</a>
        </div>
<?php endif; ?>
    </div>
</div>

</main>

<footer>
    Sistema de Gestión SDL © <?php echo date("Y"); ?>
</footer>

</body>
</html>

