# Nexo · guía de cambios y producción

Esta guía explica cómo actualizar Nexo en Hostinger sin perder la base de datos,
los archivos subidos ni la configuración privada.

## Qué contiene el proyecto

- **Backend:** Laravel 12.69 y PHP 8.2 en producción.
- **Frontend:** Angular compilado; el servidor entrega los archivos generados,
  no ejecuta Node permanentemente.
- **Base de datos:** MySQL/MariaDB de Hostinger mediante Eloquent y migraciones.
- **Archivos:** documentos e imágenes en `storage/app`, fuera de Git.
- **Autenticación:** sesiones Laravel, roles administrador/editor/solo lectura y
  espacios separados por cliente o proyecto.
- **Funciones:** tareas por ambiente, bugs, notas, biblioteca, diagramas/pizarra,
  checklist, adjuntos, contactos de aviso y correos de producción.
- **Pizarra:** plantillas, formas, texto, colores, conectores, zoom centrado en el cursor,
  pantalla completa, cuadrícula, ajuste a rejilla, mano, viñetas, listas numeradas,
  pegar imágenes, atajos de teclado, deshacer/rehacer y exportación PNG/PDF.

## Qué se protege siempre

En el servidor, `flujo` es la carpeta privada de Laravel y `public_html` es la
carpeta pública del dominio.

- No borrar ni reemplazar `flujo/.env`.
- No borrar `flujo/storage` porque contiene archivos reales.
- No borrar `flujo/vendor` salvo que se vuelva a instalar con `composer.lock`.
- No ejecutar `migrate:fresh`, `db:wipe` ni regenerar `APP_KEY`.
- No subir `.env`, contraseñas, tokens SMTP ni datos de usuarios a GitHub.

## Flujo normal cuando se publica un cambio

### 1. Preparación local

El cambio se prueba en `outputs/flujo`. Después se ejecutan las pruebas y la
compilación del frontend:

```bash
php artisan test
cd frontend
npm run build
```

El frontend compilado queda en `public/app`. El cambio se confirma y se sube a
la rama `main` del repositorio privado.

### 2. Actualización segura en Hostinger

Conéctate por SSH y ejecuta desde la carpeta privada:

```bash
cd /home/u102104426/domains/diegomartinezsepulveda.cl/flujo

test -f .env && echo ".env correcto"
test -f vendor/autoload.php && echo "vendor correcto"

git pull --ff-only origin main

/opt/alt/php82/usr/bin/php artisan --version
/opt/alt/php82/usr/bin/php artisan migrate --force
/opt/alt/php82/usr/bin/php artisan optimize:clear

cp -a public/app/. ../public_html/app/
cp -p public/.htaccess ../public_html/.htaccess
```

El `pull` actualiza el código y los archivos públicos, pero conserva el `.env`
porque ese archivo no pertenece al repositorio.

### 3. Cuándo ejecutar cada comando

- `git pull --ff-only origin main`: siempre que exista un cambio nuevo publicado.
- `php artisan migrate --force`: cuando el cambio incluye una migración nueva.
- `php artisan optimize:clear`: después de cada actualización de backend o `.env`.
- `cp -a public/app/. ../public_html/app/`: cuando cambió Angular o el contenido
  de `public/app`.
- `cp -p public/.htaccess ../public_html/.htaccess`: si cambió el adaptador
  público o las reglas de entrada.

No ejecutes una migración destructiva. Las migraciones de Nexo son incrementales
y deben conservar la información existente.

## Cron y tareas programadas

Un cron es una tarea del servidor que ejecuta un comando automáticamente. En Laravel, el cron de Hostinger solo despierta al planificador; la tarea concreta se define en `routes/console.php` o en un comando de `app/Console/Commands`. En la versión actual, los avisos de producción se envían al guardar manualmente una tarea y no necesitan cron.

Para activar el planificador en Hostinger, entra a **hPanel → Sitios web → Administrar → Avanzado → Cron Jobs**, elige **cada minuto** y usa este comando:

```bash
cd /home/u102104426/domains/diegomartinezsepulveda.cl/flujo && /opt/alt/php82/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

El cron queda guardado en la configuración de Hostinger, no dentro de Git ni de la base de datos. Si el panel pide una expresión completa, usa `* * * * *` delante del comando. Para diagnosticarlo temporalmente puedes guardar la salida en `storage/logs/cron.log` y luego volver a `/dev/null`:

```bash
cd /home/u102104426/domains/diegomartinezsepulveda.cl/flujo && /opt/alt/php82/usr/bin/php artisan schedule:run >> storage/logs/cron.log 2>&1
```

Para comprobar que Laravel ve las tareas programadas:

```bash
/opt/alt/php82/usr/bin/php artisan schedule:list
/opt/alt/php82/usr/bin/php artisan schedule:run -vvv
```

Una tarea nueva se desarrolla y prueba localmente, se define en `routes/console.php` (por ejemplo `Schedule::command(...)->hourly()`), se sube con Git y empieza a ejecutarse cuando el cron del servidor la encuentra. Nunca pongas contraseñas o tokens dentro del comando.

## Configuración SMTP

La configuración vive únicamente en `flujo/.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu-cuenta-de-trabajo
MAIL_PASSWORD=tu-contraseña-de-aplicación
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tu-cuenta-de-trabajo
MAIL_FROM_NAME="${APP_NAME}"
```

Después de cambiar `.env`:

```bash
/opt/alt/php82/usr/bin/php artisan optimize:clear
```

Nunca pegues el token en un commit, en un issue ni en el chat.

## Comprobación posterior

1. Abre el dominio e inicia sesión.
2. Comprueba que aparece el espacio correcto.
3. Abre una tarea existente y verifica checklist y adjuntos.
4. Entra a **Preferencias** y comprueba los destinatarios guardados.
5. Prueba una tarea de producción con un destinatario de prueba.
6. Abre una pizarra, prueba **Pantalla completa**, zoom con `Ctrl/Cmd + rueda`,
   `+`/`-`, `F` para ajustar y **Ajustar al lienzo**. Puedes ocultar la cuadrícula
   o desactivar **Ajuste** si quieres mover elementos libremente.

Si algo falla, conserva la carpeta anterior y revisa:

```bash
tail -n 100 storage/logs/laravel.log
git status
```

No reemplaces la base de datos ni el contenido de `storage` para corregir un
error de despliegue.

## Versionado

El repositorio remoto es:

`https://github.com/DiegoAndresMartinezSepulveda/nexo`

La rama de producción es `main`. Los commits deben describir el cambio y no
deben incluir secretos ni archivos privados del servidor.
