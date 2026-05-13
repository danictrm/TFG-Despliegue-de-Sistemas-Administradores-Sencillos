#!/bin/bash
set -e

if [ "$EUID" -ne 0 ]; then
    echo "ejecuta el script como root."
    exit 1
fi

dpkg --configure -a

read -p "password de root de MariaDB: " ROOT_PASS
read -p "nombre del usuario a crear: " DB_USER
read -s -p "password del usuario: " DB_PASS
echo

apt update -y && apt install -y mariadb-server
systemctl enable mariadb && systemctl start mariadb

# Permitir conexiones remotas
sed -i "s/^bind-address\s*=.*/bind-address = 0.0.0.0/" /etc/mysql/mariadb.conf.d/50-server.cnf
systemctl restart mariadb

mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '${ROOT_PASS}'; FLUSH PRIVILEGES;"

mysql -u root -p"${ROOT_PASS}" <<EOF

CREATE DATABASE IF NOT EXISTS academia;

CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON academia.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;

USE academia;

CREATE TABLE IF NOT EXISTS empleados (
    id_empleado  INT(11)      NOT NULL AUTO_INCREMENT,
    usuario      VARCHAR(50)  DEFAULT NULL,
    password     VARCHAR(255) DEFAULT NULL,
    nombre       VARCHAR(50)  NOT NULL,
    especialidad VARCHAR(50)  DEFAULT NULL,
    rol          VARCHAR(20)  NOT NULL DEFAULT 'profesor',
    email        VARCHAR(50)  DEFAULT NULL,
    totp_secret  VARCHAR(32)  DEFAULT NULL,
    PRIMARY KEY (id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS asignaturas (
    id_asignatura INT(11)     NOT NULL AUTO_INCREMENT,
    nombre        VARCHAR(50) NOT NULL,
    id_empleado   INT(11)     NOT NULL,
    PRIMARY KEY (id_asignatura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS clientes (
    id_cliente INT(11)      NOT NULL AUTO_INCREMENT,
    nombre     VARCHAR(50)  NOT NULL,
    telefono   VARCHAR(20)  DEFAULT NULL,
    email      VARCHAR(100) DEFAULT NULL,
    PRIMARY KEY (id_cliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS clases (
    id_clase      INT(11) NOT NULL AUTO_INCREMENT,
    id_empleado   INT(11) DEFAULT NULL,
    id_cliente    INT(11) DEFAULT NULL,
    id_asignatura INT(11) DEFAULT NULL,
    fecha         DATE    DEFAULT NULL,
    PRIMARY KEY (id_clase),
    FOREIGN KEY (id_empleado)   REFERENCES empleados(id_empleado),
    FOREIGN KEY (id_cliente)    REFERENCES clientes(id_cliente),
    FOREIGN KEY (id_asignatura) REFERENCES asignaturas(id_asignatura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS fichajes (
    id_fichaje  INT(11)                  NOT NULL AUTO_INCREMENT,
    id_empleado INT(11)                  NOT NULL,
    tipo        ENUM('entrada','salida') NOT NULL,
    fecha       TIMESTAMP                NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id_fichaje),
    FOREIGN KEY (id_empleado) REFERENCES empleados(id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS solicitudes_clases (
    id_solicitud  INT(11)                                  NOT NULL AUTO_INCREMENT,
    id_cliente    INT(11)                                  NOT NULL,
    id_empleado   INT(11)                                  NOT NULL,
    id_asignatura INT(11)                                  NOT NULL,
    fecha         DATE                                     NOT NULL,
    estado        ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
    created_at    TIMESTAMP                                NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id_solicitud),
    FOREIGN KEY (id_cliente)    REFERENCES clientes(id_cliente),
    FOREIGN KEY (id_empleado)   REFERENCES empleados(id_empleado),
    FOREIGN KEY (id_asignatura) REFERENCES asignaturas(id_asignatura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


EOF

echo "listo. base de datos 'academia' creada con usuario '${DB_USER}' accesible remotamente."
