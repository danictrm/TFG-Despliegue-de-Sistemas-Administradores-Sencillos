<?php
session_start();
if(!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administrador'){
    die("acceso no autorizado");
}

$nombre_empleado = $_SESSION['empleado'];
// ------------------- FUNCIONES -------------------

// CPU porcentaje local
function cpu_usage_percent_local() {
    $stat1 = file('/proc/stat');
    sleep(1);
    $stat2 = file('/proc/stat');

    $cpu1 = preg_split('/\s+/', $stat1[0]);
    $cpu2 = preg_split('/\s+/', $stat2[0]);

    $idle1 = $cpu1[4];
    $idle2 = $cpu2[4];

    $total1 = array_sum(array_slice($cpu1,1));
    $total2 = array_sum(array_slice($cpu2,1));

    $total_diff = $total2 - $total1;
    $idle_diff = $idle2 - $idle1;

    if ($total_diff == 0) return 0;
    return round((($total_diff - $idle_diff)/$total_diff)*100,2);
}

// obtener status del servidor local
function get_local_status() {
    $mem_total = trim(shell_exec("free -m | awk '/^Mem:/ {print $2}'"));
    $mem_used  = trim(shell_exec("free -m | awk '/^Mem:/ {print $3}'"));
    $mem_percent = $mem_total>0?round($mem_used/$mem_total*100,2):0;

    $disk_total = function_exists('disk_total_space') ? disk_total_space("/") : 0;
    $disk_free  = function_exists('disk_free_space') ? disk_free_space("/") : 0;
    $disk_used  = $disk_total - $disk_free;
    $disk_percent = $disk_total>0?round($disk_used/$disk_total*100,2):0;

    $cpu_percent = cpu_usage_percent_local();

    $os_info = trim(shell_exec("lsb_release -ds 2>/dev/null"));
    if (!$os_info) $os_info = 'Debian '.trim(shell_exec("cat /etc/debian_version 2>/dev/null"));
    $hostname = gethostname();
    $kernel = trim(shell_exec("uname -r"));
    $php_version = phpversion();
    $mariadb_version = trim(shell_exec("mysql -V 2>/dev/null")) ?: 'No disponible';

    return [
        'cpu' => $cpu_percent,
        'mem_used' => $mem_used,
        'mem_total' => $mem_total,
        'mem_percent' => $mem_percent,
        'disk_used' => $disk_used,
        'disk_total' => $disk_total,
        'disk_percent' => $disk_percent,
        'os_info' => $os_info,
        'hostname' => $hostname,
        'kernel' => $kernel,
        'php_version' => $php_version,
        'mariadb_version' => $mariadb_version
    ];
}

// ------------------- DATOS REMOTO -------------------
$remote_host = '10.20.26.150';
$remote_user = 'monitor';
$remote_pass = 'manager';

// inicializar valores por defecto en caso de fallo
$cpu_r=$mem_used_r=$mem_total_r=$mem_percent_r=$disk_used_r=$disk_total_r=$disk_percent_r=$os_info_r=$hostname_r=$kernel_r=$php_version_r=$mariadb_version_r='No disponible';

if(function_exists('ssh2_connect')) {
    $connection = @ssh2_connect($remote_host, 22);
    if($connection && @ssh2_auth_password($connection, $remote_user, $remote_pass)) {
        // ejecutar comando remoto y obtener salida
        function ssh2_exec_cmd($connection, $cmd) {
            $stream = ssh2_exec($connection, $cmd);
            stream_set_blocking($stream, true);
            return trim(stream_get_contents($stream));
        }

        $cpu_r = ssh2_exec_cmd($connection, <<<CMD
cpu1=\$(cat /proc/stat | head -1); sleep 1; cpu2=\$(cat /proc/stat | head -1);
idle1=\$(echo \$cpu1 | awk '{print \$5}'); idle2=\$(echo \$cpu2 | awk '{print \$5}');
total1=\$(echo \$cpu1 | awk '{sum=0;for(i=2;i<=NF;i++) sum+=\$i; print sum}'); 
total2=\$(echo \$cpu2 | awk '{sum=0;for(i=2;i<=NF;i++) sum+=\$i; print sum}');
echo "scale=2; if (\$total2-\$total1==0) 0 else ((\$total2-\$total1)-(\$idle2-\$idle1))/(\$total2-\$total1)*100" | bc
CMD
        );
        $mem_used_r = ssh2_exec_cmd($connection, "free -m | awk '/^Mem:/ {print \$3}'");
        $mem_total_r = ssh2_exec_cmd($connection, "free -m | awk '/^Mem:/ {print \$2}'");
        $mem_percent_r = $mem_total_r>0 ? round($mem_used_r/$mem_total_r*100,2) : 0;
        $disk_used_r = ssh2_exec_cmd($connection, "df / | tail -1 | awk '{print \$3*1024}'");
        $disk_total_r = ssh2_exec_cmd($connection, "df / | tail -1 | awk '{print \$2*1024}'");
        $disk_percent_r = $disk_total_r>0 ? round($disk_used_r/$disk_total_r*100,2) : 0;
        $os_info_r = ssh2_exec_cmd($connection, "lsb_release -ds 2>/dev/null || cat /etc/debian_version");
        $hostname_r = ssh2_exec_cmd($connection, "hostname");
        $kernel_r = ssh2_exec_cmd($connection, "uname -r");
        $php_version_r = ssh2_exec_cmd($connection, "php -v | head -1 | awk '{print \$2}'");
        $mariadb_version_r = ssh2_exec_cmd($connection, "mysql -V 2>/dev/null || echo 'No disponible'");
    }
}

// ------------------- DATOS LOCALES -------------------
$local = get_local_status();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Estado del Servidor - Sistema de Gestión SDL</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="refresh" content="2">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../../../css/estado_servidor.css">

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
<h2><i class="fas fa-server"></i> Estado del Servidor</h2>
<p>Recursos y rendimiento simplificado</p>
</div>
<div class="container">

<!-- PANEL LOCAL -->
<div class="panel">
<h3><i class="fas fa-laptop-code"></i> Servidor Local</h3>
<p class="metric">Distribución: <?php echo $local['os_info']; ?></p>
<p class="metric">Hostname: <?php echo $local['hostname']; ?></p>
<p class="metric">Kernel: <?php echo $local['kernel']; ?></p>
<p class="metric">PHP: <?php echo $local['php_version']; ?></p>
<p class="metric">MariaDB/MySQL: <?php echo $local['mariadb_version']; ?></p>
<p class="metric">CPU: <?php echo $local['cpu']; ?>% usada</p>
<p class="metric">Memoria: <?php echo $local['mem_used']; ?> MB / <?php echo $local['mem_total']; ?> MB (<?php echo $local['mem_percent']; ?>%)</p>
<p class="metric">Disco: <?php echo round($local['disk_used']/1073741824,2); ?> GB / <?php echo round($local['disk_total']/1073741824,2); ?> GB (<?php echo $local['disk_percent']; ?>%)</p>
</div>

<!-- PANEL REMOTO -->
<div class="panel">
<h3><i class="fas fa-server"></i> Servidor Remoto</h3>
<p class="metric">Distribución: <?php echo $os_info_r; ?></p>
<p class="metric">Hostname: <?php echo $hostname_r; ?></p>
<p class="metric">Kernel: <?php echo $kernel_r; ?></p>
<p class="metric">PHP: <?php echo $php_version_r; ?></p>
<p class="metric">MariaDB/MySQL: <?php echo $mariadb_version_r; ?></p>
<p class="metric">CPU: <?php echo $cpu_r; ?>% usada</p>
<p class="metric">Memoria: <?php echo $mem_used_r; ?> MB / <?php echo $mem_total_r; ?> MB (<?php echo $mem_percent_r; ?>%)</p>
<p class="metric">Disco: <?php echo round($disk_used_r/1073741824,2); ?> GB / <?php echo round($disk_total_r/1073741824,2); ?> GB (<?php echo $disk_percent_r; ?>%)</p>
</div>

</div>
</main>
<footer>Sistema de Gestión SDL © <?php echo date("Y"); ?></footer>
</body>
</html>
