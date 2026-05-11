#!/bin/bash

# Comprobacion de root
if [ "$EUID" -ne 0 ]; then
  echo "Por favor, ejecuta el script como root (usando sudo)."
  exit 1
fi

echo "--- Actualizando sistema e instalando dependencias ---"
apt update
apt install -y curl apache2 php libapache2-mod-php

echo "--- Configurando permisos de ejecucion para los scripts ---"
chmod +x /usr/local/bin/backup_inteligente.sh
chmod +x /usr/local/bin/setup_rsnapshot.sh

echo "--- Aplicando politicas de seguridad estricta en Sudoers (Nivel 2) ---"
# Eliminamos cualquier rastro de la directiva insegura anterior por si acaso
sed -i '/www-data ALL=(ALL) NOPASSWD: ALL/d' /etc/sudoers

# Creamos un archivo dedicado en sudoers.d (es la practica mas segura y limpia)
cat <<EOF > /etc/sudoers.d/rsnapshot_web
# Permisos especificos para el panel web SDL Control Center
www-data ALL=(root) NOPASSWD: /usr/local/bin/backup_inteligente.sh
www-data ALL=(root) NOPASSWD: /usr/local/bin/setup_rsnapshot.sh *
www-data ALL=(root) NOPASSWD: /usr/bin/tee /etc/cron.d/rsnapshot_web
www-data ALL=(root) NOPASSWD: /usr/bin/rm -f /etc/cron.d/rsnapshot_web
www-data ALL=(root) NOPASSWD: /usr/bin/sed -i * /etc/rsnapshot.conf
www-data ALL=(root) NOPASSWD: /usr/bin/ls -lh /copias_seguridad/*
www-data ALL=(root) NOPASSWD: /bin/ls -lh /copias_seguridad/*
EOF

# Nos aseguramos de que el archivo tenga los permisos correctos (requerido por sudoers)
chmod 0440 /etc/sudoers.d/rsnapshot_web

echo "--- Abriendo puerto web en el firewall ---"
if command -v ufw >/dev/null 2>&1; then
    ufw allow 80/tcp
    echo "Puerto 80 abierto en UFW."
else
    apt install -y iptables >/dev/null 2>&1
    iptables -I INPUT -p tcp --dport 80 -j ACCEPT
    echo "Puerto 80 abierto en iptables."
fi

echo " Instalacion y securizacion (Nivel 2) completada con exito."

# Sustituye la linea de DocumentRoot y reinicia el servicio
sed -i 's|^\s*DocumentRoot.*|\tDocumentRoot /var/www/html/index.php|' /etc/apache2/sites-enabled/000-default.conf
systemctl restart apache2

service apache2 restart