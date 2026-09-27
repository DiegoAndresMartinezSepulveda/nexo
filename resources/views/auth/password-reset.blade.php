@extends('layouts.public')
@section('title', 'Cambiar contraseña')
@section('content')
<main class="auth-wrap">
  <a class="brand" href="/"><span class="mark">N</span>nexo<span style="color:#5266eb">.</span></a>
  <span class="eyebrow">RECUPERACIÓN SEGURA</span>
  <h1>Crea una contraseña nueva.</h1>
  @if(!empty($success))
    <div class="alert success" role="status">{{ $success }}</div>
    <div class="actions"><a class="button" href="/app/">Ir al inicio de sesión</a></div>
  @else
    <p>Usa al menos 12 caracteres. El enlace es temporal y se invalida después de cambiar la contraseña.</p>
    @if(!empty($errorMessage))
      <div class="alert error" role="alert">{{ $errorMessage }}</div>
    @endif
    @if($errors->any())
      <div class="alert error" role="alert">{{ $errors->first() }}</div>
    @endif
    <form method="post" action="{{ route('password.update') }}">
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">
      <label for="email">Correo electrónico</label>
      <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required maxlength="255">
      <label for="password">Nueva contraseña</label>
      <input id="password" name="password" type="password" autocomplete="new-password" required minlength="12" maxlength="200">
      <label for="password_confirmation">Repite la nueva contraseña</label>
      <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="12" maxlength="200">
      <button class="button" type="submit">Guardar contraseña nueva</button>
    </form>
    <small class="small">Al completar el cambio, las otras sesiones y los accesos de la app se invalidan.</small>
    <div class="auth-foot"><a href="/password/forgot">Pedir otro enlace</a><a href="/soporte">Ayuda</a></div>
  @endif
</main>
@endsection
