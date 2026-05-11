<?php
session_start();

require $_SERVER['DOCUMENT_ROOT']."/php_apoyo/datosBD.php";

$resultado = $conn->query("SELECT * FROM auditoria ORDER BY fecha DESC");
?>

<h2>Registro de Auditoría</h2>

<table border="1">
<tr>
<th>Usuario</th>
<th>Acción</th>
<th>IP</th>
<th>Página</th>
<th>Fecha</th>
</tr>

<?php while($fila = $resultado->fetch_assoc()){ ?>

<tr>
<td><?= $fila['usuario'] ?></td>
<td><?= $fila['accion'] ?></td>
<td><?= $fila['ip'] ?></td>
<td><?= $fila['pagina'] ?></td>
<td><?= $fila['fecha'] ?></td>
</tr>

<?php } ?>

</table>
