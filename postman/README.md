# Colección de Postman para Nexo

Importa `Nexo.postman_collection.json` en Postman Web. La colección no incluye contraseñas, tokens ni datos reales.

## Uso en Postman Web

1. En Postman Web, selecciona **Import** y carga el JSON.
2. Selecciona **Cloud Agent** para probar producción (`https://diegomartinezsepulveda.cl`). Para `127.0.0.1` usa Postman Desktop Agent.
3. En las variables de la colección define `email` y `password` solo en **Current value**. No las guardes en Git ni en el valor inicial.
4. Ejecuta `01 · Sesión / obtener cookies` y después `02 · Iniciar sesión`.
5. Ejecuta `03 · Mis espacios`; guardará el primer `workspace_id`. Si necesitas otro espacio, cambia la variable manualmente.
6. Prueba las consultas de lectura. Las peticiones marcadas como opcionales crean datos y solo deben ejecutarse cuando quieras hacerlo.

La API usa sesión Laravel y cookie CSRF. La colección copia automáticamente `XSRF-TOKEN` al encabezado `X-XSRF-TOKEN`. Las peticiones protegidas envían `X-Workspace-ID` para seleccionar el espacio.

No se usa la API de Gmail ni Google Chat: el aviso por correo del producto usa SMTP configurado en Laravel.
