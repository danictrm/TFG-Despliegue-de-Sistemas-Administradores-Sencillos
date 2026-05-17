#!/bin/bash

echo "=========================================="
echo "   INICIANDO COPIA DE SEGURIDAD ALPHA     "
echo "=========================================="

# 1. Comprobar si rsnapshot está configurado
if [ ! -f /etc/rsnapshot.conf ]; then
    echo "ERROR: No se encuentra /etc/rsnapshot.conf. Configura primero el servidor."
    exit 1
fi

echo ">> Paso 0: Limpieza de máquinas huérfanas..."
# Extraemos los nombres de las carpetas de destino configuradas en rsnapshot.conf
# Buscamos las líneas que empiezan por "backup", cogemos la 3ª columna y le quitamos la barra final
MAQUINAS_CONFIGURADAS=$(grep "^backup" /etc/rsnapshot.conf | awk '{print $3}' | sed 's|/$||')

# Recorremos todas las carpetas dentro de los niveles de rotación de rsnapshot (por defecto están dentro de la carpeta oculta .sync, y luego en alpha.X)
# Como usamos sync_first, los datos vivos están primero en .sync
if [ -d "/copias_seguridad/.sync" ]; then
    for DIR in /copias_seguridad/.sync/*; do
        if [ -d "$DIR" ]; then
            NOMBRE_CARPETA=$(basename "$DIR")
            # Si el nombre de la carpeta no está en la lista de máquinas configuradas, la borramos
            if ! echo "$MAQUINAS_CONFIGURADAS" | grep -qx "$NOMBRE_CARPETA"; then
                echo "   - Eliminando datos huérfanos de la máquina eliminada: $NOMBRE_CARPETA"
                rm -rf "/copias_seguridad/.sync/$NOMBRE_CARPETA"
            fi
        fi
    done
fi

echo ">> Paso 1: Sincronizando cambios por red (rsnapshot sync)..."
# Esto solo descarga los archivos nuevos o modificados hacia la carpeta .sync
rsnapshot sync
resultado_sync=$?

if [ $resultado_sync -ne 0 ]; then
    echo "ERROR: Falló la sincronización. Es posible que algún nodo esté apagado o inaccesible."
    # Rsnapshot suele devolver 0 si todo va bien, o 2 si ha habido avisos pero la copia es parcial.
    # No paramos el script porque queremos rotar lo que sí haya funcionado.
fi

echo ">> Paso 2: Analizando si hubo cambios reales en los datos..."
HAY_CAMBIOS="SI"
if [ -d "/copias_seguridad/alpha.0" ] && [ -d "/copias_seguridad/.sync" ]; then
    # Usamos rsync en modo simulación (-n) para comparar .sync con alpha.0
    # Si detecta algún archivo nuevo, modificado o eliminado, lo guardará en la variable CAMBIOS
    CAMBIOS=$(rsync -ani --delete /copias_seguridad/.sync/ /copias_seguridad/alpha.0/ | grep -E '^[>c*]')
    
    if [ -z "$CAMBIOS" ]; then
        HAY_CAMBIOS="NO"
        echo "   - Los datos son exactamente iguales a la última copia (alpha.0)."
    else
        echo "   - Se han detectado archivos modificados, creados o eliminados."
    fi
else
    echo "   - No hay historial previo (es el primer backup). Se forzará la rotación."
fi

echo ">> Paso 3: Rotando y empaquetando copias (rsnapshot alpha)..."
if [ "$HAY_CAMBIOS" == "SI" ]; then
    # Esto coge el contenido de .sync y lo pasa a alpha.0, moviendo las anteriores a alpha.1, alpha.2, etc.
    rsnapshot alpha
    resultado_alpha=$?

    if [ $resultado_alpha -eq 0 ]; then
        echo "✅ Tarea completada con éxito. Las copias Alpha han sido actualizadas."
    else
        echo "⚠️ La rotación finalizó, pero se reportaron algunas advertencias (Código: $resultado_alpha)."
    fi
else
    echo "✅ Proceso completado: NO se ha creado una nueva copia porque no hubo modificaciones en el origen."
fi