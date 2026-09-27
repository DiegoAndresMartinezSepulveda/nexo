# Guía operativa de la v1 de Nexo

Esta versión está preparada para **pilotos guiados**, con una instalación y una base de datos por organización. No habilita registro público ni cobra automáticamente. No se deben cargar clientes distintos dentro de una misma instalación: el administrador es global al sitio.

## Preparación antes del primer equipo

1. Define la entidad que presta el servicio, correo comercial y de privacidad, condiciones, precio, horario de soporte y límites del piloto. No publiques los textos legales como definitivos sin revisarlos.
2. Usa un dominio con HTTPS, PHP 8.2 o posterior y MySQL/MariaDB. Mantén Laravel en `flujo`, fuera de `public_html`. Conserva el `.env`, `APP_KEY`, `storage` y la base MySQL actuales.
3. En `.env`, configura `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://tu-dominio`, `SESSION_DRIVER=database`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true` y una caché de configuración nueva. Configura SMTP real para que funcionen los avisos y la recuperación de contraseñas.
4. Completa `NEXO_LEGAL_NAME`, `NEXO_LEGAL_ADDRESS`, `NEXO_CONTACT_EMAIL` y `NEXO_PRIVACY_EMAIL` con datos reales. No copies valores privados a Git. Tras editar `.env`, ejecuta `php artisan optimize:clear` y vuelve a crear la caché de configuración.
5. Crea la cuenta administradora, confirma que llega un correo de prueba, activa verificación en dos pasos y guarda los códigos de recuperación fuera del servidor.
6. Invita al equipo con cuentas individuales, roles mínimos y solo los espacios necesarios. Entrega la contraseña inicial por un canal privado y pide cambiarla al entrar.
7. Haz un recorrido con una tarea ficticia: crear, checklist, archivo privado, mover ambiente, regresar con motivo, marcar solución y confirmar el correo de aviso. No pruebes flujos destructivos con contenido del cliente.

## Qué probar después de cada actualización

- Inicio de sesión, cierre de sesión y recuperación de contraseña por correo.
- Cambio de contraseña desde Preferencias, aviso al correo y rechazo de una sesión anterior.
- Acceso de un editor, un lector y una cuenta sin espacio asignado.
- Auditoría como administrador: filtrar una acción y descargar el CSV; confirmar que lectores reciben 403.
- Tareas, notas/listas, biblioteca, diagramas, subida y descarga privada de archivos.
- Envío de correo en el evento configurado, evitando repetir cambios automáticos.
- Web móvil y APK instalada si se entrega Android.
- `GET /up` devuelve estado correcto. Revisa el log privado y el espacio disponible en disco.

## Copia y restauración

Antes de aplicar una actualización, crea un punto de restauración en hPanel. Respalda como un solo conjunto coherente la base MySQL y los archivos de `storage/app`; conserva `.env` y el código anterior por separado. `storage/app` y MySQL juntos son necesarios porque las tablas guardan referencias a archivos privados.

Si tienes `mysqldump` por SSH, úsalo para exportar la base sin escribir la contraseña en el comando. El parámetro `-p` pide la clave de manera interactiva:

```sh
mkdir -m 700 -p /home/USUARIO/backups/nexo
cd /home/USUARIO/backups/nexo
mysqldump --host=HOST_MYSQL --user=USUARIO_MYSQL -p --single-transaction BASE_MYSQL > nexo-AAAA-MM-DD-HHMM.sql
cd /home/USUARIO/domains/DOMINIO/flujo
tar -czf /home/USUARIO/backups/nexo/nexo-storage-AAAA-MM-DD-HHMM.tar.gz storage/app
cp -p .env /home/USUARIO/backups/nexo/nexo-env-AAAA-MM-DD-HHMM.txt
chmod 600 /home/USUARIO/backups/nexo/*
```

Reemplaza las rutas por las de tu cuenta y guarda la carpeta de respaldo fuera de `public_html`. Si el hosting no tiene `mysqldump`, exporta/importa con phpMyAdmin o hPanel. Mantén al menos una copia cifrada fuera de la cuenta de hosting y define retención con cada cliente. Una vez por piloto, restaura una copia en una instalación de prueba y verifica una tarea y sus archivos; nunca hagas el ensayo sobre producción.

El sistema todavía no automatiza copias, retención, almacenamiento externo ni restauración. No ofrezcas un SLA ni prometas recuperación puntual hasta probar esa operación con el proveedor real.

## Publicar una actualización desde Git

Prueba y revisa primero el pull request en GitHub. En el servidor, desde la carpeta privada `flujo`, verifica `.env`, `vendor/autoload.php`, `public/app/index.html` y `git status --short`. Después de tener respaldo:

```sh
cd /home/USUARIO/domains/DOMINIO/flujo
/opt/alt/php82/usr/bin/php artisan down --retry=60
git pull --ff-only origin main
bash deploy/update-hostinger.sh
```

El script instala dependencias bloqueadas, aplica migraciones aditivas, copia solo `public/` a `public_html`, reconstruye cachés y termina mantenimiento. No borra `.env`, `storage` ni la base. Si el script falla, deja mantenimiento activo para revisar el error; corrige el problema y vuelve a ejecutarlo. Comprueba la lista de tareas y un archivo antes de considerar finalizada la actualización. No uses `migrate:fresh`, `git clean`, `key:generate` ni sustituyas la carpeta privada completa.

## Alcance y límites actuales

- Se vende como instalación administrada por organización, con alta manual y acompañamiento. El registro y la facturación automáticos aún no existen.
- No hay aislamiento multiempresa dentro de una misma instalación, SSO, SLA, cobro integrado, monitoreo externo ni backups automáticos.
- La recuperación requiere que SMTP esté bien configurado y que `APP_URL` use el dominio HTTPS correcto.
- El aviso de privacidad y los términos son borradores: completa proveedor/ubicación de datos, conservación y eliminación de copias con asesoría antes de firmar contratos.
- Antes de aceptar datos delicados, pide revisión independiente de seguridad y haz un ensayo documentado de restauración MySQL en el hosting o un staging equivalente.
