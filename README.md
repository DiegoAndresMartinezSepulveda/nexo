# Nexo

Espacios de trabajo con Angular 21 y Laravel 12: tareas, notas con formato e imágenes, biblioteca, clientes con código, proyectos y diagramas exportables. Administración de usuarios con roles de editor y lector.

- [Actualizar desde Flujo / usar Git y Hostinger](ACTUALIZAR.md)
- [Instalación inicial](INSTALACION.md)
- [Preparar Android y publicar en Play Store](ANDROID_PLAYSTORE.md)

## Desarrollo

```sh
composer install
cd frontend
npm ci
npm run build
cd ..
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Configura primero `.env` siguiendo la guía. No se versionan contraseñas, bases de datos locales ni archivos privados. El frontend compilado se versiona en `public/app` para que el servidor no necesite Node.

## Pruebas

```sh
php artisan test
```

La carpeta de instalación se mantiene como `flujo` para compatibilidad con las instalaciones existentes. El nombre visible es Nexo. Las migraciones amplían la base sin recrear las tablas existentes.
