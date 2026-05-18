#!/bin/bash
# instalar_cron.sh
# Añade monitor_cron.php al crontab para ejecutarse cada minuto.
# Uso: sudo bash instalar_cron.sh

read -p "donde quieres guardar el php de cron_monitorización, ej:/var/www/html/monitor_cron.php" SCRIPT_PATH

# Comprobar que el archivo existe
if [ ! -f "$SCRIPT_PATH" ]; then
    echo "❌ No se encontró $SCRIPT_PATH"
    echo "   Asegúrate de subir monitor_cron.php antes de ejecutar este script."
    exit 1
fi

# Añadir al crontab solo si no está ya
CRON_LINE="* * * * * php $SCRIPT_PATH"

(crontab -l 2>/dev/null | grep -qF "$CRON_LINE") && {
    echo "✔ El cron ya estaba configurado. No se hizo ningún cambio."
    exit 0
}

(crontab -l 2>/dev/null; echo "$CRON_LINE") | crontab -

echo "✔ Cron instalado correctamente:"
echo "   $CRON_LINE"
echo ""
echo "Puedes verificarlo con: crontab -l"
