# Actualizar de Flujo a Nexo

Nexo conserva la estructura de instalación existente: la carpeta privada sigue llamándose `flujo`, al lado de `public_html`. No necesitas renombrar la base ni crear una cuenta nueva.

## Qué incluye

- Inicio con tareas recientes y notas.
- Barra lateral plegable, navegación móvil y tema claro/oscuro persistente.
- Tablero por ambiente o estado; arrastrar tarjetas y mover mediante botón.
- Espacios separados: Safin, Sodimac y Personal, con selector y administración de espacios.
- Clientes con código interno editable y proyectos por espacio.
- Administrador, editores y lectores. Solo el administrador crea usuarios y asigna espacios.
- Lectura por defecto, botón Editar, lectura ampliada y preferencia para abrir en edición.
- Pizarra con zoom centrado en el cursor, pantalla completa, cuadrícula opcional, ajuste a rejilla, desplazamiento, figuras, texto, notas, dibujo libre, conexiones, colores, listas, plantillas y pegado de imágenes; exportación PNG/PDF.
- Privacidad de diagramas: solo yo, todo el espacio o personas específicas con permiso para ver o editar.
- Vista de bugs con estados, prioridades, etiquetas y el mismo editor ordenado de las tareas.
- Autoguardado de tareas, notas, biblioteca y diagramas existentes mientras editas.
- Aviso opcional por correo al completar una tarea en Producción, con destinatarios, mensaje y campos de la tarjeta elegidos por ti.
- Regreso controlado entre ambientes: al mover una tarjeta hacia un ambiente anterior exige anotar qué falló, permite marcarlo como resuelto y guardar cómo se solucionó.
- Avisos flotantes y pantallas de carga al cambiar de contexto.
- Fecha límite retirada de la interfaz. Los valores antiguos se conservan en la base.
- Incendio: marca independiente de la prioridad, con tarjeta roja.
- Descripción y notas de tarea con edición tipo Word: fuente, tamaño, títulos, negrita, cursiva, subrayado, listas, checklist, color e imágenes.
- Capturas con Ctrl+V, arrastrar imágenes, subirlas, ampliar y quitar.
- Notas importantes con colores, fijado, archivo y búsqueda.
- Biblioteca con archivos, imágenes, SQL, categorías, etiquetas, cliente/proyecto y filtros.
- SQL de referencia en tareas, sin ejecución de consultas.
- Historial de cambios a partir de esta actualización.

## Estados por ambiente

| Ambiente | Estados disponibles |
| --- | --- |
| Local / Desarrollo | Pendiente, En desarrollo, En revisión, Completada |
| QA | En revisión, Completada |
| Certificación | En revisión |
| Producción | En revisión, Completada |

Al arrastrar a un ambiente se pide el estado. El valor sugerido es En desarrollo para Local/Desarrollo y En revisión para QA/Certificación/Producción. Puedes activar “Mover sin preguntar” para aplicar esos valores automáticamente. Las reglas se validan también en Laravel.

Las migraciones convierten las tareas existentes de Certificación a En revisión y los estados incompatibles de QA/Producción a En revisión. Las tareas, cuentas, listas y adjuntos se conservan. La primera cuenta existente pasa a ser administradora. Los datos anteriores de cada cuenta quedan en su espacio Safin; Sodimac y Personal empiezan vacíos. Los códigos de cliente quedan vacíos para que ingreses los correctos.

Las migraciones crean las tablas dentro de la base MySQL configurada en `.env`. Si partes de cero, la base y su usuario se crean primero en hPanel; Laravel no crea bases del proveedor.

## Antes de actualizar

Exporta un respaldo MySQL desde phpMyAdmin y conserva una copia privada de `.env`, `storage/app/private/` y del código anterior. No uses `migrate:fresh`, porque borra las tablas. No vuelvas a ejecutar `key:generate` en una instalación existente.

## Opción A: ZIP de actualización

Antes de subir archivos, entra por SSH a la carpeta `flujo` y ejecuta `/opt/alt/php82/usr/bin/php artisan down --retry=60`.

1. Usa `nexo-hostinger.zip`, que contiene el proyecto bajo la carpeta `flujo`, Angular compilado y dependencias PHP de producción.
2. En la carpeta privada `flujo` del hosting, actualiza las carpetas `app`, `bootstrap`, `config`, `database/migrations`, `lang`, `routes`, `vendor`, `frontend`, `public`, `deploy`, y los archivos del proyecto. Conserva tu `.env` y todo `storage`. No copies tu base SQLite local al servidor.
3. En tu `.env`, cambia solo `APP_NAME` a `Nexo` si quieres el nuevo nombre. Conserva `APP_KEY` y los datos MySQL.
4. Por SSH, ejecuta:

```bash
cd /home/u102104426/domains/diegomartinezsepulveda.cl/flujo
/opt/alt/php82/usr/bin/php artisan down --retry=60
/opt/alt/php82/usr/bin/php artisan optimize:clear
/opt/alt/php82/usr/bin/php artisan migrate --force
```

5. Copia el contenido de `flujo/public/` a `public_html/`. Después vuelve a copiar `flujo/deploy/hostinger-index.php` como `public_html/index.php`.
6. Finaliza:

```bash
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan up
```

Abre tu dominio y recarga con Ctrl+F5. Tus credenciales anteriores siguen funcionando. Si hay un error, no continúes con los pasos siguientes: consulta `storage/logs/laravel.log`.

## Opción B: Git / GitHub

El proyecto incluye un repositorio Git local. El remoto todavía debe conectarse a tu cuenta de GitHub. `.env`, `vendor`, los archivos subidos, SQLite y las dependencias Node están excluidos de Git. `public/app` se versiona ya compilado: Hostinger no necesita Node.

Primera subida desde tu computadora, dentro del proyecto, después de crear un repositorio **privado y vacío** en GitHub:

```bash
git remote add origin URL_DE_TU_REPOSITORIO
git push -u origin main
```

Usa tu autenticación de GitHub o una clave SSH; nunca incluyas una contraseña o token en la URL ni en archivos del repositorio.

Para conectar la instalación existente del hosting sin reemplazar `.env`, `storage` ni la base, la ruta debe ser la carpeta privada `flujo`, nunca `public_html`. Después de respaldar y colocar el sitio en mantenimiento:

```bash
cd /home/u102104426/domains/diegomartinezsepulveda.cl/flujo
git init -b main
git remote add origin URL_DE_TU_REPOSITORIO
git fetch origin main
```

Antes de continuar, comprueba que estás en la carpeta correcta. El siguiente comando reemplaza únicamente los archivos que vienen del repositorio:

```bash
git reset --mixed origin/main
git restore --source=HEAD --worktree .
git branch --set-upstream-to=origin/main main
bash deploy/update-hostinger.sh
```

Estos comandos enlazan la rama local con la remota y reemplazan los archivos de código versionados. Conservan los archivos privados ignorados por Git. No ejecutes `git clean`.

Para actualizaciones posteriores, con la rama ya conectada y sin cambios locales pendientes:

```bash
/opt/alt/php82/usr/bin/php artisan down --retry=60
git pull --ff-only
bash deploy/update-hostinger.sh
```

El script usa PHP 8.2, instala dependencias con Composer, ejecuta migraciones, copia solo los archivos públicos y reconstruye las cachés. Si falla, deja mantenimiento activo para que puedas revisar el error. No ejecuta `git pull` automáticamente ni cambia tu configuración privada.

## Guardado de notas y archivos

Si una tarea vuelve desde un ambiente superior a uno anterior (por ejemplo, de Producción a Desarrollo, de Certificación a Desarrollo o de Desarrollo a Local), Nexo exige escribir el motivo del fallo. Ese texto queda guardado en la tarjeta, aparece como aviso visual y se agrega al historial. Desde la edición de la tarea puedes marcar el problema como resuelto y dejar la solución aplicada.

Las tareas, notas, biblioteca y diagramas ya guardados se autoguardan mientras los editas. Para crear un elemento nuevo se pulsa Guardar la primera vez. Las imágenes se suben de forma privada al pegar o elegir el archivo y se asocian a la tarea/nota al guardar. Los archivos de borradores abandonados se pueden limpiar después de 24 horas:

```bash
/opt/alt/php82/usr/bin/php artisan flujo:limpiar-archivos
```

La configuración visual se recuerda por navegador. No hay sincronización en tiempo real entre pestañas, ni ejecución de SQL, ni despliegue de código desde el tablero.

## Uso de espacios, personas y diagramas

- Cambia entre Safin, Sodimac y Personal con el selector de la barra lateral. En Espacios puedes agregar otros o renombrarlos.
- En Clientes, pulsa Editar para guardar el código interno, por ejemplo el que realmente corresponda a FEN. Se muestra junto al nombre en tarjetas y selectores.
- En Usuarios, el administrador agrega personas con correo, contraseña inicial y los espacios permitidos. Un editor trabaja en esos espacios; un lector consulta y descarga. Ninguno puede invitar usuarios ni crear espacios. Cambiar su acceso cierra sus sesiones anteriores.
- Una nota o tarea se abre en lectura. Editar muestra sus campos y herramientas; Ampliar lectura ofrece una ventana grande. En Preferencias puedes cambiar el modo predeterminado.
- En Diagramas, crea pasos (Proceso, Decisión o Inicio/fin), conecta origen y destino y añade etiquetas Sí/No. Arrastra los pasos en el lienzo o usa Ordenar pasos. Guarda el diagrama con su explicación y referencias. PNG/PDF exporta el diagrama visible; también aparece un enlace de descarga si el navegador no inicia la descarga automáticamente. La pizarra ofrece pantalla completa, cuadrícula, ajuste a rejilla, zoom con `Ctrl/Cmd + rueda` y atajos `+`, `-`, `F`, `Supr`, `Ctrl/Cmd + Z` y `Ctrl/Cmd + Y`.
- Los PDF de diagramas se ajustan a una página A4, con fondo blanco. Exportar no guarda cambios pendientes: pulsa Guardar diagrama para persistirlos en Nexo.

Las cuentas no incluyen recuperación de contraseña por correo. Un administrador puede reemplazar la contraseña de un colaborador desde Usuarios. Para la cuenta principal, conserva tu acceso SSH.

## Avisos por Gmail

En cada tarea puedes activar el aviso de Producción, escribir el mensaje, elegir uno o varios destinatarios y marcar qué datos incluir: cliente, proyecto, título, descripción, código, ambiente/estado y checklist. El correo se envía una sola vez cuando la tarea llega a **Producción · Completada**.

Para enviarlo desde Gmail, activa la verificación en dos pasos de la cuenta de Google y crea una contraseña de aplicación. En el `.env` privado del servidor configura:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu_cuenta@gmail.com
MAIL_PASSWORD="tu_contraseña_de_aplicacion"
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tu_cuenta@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

Después ejecuta `/opt/alt/php82/usr/bin/php artisan config:clear`. La contraseña de aplicación debe permanecer únicamente en `.env`; no la agregues a Git ni al ZIP.
