mkdir -p storage/app/private/verifactu
chmod 700 storage/app/private/verifactu
chmod 600 certificate.p12

openssl pkcs12 -in certficate.p12 -clcerts -nokeys -out storage/app/private/verifactu/certificado.pem

openssl pkcs12 -in certificate.p12 -nocerts -out storage/app/private/verifactu/clave.pem

chmod 600 storage/app/private/verifactu/*.pem
chown -R www-data:www-data storage/app/private/verifactu

VERIFACTU_CERTIFICATE_PATH="/var/www/html/storage/app/private/verifactu/certificado.pem"
VERIFACTU_CERTIFICATE_PASSWORD=
VERIFACTU_PRIVATE_KEY_PATH="/var/www/html/storage/app/private/verifactu/clave.pem"
VERIFACTU_PRIVATE_KEY_PASSWORD="CONTRASEÑA NUEVA DE LA CLAVE PEM"