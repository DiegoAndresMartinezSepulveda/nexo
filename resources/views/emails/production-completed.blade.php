@php($labels = ['client' => 'Cliente', 'project' => 'Proyecto', 'code' => 'Código', 'title' => 'Tarea', 'description' => 'Descripción', 'status' => 'Estado', 'checklist' => 'Checklist'])
<!doctype html>
<html lang="es">
<body style="margin:0;background:#f4f6fb;color:#202b43;font-family:Arial,sans-serif;padding:28px 12px;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;margin:auto;background:#fff;border:1px solid #e4e8f2;border-radius:16px;overflow:hidden;">
    <tr><td style="background:#5266eb;padding:22px 28px;color:#fff;"><div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;opacity:.85;">Nexo · Aviso de entrega</div><h1 style="margin:8px 0 0;font-size:24px;line-height:1.25;">Producción completada</h1></td></tr>
    <tr><td style="padding:28px;"><p style="margin:0 0 22px;font-size:16px;line-height:1.6;">{!! nl2br(e($intro)) !!}</p>
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
        @foreach($details as $key => $value)
          <tr><td style="padding:12px 0;border-top:1px solid #edf0f5;vertical-align:top;color:#7b8498;font-size:12px;text-transform:uppercase;letter-spacing:.5px;width:30%;">{{ $labels[$key] ?? ucfirst($key) }}</td><td style="padding:12px 0;border-top:1px solid #edf0f5;font-size:15px;line-height:1.5;">@if(is_array($value))<ul style="margin:0;padding-left:20px;">@foreach($value as $item)<li style="margin:4px 0;list-style:none;">@if(is_array($item){{-- checklist --}}){{ ($item['done'] ?? false) ? '☑' : '☐' }} {{ $item['text'] ?? '' }}@else• {{ $item }}@endif</li>@endforeach</ul>@else{!! nl2br(e($value)) !!}@endif</td></tr>
        @endforeach
      </table>
      <p style="margin:24px 0 0;padding-top:18px;border-top:1px solid #edf0f5;color:#8a93a6;font-size:12px;">Este aviso fue enviado automáticamente por Nexo.</p>
    </td></tr>
  </table>
</body>
</html>
