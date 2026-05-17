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

# 1. Seleccionar Snapshot (Filtra solo las carpetas con un punto y un número, ej: alpha.0)
echo "Buscando copias disponibles en $BASE_DIR..."
snapshots=($(ls -1d $BASE_DIR/* 2>/dev/null | grep -E '\.[0-9]+$' | awk -F/ '{print $NF}'))

if [ ${#snapshots[@]} -eq 0 ]; then
    echo "❌ No se encontraron copias de seguridad válidas (alpha.X) en $BASE_DIR."
    exit 1
fi

echo -e "\nCopias disponibles en el tiempo:"
for i in "${!snapshots[@]}"; do
    echo "$((i+1))) ${snapshots[$i]}"
done

read -p "Selecciona el número de la copia que deseas restaurar (Ej: 1): " snap_idx
snap_idx=$((snap_idx-1))

if [ -z "${snapshots[$snap_idx]}" ]; then
    echo "❌ Selección inválida."
    exit 1
fi
SELECTED_SNAP="${snapshots[$snap_idx]}"

# 2. Seleccionar Máquina/Carpeta
echo -e "\nBuscando máquinas respaldadas dentro de $SELECTED_SNAP..."
maquinas=($(ls -1 $BASE_DIR/$SELECTED_SNAP/))

if [ ${#maquinas[@]} -eq 0 ]; then
    echo "❌ La copia $SELECTED_SNAP está vacía."
    exit 1
fi

echo "Máquinas/Carpetas en esta copia:"
for i in "${!maquinas[@]}"; do
    echo "$((i+1))) ${maquinas[$i]}"
done

read -p "Selecciona el número de la máquina a restaurar (Ej: 1): " maq_idx
maq_idx=$((maq_idx-1))

if [ -z "${maquinas[$maq_idx]}" ]; then
    echo "❌ Selección inválida."
    exit 1
fi
SELECTED_MAQ="${maquinas[$maq_idx]}"

# 3. Seleccionar Destino Remoto (SSH)
echo -e "\nHas seleccionado recuperar: $SELECTED_MAQ (De la copia: $SELECTED_SNAP)"
echo "Los archivos se enviarán directamente a la máquina cliente mediante SSH de forma segura."

read -p "Introduce la IP de la máquina cliente (Ej: 192.168.1.100): " CLIENT_IP
read -p "Introduce el usuario SSH de la máquina cliente (Ej: root): " SSH_USER
read -p "Introduce la ruta destino en el cliente (Ej: /var/www/): " DESTINO

if [ -z "$CLIENT_IP" ] || [ -z "$SSH_USER" ] || [ -z "$DESTINO" ]; then
    echo "❌ Faltan datos para realizar la conexión. Saliendo."
    exit 1
fi

# 4. Confirmación y Ejecución
echo -e "\n================ RESUMEN ================"
echo " - Origen de datos:  $BASE_DIR/$SELECTED_SNAP/$SELECTED_MAQ/"
echo " - Destino remoto:   $SSH_USER@$CLIENT_IP:$DESTINO"
echo "========================================="
echo "⚠️ Nota: Al conectar por SSH, es posible que el sistema te pida la contraseña del usuario '$SSH_USER'."

read -p "¿Estás seguro de que deseas comenzar la restauración? (s/n): " confirmacion

if [[ "$confirmacion" =~ ^[sS]$ ]]; then
    echo -e "\nIniciando restauración remota preservando permisos originales..."
    
    # Asegurar que el destino final termine en barra (/) para evitar anidar carpetas no deseadas
    [[ "${DESTINO}" != */ ]] && DESTINO="${DESTINO}/"
    
    # Usamos rsync sobre SSH (-e ssh). 
    # Mantiene permisos (-a), muestra progreso (-P) y transfiere por red cifrada.
    rsync -aP -e ssh "$BASE_DIR/$SELECTED_SNAP/$SELECTED_MAQ/" "$SSH_USER@$CLIENT_IP:$DESTINO"
    
    if [ $? -eq 0 ]; then
        echo -e "\n✅ Restauración remota completada con éxito en $CLIENT_IP."
    else
        echo -e "\n⚠️ Hubo algún problema durante la transferencia de archivos (Revisa si la contraseña o IP son correctas)."
    fi
else
    echo "Restauración cancelada por el usuario."
fi