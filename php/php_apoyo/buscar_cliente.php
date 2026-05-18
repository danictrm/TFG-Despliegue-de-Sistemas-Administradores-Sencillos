<?php
// buscar_cliente.php - autocompletado ignorando mayúsculas, minúsculas y tildes

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

header('Content-Type: application/json');

function normalize($str) {
    // eliminar tildes
    $search  = ['á','é','í','ó','ú','Á','É','Í','Ó','Ú','ñ','Ñ'];
    $replace = ['a','e','i','o','u','A','E','I','O','U','n','N'];
    return str_replace($search, $replace, $str);
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $nombre = trim($_GET['nombre'] ?? '');
    if ($nombre === '') {
        echo json_encode(null);
        exit;
    }

    // normalizar nombre recibido
    $nombre_norm = normalize($nombre);

    // consulta normalizando los nombres en la base de datos
    $stmt = $pdo->prepare("
        SELECT telefono, email
        FROM clientes
        WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(nombre,'á','a'),'é','e'),'í','i'),'ó','o'),'ú','u'),'Á','A'),'É','E'),'Í','I'),'Ó','O'),'Ú','U'),'ñ','n'),'Ñ','N') 
              LIKE CONCAT('%', ?, '%')
        LIMIT 1
    ");
    $stmt->execute([$nombre_norm]);
    $cliente = $stmt->fetch();

    echo json_encode($cliente ?: null);

} catch (PDOException $e) {
    echo json_encode(null);
}
