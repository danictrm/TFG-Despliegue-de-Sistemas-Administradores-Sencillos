#!/bin/bash

set -e

echo "====================================="
echo " instalador completo de apache2"
echo " debian 11 bullseye"
echo "====================================="

# verificar que se ejecute como root
if [ "$EUID" -ne 0 ]; then
  echo "este script debe ejecutarse como root o con sudo"
  exit 1
fi

echo "actualizando repositorios..."
apt update

echo "instalando dependencias y apache..."

apt install -y \
libapr1 \
libaprutil1 \
libaprutil1-dbd-sqlite3 \
libaprutil1-ldap \
libcurl4 \
liblua5.3-0 \
apache2-bin \
apache2-data \
apache2-utils \
apache2 \
ssl-cert

echo "habilitando servicio apache2..."
systemctl enable apache2

echo "reiniciando servicio..."
systemctl restart apache2

echo "-------------------------------------"
echo "apache2 instalado correctamente"
echo "estado del servicio:"
systemctl --no-pager status apache2
echo "-------------------------------------"