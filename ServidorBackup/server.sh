#!/bin/bash

if [ "$EUID" -ne 0 ]; then
  echo "Por favor, ejecuta el script como root (usando sudo)."
  exit 1
fi

abrir_puerto_873() {
    echo "Abriendo el puerto 873 (Rsync) en el firewall..."
    if command -v ufw >/dev/null 2>&1; then
        ufw allow 873/tcp >/dev/null 2>&1
        echo " - Puerto 873 abierto usando UFW."
    else
        apt install -y iptables >/dev/null 2>&1
        iptables -I INPUT -p tcp --dport 873 -j ACCEPT
        echo " - Puerto 873 abierto usando iptables."
    fi
}

echo "===================================================="
echo "   ASISTENTE DE CONFIGURACIÓN RSNAPSHOT Y RSYNC     "
echo "===================================================="
echo "¿Qué función cumple esta máquina?"
echo "1) Cliente (Máquina Virtual a respaldar)"
echo "2) Servidor Principal de Backups (Debian 11)"
read -p "Elige una opción (1 o 2): " rol

if [ "$rol" == "1" ]; then
    echo -e "\n--- CONFIGURANDO CLIENTE (VM) ---"

    echo "Actualizando e instalando rsync..."
    apt update -y && apt install rsync -y

    abrir_puerto_873

    echo -e "\n¿Qué deseas hacer con la configuración de este cliente?"
    echo "1) Borrar todo y configurar desde cero (Se eliminarán módulos previos)"
    echo "2) Añadir un nuevo módulo (Mantener la configuración y módulos actuales)"
    echo "3) Editar una configuración ya existente"
    read -p "Elige una opción (1, 2 o 3): " opt_client

    if [ "$opt_client" == "3" ]; then
        if [ ! -f /etc/rsyncd.conf ]; then
            echo "⚠️ No se encontró configuración previa (/etc/rsyncd.conf)."
        else
            echo -e "\n--- EDITAR CONFIGURACIÓN EXISTENTE ---"
            echo "Configuración actual detectada:"
            grep -E "^hosts allow|^\[|^path" /etc/rsyncd.conf
            
            echo -e "\n1) Modificar la IP del servidor de backups autorizado (hosts allow)"
            echo "2) Modificar la ruta local de un módulo (path)"
            read -p "¿Qué deseas editar? (1 o 2): " edit_choice

            if [ "$edit_choice" == "1" ]; then
                read -p "Introduce la IP ACTUAL del servidor a reemplazar: " old_ip
                read -p "Introduce la NUEVA IP del servidor: " new_ip
                sed -i "s|hosts allow = $old_ip|hosts allow = $new_ip|g" /etc/rsyncd.conf
                echo "✅ IP del servidor actualizada."
            elif [ "$edit_choice" == "2" ]; then
                read -p "Introduce la ruta ACTUAL que quieres cambiar (Ej: /var/www/): " old_path
                read -p "Introduce la NUEVA ruta local: " new_path
                sed -i "s|path = $old_path|path = $new_path|g" /etc/rsyncd.conf
                echo "✅ Ruta del módulo actualizada."
            else
                echo "❌ Opción no válida."
            fi
            systemctl restart rsync
        fi

    elif [ "$opt_client" == "1" ] || [ "$opt_client" == "2" ]; then

        if [ "$opt_client" == "2" ] && [ ! -f /etc/rsyncd.conf ]; then
            echo "⚠️ No se encontró configuración previa. Se creará una configuración desde cero automáticamente."
            opt_client="1"
        fi

        if [ "$opt_client" == "1" ]; then
            read -p "Introduce la IP del Servidor de Backups (Debian): " ip_server
            echo "Creando configuración base del demonio en /etc/rsyncd.conf..."
            cat <<EOF > /etc/rsyncd.conf
uid = root
gid = root
read only = yes
use chroot = yes
hosts allow = $ip_server
EOF
        else
            echo "Manteniendo configuración actual. Se añadirán los nuevos módulos al final."
        fi

        read -p "¿Cuántas carpetas/rutas quieres respaldar desde esta máquina AHORA? (Ej: 1, 3, 10): " num_rutas

        if ! [[ "$num_rutas" =~ ^[0-9]+$ ]] || [ "$num_rutas" -lt 1 ]; then
            echo "❌ Número de rutas no válido. Debe ser un entero mayor que 0."
            exit 1
        fi

        for (( i=1; i<=num_rutas; i++ )); do
            echo ""
            read -p "Introduce la ruta local que quieres respaldar para el módulo $i (Ej: /var/www/): " ruta_backup
            read -p "Nombre del módulo rsync para la ruta $i (Ej: datos_vm$i, web$i, mysql$i): " nombre_modulo

            if [ -z "$ruta_backup" ] || [ -z "$nombre_modulo" ]; then
                echo "❌ La ruta y el nombre del módulo no pueden estar vacíos."
                exit 1
            fi

            cat <<EOF >> /etc/rsyncd.conf

[$nombre_modulo]
path = $ruta_backup
comment = Datos a respaldar de esta VM - módulo $i
EOF
        done

        echo "Habilitando e iniciando el servicio rsync..."
        systemctl enable rsync
        systemctl restart rsync

        echo "✅ Cliente configurado correctamente."
        echo "Módulos exportados actualmente en /etc/rsyncd.conf:"
        grep -E '^\[|^path' /etc/rsyncd.conf
    else
        echo "Opción no válida. Saliendo."
    fi

elif [ "$rol" == "2" ]; then
    echo -e "\n--- CONFIGURANDO SERVIDOR DE BACKUPS ---"

    echo "Instalando dependencias necesarias..."
    apt update -y && apt install rsync wget -y

    abrir_puerto_873

    echo "Comprobando si rsnapshot está instalado..."
    if ! command -v rsnapshot &> /dev/null; then
        echo "Descargando e instalando rsnapshot manualmente..."
        wget -q http://ftp.debian.org/debian/pool/main/r/rsnapshot/rsnapshot_1.4.5-1_all.deb
        apt install -y ./rsnapshot_1.4.5-1_all.deb
    else
        echo "rsnapshot ya está instalado."
    fi

    echo -e "\n¿Qué deseas hacer con la configuración del servidor?"
    echo "1) Borrar todo y configurar desde cero (Se eliminarán las máquinas previas)"
    echo "2) Añadir una nueva máquina a la lista (Mantener las máquinas ya guardadas)"
    echo "3) Editar la IP de una máquina ya existente"
    read -p "Elige una opción (1, 2 o 3): " opt_server

    if [ "$opt_server" == "3" ]; then
        if [ ! -f /etc/rsnapshot.conf ]; then
            echo "⚠️ No se encontró configuración previa (/etc/rsnapshot.conf)."
        else
            echo -e "\n--- EDITAR IP DE MÁQUINA EXISTENTE ---"
            echo "Máquinas configuradas actualmente:"
            grep "^backup" /etc/rsnapshot.conf | awk '{print "- Origen: " $2 "  -->  Destino: " $3}'

            echo ""
            read -p "Introduce la IP ACTUAL de la máquina que deseas modificar: " old_ip
            read -p "Introduce la NUEVA IP para esta máquina: " new_ip

            if grep -q "rsync://$old_ip/" /etc/rsnapshot.conf; then
                sed -i "s|rsync://$old_ip/|rsync://$new_ip/|g" /etc/rsnapshot.conf

                echo -e "\nComprobando la sintaxis del archivo de configuración..."
                rsnapshot configtest
                echo "✅ IP actualizada correctamente de $old_ip a $new_ip."
            else
                echo "❌ Error: No se encontró la IP $old_ip en el archivo de configuración."
            fi
        fi

    elif [ "$opt_server" == "1" ] || [ "$opt_server" == "2" ]; then

        if [ "$opt_server" == "2" ] && [ ! -f /etc/rsnapshot.conf ]; then
            echo "⚠️ No se encontró configuración previa. Se creará una configuración desde cero automáticamente."
            opt_server="1"
        fi

        if [ "$opt_server" == "1" ]; then
            echo "Preparando archivo de configuración base limpio en /etc/rsnapshot.conf..."
            [ -f /etc/rsnapshot.conf ] && mv /etc/rsnapshot.conf /etc/rsnapshot.conf.bak

            echo -e "config_version\t1.2" > /etc/rsnapshot.conf
            echo -e "snapshot_root\t/copias_seguridad/" >> /etc/rsnapshot.conf
            echo -e "cmd_cp\t/bin/cp" >> /etc/rsnapshot.conf
            echo -e "cmd_rm\t/bin/rm" >> /etc/rsnapshot.conf
            echo -e "cmd_rsync\t/usr/bin/rsync" >> /etc/rsnapshot.conf
            echo -e "cmd_logger\t/usr/bin/logger" >> /etc/rsnapshot.conf
            echo -e "sync_first\t1" >> /etc/rsnapshot.conf
            echo -e "retain\talpha\t7" >> /etc/rsnapshot.conf
            echo -e "retain\tbeta\t4" >> /etc/rsnapshot.conf
            echo -e "loglevel\t4" >> /etc/rsnapshot.conf
            echo -e "lockfile\t/var/run/rsnapshot.pid" >> /etc/rsnapshot.conf
            echo -e "exclude\t/dev/" >> /etc/rsnapshot.conf
            echo -e "exclude\t/proc/" >> /etc/rsnapshot.conf
            echo -e "exclude\t/sys/" >> /etc/rsnapshot.conf
            echo -e "exclude\t/tmp/" >> /etc/rsnapshot.conf
            echo -e "exclude\t/run/" >> /etc/rsnapshot.conf
            echo -e "exclude\t/var/run/" >> /etc/rsnapshot.conf
        else
            echo "Mantenimiento de archivo actual activado. Se añadirán nuevas líneas al final."
        fi

        echo -e "\n--- CONFIGURACIÓN DE OBJETIVOS (TARGETS) ---"
        read -p "¿Cuántas máquinas virtuales quieres añadir en este momento? (Ej: 1, 2): " num_maquinas

        for (( i=1; i<=num_maquinas; i++ )); do
            echo -e "\nDatos de la Máquina $i:"
            read -p "  Introduce la IP de la Máquina $i: " ip_vm
            read -p "  ¿Cuántos módulos/carpetas exporta esta máquina? (Ej: 1, 3, 10): " num_modulos

            if ! [[ "$num_modulos" =~ ^[0-9]+$ ]] || [ "$num_modulos" -lt 1 ]; then
                echo "❌ Número de módulos no válido para la máquina $i."
                exit 1
            fi

            for (( j=1; j<=num_modulos; j++ )); do
                echo "    Módulo $j de la máquina $i:"
                read -p "      Nombre del módulo rsync remoto (Ej: datos_vm1, web1, mysql1): " nombre_modulo
                read -p "      Nombre de la carpeta destino en backup (Ej: WebServer, BDs, Logs): " nombre_carpeta

                [[ "$nombre_carpeta" != */ ]] && nombre_carpeta="${nombre_carpeta}/"

                echo -e "backup\trsync://$ip_vm/$nombre_modulo/\t$nombre_carpeta" >> /etc/rsnapshot.conf
            done
        done

        mkdir -p /copias_seguridad/

        echo -e "\nComprobando la sintaxis del archivo de configuración..."
        rsnapshot configtest

        echo "✅ Servidor configurado correctamente."
        echo "⚠️ IMPORTANTE: Como has activado copias incrementales estrictas, el proceso ahora consta de 2 pasos:"
        echo "Paso 1: Ejecuta 'rsnapshot sync' (esto descargará solo los cambios desde las VMs)"
        echo "Paso 2: Ejecuta 'rsnapshot alpha' (esto empaquetará los cambios descargados en una copia nueva)"

    else
        echo "Opción no válida. Saliendo."
    fi

else
    echo "Opción no válida. Saliendo."
fi