<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>{{ $heading }} · {{ config('app.name', 'Nexo') }}</title>
</head>
<body style="margin:0;padding:28px 12px;background:#f3f5fa;color:#202b43;font-family:Arial,Helvetica,sans-serif;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0">{{ $intro }}</div>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;margin:0 auto;background:#fff;border:1px solid #e1e6f0;border-radius:16px;overflow:hidden;">
    <tr>
      <td style="padding:22px 28px;background:#18243b;color:#fff;">
        <table role="presentation" cellspacing="0" cellpadding="0"><tr>
          <td width="38" height="38" align="center" valign="middle" style="border-radius:10px;background:#596de9;color:#fff;font-size:23px;font-weight:bold;">N</td>
          <td style="padding-left:11px;color:#fff;font-size:19px;font-weight:bold;letter-spacing:-.4px;">nexo<span style="color:#aeb8ff">.</span></td>
        </tr></table>
        <div style="margin-top:19px;color:#bac5df;font-size:11px;font-weight:bold;letter-spacing:1.8px;text-transform:uppercase;">CUENTA Y SEGURIDAD</div>
        <h1 style="margin:8px 0 0;color:#fff;font-size:25px;line-height:1.25;">{{ $heading }}</h1>
      </td>
    </tr>
    <tr>
      <td style="padding:28px;">
        <p style="margin:0 0 15px;font-size:16px;line-height:1.55;">Hola, {{ $name }}.</p>
        <p style="margin:0 0 22px;color:#536078;font-size:15px;line-height:1.7;">{{ $intro }}</p>
        <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 22px;"><tr><td align="center" style="border-radius:9px;background:#5266eb;">
          <a href="{{ $actionUrl }}" style="display:inline-block;padding:13px 19px;border:1px solid #5266eb;border-radius:9px;color:#fff;font-size:14px;font-weight:bold;text-decoration:none;">{{ $actionLabel }}</a>
        </td></tr></table>
        <p style="margin:0;padding:13px 15px;border-left:3px solid #5266eb;border-radius:0 8px 8px 0;background:#f3f5ff;color:#44516b;font-size:13px;line-height:1.6;">{{ $note }}</p>
        <p style="margin:20px 0 0;color:#69758c;font-size:13px;line-height:1.65;">{{ $security }}</p>
        <p style="margin:24px 0 0;padding-top:17px;border-top:1px solid #e9edf4;color:#8993a7;font-size:12px;line-height:1.6;">Este correo se envió automáticamente desde Nexo. Si necesitas ayuda, visita <a href="{{ url('/soporte') }}" style="color:#5266eb;text-decoration:underline;">Soporte</a>.</p>
      </td>
    </tr>
  </table>
</body>
</html>
