# Colección de Postman para Nexo

Importa Nexo.postman_collection.json en Postman Web. La colección no incluye contraseñas, tokens ni datos reales.

## Prepararla en Postman Web

1. En Postman Web, selecciona **Import** y carga el archivo JSON.
2. Selecciona **Cloud Agent** para probar producción (https://diegomartinezsepulveda.cl). Para una dirección local como http://127.0.0.1:8000, usa Postman Desktop Agent.
3. Abre las variables de la colección y escribe email y password solo en **Current value**. Márcalos como sensibles si Postman ofrece la opción. No los agregues a Git ni exportes la colección con credenciales.
4. Ejecuta **01 · Sesión / obtener cookies** y luego **02 · Iniciar sesión**. La colección guarda los IDs automáticamente; no necesitas crear un Environment aparte.
5. Ejecuta **03 · Mis espacios** para guardar el primer workspace_id. Para probar otro espacio, cambia esa variable.
6. Las peticiones Crear, Subir, Mover, Cambiar, Marcar y Quitar modifican datos. En producción ejecútalas solo cuando quieras realizar el cambio.

## Probar fotos de perfil

En **15 · Usuarios y fotos de perfil**, ejecuta **Subir o reemplazar foto (elige archivo)** y adjunta una imagen en **Body > form-data**, campo photo. Se aceptan JPG, PNG o WEBP de hasta 5 MB. Luego ejecuta **Ver mi foto**. profile_user_id se completa al iniciar sesión. Un administrador puede consultar **Usuarios** y cambiar ese ID para gestionar la foto de otra persona.

## Probar autenticación Android

En **16 · Sesión móvil y acceso a fotos**, ejecuta **Iniciar sesión móvil**, luego **Consultar sesión móvil** y **Crear token temporal para imágenes**. El token de imagen expira en 15 minutos; después puedes usar **Ver foto como la app Android**.

La API web usa sesión Laravel y cookie CSRF. La colección copia automáticamente XSRF-TOKEN al encabezado X-XSRF-TOKEN. Las peticiones protegidas por espacio envían X-Workspace-ID.

No se usa Gmail ni Google Chat en estas pruebas: los avisos por correo del producto se envían mediante SMTP configurado en Laravel.
