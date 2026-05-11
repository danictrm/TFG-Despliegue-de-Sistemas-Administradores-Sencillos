#!/bin/bash
set -e

echo "==== instalador automatico webmin ===="

# verificar root
if [ "$EUID" -ne 0 ]; then
    echo "este script debe ejecutarse como root."
    exit 1
fi

echo "actualizando sistema..."
apt update -y

echo "instalando dependencias..."
apt install -y wget apt-transport-https software-properties-common gnupg

echo "agregando clave gpg..."
wget -qO- http://www.webmin.com/jcameron-key.asc | gpg --dearmor -o /usr/share/keyrings/webmin.gpg


echo "agregando repositorio webmin..."
echo "deb [signed-by=/usr/share/keyrings/webmin.gpg] http://download.webmin.com/download/repository sarge contrib" \
    > /etc/apt/sources.list.d/webmin.list

echo "actualizando repositorios..."
apt update -y

echo "instalando webmin..."
apt install -y webmin

echo "habilitando servicio..."
systemctl enable webmin
systemctl restart webmin

# abrir puerto si ufw esta instalado
if command -v ufw >/dev/null 2>&1; then
    echo "Configurando firewall..."
    ufw allow 10000/tcp || true
fi

ip_local=$(ip route get 8.8.8.8 | awk '{print $7; exit}')

echo "-------------------------------------"
echo "Webmin instalado correctamente"
echo "Accede desde: https://$ip_local:10000"
echo "-------------------------------------"