# Nexo para Android y Google Play

Nexo mantiene su arquitectura actual: Angular es la interfaz, Capacitor la
empaqueta como aplicación Android y Laravel sigue siendo el backend que usa la
misma base MySQL y los mismos archivos de Hostinger. No hay que migrar el
backend a Node.js.

## Qué quedó preparado

- Proyecto Android en `frontend/android`.
- Identificador de aplicación: `cl.diegomartinezsepulveda.nexo`.
- Compilación móvil separada en `frontend/mobile-app` (está ignorada por Git).
- Scripts en `frontend/package.json`:

```sh
npm run build:mobile   # compila Angular para Android
npm run cap:sync       # compila y copia los archivos al proyecto Android
npm run cap:open       # abre Android Studio
```

- Inicio de sesión móvil con token Sanctum, sin cambiar el inicio de sesión de
  la web. La migración `personal_access_tokens` es incremental y no elimina
  tareas, espacios, diagramas ni archivos.

## Primera preparación en Windows

1. Instala [Android Studio](https://developer.android.com/studio) y, desde el
   asistente, instala el Android SDK, SDK Platform y Build Tools que propone.
2. Instala Node.js LTS y verifica:

```powershell
node --version
npm --version
```

3. En la carpeta del proyecto ejecuta:

```powershell
cd outputs\flujo\frontend
npm ci
npm run cap:sync
npm run cap:open
```

4. En Android Studio espera la sincronización de Gradle. Para probar en un
   teléfono, activa las opciones de desarrollador y la depuración USB. Para
   probar en un emulador, créalo desde Device Manager. El backend debe estar
   disponible en `https://diegomartinezsepulveda.cl`.

La primera vez no hace falta crear un proyecto Android manualmente: el proyecto
ya está dentro de `frontend/android`. Si se cambia el código Angular, vuelve a
ejecutar `npm run cap:sync` antes de abrir o compilar Android Studio.

## Backend antes de probar el APK

El APK necesita que el servidor tenga el cambio de autenticación móvil. En
Hostinger, conserva el `.env`, `storage` y `vendor` existentes, y ejecuta desde
la carpeta privada `flujo`:

```sh
cd /home/u102104426/domains/diegomartinezsepulveda.cl/flujo
git pull --ff-only origin main
test -f .env && test -f vendor/autoload.php
composer install --no-dev --optimize-autoloader --no-interaction
/opt/alt/php82/usr/bin/php artisan migrate --force
/opt/alt/php82/usr/bin/php artisan optimize:clear
cp -a public/app/. ../public_html/app/
cp -p public/.htaccess ../public_html/.htaccess
```

No uses `migrate:fresh`, no reemplaces `.env`, no borres `storage` y no
regeneres `APP_KEY`. Si `composer install` termina con error, detén la
actualización y revisa el mensaje antes de continuar.

## Crear el paquete que se sube a Google Play

En Android Studio:

1. Abre `frontend/android`.
2. Revisa el nombre Nexo y el identificador
   `cl.diegomartinezsepulveda.nexo`.
3. Usa **Build > Generate Signed Bundle / APK**.
4. Elige **Android App Bundle (.aab)**, crea una clave de subida y guarda el
   archivo `.jks` y sus contraseñas fuera del repositorio. No subas la clave a
   GitHub ni la envíes junto con el ZIP.
5. Usa Google Play App Signing cuando Play Console lo ofrezca y conserva una
   copia segura de la clave de subida.

Para la primera publicación conviene usar una prueba interna en [Google Play
Console](https://play.google.com/console). Crea la aplicación con el paquete
`cl.diegomartinezsepulveda.nexo`, sube el `.aab` y completa la ficha, el icono,
las capturas, la clasificación de contenido, la política de privacidad y la
sección de seguridad de datos. Declara solo los datos que realmente maneje la
versión publicada (cuenta, tareas, archivos adjuntos y datos que el usuario
introduzca). Play Console indica los requisitos vigentes de la cuenta y de cada
formulario.

## Cómo se actualiza después

- Cambio solo en Laravel: actualiza Hostinger con `git pull`, `composer install`
  si cambió `composer.lock`, migración si corresponde, `optimize:clear` y copia
  de `public/app`.
- Cambio Angular o visual móvil: ejecuta `npm run cap:sync`, aumenta la versión
  de Android en Android Studio y genera otro `.aab` firmado.
- Cambio de ambos: actualiza primero el backend compatible y después publica el
  nuevo `.aab` en una prueba interna.

La aplicación móvil y la web comparten la información del servidor, pero cada
instalación conserva su sesión. Cerrar sesión en el teléfono revoca su token
móvil sin borrar la cuenta ni los datos.
