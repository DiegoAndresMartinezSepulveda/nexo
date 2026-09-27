@extends('layouts.public')
@section('title', 'Términos de uso')
@section('content')
<main class="main doc"><span class="eyebrow">CONDICIONES DEL PILOTO</span><h1>Términos de uso</h1><p>Última revisión: 26 de septiembre de 2026.</p><div class="notice"><strong>Borrador que requiere completar antes de contratar.</strong> Falta confirmar la entidad que presta el servicio, precio, facturación, alcance de soporte, disponibilidad, retención y proceso de cancelación. La página no reemplaza un contrato revisado.</div>
<h2>Servicio</h2><p>Nexo es una herramienta para organizar solicitudes y documentar su progreso con tableros, notas, checklists, archivos y diagramas. Los ambientes son etiquetas de seguimiento: Nexo no compila, despliega ni verifica por sí mismo el software del cliente.</p>
<h2>Acceso de piloto</h2><p>El acceso es privado y se habilita por invitación de la persona administradora. Durante el piloto cada equipo utiliza una instalación independiente. No se ofrece registro público, facturación automática ni un plan de disponibilidad garantizada.</p>
<h2>Uso responsable</h2><p>La organización debe autorizar a quienes invita, elegir permisos adecuados y evitar cargar datos que no tenga derecho a tratar. Cada persona debe proteger su contraseña y sus códigos de recuperación, cerrar su sesión en dispositivos compartidos y avisar si sospecha de un acceso no autorizado.</p>
<h2>Datos y archivos</h2><p>La organización conserva la responsabilidad sobre el contenido que carga. Antes de contratar, ambas partes deben acordar por escrito respaldos, recuperación, exportación, eliminación, soporte y tratamiento de datos. No se debe depender de Nexo como única copia de información crítica.</p>
<h2>Alcance comercial</h2><p>Precio, moneda, impuestos, período, renovaciones, cancelación, soporte, límites de almacenamiento y disponibilidad se deben indicar en una propuesta particular antes de iniciar un piloto pagado. Ninguna tarifa o SLA se supone aquí.</p>
<h2>Contacto</h2><p>Proveedor: <strong>{{ config('nexo.legal_name') ?: 'identidad legal pendiente' }}</strong>. @if(config('nexo.contact_email'))Correo: <a href="mailto:{{ config('nexo.contact_email') }}">{{ config('nexo.contact_email') }}</a>.@else El correo de contacto debe configurarse antes de publicar una oferta.@endif</p>
</main>
@endsection
