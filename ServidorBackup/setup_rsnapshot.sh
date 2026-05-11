#!/bin/bash

# Comprobación de privilegios de administrador
if [ "$EUID" -ne 0 ]; then
  echo "Por favor, ejecuta el script como root."
  exit 1
fi

# Tomamos directamente la acción como primer parámetro
ACCION_FICHERO=$1
shift 1 # Eliminamos el primer argumento (acción) para dejar solo la lista de VMs en $@

echo -e "\n--- CONFIGURANDO SERVIDOR CENTRAL ---"
apt update -y && apt install rsync wget -y

if ! command -v rsnapshot &> /dev/null; then
    wget -q http://ftp.debian.org/debian/pool/main/r/rsnapshot/rsnapshot_1.4.5-1_all.deb 
    apt install -y ./rsnapshot_1.4.5-1_all.deb
fi

# Si es 'borrar' o no existe el archivo, creamos el archivo base
if [ "$ACCION_FICHERO" == "borrar" ] || [ ! -f /etc/rsnapshot.conf ]; then
    echo "Preparando archivo base en /etc/rsnapshot.conf..."
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
    echo "Añadiendo nuevas máquinas al archivo existente..."
fi

# Bucle para inyectar todas las máquinas pasadas como argumento (formato IP:Modulo:Nombre)
for PAREJA_VM in "$@"; do
    # Extraemos las tres partes usando cut
    IP_VM=$(echo "$PAREJA_VM" | cut -d':' -f1)
    MODULO_VM=$(echo "$PAREJA_VM" | cut -d':' -f2)
    NOMBRE_VM=$(echo "$PAREJA_VM" | cut -d':' -f3)
    
    # Si el módulo llegó vacío por alguna razón, usamos datos_vm por seguridad
    [ -z "$MODULO_VM" ] && MODULO_VM="datos_vm"
    
    [[ "${NOMBRE_VM}" != */ ]] && NOMBRE_VM="${NOMBRE_VM}/"
    
    # Ahora inyectamos la línea con el módulo correcto
    echo -e "backup\trsync://$IP_VM/$MODULO_VM/\t$NOMBRE_VM" >> /etc/rsnapshot.conf
    echo " -> Añadida: $IP_VM ($MODULO_VM) hacia $NOMBRE_VM"
done

mkdir -p /copias_seguridad/
rsnapshot configtest > /dev/null 2>&1
echo "✅ Servidor configurado correctamente."