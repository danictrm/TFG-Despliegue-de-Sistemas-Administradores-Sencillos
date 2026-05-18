#!/bin/bash

# Comprobar root
if [ "$EUID" -ne 0 ]; then
  echo "❌ Error: Por favor, ejecuta el script como root (sudo)."
  exit 1
fi

echo "=========================================="
echo "       ASISTENTE DE RESTAURACIÓN          "
echo "=========================================="

BASE_DIR="/copias_seguridad"

# 1. Seleccionar Snapshot
echo "Buscando copias disponibles en $BASE_DIR..."

snapshots=($(ls -1d "$BASE_DIR"/* 2>/dev/null | grep -E '\.[0-9]+$' | awk -F/ '{print $NF}'))

if [ ${#snapshots[@]} -eq 0 ]; then
    echo "❌ No se encontraron copias de seguridad válidas (alpha.X) en $BASE_DIR."
    exit 1
fi

echo -e "\nCopias disponibles:"
for i in "${!snapshots[@]}"; do
    echo "$((i+1))) ${snapshots[$i]}"
done

read -p "Selecciona el número de la copia que deseas restaurar: " snap_idx
snap_idx=$((snap_idx-1))

if [ -z "${snapshots[$snap_idx]}" ]; then
    echo "❌ Selección inválida."
    exit 1
fi

SELECTED_SNAP="${snapshots[$snap_idx]}"

# 2. Seleccionar Máquina/Carpeta
echo -e "\nBuscando máquinas respaldadas dentro de $SELECTED_SNAP..."

maquinas=($(ls -1 "$BASE_DIR/$SELECTED_SNAP/"))

if [ ${#maquinas[@]} -eq 0 ]; then
    echo "❌ La copia $SELECTED_SNAP está vacía."
    exit 1
fi

echo "Máquinas/Carpetas disponibles:"
for i in "${!maquinas[@]}"; do
    echo "$((i+1))) ${maquinas[$i]}"
done

read -p "Selecciona el número de la máquina a restaurar: " maq_idx
maq_idx=$((maq_idx-1))

if [ -z "${maquinas[$maq_idx]}" ]; then
    echo "❌ Selección inválida."
    exit 1
fi

SELECTED_MAQ="${maquinas[$maq_idx]}"

SOURCE_PATH="$BASE_DIR/$SELECTED_SNAP/$SELECTED_MAQ"

# 3. Elegir restauración
echo -e "\n¿Qué deseas restaurar?"
echo "1) Restaurar toda la carpeta"
echo "2) Restaurar un único fichero"
echo "3) Restaurar una carpeta concreta"

read -p "Selecciona una opción (1, 2 o 3): " RESTORE_OPTION

case "$RESTORE_OPTION" in

    1)
        RESTORE_SOURCE="$SOURCE_PATH/"
    ;;

    2)
        echo -e "\nListado de archivos disponibles:"
        find "$SOURCE_PATH" -type f | sed "s|$SOURCE_PATH/||"

        echo
        read -p "Introduce la ruta exacta del fichero a restaurar: " FILE_TO_RESTORE

        FULL_FILE_PATH="$SOURCE_PATH/$FILE_TO_RESTORE"

        if [ ! -f "$FULL_FILE_PATH" ]; then
            echo "❌ El fichero seleccionado no existe."
            exit 1
        fi

        RESTORE_SOURCE="$FULL_FILE_PATH"
    ;;

    3)
        echo -e "\nListado de carpetas disponibles:"
        find "$SOURCE_PATH" -type d | sed "s|$SOURCE_PATH/||"

        echo
        read -p "Introduce la ruta exacta de la carpeta a restaurar: " DIR_TO_RESTORE

        FULL_DIR_PATH="$SOURCE_PATH/$DIR_TO_RESTORE"

        if [ ! -d "$FULL_DIR_PATH" ]; then
            echo "❌ La carpeta seleccionada no existe."
            exit 1
        fi

        RESTORE_SOURCE="${FULL_DIR_PATH}/"
    ;;

    *)
        echo "❌ Opción inválida."
        exit 1
    ;;

esac

# 4. Datos conexión SSH
echo -e "\nHas seleccionado recuperar:"
echo "$RESTORE_SOURCE"

read -p "Introduce la IP de la máquina cliente: " CLIENT_IP
read -p "Introduce el usuario SSH: " SSH_USER
read -p "Introduce la ruta destino en el cliente: " DESTINO

if [ -z "$CLIENT_IP" ] || [ -z "$SSH_USER" ] || [ -z "$DESTINO" ]; then
    echo "❌ Faltan datos para realizar la conexión."
    exit 1
fi

# Asegurar barra final
[[ "${DESTINO}" != */ ]] && DESTINO="${DESTINO}/"

# 5. Crear conexión SSH persistente
echo -e "\nEstableciendo conexión SSH segura..."

SSH_SOCKET="/tmp/restore_${CLIENT_IP}_${SSH_USER}.sock"

ssh -o ControlMaster=yes \
    -o ControlPath="$SSH_SOCKET" \
    -o ControlPersist=10m \
    -fnN "$SSH_USER@$CLIENT_IP"

if [ $? -ne 0 ]; then
    echo "❌ No se pudo establecer la conexión SSH."
    exit 1
fi

echo "✅ Conexión SSH establecida."

# 6. Verificar/Crear destino remoto
echo -e "\nComprobando ruta destino remota..."

ssh -o ControlPath="$SSH_SOCKET" \
    "$SSH_USER@$CLIENT_IP" \
    "mkdir -p '$DESTINO'"

if [ $? -ne 0 ]; then
    echo "❌ No se pudo crear/verificar la ruta destino."

    ssh -O exit -o ControlPath="$SSH_SOCKET" \
        "$SSH_USER@$CLIENT_IP" 2>/dev/null

    exit 1
fi

echo "✅ Ruta destino preparada."

# 7. Confirmación
echo -e "\n================ RESUMEN ================"
echo " - Origen:           $RESTORE_SOURCE"
echo " - Destino remoto:   $SSH_USER@$CLIENT_IP:$DESTINO"
echo "========================================="

read -p "¿Deseas comenzar la restauración? (s/n): " confirmacion

if [[ "$confirmacion" =~ ^[sS]$ ]]; then

    echo -e "\nIniciando restauración..."

    rsync -aP \
        -e "ssh -o ControlPath=$SSH_SOCKET" \
        "$RESTORE_SOURCE" \
        "$SSH_USER@$CLIENT_IP:$DESTINO"

    if [ $? -eq 0 ]; then
        echo -e "\n✅ Restauración completada correctamente."
    else
        echo -e "\n⚠️ Hubo algún problema durante la restauración."
    fi

else
    echo "❌ Restauración cancelada por el usuario."
fi

# 8. Cerrar conexión SSH persistente
ssh -O exit \
    -o ControlPath="$SSH_SOCKET" \
    "$SSH_USER@$CLIENT_IP" 2>/dev/null

exit 0