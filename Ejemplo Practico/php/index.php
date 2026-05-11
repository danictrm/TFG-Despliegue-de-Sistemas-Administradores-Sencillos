<?php
// aquí puedes añadir validación de sesión si más adelante agregas login
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inicio - Sistema de Gestión SDL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/estiloAzul.css">
</head>
<body class="inicio">

<header>
    <h1>Sistema de Gestión SDL</h1>
    <nav>
        <a href="index.php">Inicio</a>
        <a href="/clientes/formulario.php">Área Clientes</a>
        <a href="/empleados/administracion.php">Área Empleados</a>
    </nav>
</header>

<main>

<div class="hero">
    <h2>Bienvenido al Sistema de Gestión</h2>
    <p>Plataforma centralizada para la administración de clientes y empleados</p>
</div>

<div class="container">
    <div class="panels">

        <div class="panel">
            <i class="fas fa-users"></i>
            <h3>Área de Clientes</h3>
            <p>Registro y administración de clientes y clases.</p>
            <a href="/clientes/formulario.php">Acceder</a>
        </div>

        <div class="panel">
            <i class="fas fa-user-tie"></i>
            <h3>Área de Empleados</h3>
            <p>Monitorización, control y herramientas internas del personal.</p>
            <a href="/empleados/administracion.php">Acceder</a>
        </div>

    </div>
</div>

</main>

<footer>
    Sistema de Gestión SDL © <?php echo date("Y"); ?>
</footer>

</body>
</html>
