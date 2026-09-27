# Evaluación comercial de Nexo

**Revisado:** 26 de septiembre de 2026
**Alcance:** revisión local de código, documentación, pruebas y dependencias. No incluye una auditoría del servidor Hostinger ni una prueba de penetración.

## Decisión

Nexo tiene una base funcional suficiente para ofrecer un **piloto guiado a un equipo pequeño**, pero todavía no lo presentaría como SaaS listo para que cualquier empresa se registre y lo use sin ayuda.

La primera oferta será una **instalación administrada por cliente**, con base de datos y archivos separados por empresa. El alta, la configuración y el soporte se hacen contigo durante el piloto. No mezclaría compañías distintas en una misma instalación hasta construir y auditar un modelo de organizaciones con aislamiento completo: los espacios organizan recursos dentro de una cuenta, pero la administración de usuarios y el rol administrador son globales a la instalación (`AdministrationController`).

## Qué producto vendería

**Nexo ayuda a equipos pequeños de desarrollo y TI a seguir un requerimiento desde el trabajo inicial hasta revisión y entrega**, conservando descripción, checklist, archivos y diagramas en espacios de trabajo. Su diferencia está en visualizar ambientes y documentar por qué un cambio regresa a desarrollo.

Los ambientes son seguimiento del trabajo: **Nexo no despliega código ni ejecuta entregas técnicas**. Lo explicaría desde la primera demo para no prometer una integración que la aplicación no ofrece.

El cliente inicial ideal es una agencia pequeña de desarrollo o un equipo interno de TI que hoy coordina solicitudes por mensajes, hojas de cálculo y carpetas. Dejaría para más adelante empresas que exijan inicio SSO, aprovisionamiento automático, SLA formal o certificaciones de seguridad.

## Lo que ya está bien encaminado

- Angular 21, Laravel 12 y PHP 8.2; el hosting sirve Angular compilado y Laravel, sin mantener un proceso Node.
- Tableros, espacios, roles de administrador/editor/lector, notas con checklist, biblioteca privada, diagramas y adjuntos.
- Controles de inicio de sesión con CAPTCHA tras fallos, bloqueo temporal por IP y segundo factor TOTP opcional.
- La colección Postman y guías para instalación, cambios y Android ya existen.
- La cuenta puede restablecer su contraseña mediante un enlace temporal de 60 minutos, o cambiarla desde Preferencias. El sistema usa respuestas que no revelan si un correo existe, limita intentos, revoca tokens móviles y sesiones antiguas y notifica el cambio por correo.
- Hay página informativa, soporte, borradores de privacidad y términos, páginas de error en español, colección Postman para recuperación y guía operativa del piloto.
- GitHub Actions ejecuta pruebas contra SQLite y MySQL, compila Angular y revisa todas las dependencias PHP y JavaScript. En la verificación local actual pasaron **49 pruebas con 400 aserciones**; ambos builds Angular (web y Android) compilaron y las auditorías completas de producción/desarrollo quedaron sin vulnerabilidades.
- Las pruebas y análisis locales no sustituyen una auditoría ni prueban el servidor Hostinger. La compilación Angular puede emitir avisos CommonJS de la librería usada para exportar diagramas.

Estos resultados respaldan una demo y un piloto controlado, pero no sustituyen una revisión de la instalación real ni una evaluación externa de seguridad.

## Bloqueos antes de cobrar por un piloto

1. **Completar la identidad comercial y los avisos.** Las páginas públicas explican el producto y muestran borradores de privacidad y términos. Debes completar el nombre legal, domicilio, correos de contacto/privacidad y proveedores reales en `.env`, y pedir revisión jurídica del contrato, retención, cancelación y derechos de datos antes de aceptar información de terceros.

2. **Respaldos y recuperación operativa.** La guía ahora define cómo respaldar juntos MySQL y `storage/app`, y cómo practicar una restauración. Aún hay que configurar la copia periódica, cifrar una copia fuera del hosting y ejecutar el ensayo. Nexo no automatiza respaldo, retención, alertas ni recuperación todavía.

3. **Validación real en el hosting.** La automatización cubre MySQL 8.0 en GitHub Actions, pero no la instancia Hostinger. Antes de un piloto pagado, comprobar PHP, migraciones, cookies seguras, proxy/IP, SMTP, carga y descarga privada, permisos y espacio de disco en un staging equivalente.

4. **Revisión de seguridad externa.** Los controles locales cubren login, TOTP, permisos por espacio, CSRF, limitación de intentos y archivos privados; no son una prueba de penetración. Encarga una revisión independiente antes de guardar información delicada.

5. **Oferta y soporte.** Define precio, moneda/impuestos, horario, respuesta, límite de almacenamiento, cancelación y salida de datos por contrato. Las páginas muestran que estos detalles dependen de una propuesta y no inventan un precio ni un SLA.

## Decisiones de lanzamiento

- **Tres primeros clientes:** pilotos guiados, con una instancia y una base por empresa. Alta manual y factura gestionada por ti; primero valida uso y disposición a pagar antes de programar cobro automático.
- **No habilitar registro público todavía.** Mantener creación de cuentas bajo invitación durante el piloto. La recuperación está disponible y el administrador conserva la asignación manual de espacios y permisos.
- **Vender el resultado, no una lista de herramientas:** “Sigue cada requerimiento desde desarrollo hasta entrega y deja claro qué falta, qué se revisó y por qué volvió atrás”.
- **Web primero.** Android queda como complemento; la publicación en Play Store requiere cerrar el firmado, la ficha, privacidad y el proceso de pruebas. No es condición para empezar un piloto web.
- **No fijar precio al azar.** Entrevistar a cinco equipos y cotizar los primeros pilotos como servicio que incluya instalación, configuración y soporte. Con tres clientes activos, definir planes y precio recurrente.

## Siguiente orden de trabajo

1. Completar datos comerciales/legales y revisar los textos antes de enviar una propuesta real.
2. Configurar SMTP en Hostinger y probar el ciclo completo de recuperación de una cuenta de prueba.
3. Configurar copia cifrada fuera del hosting y ensayar restauración de base y archivos en staging.
4. Completar el checklist real de Hostinger y solicitar revisión de seguridad independiente.
5. Preparar espacio demo ficticio, propuesta de piloto, horario/canal de soporte y capacitación. La guía PDF y el tutorial animado existentes sirven de apoyo.
6. Solo después de los pilotos: organizaciones multiempresa con aislamiento probado, límites por plan, exportación autoservicio, pagos automáticos e integraciones empresariales.

## Criterio de salida

Nexo ya tiene una base lista para un **piloto guiado de prueba**, pero no se debe vender ni cargar datos de clientes hasta completar los cinco requisitos operativos anteriores con evidencia, revisar los borradores legales y acordar por escrito el alcance y manejo de datos. Puede anunciarse como **SaaS autoservicio** solo después de completar aislamiento multiempresa, altas/bajas autónomas, cobro, exportación/eliminación de datos y operación con restauración probada.
