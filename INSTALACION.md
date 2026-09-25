# Nexo — Angular + Laravel en Hostinger

Gestor de espacios, tareas, notas y documentación. Angular 21 es la interfaz; Laravel 12 ofrece una API JSON con sesiones, protección CSRF, base de datos y archivos privados. No necesita un proceso Node en el hosting.

## Incluido

- Espacios independientes, tablero de tareas por ambiente y estado, incidentes en rojo y pasos de entrega.
- Clientes con código interno, proyectos, notas con formato e imágenes, biblioteca privada y diagramas con exportación PNG/PDF.
- Lectura ampliada, edición opcional, avisos flotantes, tema claro/oscuro y menú adaptable.
- Administrador, editores y lectores, con acceso limitado a los espacios asignados.

Consulta ACTUALIZAR.md para todas las funciones y reglas. Los ambientes son etiquetas de seguimiento: la aplicación no despliega código ni ejecuta scripts. No incluye sincronización en tiempo real ni recuperación de contraseña por correo.

## Requisitos

- Hosting con PHP 8.2 o posterior compatible con Laravel 12, MySQL/MariaDB y reescritura de URLs.
- Extensiones PHP habituales de Laravel: PDO MySQL, mbstring, openssl, fileinfo, tokenizer, XML/DOM, ctype, curl y session.
- Acceso SSH para ejecutar Artisan durante la instalación. Composer 2 solo es necesario si instalas desde código fuente sin `vendor/`.
- Un dominio o subdominio con HTTPS. Esta entrega está preparada para la raíz de un dominio, por ejemplo `tareas.tudominio.com`, no para una subcarpeta.

El nombre de tu plan no se ha confirmado. Comprueba en hPanel que dispones de PHP 8.2+, base de datos y acceso SSH. No se ha publicado nada en tu cuenta de Hostinger.

## Instalación con el ZIP

El archivo `nexo-hostinger.zip` incluye las dependencias PHP de producción y Angular ya compilado. No incluye credenciales, base local, datos de ejemplo ni dependencias de desarrollo.

1. Crea una base MySQL y un usuario en hPanel. Anota nombre, usuario, contraseña y host.
2. Descomprime el proyecto en una carpeta privada llamada `flujo`, fuera de `public_html`.
3. Si el panel permite cambiar la raíz pública del sitio, apúntala a `flujo/public`.
4. Si la raíz debe ser `public_html`, copia **solo el contenido de `flujo/public/`** a `public_html/`, incluyendo `.htaccess`, y reemplaza su `index.php` con `deploy/hostinger-index.php`. La estructura debe quedar así:

```text
directorio-del-dominio/
├── flujo/
│   ├── app/
│   ├── bootstrap/
│   ├── storage/
│   ├── vendor/
│   ├── artisan
│   └── .env
└── public_html/
    ├── .htaccess
    ├── index.php  ← contenido de deploy/hostinger-index.php
    ├── favicon.svg
    └── app/       ← Angular compilado
```

No subas `.env`, `vendor`, `storage` ni todo el proyecto dentro de `public_html`. El adaptador presupone que `flujo` y `public_html` son carpetas hermanas; ajusta la ruta privada del adaptador si usas otra estructura.

5. Dentro de `flujo`, copia `.env.hostinger.example` como `.env`. Completa `APP_URL` y los valores `DB_*`. Mantén `APP_DEBUG=false`, `APP_ENV=production` y `SESSION_SECURE_COOKIE=true` en producción.
6. En SSH, entra a `flujo` y ejecuta con la versión correcta de PHP:

```sh
php -v
php artisan key:generate --force
php artisan migrate --force
php artisan flujo:usuario
php artisan config:cache
php artisan route:cache
```

El comando `flujo:usuario` pide nombre, correo y contraseña de al menos 12 caracteres. No hay registro público ni contraseña predeterminada.

7. Asegura que PHP pueda escribir en `storage/` y `bootstrap/cache/`. Usa los permisos que recomienda tu hosting, sin habilitar escritura pública general.
8. Activa HTTPS y abre `https://tudominio.com/app/`. Prueba crear una tarea, adjuntar un documento, descargarlo y cerrar sesión.

No necesitas `npm`, un servicio Node, Redis ni un trabajador de colas en Hostinger. Tampoco necesitas `storage:link`: los adjuntos se descargan mediante la API autenticada.

### Límites de archivos

Cada archivo puede pesar hasta 10 MB, con un máximo de 8 por envío. En la configuración PHP de hPanel establece `upload_max_filesize` en al menos `10M`, `post_max_size` en `90M` o superior y `max_file_uploads` en al menos 8, si el plan lo permite. Si tu plan limita el tamaño total, sube los adjuntos en varios envíos.

## Desarrollo local desde el código fuente

```sh
composer install
```

Copia `.env.example` a `.env`, configura la base y luego ejecuta:

```sh
php artisan key:generate
php artisan migrate
php artisan flujo:usuario
cd frontend
npm ci
npm run build
cd ..
php artisan serve --host=127.0.0.1 --port=8000
```

Para SQLite crea primero el archivo vacío `database/database.sqlite` y usa `DB_CONNECTION=sqlite`. El ZIP no incluye ese archivo. Para MySQL configura `DB_CONNECTION=mysql` y los valores `DB_*`.

Abre `http://127.0.0.1:8000/app/`. La compilación de Angular requiere Node compatible con Angular 21: Node 20.19+, 22.12+ o 24.x. No se usa SSR.

Para trabajar con recarga de la interfaz, deja Laravel en el puerto 8000 y ejecuta `npm start` desde `frontend`. Angular usa el proxy definido en `frontend/proxy.conf.json`; abre la URL que indique su servidor. Para probar la entrega real utiliza `npm run build` y la URL de Laravel.

## Estructura y API

- `frontend/src/`: componentes Angular, plantilla y estilos.
- `public/app/`: versión compilada que se entrega al navegador.
- `app/Http/Controllers/WorkspaceController.php`: autenticación y operaciones.
- `app/Models/`: tareas y adjuntos.
- `database/migrations/`: esquema portable entre SQLite y MySQL.
- `routes/web.php`: rutas `/api/*` con middleware de sesión y CSRF.
- `tests/Feature/WorkspaceTest.php`: pruebas funcionales.

La API y Angular deben compartir dominio. `GET /api/session` inicializa la cookie CSRF y consulta la sesión; `POST /api/login` inicia sesión. Angular envía automáticamente `X-XSRF-TOKEN` en las peticiones de escritura al mismo origen. Las rutas de tareas y adjuntos requieren autenticación y verifican el propietario. No se guardan tokens en localStorage.

## Verificación y mantenimiento

```sh
php artisan test
cd frontend
npm run build
```

La entrega se verificó con SQLite, compilación de Angular y navegación local. La conexión MySQL y la configuración específica de Hostinger deberán verificarse al instalarla.

Para actualizar Angular, compila y vuelve a subir el contenido de `public/app/`. Para actualizar Laravel, respalda la base, `.env` y `storage/app/private/`, aplica el código y dependencias, ejecuta migraciones y vuelve a generar la caché. Conserva siempre la misma `APP_KEY` en las actualizaciones: `key:generate` es solo para la primera instalación.

En caso de problemas consulta `storage/logs/laravel.log`. No actives errores públicos en producción. Respalda juntos la base y `storage/app/private/`, porque los registros hacen referencia a esos archivos.

Referencias: [Hostinger: tecnologías compatibles](https://www.hostinger.com/support/which-programming-languages-and-frameworks-are-supported-at-hostinger/), [Laravel 12](https://laravel.com/docs/12.x), [versiones de Angular](https://angular.dev/reference/versions).
