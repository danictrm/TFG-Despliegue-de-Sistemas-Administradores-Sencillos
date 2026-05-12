#!/usr/bin/env bash
# install_totp.sh – Instala 2FA TOTP (Google Authenticator) en Sistema SDL

set -euo pipefail

G='\033[0;32m' Y='\033[1;33m' C='\033[0;36m' W='\033[1m' N='\033[0m'

clear
echo -e "${W}"
echo "  ╔══════════════════════════════════════════════╗"
echo "  ║     Instalador 2FA TOTP – Sistema SDL        ║"
echo "  ╚══════════════════════════════════════════════╝"
echo -e "${N}"

read -rp "  Ruta destino (ej: /var/www/html/empleados) : " DEST
read -rp "  Dominio público  (ej: https://miapp.com)   : " DOMAIN
read -rp "  Host BD                                    : " DB_HOST
read -rp "  Nombre BD                                  : " DB_NAME
read -rp "  Usuario BD                                 : " DB_USER
read -rsp "  Password BD                               : " DB_PASS; echo ""
read -rp "  Tabla de usuarios  (ej: empleados)         : " DB_TABLE
mkdir -p "$DEST"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Copiar archivos sustituyendo placeholders
for f in totp_helper.php login.php verify_totp.php setup_totp.php logout.php; do
    sed \
        -e "s|'10.20.26.150'|'${DB_HOST}'|g" \
        -e "s|'academia'|'${DB_NAME}'|g"      \
        -e "s|'webuser'|'${DB_USER}'|g"       \
        -e "s|'manager'|'${DB_PASS}'|g"       \
        -e "s|__TABLE__|${DB_TABLE}|g"         \
        "$SCRIPT_DIR/$f" > "$DEST/$f"
    echo -e "  ${G}✓${N} $f"
done

# MySQL no soporta IF NOT EXISTS en ALTER TABLE: comprobamos primero
COL=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" --skip-column-names \
    -e "SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA='${DB_NAME}' AND TABLE_NAME='${DB_TABLE}' AND COLUMN_NAME='totp_secret';" 2>/dev/null || echo "0")

if [ "$COL" = "0" ]; then
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" \
        -e "ALTER TABLE ${DB_TABLE} ADD COLUMN totp_secret VARCHAR(32) NULL;" \
        && echo -e "  ${G}✓${N} Columna totp_secret añadida" \
        || echo -e "  ${Y}⚠${N}  Añádela manualmente: ALTER TABLE ${DB_TABLE} ADD COLUMN totp_secret VARCHAR(32) NULL;"
else
    echo -e "  ${G}✓${N} Columna totp_secret ya existe, sin cambios"
fi

echo ""
echo -e "${G}${W}  ✅  Instalación completada${N}"
echo ""
echo -e "  ${W}Próximos pasos:${N}"
echo -e "    1. Cada usuario visita ${C}${DEST}/setup_totp.php${N} una vez para escanear su QR"
echo -e "    2. Desde entonces el login pide usuario + contraseña + código del móvil"
echo -e "    3. El usuario usa ${W}Google Authenticator${N} o ${W}Authy${N} (gratuitos)"
echo ""
