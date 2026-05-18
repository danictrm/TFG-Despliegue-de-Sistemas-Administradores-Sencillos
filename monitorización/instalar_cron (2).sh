#!/bin/bash
# instalar_cron.sh
# Instala dependencias, añade IDs de Telegram al monitor y configura el cron.
# Uso: sudo bash instalar_cron.sh

# ── Instalar dependencias ────────────────────────────────────────────────────

echo "📦 Instalando curl y php-curl..."
apt-get update -qq
apt-get install -y curl php-curl

echo "✔ Dependencias instaladas."
echo ""

# ── Ruta del script PHP ──────────────────────────────────────────────────────

read -p "¿Dónde está guardado monitor_cron.php? (ej: /var/www/html/monitor_cron.php): " SCRIPT_PATH

if [ ! -f "$SCRIPT_PATH" ]; then
    echo "❌ No se encontró $SCRIPT_PATH"
    echo "   Asegúrate de subir monitor_cron.php antes de ejecutar este script."
    exit 1
fi

# ── Añadir nuevos Chat IDs de Telegram ──────────────────────────────────────

echo ""
echo "➕ ¿Quieres añadir nuevos Chat IDs de Telegram al monitor?"
read -p "   (s/n): " AÑADIR_IDS

if [[ "$AÑADIR_IDS" =~ ^[sS]$ ]]; then
    while true; do
        read -p "   Introduce un Chat ID (o deja vacío para terminar): " NUEVO_ID
        [ -z "$NUEVO_ID" ] && break

        # Comprueba si ya existe en el archivo
        if grep -q "'$NUEVO_ID'" "$SCRIPT_PATH"; then
            echo "   ⚠️  El ID $NUEVO_ID ya estaba en el archivo. Se omite."
        else
            # Lo inserta justo antes del cierre del array $CHAT_IDS
            sed -i "/^\];/i\\    '$NUEVO_ID'," "$SCRIPT_PATH"
            echo "   ✔ ID $NUEVO_ID añadido."
        fi
    done
fi

# ── Configurar crontab ───────────────────────────────────────────────────────

CRON_LINE="* * * * * php $SCRIPT_PATH >> ${SCRIPT_PATH%.*}.log 2>&1"

(crontab -l 2>/dev/null | grep -qF "php $SCRIPT_PATH") && {
    echo ""
    echo "✔ El cron ya estaba configurado. No se hizo ningún cambio."
    exit 0
}

(crontab -l 2>/dev/null; echo "$CRON_LINE") | crontab -

echo ""
echo "✔ Cron instalado correctamente:"
echo "   $CRON_LINE"
echo ""
echo "Puedes verificarlo con: crontab -l"
