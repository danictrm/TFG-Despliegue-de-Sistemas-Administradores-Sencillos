Guía de Configuración del Servidor
Requisitos Previos
Antes de comenzar, asegúrate de contar con un mínimo de 2 máquinas:

Máquina Cliente — ejecutará el rol de cliente en la infraestructura.

Máquina Servidor — gestionará los backups y alojará la interfaz web.

Pasos de Configuración
1. Preparación de la red
Configura las IPs de cada máquina antes de ejecutar cualquier script.

2. Configuración del Cliente
En la máquina cliente, coloca el script Server y ejecútalo. Selecciona la opción 1 para crear el cliente.

3. Configuración del Servidor
En la máquina servidor, coloca el script Server y ejecútalo. Selecciona la opción 2 para crear el servidor e introducir directamente las máquinas cliente.

⚠️ Nota: Esta opción requiere que indiques al menos una máquina cliente; no es posible omitir este paso.

4. Instalación de scripts en el servidor
Copia los siguientes scripts en /usr/local/bin/ y asigna los permisos de ejecución correspondientes:

Script	Ruta	Permisos
Script	Ruta	Permisos
backup_inteligente	/usr/local/bin/	chmod +x
setup_rsnapshot.sh	/usr/local/bin/	chmod +x
restaurar_backup	/usr/local/bin/	chmod +x
Puedes aplicar los permisos con:

bash
sudo chmod +x /usr/local/bin/backup_inteligente
sudo chmod +x /usr/local/bin/setup_rsnapshot.sh
sudo chmod +x /usr/local/bin/restaurar_backup
5. Instalación del entorno web
En el servidor, coloca y ejecuta el script resolv para instalar las dependencias necesarias para la página web.

6. Despliegue de la interfaz web
Copia el archivo index.php en la ruta /var/www/html/:


