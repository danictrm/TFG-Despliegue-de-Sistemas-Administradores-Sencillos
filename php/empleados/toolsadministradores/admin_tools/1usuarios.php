<?php
session_start();

// solo administradores
if(!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administrador'){
    die("acceso no autorizado");
}

$nombre_empleado = $_SESSION['empleado'];

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

// manejar eliminación
if(isset($_GET['delete'])){
    $stmt = $pdo->prepare("DELETE FROM empleados WHERE id_empleado = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: usuarios.php");
    exit;
}

// obtener datos para edición
$edit_user = null;
if(isset($_GET['edit'])){
    $stmt = $pdo->prepare("SELECT * FROM empleados WHERE id_empleado=?");
    $stmt->execute([$_GET['edit']]);
    $edit_user = $stmt->fetch();
}

// manejar creación/edición
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $nombre = trim($_POST['nombre']);
    $usuario = trim($_POST['usuario']);
    $password = trim($_POST['password']);
    $rol = $_POST['rol'];

    if(!empty($_POST['id_empleado'])){ // editar
        $stmt = $pdo->prepare("UPDATE empleados SET nombre=?, usuario=?, password=?, rol=? WHERE id_empleado=?");
        $stmt->execute([$nombre, $usuario, $password, $rol, $_POST['id_empleado']]);
    } else { // crear nuevo
        $stmt = $pdo->prepare("INSERT INTO empleados (nombre, usuario, password, rol) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nombre, $usuario, $password, $rol]);
    }
    header("Location: usuarios.php");
    exit;
}

// obtener lista de empleados
$stmt = $pdo->query("SELECT id_empleado, nombre, usuario, rol FROM empleados ORDER BY id_empleado ASC");
$empleados = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Gestión de Empleados - Sistema SDL</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../../../../css/usuarios.css">

</head>
<body>

<header>
<h1>Sistema de Gestión SDL</h1>
<nav>
    <span>Bienvenido <strong><?php echo htmlspecialchars($nombre_empleado); ?></strong></span>
    <a href="/index.php">Inicio</a>
    <a href="/clientes/formulario.php">Área Clientes</a>
    <a href="/empleados/administracion.php">Área Empleados</a>
    <a href="/empleados/logout.php">Cerrar sesión</a>
</nav>
</header>

<main>

<!-- formulario crear/editar -->
<div class="form-card">
<h2><?php echo $edit_user ? "Editar Usuario" : "Crear Usuario"; ?></h2>
<form method="POST">
<input type="hidden" name="id_empleado" value="<?php echo $edit_user['id_empleado'] ?? ''; ?>">
<input type="text" name="nombre" placeholder="Nombre completo" required value="<?php echo $edit_user['nombre'] ?? ''; ?>">
<input type="text" name="usuario" placeholder="Usuario" required value="<?php echo $edit_user['usuario'] ?? ''; ?>">
<input type="text" name="password" placeholder="Contraseña" required value="<?php echo $edit_user['password'] ?? ''; ?>">
<select name="rol" required>
    <option value="profesor" <?php if(($edit_user['rol'] ?? '')==='profesor') echo 'selected'; ?>>Profesor</option>
    <option value="administrador" <?php if(($edit_user['rol'] ?? '')==='administrador') echo 'selected'; ?>>Administrador</option>
</select>
<button type="submit"><?php echo $edit_user ? "Actualizar" : "Crear Usuario"; ?></button>
</form>
</div>

<!-- tabla de usuarios -->
<table>
<thead>
<tr>
<th>ID</th>
<th>Nombre</th>
<th>Usuario</th>
<th>Rol</th>
<th>Acciones</th>
</tr>
</thead>
<tbody>
<?php foreach($empleados as $emp): ?>
<tr>
<td><?php echo $emp['id_empleado']; ?></td>
<td><?php echo htmlspecialchars($emp['nombre']); ?></td>
<td><?php echo htmlspecialchars($emp['usuario']); ?></td>
<td><?php echo htmlspecialchars($emp['rol']); ?></td>
<td class="actions">
<a href="?edit=<?php echo $emp['id_empleado']; ?>">Editar</a>
<a href="?delete=<?php echo $emp['id_empleado']; ?>" onclick="return confirm('¿Eliminar este usuario?')">Eliminar</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</main>

<footer>Sistema de Gestión SDL © <?php echo date("Y"); ?></footer>
</body>
</html>
