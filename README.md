# TFG-Despliegue-de-Sistemas-Administradores-Sencillos
En este proyecto presentamos un software para gestión de empresas de instalación fácil y rápida para que cualquiera sea capaz de implementarla, este software busca facilitar la gestión de sistemas de empresas enfocándose en la administración, la alta disponibilidad y la seguridad de dichos sistemas

## Proceso de instalación del despliegue

## Setup inicial:
- Necesitamos `1+` máquinas con sistemas operativos basados en linux, preferiblemente distribuciones Debian y con SSH ya previamente instalado.
- Ejecutamos el script `instalador_webmin.sh` en TODAS las máquinas con permisos `root` o `sudo`, esto nos servirá como panel de gestión para todas nuestras máquinas.
```bash
sudo bash instalador_webmin.sh
```
- Accedemos a `https://ip_servidor:10000`
- Desde Webmin instalaremos las dependencias que necesite esa máquina (mysql, Mariadb, Apache2...)
- Listo, configura todo desde el mismo panel y no te compliques la vida!

## Monitorización:
- Ejecutamos el script `instalador_monitorizacion.sh` en la maquina principal.
```bash
sudo bash instalador_monitorizacion.sh
```
- Accedemos al panel de monitorización en `http://ip_servidor:19999`
- Listo, visualiza en tiempo real CPU, RAM, disco, red y servicios desde el navegador!

## Autenticación de doble factor (TOTP 2FA):
- Solo se aplica a usuarios con rol `administrador`, el resto acceden directamente.
- Ejecutamos el script `install_totp.sh` en el servidor web con permisos `root` o `sudo`.
```bash
sudo bash install_totp.sh
```
- Los administradores deberán escanear el QR con Google Authenticator o Authy la primera vez que inicien sesión.
- Listo, el acceso de administradores queda protegido con verificación en dos pasos!
