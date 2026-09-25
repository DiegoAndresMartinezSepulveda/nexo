# Nexo: conservar lo que el usuario ya aprobó

## Regla principal

El usuario pidió expresamente evitar cambios inesperados como los de la versión
anterior. Modifica únicamente lo que pida en la tarea actual y lo estrictamente
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
