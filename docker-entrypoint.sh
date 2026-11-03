#!/bin/bash

# # Si no existe el flag, instalamos dependencias
# if [ ! -f /var/www/html/.installed ]; then
#   echo "Primera ejecución: ejecutando composer install..."
#   composer install --no-interaction
#   touch /var/www/html/.installed
# fi

echo "Configurando permisos..."
# Asegurar que www-data es dueño de directorios clave
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/bootstrap/cache

# Limpiar PID de Apache si existe
rm -f /var/run/apache2/apache2.pid

exec apache2-foreground
