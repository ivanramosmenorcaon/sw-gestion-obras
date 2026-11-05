# Gestión de obras

Aplicación Laravel 13 / Filament 5 para una empresa en EUR. La operativa del usuario se explica en la guía del dashboard. El alcance, decisiones, pruebas y pendientes se mantienen en PROYECTO.md y PLAN.md.

## Instalación y despliegue

1. Configurar PHP 8.3, extensiones requeridas por Composer, MySQL y Node compatible con Vite.
2. Ejecutar composer install --no-interaction y npm ci.
3. Preparar .env privado; configurar base, APP_KEY, dominio HTTPS, correo y entorno. No copiar claves ni datos reales al repositorio.
4. Antes de actualizar una instalación con datos, ejecutar php artisan operations:backup --no-interaction.
5. Aplicar php artisan migrate --force --no-interaction. No usar migrate:fresh en una base operativa.
6. Ejecutar npm run build, php artisan optimize y php artisan queue:restart.
7. Configurar un worker supervisado para php artisan queue:work y cron para php artisan schedule:run cada minuto, con el usuario del servidor web y directorio /var/www/html.
8. Ejecutar php artisan operations:check --no-interaction y resolver los avisos antes de operar en producción.

No se instalan ni activan servicios del sistema automáticamente. En esta revisión no se han detectado procesos cron/scheduler o queue:work activos; podrían estar gestionados externamente, lo que debe verificar el responsable del despliegue.

## Copias y recuperación

operations:backup crea un SQL cifrado con APP_KEY, checksum y recuentos de tablas en storage/app/private/backups. Es una copia de la base, no un respaldo completo de documentos ni de la configuración. Conservar además documentos privados y APP_KEY por separado, con acceso restringido, y una copia externa. Todavía no se ha configurado destino externo ni política de retención.

Comprobar una copia con php artisan operations:verify-backup operations-AAAAMMDD-HHMMSS.sql.enc. La opción --restore crea una base temporal con prefijo operations_restore_, restaura y compara los recuentos y la elimina al finalizar. Requiere una cuenta con permiso para crear bases temporales. No utilizar una base de negocio como destino de ensayo.

La cuenta disponible en esta instalación no tiene permiso para crear la base temporal: se verificó el descifrado, pero la restauración aún no está ensayada.

## Pruebas y exportaciones

php artisan test --compact usa SQLite :memory: forzado por phpunit.xml. No utilizar credenciales de producción para pruebas. Las exportaciones CSV autorizadas se descargan desde el dashboard para la contabilidad externa; el formato de importación final se acuerda con la asesoría.

## Certificado fiscal

Mantener certificado y clave PEM fuera de public/ y del repositorio. El contenedor PKCS#12 se prepara en un entorno privado con:

    openssl pkcs12 -in certificate.p12 -clcerts -nokeys -out storage/app/private/verifactu/certificado.pem
    openssl pkcs12 -in certificate.p12 -nocerts -out storage/app/private/verifactu/clave.pem

Aplicar permisos restringidos a los archivos. Configurar rutas y contraseñas exclusivamente en .env privado. La emisión de facturas reales permanece bloqueada: el destino de transporte production no sustituye la validación fiscal ni la declaración responsable.
