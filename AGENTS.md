# Nexo: proteger el funcionamiento interno y la instalación en Hostinger

## Regla principal

El usuario aclaró que su prioridad es evitar cambios internos que rompan el
servidor, no congelar el diseño visual. Protege la estructura de instalación,
el arranque de Laravel, las dependencias y la configuración de producción.
Una mejora visual solicitada puede realizarse sin alterar esa base técnica.
Modifica únicamente lo que pida en la tarea actual y lo estrictamente
necesario para que funcione. Una petición de arreglar un error, publicar o mejorar
algo puntual no autoriza a cambiar el resto de la aplicación.

Las instrucciones explícitas posteriores del usuario prevalecen sobre este
documento. Si ya autorizó un cambio concreto, ejecútalo sin pedirle la misma
confirmación otra vez.

## Cambios que requieren una petición o autorización concreta

- Rediseñar pantallas, cambiar colores, tipografías, navegación o distribución.
- Cambiar el nombre Nexo, etiquetas, nombres de espacios o estados existentes.
- Eliminar, ocultar o reemplazar funciones que ya utiliza.
- Cambiar valores predeterminados o reglas de negocio, roles y permisos.
- Sustituir Angular/Laravel, reorganizar el proyecto o actualizar dependencias
  sin necesidad para la tarea.

No agregues funciones por iniciativa propia bajo el supuesto de que serían
mejores. Si detectas una mejora ajena al pedido, descríbela aparte sin aplicarla.
Si una solución necesita modificar una conducta que el usuario no pidió cambiar,
explica el efecto concreto y pide autorización antes de realizar ese cambio.
Los arreglos internos necesarios que conservan la conducta esperada no necesitan
una confirmación adicional.

## Comportamiento aprobado que debe conservarse

- Espacios separados, inicialmente Safin, Sodimac y Personal; sus datos y archivos
  no se mezclan. Sus nombres actuales pueden haber sido editados por el usuario.
- Clientes con código interno editable, proyectos, tareas, notas, biblioteca y
  diagramas exportables a PNG/PDF.
- Notas y tareas en lectura por defecto, edición mediante botón o preferencia
  elegida por el usuario, y lectura ampliada.
- Avisos flotantes y carga sin mostrar información del espacio anterior.
- Tarjetas arrastrables y marca de incendio que las destaca en rojo.
- Local y Desarrollo permiten pendiente, desarrollo, revisión y completada;
  Certificación usa revisión; Producción permite revisión y completada.
  Conservar la compatibilidad con tareas QA existentes.
- Fecha límite retirada de la interfaz; no reintroducirla por iniciativa propia.
- Solo el administrador gestiona personas y espacios. Editores y lectores
  mantienen sus permisos y el acceso solo a los espacios asignados.

Esta lista orienta la conservación; no es una orden de restablecer valores ni de
sobrescribir configuraciones o datos que el usuario haya cambiado después.

## Datos e instalación

- No cambiar por iniciativa propia rutas de instalación, puntos de entrada,
  configuración de arranque, reglas `.htaccess`, conexión MySQL, sesiones,
  versiones de PHP/Laravel ni el mecanismo de despliegue. No migrar el servidor
  a SQLite ni introducir un proceso Node permanente: Hostinger sirve PHP y
  Angular ya compilado. Los arreglos compatibles dentro de esta estructura
  siguen permitidos cuando son necesarios para la tarea solicitada.
- Si una tarea exige cambiar esa base técnica, explica antes el cambio concreto,
  su impacto y cómo se recuperaría la instalación. Pide autorización si no está
  ya incluida en la solicitud del usuario. Prepara y verifica primero una
  solución local; no experimentes sobre la instalación en producción.
- Usa `composer install` con `composer.lock` para desplegar las versiones
  acordadas. No ejecutes `composer update` en producción ni cambies dependencias
  como intento genérico de resolver un error.
- Conservar `.env`, `APP_KEY`, cuentas, base de datos y archivos subidos.
  No incluir secretos ni datos privados en Git, documentación o paquetes.
- Para actualizar tablas, usar migraciones incrementales. No ejecutar
  `migrate:fresh`, reinicializar la base ni regenerar la clave de una instalación
  existente para resolver errores.
- La carpeta privada del servidor sigue siendo `flujo`, fuera de `public_html`.
  Allí van Laravel, `.env`, `vendor` y `storage`.
- `public_html` recibe el contenido de `public/`, con
  `deploy/hostinger-index.php` como `index.php`. No confundir `app/` de Laravel con
  `public/app/`, que contiene Angular compilado.
- No publicar en Hostinger ni sobrescribir una entrega existente por una petición
  que solo concierna a documentación o a una revisión local.

## Verificación obligatoria de una actualización

Los fallos observados durante esta instalación incluyeron `.env` ausente y
`vendor/autoload.php` ausente. No fueron evidencia de contraseña incorrecta ni
de que se estuviera usando SQLite. Diagnostica el error real antes de proponer
cambios de credenciales o de base de datos.

- Antes de dar instrucciones de actualización, identifica qué archivos ya tiene
  el servidor y cuáles aporta el paquete. Nunca indiques reemplazar toda la
  carpeta privada perdiendo `.env`, `storage` o los datos existentes.
- En una entrega ZIP para Hostinger comprueba que estén `vendor/autoload.php`,
  `public/app/index.html`, `public/.htaccess` y el adaptador de entrada. No incluyas
  el `.env` real: la guía debe indicar expresamente conservar el del servidor.
- Antes de ejecutar migraciones, comprueba la existencia de `.env` y
  `vendor/autoload.php`, y que Artisan muestre su versión. No imprimas secretos
  para comprobar la configuración.
- Verifica resultados y códigos de salida. Un comando que no imprime nada no
  demuestra éxito; no avances suponiendo que se ejecutó. Si PHP termina en
  silencio, diagnostica con errores visibles en CLI, sin activar depuración
  pública en la web.
- Prueba migraciones en una base de prueba compatible con MySQL/MariaDB cuando
  cambien el esquema o las consultas. Conserva los datos mediante migraciones
  incrementales y prepara un respaldo antes de aplicarlas a producción.
- Antes de declarar terminada una publicación, comprueba inicio de sesión,
  carga de un espacio y acceso a sus datos. Distingue entre comprobación local,
  comprobación del paquete y comprobación real del servidor.

## Forma de trabajar

1. Lee el estado actual y revisa `git status` antes de editar. Conserva los cambios
   del usuario; no restaures archivos ni descartes trabajo ajeno.
2. Haz el cambio mínimo suficiente. Evita limpiezas, reformateos y refactorizaciones
   ajenas al pedido, aunque parezcan convenientes.
3. Si cambia la interfaz, comprueba la pantalla afectada y que las funciones
   próximas se conserven. Ejecuta las pruebas pertinentes para cambios de lógica,
   permisos o datos; no agregues pruebas por una edición solo documental.
4. Revisa el diff antes de entregar. Explica qué cambió, cómo se verificó y si está
   solo local o publicado. No afirmes que el servidor está actualizado sin haberlo
   comprobado.

Habla en español claro. Para operaciones manuales en SSH, ofrece pasos cortos y
espera resultados verificables antes de dar por ejecutado un comando.
