#!/bin/bash
set -e

# ==============================
# funciones
# ==============================

check_root() {
    if [ "$EUID" -ne 0 ]; then
        echo "este script debe ejecutarse como root."
        exit 1
    fi
}

parametros() {
    read -p "nombre de la base de datos: " DB_NAME
    read -p "nombre del usuario de la base de datos: " DB_USER

    read -s -p "password del usuario de la base de datos: " DB_PASS
    echo
    read -s -p "password de root: " ROOT_PASS
    echo
    read -s -p "password para el usuario administrador: " DB_PASS_A
    echo
}

instalar_mariadb() {
    echo "instalando mariadb..."
    apt update -y
    apt install -y mariadb-server
    systemctl enable mariadb
    systemctl start mariadb
}

seguridad_mariadb() {
    echo "configurando seguridad..."

    mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '${ROOT_PASS}'; FLUSH PRIVILEGES;"

    mysql -u root -p${ROOT_PASS} -e "DELETE FROM mysql.user WHERE User='';"
    mysql -u root -p${ROOT_PASS} -e "DELETE FROM mysql.user WHERE User='root' AND Host!='localhost';"
    mysql -u root -p${ROOT_PASS} -e "FLUSH PRIVILEGES;"
}

crear_database() {
echo "creando base de datos y usuarios..."

mysql -u root -p${ROOT_PASS} <<EOF
CREATE DATABASE IF NOT EXISTS ${DB_NAME};
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS 'adminDB'@'localhost' IDENTIFIED BY '${DB_PASS_A}';

# permisos de cada usuario

GRANT SELECT ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO 'adminDB'@'localhost';

FLUSH PRIVILEGES;
EOF
}

# ==============================
# ejecucion principal
# ==============================

echo "==== instalador modular mariadb ===="

check_root
parametros
instalar_mariadb
seguridad_mariadb
crear_database

echo "instalacion completada correctamente."


read -p "Quiere crear la base de datos ahora[s/n]?" empleados

if (empleados -eq y){

echo "==== gestion de empleados mariadb ===="

while true; do
    echo "--------------------------------------"
    echo "1) crear usuario solo lectura (select)"
    echo "2) crear usuario lectura y escritura"
    echo "3) crear usuario con todos los permisos"
    echo "4) eliminar usuario"
    echo "5) salir"
    echo "--------------------------------------"
    read -p "elige una opcion: " OPCION

    case $OPCION in
        1)
            read -p "nombre del usuario: " USER_NAME
            read -s -p "password del usuario: " USER_PASS
            echo

            mysql -u root -p${ROOT_PASS} <<EOF
	    CREATE USER IF NOT EXISTS '${USER_NAME}'@'localhost' IDENTIFIED BY '${USER_PASS}';
	    GRANT SELECT ON \`${DB_NAME}\`.* TO '${USER_NAME}'@'localhost';
	    FLUSH PRIVILEGES;
	    EOF

            echo "usuario creado con permisos de solo lectura."
            ;;

        2)
            read -p "nombre del usuario: " USER_NAME
            read -s -p "password del usuario: " USER_PASS
            echo

            mysql -u root -p${ROOT_PASS} <<EOF
	    CREATE USER IF NOT EXISTS '${USER_NAME}'@'localhost' IDENTIFIED BY '${USER_PASS}';
	    GRANT SELECT, INSERT, UPDATE, DELETE ON \`${DB_NAME}\`.* TO '${USER_NAME}'@'localhost';
	    FLUSH PRIVILEGES;
	    EOF

            echo "usuario creado con permisos de lectura y escritura."
            ;;

        3)
            read -p "nombre del usuario: " USER_NAME
            read -s -p "password del usuario: " USER_PASS
            echo

            mysql -u root -p${ROOT_PASS} <<EOF
	    CREATE USER IF NOT EXISTS '${USER_NAME}'@'localhost' IDENTIFIED BY '${USER_PASS}';
	    GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${USER_NAME}'@'localhost';
	    FLUSH PRIVILEGES;
	    EOF

            echo "usuario creado con todos los permisos."
            ;;

        4)
            read -p "nombre del usuario a eliminar: " USER_NAME

            mysql -u root -p${ROOT_PASS} -e "DROP USER IF EXISTS '${USER_NAME}'@'localhost'; FLUSH PRIVILEGES;"

            echo "usuario eliminado."
            ;;

        5)
            echo "saliendo..."
            exit 0
            ;;

        *)
            echo "opcion no valida."
            ;;
    esac
done


}
