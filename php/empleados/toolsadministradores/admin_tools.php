<?php
session_start();
if(!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administrador'){
    die("acceso no autorizado");
}

$nombre_empleado = $_SESSION['empleado'];
?>

<!DOCTYPE html>

<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Herramientas de Administrador - Sistema de Gestión SDL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- iconos -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../../../css/admin_tools.css">

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
    <h2><i class="fas fa-user-shield"></i> Herramientas de Administrador</h2>
    <p>Gestión avanzada y herramientas internas del sistema</p>
</div>

<div class="container">

    <!-- Gestión de Usuarios -->
    <a class="tool-link" href="/empleados/toolsadministradores/admin_tools/usuarios.php">
        <div class="tool-box">
            <h3><i class="fas fa-users-cog"></i> Gestión de Usuarios</h3>
            <p>Crear, editar y eliminar usuarios del sistema.</p>
        </div>
    </a>

    <!-- Panel Base de Datos -->
    <a class="tool-link" href="/empleados/toolsadministradores/admin_tools/datosBD.php">
        <div class="tool-box">
            <h3><i class="fas fa-database"></i> Panel Base de Datos</h3>
            <p>Visualizar alumnos, profesores, asignaturas y clases registradas.</p>
        </div>
    </a>

</div>

</main>
<footer>
    Sistema de Gestión SDL © <?php echo date("Y"); ?>
</footer>

</body>
</html>
