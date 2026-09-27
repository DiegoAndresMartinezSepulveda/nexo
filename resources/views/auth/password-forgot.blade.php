@extends('layouts.public')
@section('title', 'Recuperar contraseña')
@section('content')
<main class="auth-wrap">
  <a class="brand" href="/"><span class="mark">N</span>nexo<span style="color:#5266eb">.</span></a>
  <span class="eyebrow">RECUPERACIÓN SEGURA</span>
  <h1>Recupera tu acceso.</h1>
  <p>Escribe el correo de tu cuenta. Si coincide con una cuenta activa, enviaremos un enlace temporal para cambiar la contraseña.</p>
  @if(session('status'))
    <div class="alert success" role="status">{{ session('status') }}</div>
  @endif
  @if($errors->any())
    <div class="alert error" role="alert">{{ $errors->first() }}</div>
  @endif
  <form method="post" action="{{ route('password.email') }}">
    @csrf
    <label for="email">Correo electrónico</label>
    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required maxlength="255" autofocus>
    <button class="button" type="submit">Enviar enlace de recuperación</button>
  </form>
  <small class="small">El enlace vence en 60 minutos y solo se puede usar una vez. Revisa también la carpeta Spam.</small>
  <div class="auth-foot"><a href="/app/">Volver al inicio de sesión</a><a href="/soporte">Ayuda</a></div>
</main>
@endsection
